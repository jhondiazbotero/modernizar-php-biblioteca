<?php

declare(strict_types=1);

namespace Library\Tests\Domain;

use Library\Domain\FinePolicy;
use Library\Domain\LendingRules;
use PHPUnit\Framework\TestCase;

final class FinePolicyTest extends TestCase
{
    private function fine(string $due, string $returned): int
    {
        return (new FinePolicy())->fineFor(new \DateTimeImmutable($due), new \DateTimeImmutable($returned));
    }

    public function test_the_rules_constants_are_the_library_policy(): void
    {
        self::assertSame(14, LendingRules::LOAN_DAYS);
        self::assertSame(3, LendingRules::MAX_ACTIVE_LOANS);
        self::assertSame(500, LendingRules::FINE_PER_DAY);
    }

    public function test_returned_on_the_due_date_has_no_fine(): void
    {
        self::assertSame(0, $this->fine('2026-11-30', '2026-11-30'));
    }

    public function test_returned_before_the_due_date_has_no_fine(): void
    {
        self::assertSame(0, $this->fine('2026-11-30', '2026-11-20'));
    }

    public function test_returned_before_the_due_date_in_an_earlier_month_has_no_fine(): void
    {
        self::assertSame(0, $this->fine('2026-12-05', '2026-11-27'));
    }

    public function test_one_day_late_in_the_same_month_costs_one_day(): void
    {
        self::assertSame(500, $this->fine('2026-11-10', '2026-11-11'));
    }

    public function test_late_in_the_same_month_charges_500_per_day(): void
    {
        self::assertSame(13000, $this->fine('2026-11-01', '2026-11-27'));
    }

    public function test_the_time_of_day_does_not_matter(): void
    {
        self::assertSame(
            1500,
            (new FinePolicy())->fineFor(new \DateTimeImmutable('2026-11-10 18:30:00'), new \DateTimeImmutable('2026-11-13 08:00:00'))
        );
    }

    /** Current behavior, including known defect F1: crossing a month with a smaller day gives 0. */
    public function test_f1_crossing_a_month_with_a_smaller_day_gives_zero(): void
    {
        // due 28 March, returned 3 April: six days late, the day of month difference is negative
        self::assertSame(0, $this->fine('2026-03-28', '2026-04-03'));
    }

    /** Current behavior, including known defect F1: crossing a month with a bigger day charges the difference only. */
    public function test_f1_crossing_a_month_with_a_bigger_day_charges_the_difference_of_days(): void
    {
        // due 10 October, returned 27 November: 48 days late, charged as 27 - 10 = 17 days
        self::assertSame(8500, $this->fine('2026-10-10', '2026-11-27'));
    }

    /** Current behavior, including known defect F1: 31 December to 2 January. */
    public function test_f1_across_the_new_year_from_31_december_gives_zero(): void
    {
        self::assertSame(0, $this->fine('2026-12-31', '2027-01-02'));
    }

    /** Current behavior, including known defect F1: the same day number in another month charges nothing. */
    public function test_f1_the_same_day_number_in_the_next_month_gives_zero(): void
    {
        self::assertSame(0, $this->fine('2026-10-31', '2026-11-30'));
    }

    public function test_leap_day_due_date_late_by_one_day_in_the_same_month(): void
    {
        self::assertSame(500, $this->fine('2028-02-28', '2028-02-29'));
    }

    /** Current behavior, including known defect F1: due on 29 February, returned on 2 March. */
    public function test_f1_leap_day_to_the_start_of_march_gives_zero(): void
    {
        self::assertSame(0, $this->fine('2028-02-29', '2028-03-02'));
    }

    public function test_the_result_is_always_an_integer_number_of_pesos(): void
    {
        self::assertSame(500 * 26, $this->fine('2026-11-01', '2026-11-27'));
    }
}
