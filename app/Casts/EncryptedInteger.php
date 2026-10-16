<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypt an integer attribute at rest; reads return an int (RES-4).
 *
 * The built-in cast name `encrypted:integer` is not recognised by this
 * framework version's isEncryptedCastable() whitelist, so it was silently
 * inert: salary was stored and read in plaintext. This custom cast encrypts
 * with the application key (serialize=false, matching the framework's own
 * encrypted casts) and casts the decrypted value back to int.
 *
 * @implements CastsAttributes<int|null, int|null>
 */
class EncryptedInteger implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) Crypt::decryptString((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|string|null
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Crypt::encryptString((string) (int) $value);
    }
}
