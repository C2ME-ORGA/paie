<?php

namespace App\Console\Commands;

use App\Actions\Administration\InviteAdministrator;
use App\Exceptions\AdministratorInvitationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

use function Laravel\Prompts\text;

final class InviteAdministratorCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'batipaie:admin-invite
        {--name= : Nom du futur administrateur}
        {--email= : Adresse e-mail du futur administrateur}';

    /**
     * @var string
     */
    protected $description = 'Crée une invitation unique pour le premier administrateur';

    public function handle(InviteAdministrator $inviteAdministrator): int
    {
        if (! $this->laravel->runningInConsole()) {
            $this->error('Cette commande doit être exécutée depuis la console.');

            return self::FAILURE;
        }

        $name = (string) ($this->option('name') ?: text(
            label: 'Nom du futur administrateur',
            required: true,
            validate: fn (string $value): ?string => trim($value) === ''
                ? 'Le nom est obligatoire.'
                : null,
        ));

        $email = (string) ($this->option('email') ?: text(
            label: 'Adresse e-mail du futur administrateur',
            required: true,
            validate: fn (string $value): ?string => filter_var(trim($value), FILTER_VALIDATE_EMAIL) === false
                ? 'L’adresse e-mail est invalide.'
                : null,
        ));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $validated = $validator->validated();

        try {
            $invitation = $inviteAdministrator->handle(
                (string) $validated['name'],
                Str::lower(trim((string) $validated['email'])),
            );
        } catch (AdministratorInvitationException|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            logger()->error('Administrator invitation command failed.', [
                'exception' => $exception::class,
            ]);

            $this->error('L’invitation n’a pas pu être créée. Consultez les journaux applicatifs.');

            return self::FAILURE;
        }

        $this->info('Invitation administrateur créée.');
        $this->line('Destinataire : '.$this->maskEmail($invitation->email));
        $this->line('Expiration : '.$invitation->expires_at->format('d/m/Y H:i'));
        $this->line('Aucun utilisateur n’a été créé. Le compte sera créé après acceptation de l’invitation.');

        if (config('mail.default') === 'log') {
            $this->warn('MAIL_MAILER=log : le lien est écrit dans les journaux. Traitez ces journaux comme sensibles.');
        }

        return self::SUCCESS;
    }

    private function maskEmail(string $email): string
    {
        [$localPart, $domain] = explode('@', $email, 2);

        $visible = mb_substr($localPart, 0, 1);
        $masked = str_repeat('*', max(3, mb_strlen($localPart) - 1));

        return $visible.$masked.'@'.$domain;
    }
}
