<?php
/**
 * Unit tests file for the validate() function.
 *
 * Tests the input sanitisation function used throughout Wallos.
 * Source: includes/inputvalidation.php
 *
 * Design concern identified during testing:
 *   Defect 5: validate() applies htmlspecialchars() at input time,
 *     conflating input sanitisation with output encoding. This means
 *     data stored in the database is already HTML-encoded.
 *   Defect 6: If output templates also apply htmlspecialchars(),
 *     double encoding occurs (e.g. & becomes &amp;amp;).
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversFunction;

#[CoversFunction('validate')]
class InputValidationTest extends TestCase
{
    // Whitespace handling tests

    #[Test]
    public function trimRemovesLeadingAndTrailingWhitespace(): void
    {
        $this->assertEquals('hello', validate('  hello  '));
    }

    #[Test]
    public function trimRemovesTabsAndNewlines(): void
    {
        $this->assertEquals('test', validate("\t\ntest\r\n"));
    }

    #[Test]
    public function emptyStringReturnsEmpty(): void
    {
        $this->assertEquals('', validate(''));
    }

    #[Test]
    public function whitespaceOnlyReturnsEmpty(): void
    {
        $this->assertEquals('', validate('   '));
    }

    // Slash handling tests

    #[Test]
    public function stripslashesRemovesBackslashes(): void
    {
        $this->assertEquals("it&#039;s a test", validate("it\'s a test"));
    }

    // HTML encoding behaviour tests

    #[Test]
    public function scriptTagIsEncoded(): void
    {
        $input = '<script>alert("xss")</script>';
        $result = validate($input);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    #[Test]
    public function htmlTagsAreEncoded(): void
    {
        $result = validate('<img src=x onerror=alert(1)>');
        $this->assertStringNotContainsString('<img', $result);
    }

    #[Test]
    public function doubleQuotesAreEncoded(): void
    {
        $result = validate('" onmouseover="alert(1)"');
        $this->assertStringNotContainsString('"', $result);
        $this->assertStringContainsString('&quot;', $result);
    }

    #[Test]
    public function ampersandIsEncoded(): void
    {
        $result = validate('Tom & Jerry');
        $this->assertStringContainsString('&amp;', $result);
    }

    // XSS payload testing

    public static function xssPayloadProvider(): array
    {
        return [
            'basic script tag'     => ['<script>alert(1)</script>'],
            'img onerror'          => ['<img src=x onerror=alert(1)>'],
            'svg onload'           => ['<svg onload=alert(1)>'],
            'event handler in div' => ['<div onmouseover="alert(1)">'],
            'javascript protocol'  => ['<a href="javascript:alert(1)">click</a>'],
            'encoded script'       => ['%3Cscript%3Ealert(1)%3C/script%3E'],
            'nested tags'          => ['<<script>script>alert(1)<</script>/script>'],
            'null byte injection'  => ["test\0<script>alert(1)</script>"],
        ];
    }

    #[Test]
    #[DataProvider('xssPayloadProvider')]
    public function xssPayloadsAreNeutralised(string $payload): void
    {
        $result = validate($payload);
        $this->assertStringNotContainsString('<script>', $result);
    }

    // Unicode and special character preservation

    #[Test]
    public function unicodeCharactersPreserved(): void
    {
        $this->assertEquals('日本語テスト', validate('日本語テスト'));
    }

    // Additional tests for emojis, accented characters, and currency symbols to ensure they are not altered by the validation function
    #[Test]
    public function emojiPreserved(): void
    {
        $this->assertEquals('💰 Subscription', validate('💰 Subscription'));
    }

    #[Test]
    public function accentedCharactersPreserved(): void
    {
        $this->assertEquals('café résumé naïve', validate('café résumé naïve'));
    }

    #[Test]
    public function currencySymbolsPreserved(): void
    {
        $this->assertEquals('€100 £50 ¥1000', validate('€100 £50 ¥1000'));
    }


    // Design concern: double encoding risk 

    /**
     * Defect 5 and Defect 6: validate() applies htmlspecialchars() at input time. If the output layer also encodes, double encoding occurs.
     * For example: "Tom & Jerry" becomes "Tom &amp; Jerry" after validate(), then "Tom &amp;amp; Jerry" if the template also encodes.
     * Related categories: ISO 25010: Maintainability (Analysability).
     */
    #[Test]
    public function doubleEncodingRiskWhenOutputAlsoEncodes(): void
    {
        $input = 'Tom & Jerry';
        $afterValidate = validate($input);
        $afterDoubleEncode = htmlspecialchars($afterValidate);

        $this->assertStringContainsString('&amp;amp;', $afterDoubleEncode,
            'Double encoding occurs if output layer also applies htmlspecialchars()');
    }
}
