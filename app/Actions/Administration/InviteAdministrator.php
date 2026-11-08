<?php

namespace App\Actions\Administration;

use App\Enums\PermissionName;
use App\Enums\RbacRole;
use App\Exceptions\AdministratorInvitationException;
use App\Models\AdministratorInvitation;
use App\Models\User;
use App\Notifications\AdministratorInvitationNotification;
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

        $invitation = DB::transaction(function () use ($name, $email, $token): AdministratorInvitation {
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
                    $existing->forceFill(['revoked_at' => $now])->save();
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

            return AdministratorInvitation::query()->create([
                'name' => $name,
                'email' => $email,
                'token_hash' => AdministratorInvitation::hashToken($token),
                'expires_at' => $now->addHours($this->expiryHours()),
            ]);
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

            $invitation->forceFill(['revoked_at' => now()])->save();

            throw AdministratorInvitationException::emailDeliveryFailed();
        }

        return $invitation;
    }

    private function expiryHours(): int
    {
        return max(1, (int) config('administration.invitation_expiry_hours', 24));
    }
}
