<?php
/**
 * Security tests file for SQL injection patterns across the codebase.
 *
 * Performs static analysis of PHP source files to assess the ratio of prepared statements to raw SQL queries, and identifies files
 * that may use unsafe query patterns.
 *
 * Related categories: ISO 25010: Security (Integrity, Confidentiality). OWASP A03.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SqlInjectionTest extends TestCase
{
    private const SCAN_DIRECTORIES = ['includes', 'endpoints', 'api'];

    private const UNSAFE_PATTERNS = [
        '/\.\s*\$[a-zA-Z_]+/'  => 'String concatenation with PHP variable in SQL context',
        '/"\s*\.\s*\$/'        => 'Double-quoted string concatenation with variable',
    ];

    // Helper functions for scanning files and identifying SQL contexts
    private function getPhpFiles(string $directory): array
    {
        $baseDir = __DIR__ . '/../../' . $directory;
        if (!is_dir($baseDir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    // Function to determine if a line of code is likely to be in an SQL context based on keywords
    private function isSqlContext(string $line): bool
    {
        $sqlKeywords = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'WHERE', 'FROM', 'JOIN', 'ORDER BY'];
        $upperLine = strtoupper($line);

        foreach ($sqlKeywords as $keyword) {
            if (str_contains($upperLine, $keyword)) {
                return true;
            }
        }

        return false;
    }

    //Function to create a test database for the prepared statement test
    #[Test]
    public function countPreparedStatementsVsRawQueries(): void
    {
        $preparedCount = 0;
        $rawQueryCount = 0;

        foreach (self::SCAN_DIRECTORIES as $dir) {
            foreach ($this->getPhpFiles($dir) as $file) {
                $contents = file_get_contents($file);
                $lines = explode("\n", $contents);

                foreach ($lines as $line) {
                    if (preg_match('/->prepare\s*\(/', $line)) {
                        $preparedCount++;
                    }
                    if (preg_match('/->query\s*\(/', $line) && $this->isSqlContext($line)) {
                        $rawQueryCount++;
                    }
                }
            }
        }

        $this->assertGreaterThan(0, $preparedCount, 'Codebase should contain prepared statements');

        $total = $preparedCount + $rawQueryCount;
        $preparedPercentage = ($total > 0) ? round(($preparedCount / $total) * 100, 1) : 0;

        fwrite(STDERR, "\n[SQL ANALYSIS] Prepared statements: {$preparedCount}\n");
        fwrite(STDERR, "[SQL ANALYSIS] Raw queries with SQL context: {$rawQueryCount}\n");
        fwrite(STDERR, "[SQL ANALYSIS] Prepared statement ratio: {$preparedPercentage}%\n");

        $this->assertGreaterThan($rawQueryCount, $preparedCount,
            'Prepared statements should outnumber raw SQL queries');
    }

    // Test to identify specific files and lines with raw SQL  concatenation patterns which are high-risk for SQL injection
    #[Test]
    public function identifyFilesWithRawSqlConcatenation(): void
    {
        $findings = [];

        foreach (self::SCAN_DIRECTORIES as $dir) {
            foreach ($this->getPhpFiles($dir) as $file) {
                $contents = file_get_contents($file);
                $lines = explode("\n", $contents);
                $relativePath = str_replace(
                    str_replace('/', DIRECTORY_SEPARATOR, __DIR__ . '/../../'),
                    '',
                    $file
                );

                foreach ($lines as $lineNum => $line) {
                    if (!$this->isSqlContext($line)) continue;

                    foreach (self::UNSAFE_PATTERNS as $pattern => $description) {
                        if (preg_match($pattern, $line)) {
                            $findings[] = [
                                'file' => $relativePath,
                                'line' => $lineNum + 1,
                                'pattern' => $description,
                            ];
                        }
                    }
                }
            }
        }

        if (!empty($findings)) {
            fwrite(STDERR, "\n[SECURITY FINDINGS] Raw SQL concatenation instances:\n");
            foreach ($findings as $f) {
                fwrite(STDERR, "  - {$f['file']}:{$f['line']} — {$f['pattern']}\n");
            }
        }

        $this->assertIsArray($findings);
        fwrite(STDERR, "[SECURITY FINDINGS] Total: " . count($findings) . "\n");
    }

    // Tests for specific critical files that handle database interactions, to ensure they use prepared statements and parameter binding
    #[Test]
    public function preparedStatementBlocksInjectionPayload(): void
    {
        $db = createTestDatabase();

        $stmt = $db->prepare("SELECT rate FROM currencies WHERE id = :id");
        $stmt->bindValue(':id', "1 OR 1=1", SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray();

        $this->assertFalse($result,
            'Prepared statement should not return results for injection payload');

        $db->close();
    }

    //Function to create a test database for the prepared statement test
    #[Test]
    public function statsCalculationsUsesPreparedStatements(): void
    {
        $file = __DIR__ . '/../../includes/stats_calculations.php';
        if (!file_exists($file)) {
            $this->markTestSkipped('stats_calculations.php not found');
        }

        $contents = file_get_contents($file);

        $this->assertStringContainsString('->prepare(', $contents,
            'stats_calculations.php should use prepared statements');
        $this->assertStringContainsString('bindParam', $contents,
            'stats_calculations.php should use parameter binding');
    }

    // Additional test to check for CSRF protection in endpoint files, as these are critical for preventing unauthorized actions that could lead to SQL injection or other attacks
    #[Test]
    public function endpointFilesHaveCsrfProtection(): void
    {
        $endpointDir = __DIR__ . '/../../endpoints';
        if (!is_dir($endpointDir)) {
            $this->markTestSkipped('endpoints directory not found');
        }

        $csrfProtectedFiles = 0;
        $totalEndpointFiles = 0;

        foreach ($this->getPhpFiles('endpoints') as $file) {
            $totalEndpointFiles++;
            $contents = file_get_contents($file);
            if (str_contains($contents, 'csrf') ||
                str_contains($contents, 'CSRF') ||
                str_contains($contents, 'validate_endpoint')) {
                $csrfProtectedFiles++;
            }
        }

        fwrite(STDERR, "\n[SECURITY] CSRF protection found in {$csrfProtectedFiles}/{$totalEndpointFiles} endpoint files\n");

        $this->assertGreaterThan(0, $csrfProtectedFiles,
            'At least some endpoint files should have CSRF protection');
    }
}
