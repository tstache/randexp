<?php
declare(strict_types=1);

namespace Tests\RandExp;

use Exception;
use PHPUnit\Framework\TestCase;
use RandExp\RandExp;
use RandExp\RegexException;

use function max;
use function mb_ord;
use function mb_strlen;
use function mb_substr;
use function min;
use function preg_match;
use function strlen;

class RandExpTest extends TestCase
{
    /**
     * @return void
     * @throws Exception
     */
    public function testModifyMax(): void
    {
        $re = new RandExp('.*');
        $re->maxRepetition = 0;
        static::assertEquals('', $re->generate());
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testFixedSeed(): void
    {
        $aRE = new RandExp('.{100}', 'f');
        $a = $aRE->generate();

        $bRE = new RandExp('.{100}', 'f');
        $b = $bRE->generate();

        static::assertEquals($a, $b, 'same seed should produce same output');
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testBasicGeneration(): void
    {
        static::assertEquals(4, strlen((new RandExp('\\d{4}'))->generate()));
        static::assertEquals('hi', (new RandExp('hi(?= no one)'))->generate());
        static::assertEquals('hi', (new RandExp('hi(?! no one)'))->generate());
        // Matches the ASCII character expressed by the UNICODE XXXX
        static::assertEquals("\u{00A3}", (new RandExp("\\u00A3"))->generate());
    }

    public function assertCharactersBetween(string $actualValue, int $expectedMin, int $expectedMax, string $message): void
    {
        $length = mb_strlen($actualValue);
        $maxChar = 0;
        $minChar = PHP_INT_MAX;
        for ($i = 0; $i < $length; $i++) {
            $maxChar = max($maxChar, mb_ord(mb_substr($actualValue, $i, 1)));
            $minChar = min($minChar, mb_ord(mb_substr($actualValue, $i, 1)));
        }
        static::assertLessThanOrEqual($expectedMax, $maxChar, $message);
        static::assertGreaterThanOrEqual($expectedMin, $minChar, $message);
    }

    /**
     * @dataProvider rangeProvider
     *
     * @param string $regex
     *
     * @return void
     * @throws RegexException
     * @throws Exception
     */
    public function testRange(string $regex): void
    {
        $re = new RandExp($regex);

        // Before changing range
        $this->assertCharactersBetween($re->generate(), 0, 127, 'ascii characters should have been generated');

        // After changing range
        $re->charRange->subtract(0, 126);
        $re->charRange->add(127, 65535);
        $this->assertCharactersBetween($re->generate(), 127, 65535, 'non-ascii characters should have been generated');
    }

    /**
     * A string that matches these regular expressions does not exist
     *
     * @return string[]
     */
    public function rangeProvider(): array
    {
        return [
            ['.{100}'],
            ['[\s\S]{100}'],
            ['[^a]{100}'],
            ['.{10000}'],
        ];
    }

    /**
     * @dataProvider badPatternProvider
     *
     * @param string $pattern
     *
     * @return void
     * @throws RegexException
     * @throws Exception
     */
    public function testBadPatternDoesNotMatch(string $pattern): void
    {
        $rand = new RandExp($pattern);
        // Generate several times.
        for ($k = 0; $k < 5; $k++) {
            $str = $rand->generate();
            $match = (bool)preg_match("/$pattern/u", $str);
            static::assertNotTrue($match, "Generated string '$str' matches regexp '$pattern'");
        }
    }

    /**
     * A string that matches these regular expressions does not exist
     *
     * @return string[]
     */
    public function badPatternProvider(): array
    {
        return [
            'a^'      => ['a^'],
            'b^'      => ['b^'],
            '$c'      => ['$c'],
            '$d'      => ['$d'],
            'e\bf'    => ['e\bf'],
            '\Bg'     => ['\Bg'],
            '[^\W\w]' => ['[^\W\w]'],
            '[^\D\d]' => ['[^\D\d]'],
            '[^\S\s]' => ['[^\S\s]'],
        ];
    }

    /**
     * @dataProvider goodPatternProvider
     *
     * @param string $pattern
     *
     * @return void
     * @throws RegexException
     * @throws Exception
     */
    public function testGoodPatternMatches(string $pattern): void
    {
        $rand = new RandExp($pattern);
        // Generate several times.
        for ($k = 0; $k < 5; $k++) {
            $str = $rand->generate();
            $match = (bool)preg_match("/$pattern/u", $str);
            static::assertTrue($match, "Generated string '$str' does not match regexp '$pattern'");
        }
    }

    /**
     * A string that matches these regular expressions does not exist
     *
     * @return string[]
     */
    public function goodPatternProvider(): array
    {
        return [
            // Ignore the case of alphabetic characters
            //'hey there/i',
            // Only matches the beginning of a string
            '^The'             => ['^The'],

            // Only matches the end of a string
            'and$'             => ['and$'],

            // Matches any word boundary (test characters must exist at the beginning or end of a word within the string)
            'ly\b'             => ['ly\b'],

            // Matches any non-word boundary.
            'm\Bore'           => ['m\Bore'],

            // All characters except the listed special characters match a single instance of themselves
            'a'                => ['a'],

            // A backslash escapes special characters to suppress their special meaning
            '\+'               => ['\+'],

            // Matches a new line character
            'a new\nline'      => ['a new\nline'],

            // Matches a form feed character
            '\f'               => ['\f'],

            // Matches a tab character
            'col1\tcol2\tcol3' => ['col1\tcol2\tcol3'],

            // Matches a vertical tab character
            'row1\vrow2'       => ['row1\vrow2'],

            // Matches a backspace
            'something[\b]'    => ['something[\b]'],

            // Matches any one character enclosed in the character set. You may use a hyphen to denote range
            '[abcD!]'          => ['[abcD!]'],
            '[a-z]'            => ['[a-z]'],
            '[0-4]'            => ['[0-4]'],
            '[a-zA-Z0-9]'      => ['[a-zA-Z0-9]'],
            '[\w]'             => ['[\w]'],
            '[\d]'             => ['[\d]'],
            '[\s]'             => ['[\s]'],
            '[\W]'             => ['[\W]'],
            '[\D]'             => ['[\D]'],
            '[\S]'             => ['[\S]'],

            // Matches any one character not enclosed in the character set
            '[^AN]BC'          => ['[^AN]BC'],
            '[^\w]'            => ['[^\w]'],
            '[^\d]'            => ['[^\d]'],
            '[^\s]'            => ['[^\s]'],
            '[^\W]'            => ['[^\W]'],
            '[^\D]'            => ['[^\D]'],
            '[^\S]'            => ['[^\S]'],

            // Matches any character except newline or another Unicode line terminator
            'b.t'              => ['b.t'],

            // Matches any alphanumeric character including the underscore. Equivalent to [a-zA-Z0-9]
            '\w'               => ['\w'],

            // Matches any single non-word character. Equivalent to [^a-zA-Z0-9]
            '\W'               => ['\W'],

            // Matches any single digit. Equivalent to [0-9]
            '\d\d\d\d'         => ['\d\d\d\d'],

            // Matches any non-digit, Equivalent to [^0-9]
            '\D'               => ['\D'],

            // Matches any single space character. Equivalent to
            // [ \\f\\n\\r\\t\\v\\u00A0\\u1680\\u180e\\u2000\\u2001\\u2002\\u2003\\u2004\\u2005\\u2006\\u2007\\u2008\\u2009\\u200a\\u2028\\u2029\\u2028\\u2029\\u202f\\u205f\\u3000]
            'in\sbetween'      => ['in\sbetween'],

            // Matches any single non-space character. Equivalent to
            // [^ \\f\\n\\r\\t\\v\\u00A0\\u1680\\u180e\\u2000\\u2001\\u2002\\u2003\\u2004\\u2005\\u2006\\u2007\\u2008\\u2009\\u200a\\u2028\\u2029\\u2028\\u2029\\u202f\\u205f\\u3000]
            '\S'               => ['\S'],

            // Matches exactly x occurrences of a regular expression
            '\d{5}'            => ['\d{5}'],

            // Matches x or more occurrences of a regular expression
            '\s{2,}'           => ['\s{2,}'],

            // Matches x to y number of occurrences of a regular expression
            '\d{2,4}'          => ['\d{2,4}'],

            // Matches zero or one occurrences. Equivalent to {0,1}
            'a\s?b'            => ['a\s?b'],

            // Matches zero or more occurrences. Equivalent to {0,}
            'we*'              => ['we*'],

            // Matches one ore more occurrences. Equivalent to {1,}
            'fe+d'             => ['fe+d'],

            // Grouping characters together to create a clause. May be nested. Also captures the desired subpattern
            '(abc)+(def)'      => ['(abc)+(def)'],

            // Matches x but does not capture it
            '(?:.d){2}'        => ['(?:.d){2}'],

            // Matches only one clause on either side of the pipe
            'forever|young'    => ['forever|young'],

            // "\\x" (where x is a number from 1 to 9) when added to the end of a regular expression pattern allows you
            // to back reference a subpattern within the pattern, so the value of the subpattern is remembered and used
            // as part of the matching.
            '(\w+)\s+\1'       => ['(\w+)\s+\1'],
            '(a|b){5}\1'       => ['(a|b){5}\1'],
            '(a)(b)\1\2'       => ['(a)(b)\1\2'],
        ];
    }

    /**
     * @dataProvider escapedPatternProvider
     *
     * @param string $pattern
     * @param string $expectation
     *
     * @return void
     * @throws RegexException
     * @throws Exception
     */
    public function testEscapedPatterns(string $pattern, string $expectation): void
    {
        static::assertEquals($expectation, (new RandExp($pattern))->generate());
    }

    public function escapedPatternProvider(): array
    {
        return [
            'as.df' => ['as\.df', 'as.df'],
            'as^df' => ['as\^df', 'as^df'],
            'as$df' => ['as\$df', 'as$df'],
            'as|df' => ['as\|df', 'as|df'],
            'as[d]' => ['as\[d\]', 'as[d]'],
            'as(d)' => ['as\(d\)', 'as(d)'],
            'as{d}' => ['as\{d\}', 'as{d}'],
            'as?df' => ['as\?df', 'as?df'],
            'as+df' => ['as\+df', 'as+df'],
            'as*df' => ['as\*df', 'as*df'],
            // Backslash must be escaped multiple times for PHP strings
            'as\df' => ['as\\\\df', 'as\df'],
        ];
    }
}
