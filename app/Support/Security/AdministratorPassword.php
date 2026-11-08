<?php

namespace App\Support\Security;

use Illuminate\Validation\Rules\Password;

/**
 * Password policy for the first administrator account.
 *
 * It intentionally does not rely on the nullable local default: an
 * administrator account must remain strong even on a development machine.
 */
final class AdministratorPassword
{
    /**
     * @return list<string|Password>
     */
    public static function rules(): array
    {
        return ['required', 'string', 'max:255', 'confirmed', self::rule()];
    }

    /**
     * @return list<string|Password>
     */
    public static function rulesWithoutConfirmation(): array
    {
        return ['required', 'string', 'max:255', self::rule()];
    }

    private static function rule(): Password
    {
        $password = Password::min(12)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols();

        if (app()->isProduction()) {
            $password->uncompromised();
        }

        return $password;
    }
}
