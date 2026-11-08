<?php

use App\Models\AdministratorInvitation;
use App\Models\User;
use App\Notifications\AdministratorInvitationNotification;
use App\Support\Rbac\RbacSynchronizer;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(RbacSynchronizer::class)->synchronize();
});

test('revokes the invitation when the mail transport fails and allows a clean retry', function () {
    $this->app->instance(Dispatcher::class, new class implements Dispatcher
    {
        public function send($notifiables, $notification): void
        {
            throw new RuntimeException('SMTP transport failure');
        }

        /**
         * @param  array<int, string>|null  $channels
         */
        public function sendNow($notifiables, $notification, ?array $channels = null): void
        {
            throw new RuntimeException('SMTP transport failure');
        }
    });

    $logger = Log::spy();

    $exitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($exitCode)->toBe(1)
        ->and(User::query()->count())->toBe(0)
        ->and(AdministratorInvitation::query()->count())->toBe(1);

    $failedInvitation = AdministratorInvitation::query()->sole();

    expect($failedInvitation->revoked_at)->not->toBeNull()
        ->and($failedInvitation->consumed_at)->toBeNull();

    $logger->shouldHaveReceived('error')->withArgs(function (string $message, array $context): bool {
        return preg_match('/[a-f0-9]{64}/', $message.' '.json_encode($context)) !== 1;
    });

    $notificationFake = Notification::fake();
    $this->app->instance(Dispatcher::class, $notificationFake);

    $retryExitCode = Artisan::call('batipaie:admin-invite', [
        '--name' => 'Alice Admin',
        '--email' => 'admin@example.com',
    ]);

    expect($retryExitCode)->toBe(0)
        ->and(AdministratorInvitation::query()->count())->toBe(2)
        ->and(AdministratorInvitation::query()->whereNull('revoked_at')->count())->toBe(1)
        ->and(User::query()->count())->toBe(0);

    Notification::assertSentOnDemandTimes(AdministratorInvitationNotification::class, 1);
});
