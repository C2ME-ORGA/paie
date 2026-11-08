<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Administration\AcceptAdministratorInvitation;
use App\Exceptions\AdministratorInvitationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptAdministratorInvitationRequest;
use App\Models\AdministratorInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AcceptAdministratorInvitationController extends Controller
{
    public function show(string $token): View|Response
    {
        try {
            $invitation = AdministratorInvitation::findByToken($token);

            if ($invitation === null || ! $invitation->isUsableAt()) {
                return response()->view('auth.administrator-invitation-invalid', [], 404);
            }

            return view('auth.administrator-invitation', [
                'invitation' => $invitation,
                'token' => $token,
            ]);
        } catch (Throwable $exception) {
            logger()->error('Administrator invitation display failed.', [
                'exception' => $exception::class,
            ]);

            return response()->view('auth.administrator-invitation-invalid', [], 500);
        }
    }

    public function store(
        AcceptAdministratorInvitationRequest $request,
        string $token,
        AcceptAdministratorInvitation $acceptInvitation,
    ): RedirectResponse|Response {
        try {
            $acceptInvitation->handle($token, (string) $request->validated('password'));
        } catch (AdministratorInvitationException $exception) {
            if ($exception->reason === AdministratorInvitationException::REASON_EMAIL_ALREADY_USED) {
                throw ValidationException::withMessages([
                    'email' => 'Cette invitation ne peut pas être utilisée. Contactez l’administrateur.',
                ]);
            }

            if ($exception->reason === AdministratorInvitationException::REASON_RBAC_MISSING) {
                return response()->view('auth.administrator-invitation-invalid', [], 503);
            }

            return response()->view('auth.administrator-invitation-invalid', [], 404);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            logger()->error('Administrator invitation acceptance failed.', [
                'exception' => $exception::class,
            ]);

            return response()->view('auth.administrator-invitation-invalid', [], 500);
        }

        return redirect()
            ->route('filament.admin.auth.login')
            ->with('status', 'Votre compte administrateur a été créé. Vous pouvez vous connecter.');
    }
}
