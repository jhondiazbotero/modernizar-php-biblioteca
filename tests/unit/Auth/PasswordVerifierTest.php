<?php

declare(strict_types=1);

namespace Library\Tests\Auth;

use Library\Auth\PasswordVerifier;
use PHPUnit\Framework\TestCase;

final class PasswordVerifierTest extends TestCase
{
    /** @var PasswordVerifier */
    private $verifier;

    protected function setUp(): void
    {
        $this->verifier = new PasswordVerifier();
    }

    public function test_a_correct_password_matches_an_md5_account(): void
    {
        self::assertTrue($this->verifier->verify('secreto', md5('secreto')));
    }

    public function test_a_wrong_password_does_not_match_an_md5_account(): void
    {
        self::assertFalse($this->verifier->verify('otro', md5('secreto')));
    }

    public function test_an_uppercase_stored_md5_still_matches(): void
    {
        self::assertTrue($this->verifier->verify('secreto', strtoupper(md5('secreto'))));
    }

    public function test_a_correct_password_matches_a_bcrypt_hash(): void
    {
        self::assertTrue($this->verifier->verify('secreto', password_hash('secreto', PASSWORD_DEFAULT)));
    }

    public function test_a_wrong_password_does_not_match_a_bcrypt_hash(): void
    {
        self::assertFalse($this->verifier->verify('otro', password_hash('secreto', PASSWORD_DEFAULT)));
    }

    public function test_an_empty_stored_value_never_matches(): void
    {
        self::assertFalse($this->verifier->verify('', ''));
        self::assertFalse($this->verifier->verify('secreto', ''));
    }

    public function test_a_stored_value_that_is_not_a_hash_never_matches(): void
    {
        self::assertFalse($this->verifier->verify('secreto', 'secreto'));
        self::assertFalse($this->verifier->verify('secreto', md5('secreto') . '0'));
    }

    public function test_an_md5_account_needs_rehash(): void
    {
        self::assertTrue($this->verifier->needsRehash(md5('secreto')));
        self::assertTrue($this->verifier->needsRehash(strtoupper(md5('secreto'))));
    }

    public function test_a_hash_made_with_the_current_algorithm_does_not_need_rehash(): void
    {
        self::assertFalse($this->verifier->needsRehash($this->verifier->hash('secreto')));
    }

    public function test_a_hash_with_an_old_cost_needs_rehash(): void
    {
        $old = password_hash('secreto', PASSWORD_BCRYPT, ['cost' => 4]);

        self::assertTrue($this->verifier->verify('secreto', $old));
        self::assertTrue($this->verifier->needsRehash($old));
    }

    public function test_hash_is_verifiable_and_salted(): void
    {
        $first = $this->verifier->hash('secreto');
        $second = $this->verifier->hash('secreto');

        self::assertNotSame($first, $second);
        self::assertNotSame('secreto', $first);
        self::assertTrue($this->verifier->verify('secreto', $first));
        self::assertTrue($this->verifier->verify('secreto', $second));
        self::assertFalse($this->verifier->verify('otro', $first));
    }

    public function test_the_dummy_hash_is_valid_and_has_the_default_cost(): void
    {
        $dummy = password_get_info(PasswordVerifier::DUMMY_HASH);
        $current = password_get_info(password_hash('x', PASSWORD_DEFAULT));

        self::assertSame($current['algo'], $dummy['algo']);
        self::assertSame($current['options']['cost'], $dummy['options']['cost'], 'The dummy hash must cost the same as a real one');
        self::assertFalse(password_verify('', PasswordVerifier::DUMMY_HASH));
    }
}
