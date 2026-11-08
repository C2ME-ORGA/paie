<?php

namespace App\Actions\Administration;

use App\Enums\AuditEventType;
use App\Enums\PermissionName;
use App\Enums\RbacRole;
use App\Exceptions\AdministratorInvitationException;
use App\Models\AdministratorInvitation;
use App\Models\User;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditManager;
use App\Support\Rbac\RbacSynchronizer;
use App\Support\Security\AdministratorPassword;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

final class AcceptAdministratorInvitation
{
    public function __construct(
        private readonly AuditManager $audit,
    ) {}

    public function handle(string $token, string $password): User
    {
        Validator::make(
            ['password' => $password],
            ['password' => AdministratorPassword::rulesWithoutConfirmation()],
        )->validate();

        try {
            return DB::transaction(function () use ($token, $password): User {
                // Same lock order as InviteAdministrator: administrator role
                // first, then invitation. This is the global bootstrap mutex
                // that prevents two distinct invitations from creating two
                // administrators concurrently.
                $administratorRole = Role::query()
                    ->where('name', RbacRole::Administrator->value)
                    ->where('guard_name', RbacSynchronizer::GUARD)
                    ->lockForUpdate()
                    ->first();

                if ($administratorRole === null
                    || ! $administratorRole->checkPermissionTo(PermissionName::AdministrationPanelAccess->value)) {
                    throw AdministratorInvitationException::rbacMissing();
                }

                $invitation = AdministratorInvitation::query()
                    ->where('token_hash', AdministratorInvitation::hashToken($token))
                    ->lockForUpdate()
                    ->first();

                if ($invitation === null || ! $invitation->isUsableAt()) {
                    throw AdministratorInvitationException::invalidInvitation();
                }

                $existingAdministrator = User::query()
                    ->role(RbacRole::Administrator->value, RbacSynchronizer::GUARD)
                    ->lockForUpdate()
                    ->first();

                if ($existingAdministrator !== null) {
                    throw AdministratorInvitationException::administratorExists();
                }

                $email = Str::lower($invitation->email);

                if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                    throw AdministratorInvitationException::emailAlreadyUsed();
                }

                $user = User::query()->create([
                    'name' => $invitation->name,
                    'email' => $email,
                    'password' => $password,
                ]);

                $user->forceFill(['email_verified_at' => now()])->save();
                $user->assignRole($administratorRole);

                $invitation->forceFill(['consumed_at' => now()])->save();

                $context = AuditContext::system(
                    correlationId: (string) Str::uuid(),
                    reason: 'Acceptation d’une invitation administrateur',
                    metadata: [
                        'source' => 'http',
                        'channel' => 'administrator_invitation',
                        'initiated_by' => 'invitation_recipient',
                    ],
                );

                $this->audit->record(
                    event: AuditEventType::UserCreated,
                    subject: $user,
                    newValues: $user->only(['name', 'email', 'email_verified_at']),
                    context: $context,
                );

                $this->audit->record(
                    event: AuditEventType::RbacRoleAssigned,
                    subject: $user,
                    context: $context->withMetadata([
                        'role' => $administratorRole->name,
                        'guard' => RbacSynchronizer::GUARD,
                    ]),
                );

                $this->audit->record(
                    event: AuditEventType::AdministratorInvitationConsumed,
                    subject: $invitation,
                    oldValues: ['consumed_at' => null],
                    newValues: ['consumed_at' => $invitation->consumed_at],
                    context: $context,
                );

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Another request created the same account between the check and
            // the insert. Do not reveal which constraint failed.
            throw AdministratorInvitationException::emailAlreadyUsed();
        }
    }
}
