<?php

namespace Tests\RandExp;

use Exception;
use PHPUnit\Framework\TestCase;
use RandExp\RandExp;
use RandExp\RegexException;

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
        $this->assertEquals('', $re->generate());
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

        $this->assertEquals($a, $b, 'same seed should produce same output');
    }

    /**
     * @return void
     */
    public function testRange(): void
    {
        function genMaxChar(RandExp $re) {
            $output = $re->generate();
            $maxChar = 0;
            $length = strlen($output);
            for ($i = 0; $i < $length; $i++) {
                $maxChar = max($maxChar, ord($output[$i]));
            }
            return $maxChar;
        }

        $re = new RandExp('.{100}');
        $re->charRange->subtract(0, 126);
        $re->charRange->add(127, 65535);
        $maxChar = genMaxChar($re);
        $this->assertTrue($maxChar >= 127, 'non-ascii characters should have been generated');

        $re = new RandExp('[\s\S]{100}');
        $maxChar = genMaxChar($re);
        $this->assertTrue($maxChar < 127, 'ascii characters should have been generated');
        $re->charRange->subtract(0, 126);
        $re->charRange->add(127, 65535);
        $maxChar = genMaxChar($re);
        $this->assertTrue($maxChar >= 127, 'non-ascii characters should have been generated');

        $re = new RandExp('[^a]{100}');
        $maxChar = genMaxChar($re);
        $this->assertTrue($maxChar < 127, 'ascii characters should have been generated');
        $re->charRange->subtract(0, 126);
        $re->charRange->add(127, 65535);
        $maxChar = genMaxChar($re);
        $this->assertTrue($maxChar >= 127, 'non-ascii characters should have been generated');
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testBasicGeneration(): void
    {
        $this->assertEquals(4, strlen((new RandExp('\\d{4}'))->generate()));
        $this->assertEquals('hi', (new RandExp('hi(?= no one)'))->generate());
        $this->assertEquals('hi', (new RandExp('hi(?! no one)'))->generate());
        // Matches the ASCII character expressed by the UNICODE XXXX
        $this->assertEquals("\u{00A3}", (new RandExp("\\u00A3"))->generate());
    }

    /**
     * @dataProvider badPatternProvider
     *
     * @param $pattern
     *
     * @return void
     * @throws RegexException
     * @throws Exception
     */
    public function testBadPatternDoesNotMatch($pattern): void
    {
        $rand = new RandExp($pattern);
        // Generate several times.
        for ($k = 0; $k < 5; $k++) {
            $str = $rand->generate();
            $match = (bool)preg_match("/$pattern/u", $str);
            $this->assertNotTrue($match, "Generated string '$str' matches regexp '$pattern'");
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
            ['a^'],
            ['b^'],
            ['$c'],
            ['$d'],
            ['e\bf'],
            ['\Bg'],
            ['[^\W\w]'],
            ['[^\D\d]'],
            ['[^\S\s]'],
        ];
    }

    /**
     * @dataProvider goodPatternProvider
     *
     * @param $pattern
     *
     * @return void
     * @throws RegexException
     * @throws Exception
     */
    public function testGoodPatternMatches($pattern): void
    {
        $rand = new RandExp($pattern);
        // Generate several times.
        for ($k = 0; $k < 5; $k++) {
            $str = $rand->generate();
            $match = (bool)preg_match("/$pattern/u", $str);
            $this->assertTrue($match, "Generated string '$str' does not match regexp '$pattern'");
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
            ['^The'],
            // Only matches the end of a string
            ['and$'],
            // Matches any word boundary (test characters must exist at the beginning or end of a word within the string)
            ['ly\b'],
            // Matches any non-word boundary.
            ['m\Bore'],
            // All characters except the listed special characters match a single instance of themselves
            ['a'],
            // A backslash escapes special characters to suppress their special meaning
            ['\+'],
            // Matches a new line character
            ['a new\nline'],
            // Matches a form feed character
            ['\f'],
            // Matches a tab character
            ['col1\tcol2\tcol3'],
            // Matches a vertical tab character
            ['row1\vrow2'],
            // Matches a backspace
            ['something[\b]'],
            // Matches any one character enclosed in the character set. You may use a hyphen to denote range
            ['[abcD!]'],
            ['[a-z]'],
            ['[0-4]'],
            ['[a-zA-Z0-9]'],
            ['[\w]'],
            ['[\d]'],
            ['[\s]'],
            ['[\W]'],
            ['[\D]'],
            ['[\S]'],
            // Matches any one character not enclosed in the character set
            ['[^AN]BC'],
            ['[^\w]'],
            ['[^\d]'],
            ['[^\s]'],
            ['[^\W]'],
            ['[^\D]'],
            ['[^\S]'],
            // Matches any character except newline or another Unicode line terminator
            ['b.t'],
            // Matches any alphanumeric character including the underscore. Equivalent to [a-zA-Z0-9]
            ['\w'],
            // Matches any single non-word character. Equivalent to [^a-zA-Z0-9]
            ['\W'],
            // Matches any single digit. Equivalent to [0-9]
            ['\d\d\d\d'],
            // Matches any non-digit, Equivalent to [^0-9]
            ['\D'],
            // Matches any single space character. Equivalent to
            // [ \\f\\n\\r\\t\\v\\u00A0\\u1680\\u180e\\u2000\\u2001\\u2002\\u2003\\u2004\\u2005\\u2006\\u2007\\u2008\\u2009\\u200a\\u2028\\u2029\\u2028\\u2029\\u202f\\u205f\\u3000]
            ['in\sbetween'],
            // Matches any single non-space character. Equivalent to
            // [^ \\f\\n\\r\\t\\v\\u00A0\\u1680\\u180e\\u2000\\u2001\\u2002\\u2003\\u2004\\u2005\\u2006\\u2007\\u2008\\u2009\\u200a\\u2028\\u2029\\u2028\\u2029\\u202f\\u205f\\u3000]
            ['\S'],
            // Matches exactly x occurrences of a regular expression
            ['\d{5}'],
            // Matches x or more occurrences of a regular expression
            ['\s{2,}'],
            // Matches x to y number of occurrences of a regular expression
            ['\d{2,4}'],
            // Matches zero or one occurrences. Equivalent to {0,1}
            ['a\s?b'],
            // Matches zero or more occurrences. Equivalent to {0,}
            ['we*'],
            // Matches one ore more occurrences. Equivalent to {1,}
            ['fe+d'],
            // Grouping characters together to create a clause. May be nested. Also captures the desired subpattern
            ['(abc)+(def)'],
            // Matches x but does not capture it
            ['(?:.d){2}'],
            // Matches only one clause on either side of the pipe
            ['forever|young'],
            // "\\x" (where x is a number from 1 to 9) when added to the end of a regular expression pattern allows you
            // to back reference a subpattern within the pattern, so the value of the subpattern is remembered and used as part of the matching.
            ['(\w+)\s+\1'],
            ['(a|b){5}\1'],
            ['(a)(b)\1\2'],
        ];
    }
}
