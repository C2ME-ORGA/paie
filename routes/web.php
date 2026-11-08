<?php

use App\Http\Controllers\Auth\AcceptAdministratorInvitationController;
use App\Http\Middleware\AdministratorInvitationSecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/admin/invitation/{token}', [AcceptAdministratorInvitationController::class, 'show'])
    ->where('token', '[a-f0-9]{64}')
    ->middleware(['throttle:10,1,administrator-invitation-show', AdministratorInvitationSecurityHeaders::class])
    ->name('administrator-invitations.show');

Route::post('/admin/invitation/{token}', [AcceptAdministratorInvitationController::class, 'store'])
    ->where('token', '[a-f0-9]{64}')
    ->middleware(['throttle:5,1,administrator-invitation-accept', AdministratorInvitationSecurityHeaders::class])
    ->name('administrator-invitations.accept');
