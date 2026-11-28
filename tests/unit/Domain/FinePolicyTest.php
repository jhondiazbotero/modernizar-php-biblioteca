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

    public function test_crossing_a_month_with_a_smaller_day_charges_the_real_days_late(): void
    {
        // due 28 March, returned 3 April: six calendar days late
        self::assertSame(3000, $this->fine('2026-03-28', '2026-04-03'));
    }

    public function test_crossing_a_month_with_a_bigger_day_charges_the_real_days_late(): void
    {
        // due 10 October, returned 27 November: 48 calendar days late
        self::assertSame(24000, $this->fine('2026-10-10', '2026-11-27'));
    }

    public function test_across_the_new_year_from_31_december_charges_two_days(): void
    {
        self::assertSame(1000, $this->fine('2026-12-31', '2027-01-02'));
    }

    public function test_the_same_day_number_in_the_next_month_charges_the_days_of_the_month(): void
    {
        // due 31 October, returned 30 November: 30 days late
        self::assertSame(15000, $this->fine('2026-10-31', '2026-11-30'));
    }

    public function test_the_end_of_january_to_the_start_of_march_in_a_common_year_is_29_days(): void
    {
        self::assertSame(14500, $this->fine('2026-01-31', '2026-03-01'));
    }

    public function test_the_end_of_february_to_the_start_of_march_in_a_leap_year_is_2_days(): void
    {
        self::assertSame(1000, $this->fine('2028-02-28', '2028-03-01'));
    }

    public function test_a_loan_due_on_16_november_returned_on_12_december_is_26_days_late(): void
    {
        self::assertSame(13000, $this->fine('2026-11-16', '2026-12-12'));
    }

    public function test_spring_forward_in_a_zone_with_daylight_saving_does_not_lose_a_day(): void
    {
        $zone = new \DateTimeZone('America/New_York');

        self::assertSame(
            3000,
            (new FinePolicy())->fineFor(new \DateTimeImmutable('2026-03-06 20:00:00', $zone), new \DateTimeImmutable('2026-03-12 07:00:00', $zone))
        );
    }

    public function test_the_end_of_daylight_saving_does_not_add_a_day(): void
    {
        $zone = new \DateTimeZone('America/New_York');

        self::assertSame(
            1500,
            (new FinePolicy())->fineFor(new \DateTimeImmutable('2026-10-30 23:30:00', $zone), new \DateTimeImmutable('2026-11-02 00:30:00', $zone))
        );
    }

    public function test_leap_day_due_date_late_by_one_day_in_the_same_month(): void
    {
        self::assertSame(500, $this->fine('2028-02-28', '2028-02-29'));
    }

    public function test_leap_day_due_date_returned_on_2_march_is_2_days_late(): void
    {
        self::assertSame(1000, $this->fine('2028-02-29', '2028-03-02'));
    }

    public function test_the_result_is_always_an_integer_number_of_pesos(): void
    {
        self::assertSame(500 * 26, $this->fine('2026-11-01', '2026-11-27'));
    }
}
