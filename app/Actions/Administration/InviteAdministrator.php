<?php

namespace App\Actions\Administration;

use App\Enums\AuditEventType;
use App\Enums\PermissionName;
use App\Enums\RbacRole;
use App\Exceptions\AdministratorInvitationException;
use App\Models\AdministratorInvitation;
use App\Models\User;
use App\Notifications\AdministratorInvitationNotification;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Throwable;

final class InviteAdministrator
{
    public function __construct(
        private readonly AuditManager $audit,
    ) {}

    public function handle(string $name, string $email): AdministratorInvitation
    {
        $name = trim($name);
        $email = Str::lower(trim($email));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Les informations du futur administrateur sont invalides.');
        }

        if (app()->isProduction() && in_array((string) config('mail.default'), ['log', 'array'], true)) {
            throw AdministratorInvitationException::insecureMailConfiguration();
        }

        $token = bin2hex(random_bytes(32));
        $correlationId = (string) Str::uuid();

        $invitation = DB::transaction(function () use ($name, $email, $token, $correlationId): AdministratorInvitation {
            // Lock the administrator role as a stable bootstrap mutex. This
            // serializes concurrent commands without relying on an empty range.
            $administratorRole = Role::query()
                ->where('name', RbacRole::Administrator->value)
                ->where('guard_name', RbacSynchronizer::GUARD)
                ->lockForUpdate()
                ->first();

            if ($administratorRole === null
                || ! $administratorRole->checkPermissionTo(PermissionName::AdministrationPanelAccess->value)) {
                throw AdministratorInvitationException::rbacMissing();
            }

            $now = now();

            // Same critical lock order as acceptance: administrator role,
            // then invitations. The role lock serializes every bootstrap path.
            /** @var Collection<int, AdministratorInvitation> $pending */
            $pending = AdministratorInvitation::query()
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->get();

            foreach ($pending as $existing) {
                if ($existing->isUsableAt($now)) {
                    throw AdministratorInvitationException::invitationPending();
                }

                if ($existing->revoked_at === null) {
                    $oldRevokedAt = $existing->revoked_at;

                    $existing->forceFill(['revoked_at' => $now])->save();

                    $this->audit->record(
                        event: AuditEventType::AdministratorInvitationRevoked,
                        subject: $existing,
                        oldValues: ['revoked_at' => $oldRevokedAt],
                        newValues: ['revoked_at' => $existing->revoked_at],
                        context: $this->systemContext('Révocation d’une invitation en attente', $correlationId),
                    );
                }
            }

            $existingAdministrator = User::query()
                ->role(RbacRole::Administrator->value, RbacSynchronizer::GUARD)
                ->lockForUpdate()
                ->first();

            if ($existingAdministrator !== null) {
                throw AdministratorInvitationException::administratorExists();
            }

            if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw AdministratorInvitationException::emailAlreadyUsed();
            }

            if (AdministratorInvitation::query()->whereNotNull('consumed_at')->exists()) {
                // A consumed invitation without an administrator means the
                // database state is inconsistent: fail closed rather than
                // creating another bootstrap invitation.
                throw AdministratorInvitationException::inconsistentState();
            }

            $invitation = AdministratorInvitation::query()->create([
                'name' => $name,
                'email' => $email,
                'token_hash' => AdministratorInvitation::hashToken($token),
                'expires_at' => $now->addHours($this->expiryHours()),
            ]);

            $this->audit->record(
                event: AuditEventType::AdministratorInvitationCreated,
                subject: $invitation,
                newValues: $invitation->only(['name', 'email', 'expires_at']),
                context: $this->systemContext('Création d’une invitation administrateur', $correlationId),
            );

            return $invitation;
        });

        try {
            Notification::route('mail', $invitation->email)
                ->notify(new AdministratorInvitationNotification(
                    $invitation,
                    route('administrator-invitations.show', ['token' => $token]),
                ));
        } catch (Throwable) {
            // Never log the token or the e-mail body. Revoke the invitation so
            // the operator can retry with a clean state.
            logger()->error('Administrator invitation e-mail delivery failed.', [
                'invitation_id' => $invitation->getKey(),
            ]);

            $oldRevokedAt = $invitation->revoked_at;

            $invitation->forceFill(['revoked_at' => now()])->save();

            $context = $this->systemContext('Échec d’envoi de l’invitation administrateur', $correlationId);

            $this->audit->record(
                event: AuditEventType::AdministratorInvitationDeliveryFailed,
                subject: $invitation,
                context: $context,
            );

            $this->audit->record(
                event: AuditEventType::AdministratorInvitationRevoked,
                subject: $invitation,
                oldValues: ['revoked_at' => $oldRevokedAt],
                newValues: ['revoked_at' => $invitation->revoked_at],
                context: $this->systemContext(
                    'Révocation après échec d’envoi de l’invitation administrateur',
                    $correlationId,
                ),
            );

            throw AdministratorInvitationException::emailDeliveryFailed();
        }

        return $invitation;
    }

    private function systemContext(string $reason, string $correlationId): AuditContext
    {
        return AuditContext::system(
            correlationId: $correlationId,
            reason: $reason,
            metadata: [
                'source' => 'console',
                'channel' => 'administrator_invitation',
                'command' => 'batipaie:admin-invite',
            ],
        );
    }

    private function expiryHours(): int
    {
        return max(1, (int) config('administration.invitation_expiry_hours', 24));
    }
}
