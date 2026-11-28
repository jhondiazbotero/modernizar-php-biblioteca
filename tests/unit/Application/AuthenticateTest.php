<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\Authenticate;
use Library\Auth\PasswordVerifier;
use Library\Domain\LibrarianAccount;
use Library\Tests\Fake\InMemoryLibrarianRepository;
use PHPUnit\Framework\TestCase;

final class AuthenticateTest extends TestCase
{
    /** @var InMemoryLibrarianRepository */
    private $librarians;

    /** @var PasswordVerifier */
    private $verifier;

    protected function setUp(): void
    {
        $this->librarians = new InMemoryLibrarianRepository();
        $this->verifier = new PasswordVerifier();
    }

    private function useCase(): Authenticate
    {
        return new Authenticate($this->librarians, $this->verifier);
    }

    public function test_a_correct_password_signs_in_and_names_the_librarian(): void
    {
        $this->librarians->add('admin', new LibrarianAccount(1, 'Administrador', $this->verifier->hash('s3cret')));

        $result = $this->useCase()->handle('admin', 's3cret');

        self::assertTrue($result->isSuccess());
        self::assertSame(1, $result->getLibrarianId());
        self::assertSame('Administrador', $result->getLibrarianName());
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->librarians->add('admin', new LibrarianAccount(1, 'Administrador', $this->verifier->hash('s3cret')));

        $result = $this->useCase()->handle('admin', 'other');

        self::assertFalse($result->isSuccess());
        self::assertSame('invalid_credentials', $result->getErrorCode());
        self::assertSame([], $this->librarians->rewritten);
    }

    public function test_an_unknown_user_gets_the_same_error_as_a_wrong_password(): void
    {
        $result = $this->useCase()->handle('nobody', 's3cret');

        self::assertSame('invalid_credentials', $result->getErrorCode());
    }

    public function test_an_empty_password_never_matches(): void
    {
        $this->librarians->add('admin', new LibrarianAccount(1, 'Administrador', $this->verifier->hash('s3cret')));

        self::assertSame('invalid_credentials', $this->useCase()->handle('admin', '')->getErrorCode());
        self::assertSame('invalid_credentials', $this->useCase()->handle('', '')->getErrorCode());
    }

    public function test_an_md5_account_signs_in_and_is_moved_to_a_modern_hash(): void
    {
        $this->librarians->add('admin', new LibrarianAccount(7, 'Administrador', md5('s3cret')));

        $result = $this->useCase()->handle('admin', 's3cret');

        self::assertTrue($result->isSuccess());
        self::assertSame(['7'], $this->librarians->rewritten);
        $stored = $this->librarians->accounts['admin']->getStoredPassword();
        self::assertStringStartsWith('$2y$', $stored);
        self::assertTrue(password_verify('s3cret', $stored));
    }

    public function test_a_wrong_password_leaves_an_md5_account_untouched(): void
    {
        $this->librarians->add('admin', new LibrarianAccount(7, 'Administrador', md5('s3cret')));

        $this->useCase()->handle('admin', 'other');

        self::assertSame([], $this->librarians->rewritten);
        self::assertSame(md5('s3cret'), $this->librarians->accounts['admin']->getStoredPassword());
    }

    public function test_an_up_to_date_hash_is_not_rewritten(): void
    {
        $this->librarians->add('admin', new LibrarianAccount(1, 'Administrador', $this->verifier->hash('s3cret')));

        $this->useCase()->handle('admin', 's3cret');

        self::assertSame([], $this->librarians->rewritten);
    }
}
