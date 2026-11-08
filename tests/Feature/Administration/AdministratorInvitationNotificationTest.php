<?php

use App\Notifications\AdministratorInvitationNotification;
use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    app(RbacSynchronizer::class)->synchronize();
});

test('sends a non-queued notification with the invitation link and security notice', function () {
    Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    $sent = null;

    Notification::assertSentOnDemand(
        AdministratorInvitationNotification::class,
        function (AdministratorInvitationNotification $notification) use (&$sent): bool {
            $sent = $notification;

            return true;
        },
    );

    if (! $sent instanceof AdministratorInvitationNotification) {
        $this->fail('The administrator invitation notification was not sent.');
    }

    expect($sent)->not->toBeInstanceOf(ShouldQueue::class)
        ->and($sent->acceptanceUrl)->toContain('/admin/invitation/');

    $mail = $sent->toMail(new AnonymousNotifiable);
    $mailText = implode(' ', array_merge($mail->introLines, $mail->outroLines));

    expect($mail->subject)->toContain((string) config('app.name'))
        ->and($mail->actionUrl)->toBe($sent->acceptanceUrl)
        ->and($mail->actionText)->toBe('Créer mon compte administrateur')
        ->and($mailText)->toContain('une seule fois')
        ->and($mailText)->toContain('n’êtes pas à l’origine');
});
