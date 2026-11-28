<?php

declare(strict_types=1);

namespace Library\Application;

use Library\Application\Port\MemberRepository;
use Library\Application\Port\TransactionManager;

/**
 * Rewrites the data of a member. Error codes: required_fields (document and name),
 * document_taken (by another member).
 */
final class EditMember
{
    /** @var TransactionManager */
    private $transactions;

    /** @var MemberRepository */
    private $members;

    public function __construct(TransactionManager $transactions, MemberRepository $members)
    {
        $this->transactions = $transactions;
        $this->members = $members;
    }

    public function handle(int $id, string $document, string $name, string $email, string $phone, bool $active): SaveResult
    {
        $document = trim($document);
        $name = trim($name);
        $email = trim($email);
        $phone = trim($phone);

        if ($document === '' || $name === '') {
            return SaveResult::failure('required_fields');
        }

        return $this->transactions->transactional(function () use ($id, $document, $name, $email, $phone, $active): SaveResult {
            if ($this->members->documentTaken($document, $id)) {
                return SaveResult::failure('document_taken');
            }
            $this->members->update($id, $document, $name, $email, $phone, $active);

            return SaveResult::success($id);
        });
    }
}
