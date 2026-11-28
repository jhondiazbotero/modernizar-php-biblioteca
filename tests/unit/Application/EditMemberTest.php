<?php

declare(strict_types=1);

namespace Library\Tests\Application;

use Library\Application\EditMember;

final class EditMemberTest extends UseCaseTestCase
{
    private function useCase(): EditMember
    {
        return new EditMember($this->transactions, $this->members);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->store->addMember(1, 'Ana Torres', true, '1001001001');
        $this->store->addMember(2, 'Luis Pérez', true, '1001001002');
    }

    public function test_the_data_of_a_member_is_rewritten_and_the_registration_date_stays(): void
    {
        $before = $this->store->memberData[1]['registeredOn'];

        $result = $this->useCase()->handle(1, '1001001001', ' Ana María Torres ', 'ana@example.com', '3115550000', false);

        self::assertTrue($result->isSuccess());
        self::assertSame(1, $result->getId());
        self::assertSame('Ana María Torres', $this->store->members[1]->getName());
        self::assertFalse($this->store->members[1]->isActive());
        self::assertSame('ana@example.com', $this->store->memberData[1]['email']);
        self::assertSame('3115550000', $this->store->memberData[1]['phone']);
        self::assertSame($before, $this->store->memberData[1]['registeredOn']);
        self::assertSame(['members.update'], $this->store->writes);
        self::assertCount(2, $this->store->members);
    }

    public function test_a_member_can_keep_its_own_document(): void
    {
        self::assertTrue($this->useCase()->handle(2, '1001001002', 'Luis', '', '', true)->isSuccess());
    }

    public function test_the_document_of_another_member_is_rejected(): void
    {
        $result = $this->useCase()->handle(2, '1001001001', 'Luis', '', '', true);

        self::assertSame('document_taken', $result->getErrorCode());
        self::assertSame('1001001002', $this->store->memberData[2]['document']);
        self::assertSame([], $this->store->writes);
    }

    public function test_document_and_name_are_required(): void
    {
        self::assertSame('required_fields', $this->useCase()->handle(2, '', 'Luis', '', '', true)->getErrorCode());
        self::assertSame('required_fields', $this->useCase()->handle(2, '1001001002', '  ', '', '', true)->getErrorCode());
        self::assertSame([], $this->store->writes);
    }

    public function test_a_failure_while_saving_leaves_the_member_as_it_was(): void
    {
        $this->store->failOn('members.update');

        try {
            $this->useCase()->handle(1, '1001001001', 'Otro nombre', '', '', true);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            // expected
        }

        self::assertSame('Ana Torres', $this->store->members[1]->getName());
        self::assertSame(1, $this->transactions->rollbacks);
    }
}
