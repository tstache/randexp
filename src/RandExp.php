<?php

declare(strict_types=1);

namespace RandExp;

use Exception;

use function array_push;
use function count;
use function mb_chr;
use function random_int;
use function time;

/**
 * Class RandExp creates a random string based on a regular expression
 *
 *   $randExp = new \RandExp\RandExp('H[euioa]llo{1,10} (World|You)');
 *   echo $randExp->generate();  // Output: Hellooooooo World
 *
 * @see https://github.com/fent/randexp.js
 */
class RandExp
{
    private array $tokens;
    private bool $ignoreCase;
    private bool $fixedSeed;
    private static ?int $seed = null;
    public int $maxRepetition;
    public RangeSet $charRange;

    /**
     * Creates a new object based on given regex and options.
     *
     * @param string $regexp  The regex, e.g. 'H[euioa]llo{1,10} (World|You)'
     * @param string $options Regex options to apply. Possible choices:
     *                        'i': Ignores regex case
     *                        'f': Uses a fixed seed so every PHP process generates the same string
     *
     * @throws RegexException If regex cannot be parsed
     */
    public function __construct(string $regexp, string $options = '')
    {
        $this->maxRepetition = 10;
        $this->charRange = new RangeSet(32, 126);
        $this->ignoreCase = str_contains($options, 'i');
        $this->fixedSeed = str_contains($options, 'f');
        $this->tokens = RegexTokenizer::exports($regexp);
    }

    /**
     * Generates a new random string based on the given regex.
     *
     * @return string
     *
     * @throws Exception If an appropriate source of randomness cannot be found.
     */
    public function generate(): string
    {
        return $this->generateByTokens($this->tokens);
    }

    /**
     * @param array $token
     * @param array $groups
     *
     * @return string
     * @throws Exception If an appropriate source of randomness cannot be found.
     */
    private function generateByTokens(array &$token, array &$groups = []): string
    {
        switch ($token['type']) {
            case RegexTokenizer::TYPE_ROOT:
            case RegexTokenizer::TYPE_GROUP:
                if (($token['followedBy'] ?? false) || ($token['notFollowedBy'] ?? false)) {
                    return '';
                }

                if (($token['remember'] ?? false) && !isset($token['groupNumber'])) {
                    $token['groupNumber'] = array_push($groups, null) - 1;
                }

                $stack = isset($token['options']) ? $this->randomSelect($token['options']) : $token['stack'];

                $str = '';
                foreach ($stack as $i => $_) {
                    $str .= $this->generateByTokens($stack[$i], $groups);
                }

                if ($token['remember'] ?? false) {
                    $groups[$token['groupNumber']] = $str;
                }
                return $str;

            case RegexTokenizer::TYPE_POSITION:
                return '';

            case RegexTokenizer::TYPE_SET:
                $expandedSet = $this->expand($token);
                if (!$expandedSet->length) {
                    return '';
                }

                // If any invalid utf-8 codepoints are in the list of ranges, try again up to 100 times
                $rounds = 0;
                do {
                    $chr = mb_chr($this->randomSelect($expandedSet), 'UTF-8');
                    $rounds++;
                } while ($chr === false && $rounds < 100);
                if ($chr === false) {
                    throw new RandGenerationException('Could not find a valid utf-8 codepoint');
                }

                return $chr;

            case RegexTokenizer::TYPE_REPETITION:
                $n = $this->randomInt(
                    $token['min'],
                    $token['max'] === 'INF' ? $token['min'] + $this->maxRepetition : $token['max']
                );
                $str = '';
                for ($i = 0; $i < $n; $i++) {
                    $str .= $this->generateByTokens($token['value'], $groups);
                }

                return $str;

            case RegexTokenizer::TYPE_REFERENCE:
                return $groups[$token['value'] - 1] ?? '';

            case RegexTokenizer::TYPE_CHAR:
                $code = $this->ignoreCase && $this->randomBool()
                    ? $this->toOtherCase($token['value'])
                    : $token['value'];
                return mb_chr($code, 'UTF-8');
        }

        throw new RandGenerationException('Unknown token found');
    }

    private function toOtherCase(int $code): int
    {
        if (97 <= $code && $code <= 122) {
            return $code - 32;
        }
        if (65 <= $code && $code <= 90) {
            return $code + 32;
        }

        return $code;
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function randomBool(): bool
    {
        return !$this->randomInt(0, 1);
    }

    /**
     * @param $arr
     *
     * @return mixed
     * @throws Exception
     */
    private function randomSelect($arr): mixed
    {
        if ($arr instanceof RangeSet) {
            return $arr->index($this->randomInt(0, $arr->length - 1));
        }
        return $arr[$this->randomInt(0, count($arr) - 1)];
    }

    /**
     * @param int $min
     * @param int $max
     *
     * @return int
     * @throws Exception If an appropriate source of randomness cannot be found.
     */
    private function randomInt(int $min, int $max): int
    {
        if ($this->fixedSeed) {
            if (self::$seed === null) {
                $seed = random_int(0, 2 ** 16) + time();
                self::$seed = ($seed ** 2) % 94906249;
            }

            return self::$seed % (1 + $max - $min) + $min;
        }

        return random_int($min, $max);
    }

    private function expand(array $token): RangeSet
    {
        if ($token['type'] === RegexTokenizer::TYPE_CHAR) {
            return new RangeSet($token['value']);
        }

        if ($token['type'] === RegexTokenizer::TYPE_RANGE) {
            return new RangeSet($token['from'], $token['to']);
        }

        $rangeSet = new RangeSet();
        foreach ($token['set'] as $tokenSet) {
            $subRange = $this->expand($tokenSet);
            $rangeSet->addRangeSet($subRange);
            if ($this->ignoreCase) {
                for ($j = 0; $j < $subRange->length; $j++) {
                    $code = $subRange->index($j);
                    $otherCaseCode = $this->toOtherCase($code);
                    if ($code !== $otherCaseCode) {
                        $rangeSet->add($otherCaseCode);
                    }
                }
            }
        }
        $defaultRange = clone $this->charRange;
        if ($token['not']) {
            return $defaultRange->subtractRangeSet($rangeSet);
        }

        return $defaultRange->intersectRangeSet($rangeSet);
    }
}
