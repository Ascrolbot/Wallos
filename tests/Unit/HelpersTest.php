<?php
/**
 * Unit tests file for getPricePerMonth() function.
 *
 * Theis test about the core financial calculation that normalises subscription costs from various billing cycles (daily/weekly/monthly/yearly) to a monthly equivalent.
 * This function is critical for accurate spending statistics
 *
 * Source: includes/stats_calculations.php, lines 3-19
 *
 * Note: Actual function signature is getPricePerMonth($cycle, $frequency, $price)
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversFunction;

#[CoversFunction('getPricePerMonth')]
class HelpersTest extends TestCase
{
    private const CYCLE_DAILY   = 1;
    private const CYCLE_WEEKLY  = 2;
    private const CYCLE_MONTHLY = 3;
    private const CYCLE_YEARLY  = 4;

    // Standard billing cycle tests (happy path)

    #[Test]
    public function dailySubscriptionConvertsToMonthlyCorrectly(): void
    {
        $result = getPricePerMonth(self::CYCLE_DAILY, 1, 1.00);
        $this->assertEqualsWithDelta(30.00, $result, 0.01,
            'Daily $1 subscription should equal $30/month');
    }

    #[Test]
    public function weeklySubscriptionConvertsToMonthlyCorrectly(): void
    {
        $result = getPricePerMonth(self::CYCLE_WEEKLY, 1, 10.00);
        $this->assertEqualsWithDelta(43.50, $result, 0.01,
            'Weekly $10 subscription should equal $43.50/month');
    }

    #[Test]
    public function monthlySubscriptionReturnsDirectPrice(): void
    {
        $result = getPricePerMonth(self::CYCLE_MONTHLY, 1, 15.00);
        $this->assertEqualsWithDelta(15.00, $result, 0.01,
            'Monthly subscription should return the direct price');
    }

    #[Test]
    public function yearlySubscriptionConvertsToMonthlyCorrectly(): void
    {
        $result = getPricePerMonth(self::CYCLE_YEARLY, 1, 120.00);
        $this->assertEqualsWithDelta(10.00, $result, 0.01,
            'Yearly $120 subscription should equal $10/month');
    }


    // Multi-frequency tests 

    #[Test]
    public function biweeklySubscriptionCalculatesCorrectly(): void
    {
        $result = getPricePerMonth(self::CYCLE_WEEKLY, 2, 20.00);
        $this->assertEqualsWithDelta(43.50, $result, 0.01,
            'Biweekly $20 subscription should equal $43.50/month');
    }

    #[Test]
    public function quarterlySubscriptionCalculatesCorrectly(): void
    {
        $result = getPricePerMonth(self::CYCLE_MONTHLY, 3, 30.00);
        $this->assertEqualsWithDelta(10.00, $result, 0.01,
            'Quarterly $30 subscription should equal $10/month');
    }

    #[Test]
    public function biannualSubscriptionCalculatesCorrectly(): void
    {
        $result = getPricePerMonth(self::CYCLE_MONTHLY, 6, 60.00);
        $this->assertEqualsWithDelta(10.00, $result, 0.01,
            'Biannual $60 subscription should equal $10/month');
    }

    
    // Defect 1 Fixed: frequency=0 now returns 0.0 safely 

    #[Test]
    public function frequencyZeroReturnsZeroAfterFix(): void
    {
        $result = getPricePerMonth(self::CYCLE_MONTHLY, 0, 10.00);
        $this->assertEqualsWithDelta(0.0, $result, 0.01,
            'Frequency zero should return 0.0 after fix (was DivisionByZeroError)');
    }

    #[Test]
    public function negativeFrequencyReturnsZeroAfterFix(): void
    {
        $result = getPricePerMonth(self::CYCLE_MONTHLY, -1, 10.00);
        $this->assertEqualsWithDelta(0.0, $result, 0.01,
            'Negative frequency should return 0.0 after fix');
    }

    #[Test]
    public function frequencyZeroDailyCycleReturnsZeroAfterFix(): void
    {
        $result = getPricePerMonth(self::CYCLE_DAILY, 0, 10.00);
        $this->assertEqualsWithDelta(0.0, $result, 0.01);
    }

    #[Test]
    public function frequencyZeroYearlyCycleReturnsZeroAfterFix(): void
    {
        $result = getPricePerMonth(self::CYCLE_YEARLY, 0, 10.00);
        $this->assertEqualsWithDelta(0.0, $result, 0.01);
    }


    // Defect 2 Fixed: unrecognised cycle uses default case

    #[Test]
    public function unrecognisedCycleUsesDefaultAfterFix(): void
    {
        $result = getPricePerMonth(99, 1, 10.00);
        $this->assertEqualsWithDelta(10.00, $result, 0.01,
            'Unrecognised cycle should fall back to monthly calculation after fix');
    }

    #[Test]
    public function cycleZeroUsesDefaultAfterFix(): void
    {
        $result = getPricePerMonth(0, 1, 10.00);
        $this->assertEqualsWithDelta(10.00, $result, 0.01,
            'Cycle zero should fall back to monthly calculation after fix');
    }

    #[Test]
    public function negativeCycleUsesDefaultAfterFix(): void
    {
        $result = getPricePerMonth(-1, 1, 10.00);
        $this->assertEqualsWithDelta(10.00, $result, 0.01,
            'Negative cycle should fall back to monthly calculation after fix');
    }


    // Boundary and precision tests 

    #[Test]
    public function zeroPriceReturnsZero(): void
    {
        $result = getPricePerMonth(self::CYCLE_MONTHLY, 1, 0.00);
        $this->assertEqualsWithDelta(0.00, $result, 0.01);
    }

    #[Test]
    public function verySmallPriceHandledCorrectly(): void
    {
        $result = getPricePerMonth(self::CYCLE_MONTHLY, 1, 0.01);
        $this->assertEqualsWithDelta(0.01, $result, 0.001);
    }

    #[Test]
    public function largePriceHandledCorrectly(): void
    {
        $result = getPricePerMonth(self::CYCLE_YEARLY, 1, 9999.99);
        $this->assertEqualsWithDelta(833.33, $result, 0.01);
    }

    #[Test]
    public function negativePriceCalculatesWithoutError(): void
    {
        $result = getPricePerMonth(self::CYCLE_MONTHLY, 1, -10.00);
        $this->assertEqualsWithDelta(-10.00, $result, 0.01,
            'Negative prices are not rejected by the function');
    }


    // Data provider for parametric coverage

    public static function billingCycleProvider(): array
    {
        return [
            'daily $1/day'         => [1, 1, 1.00, 30.00],
            'daily $2/2days'       => [1, 2, 2.00, 30.00],
            'weekly $10/week'      => [2, 1, 10.00, 43.50],
            'weekly $20/2weeks'    => [2, 2, 20.00, 43.50],
            'monthly $15/month'    => [3, 1, 15.00, 15.00],
            'monthly $30/3months'  => [3, 3, 30.00, 10.00],
            'yearly $120/year'     => [4, 1, 120.00, 10.00],
            'yearly $240/2years'   => [4, 2, 240.00, 10.00],
        ];
    }

    #[Test]
    #[DataProvider('billingCycleProvider')]
    public function billingCycleCalculationsAreAccurate(
        int $cycle, int $frequency, float $price, float $expectedMonthly
    ): void {
        $result = getPricePerMonth($cycle, $frequency, $price);
        $this->assertEqualsWithDelta($expectedMonthly, $result, 0.01,
            "Cycle={$cycle}, Freq={$frequency}, Price={$price} should give {$expectedMonthly}/month");
    }
}
