<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\RegisterMember;

final class RegisterMemberTest extends UseCaseTestCase
{
    private function useCase(): RegisterMember
    {
        return new RegisterMember($this->transactions, $this->members, $this->clock);
    }

    public function test_a_member_is_registered_with_today_as_registration_date(): void
    {
        $result = $this->useCase()->handle('2002002001', 'Elena Prieto Luna', 'elena@example.com', '3009998877', true);

        self::assertTrue($result->isSuccess());
        self::assertSame(1, $result->getId());
        self::assertSame('Elena Prieto Luna', $this->store->members[1]->getName());
        self::assertTrue($this->store->members[1]->isActive());
        self::assertSame('2002002001', $this->store->memberData[1]['document']);
        self::assertSame('elena@example.com', $this->store->memberData[1]['email']);
        self::assertSame('3009998877', $this->store->memberData[1]['phone']);
        self::assertSame('2026-11-27', $this->store->memberData[1]['registeredOn']->format('Y-m-d'));
        self::assertSame(['members.add'], $this->store->writes);
        self::assertSame(1, $this->transactions->transactions);
    }

    public function test_the_text_fields_are_trimmed(): void
    {
        $this->useCase()->handle(' 2002002001 ', '  Elena Prieto Luna  ', ' e@example.com ', ' 300 ', true);

        self::assertSame('2002002001', $this->store->memberData[1]['document']);
        self::assertSame('Elena Prieto Luna', $this->store->members[1]->getName());
        self::assertSame('e@example.com', $this->store->memberData[1]['email']);
        self::assertSame('300', $this->store->memberData[1]['phone']);
    }

    public function test_a_member_can_be_registered_inactive(): void
    {
        $this->useCase()->handle('2002002001', 'Elena', '', '', false);

        self::assertFalse($this->store->members[1]->isActive());
    }

    public function test_document_and_name_are_required(): void
    {
        self::assertSame('required_fields', $this->useCase()->handle('', 'Elena', '', '', true)->getErrorCode());
        self::assertSame('required_fields', $this->useCase()->handle('2002002001', '', '', '', true)->getErrorCode());
        self::assertSame('required_fields', $this->useCase()->handle('   ', 'Elena', '', '', true)->getErrorCode());
        self::assertSame('required_fields', $this->useCase()->handle('2002002001', "  \t ", '', '', true)->getErrorCode());
        self::assertSame([], $this->store->writes);
        self::assertSame([], $this->store->members);
    }

    public function test_a_repeated_document_is_rejected(): void
    {
        $this->store->addMember(5, 'Ana Torres', true, '1001001001');

        $result = $this->useCase()->handle('1001001001', 'Elena', '', '', true);

        self::assertFalse($result->isSuccess());
        self::assertSame('document_taken', $result->getErrorCode());
        self::assertSame([], $this->store->writes);
        self::assertCount(1, $this->store->members);
    }

    public function test_the_repeated_document_is_found_after_trimming(): void
    {
        $this->store->addMember(5, 'Ana Torres', true, '1001001001');

        self::assertSame('document_taken', $this->useCase()->handle(' 1001001001 ', 'Elena', '', '', true)->getErrorCode());
    }

    public function test_the_missing_fields_are_reported_before_the_repeated_document(): void
    {
        $this->store->addMember(5, 'Ana Torres', true, '1001001001');

        self::assertSame('required_fields', $this->useCase()->handle('1001001001', '', '', '', true)->getErrorCode());
    }

    public function test_a_failure_while_saving_leaves_nothing_behind(): void
    {
        $this->store->failOn('members.add');

        try {
            $this->useCase()->handle('2002002001', 'Elena', '', '', true);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertSame([], $this->store->members);
        self::assertSame(1, $this->transactions->rollbacks);
    }
}
