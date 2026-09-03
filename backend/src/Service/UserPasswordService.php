<?php

declare(strict_types=1);

namespace App\Service;

final class UserPasswordService
{
    private const TEMPORARY_PASSWORD_LENGTH = 24;

    private const TEMPORARY_ALPHABET =
        'ABCDEFGHJKLMNPQRSTUVWXYZ'
        .'abcdefghijkmnopqrstuvwxyz'
        .'23456789';

    public function generateTemporaryPassword(): string
    {
        $alphabetLength = strlen(
            self::TEMPORARY_ALPHABET
        );

        $password = '';

        for (
            $i = 0;
            $i < self::TEMPORARY_PASSWORD_LENGTH;
            ++$i
        ) {
            $password .= self::TEMPORARY_ALPHABET[
                random_int(0, $alphabetLength - 1)
            ];
        }

        return $password;
    }

    public function hash(string $password): string
    {
        return password_hash(
            $password,
            PASSWORD_DEFAULT,
        );
    }

    public function verify(
        string $password,
        string $hash,
    ): bool {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash(
            $hash,
            PASSWORD_DEFAULT,
        );
    }
}
