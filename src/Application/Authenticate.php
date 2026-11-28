<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\LibrarianRepository;
use Library\Auth\PasswordVerifier;

/**
 * Checks a user name and password. Error code: invalid_credentials, the same for an unknown
 * user and for a wrong password. A correct password stored as an old MD5 (or with an outdated
 * hash) is replaced by a current hash.
 */
final class Authenticate
{
    /** @var LibrarianRepository */
    private $librarians;

    /** @var PasswordVerifier */
    private $verifier;

    public function __construct(LibrarianRepository $librarians, PasswordVerifier $verifier)
    {
        $this->librarians = $librarians;
        $this->verifier = $verifier;
    }

    public function handle(string $username, string $password): AuthResult
    {
        $account = $this->librarians->findByUsername($username);

        if ($account === null) {
            // Unknown user: the same work is done so the time does not reveal who exists.
            password_verify($password, PasswordVerifier::DUMMY_HASH);

            return AuthResult::failure('invalid_credentials');
        }

        if (!$this->verifier->verify($password, $account->getStoredPassword())) {
            return AuthResult::failure('invalid_credentials');
        }

        if ($this->verifier->needsRehash($account->getStoredPassword())) {
            $this->librarians->updatePassword($account->getId(), $this->verifier->hash($password));
        }

        return AuthResult::success($account->getId(), $account->getName());
    }
}
