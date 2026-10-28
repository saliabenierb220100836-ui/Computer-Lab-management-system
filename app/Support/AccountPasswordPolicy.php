<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class AccountPasswordPolicy
{
    public static function rule(): Password
    {
        return Password::min(12)
            ->max(16)
            ->mixedCase()
            ->numbers()
            ->symbols();
    }

    public static function description(): string
    {
        return 'Use 12–16 characters with at least one uppercase letter, one lowercase letter, one number, and one special character.';
    }
}
