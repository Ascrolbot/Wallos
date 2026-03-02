<?php
/**
 * Unit tests file for getPriceConverted() function.
 *
 * Tests currency conversion logic that converts subscription prices from foreign currencies back to the user's main currency using
 * stored exchange rates.
 *
 * Source: includes/stats_calculations.php, lines 21-36
 *
 * Two key defects discovered during testing:
 *   Defect 3: getPriceConverted() is duplicated across multiple files with inconsistent function signatures (3-param vs 4-param versions).
 *   Defect 4: The duplicate in list_subscriptions.php lacks a user_id parameter, meaning currency lookups are not scoped to the user. 
 *             This is a tenant isolation concern
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversFunction;

#[CoversFunction('getPriceConverted')]
class PriceConversionTest extends TestCase
{
    private SQLite3 $db;

    protected function setUp(): void
    {
        $this->db = createTestDatabase();
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    // Standard conversion tests (happy path) 

    #[Test]
    public function mainCurrencyReturnsUnchangedPrice(): void
    {
        $result = getPriceConverted(100.00, 1, $this->db, 1);
        $this->assertEqualsWithDelta(100.00, $result, 0.01,
            'Main currency (rate=1.0) should return the original price');
    }

    #[Test]
    public function eurConversionCalculatesCorrectly(): void
    {
        $result = getPriceConverted(100.00, 2, $this->db, 1);
        $this->assertEqualsWithDelta(117.65, $result, 0.01,
            'EUR price should be divided by EUR rate to convert to main currency');
    }

    #[Test]
    public function gbpConversionCalculatesCorrectly(): void
    {
        $result = getPriceConverted(50.00, 3, $this->db, 1);
        $this->assertEqualsWithDelta(68.49, $result, 0.01,
            'GBP price should be divided by GBP rate');
    }

    #[Test]
    public function jpyConversionCalculatesCorrectly(): void
    {
        $result = getPriceConverted(1000.00, 4, $this->db, 1);
        $this->assertEqualsWithDelta(6.69, $result, 0.01,
            'JPY price should be divided by JPY rate');
    }

    #[Test]
    public function nonExistentCurrencyReturnsOriginalPrice(): void
    {
        $result = getPriceConverted(100.00, 999, $this->db, 1);
        $this->assertEqualsWithDelta(100.00, $result, 0.01,
            'Non-existent currency should fall back to returning original price');
    }

    // Edge case and boundary tests 

    #[Test]
    public function zeroPriceReturnsZero(): void
    {
        $result = getPriceConverted(0.00, 2, $this->db, 1);
        $this->assertEqualsWithDelta(0.00, $result, 0.01);
    }

    #[Test]
    public function verySmallPriceConvertsAccurately(): void
    {
        $result = getPriceConverted(0.01, 2, $this->db, 1);
        $this->assertGreaterThan(0, $result,
            'Very small price should still produce a positive converted value');
    }

    #[Test]
    public function largePriceConvertsWithoutOverflow(): void
    {
        $result = getPriceConverted(999999.99, 2, $this->db, 1);
        $this->assertIsFloat($result);
        $this->assertGreaterThan(0, $result);
    }

    // Defect detection tests 

    /**
     * Defect 3: getPriceConverted() is duplicated across multiple files with inconsistent signatures. The version in stats_calculations.php
     * takes 4 parameters (including userId for tenant scoping), while list_subscriptions.php and the API files use only 3 parameters.
     * Related categories: ISO 25010: Maintainability (Reusability, Modifiability)
     */
    #[Test]
    public function duplicateImplementationsExistWithInconsistentSignatures(): void
    {
        $statsFile = __DIR__ . '/../../includes/stats_calculations.php';
        $listFile = __DIR__ . '/../../includes/list_subscriptions.php';
        $apiFile = __DIR__ . '/../../api/subscriptions/get_subscriptions.php';

        // All files should contain getPriceConverted
        $this->assertStringContainsString('getPriceConverted',
            file_get_contents($statsFile),
            'stats_calculations.php should contain getPriceConverted');

        $this->assertStringContainsString('getPriceConverted',
            file_get_contents($listFile),
            'list_subscriptions.php should contain getPriceConverted (duplicate)');

        if (file_exists($apiFile)) {
            $this->assertStringContainsString('getPriceConverted',
                file_get_contents($apiFile),
                'API file should contain getPriceConverted (another duplicate)');
        }

        // The stats version uses user_id scoping, list version does not
        $statsSource = file_get_contents($statsFile);
        $listSource = file_get_contents($listFile);

        $statsHasUserId = (bool)preg_match(
            '/function\s+getPriceConverted\s*\([^)]*\$userId/',
            $statsSource
        );
        $listHasUserId = (bool)preg_match(
            '/function\s+getPriceConverted\s*\([^)]*\$userId/',
            $listSource
        );

        $this->assertTrue($statsHasUserId,
            'stats_calculations.php version includes userId parameter');
        $this->assertFalse($listHasUserId,
            'Defect 3: list_subscriptions.php version is missing userId parameter');
    }

    /**
     * Defect 4: The duplicate getPriceConverted() in list_subscriptions.php queries currencies without filtering by user_id. In a multi-tenant
     * context, this means currency lookups are not scoped to the owning user. Related categories: ISO 25010: Security (Integrity), Maintainability
     */
    #[Test]
    public function listSubscriptionsVersionLacksUserIdFiltering(): void
    {
        $listFile = __DIR__ . '/../../includes/list_subscriptions.php';
        if (!file_exists($listFile)) {
            $this->markTestSkipped('list_subscriptions.php not found');
        }

        $source = file_get_contents($listFile);

        // Extract just the getPriceConverted function body
        $pattern = '/function\s+getPriceConverted\s*\([^)]*\)\s*\{[^}]+\}/s';
        preg_match($pattern, $source, $matches);

        $this->assertNotEmpty($matches,
            'Should find getPriceConverted function in list_subscriptions.php');

        $functionBody = $matches[0];

        // Check that user_id is NOT referenced in this version
        $this->assertStringNotContainsString('user_id', $functionBody,
            'Defect 4: list_subscriptions.php getPriceConverted does not filter by user_id');
    }
}
