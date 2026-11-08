<?php

namespace App\Http\Requests\Auth;

use App\Support\Security\AdministratorPassword;
use Illuminate\Foundation\Http\FormRequest;

final class AcceptAdministratorInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'password' => AdministratorPassword::rules(),
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => 'mot de passe',
            'password_confirmation' => 'confirmation du mot de passe',
        ];
    }
}
