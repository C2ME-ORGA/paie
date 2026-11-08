<?php

namespace App\Notifications;

use App\Models\AdministratorInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation sent to the first administrator.
 *
 * This notification is intentionally not queued: the raw token must never be
 * serialized into the jobs table. It is sent synchronously after the
 * invitation transaction commits.
 */
final class AdministratorInvitationNotification extends Notification
{
    public function __construct(
        public readonly AdministratorInvitation $invitation,
        public readonly string $acceptanceUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $applicationName = (string) config('app.name', 'BatiPaie');
        $expiresAt = $this->invitation->expires_at->translatedFormat('d/m/Y à H:i');

        return (new MailMessage)
            ->subject('Invitation à administrer '.$applicationName)
            ->greeting('Bonjour '.$this->invitation->name.',')
            ->line('Vous êtes invité(e) à créer le compte administrateur de '.$applicationName.'.')
            ->action('Créer mon compte administrateur', $this->acceptanceUrl)
            ->line('Ce lien est valable jusqu’au '.$expiresAt.' et ne peut être utilisé qu’une seule fois.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail et informez l’équipe technique.')
            ->salutation('L’équipe '.$applicationName);
    }
}
