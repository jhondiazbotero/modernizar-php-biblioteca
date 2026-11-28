<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\MemberRepository;
use Library\Application\Port\TransactionManager;
use Library\Domain\Clock;

/** Registers a member today. Error codes: required_fields (document and name), document_taken. */
final class RegisterMember
{
    /** @var TransactionManager */
    private $transactions;

    /** @var MemberRepository */
    private $members;

    /** @var Clock */
    private $clock;

    public function __construct(TransactionManager $transactions, MemberRepository $members, Clock $clock)
    {
        $this->transactions = $transactions;
        $this->members = $members;
        $this->clock = $clock;
    }

    public function handle(string $document, string $name, string $email, string $phone, bool $active): SaveResult
    {
        $document = trim($document);
        $name = trim($name);
        $email = trim($email);
        $phone = trim($phone);

        if ($document === '' || $name === '') {
            return SaveResult::failure('required_fields');
        }

        return $this->transactions->transactional(function () use ($document, $name, $email, $phone, $active): SaveResult {
            if ($this->members->documentTaken($document, 0)) {
                return SaveResult::failure('document_taken');
            }

            return SaveResult::success($this->members->add($document, $name, $email, $phone, $this->clock->today(), $active));
        });
    }
}
