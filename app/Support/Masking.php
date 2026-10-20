<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Masks contact details shown to staff. Full values are never rendered outside the customer's own ticket flow.
 */
final class Masking
{
    public static function phone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        if (strlen($phone) < 8) {
            return str_repeat('*', strlen($phone));
        }

        return Str::mask($phone, '*', 4, strlen($phone) - 7);
    }

    public static function email(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return Str::substr($local, 0, 1).str_repeat('*', max(strlen($local) - 1, 1)).'@'.$domain;
    }
}
