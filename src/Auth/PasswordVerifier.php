<?php

declare(strict_types=1);

namespace Library\Auth;

/** Checks and creates librarian passwords. */
final class PasswordVerifier
{
    /**
     * Hash of a random value nobody knows. Login verifies against it when the
     * user does not exist, so the response time does not reveal which users exist.
     * Its cost must match PASSWORD_DEFAULT (a unit test checks it).
     */
    public const DUMMY_HASH = '$2y$10$6JMR3hM56c5P2PlM9d30PuQfuvi0848tyeHOdp2iaPksEOOxwQpGK';

    /**
     * Accounts created before password_hash keep a 32-character MD5; they are
     * compared in constant time. Anything else is a password_hash value.
     */
    public function verify(string $plain, string $stored): bool
    {
        if ($stored === '') {
            return false;
        }

        if ($this->isMd5($stored)) {
            return hash_equals(md5($plain), strtolower($stored));
        }

        return password_verify($plain, $stored);
    }

    /** True for old MD5 values and for hashes made with another algorithm or cost. */
    public function needsRehash(string $stored): bool
    {
        return $this->isMd5($stored) || password_needs_rehash($stored, PASSWORD_DEFAULT);
    }

    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    private function isMd5(string $stored): bool
    {
        return preg_match('/\A[0-9a-fA-F]{32}\z/', $stored) === 1;
    }
}
