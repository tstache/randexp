<?php

declare(strict_types=1);

namespace RandExp;

use function array_column;
use function array_pop;
use function chr;
use function count;
use function end;
use function hexdec;
use function json_decode;
use function json_encode;
use function key;
use function max;
use function mb_convert_encoding;
use function mb_ord;
use function mb_strlen;
use function mb_strpos;
use function mb_substr;
use function octdec;
use function pack;
use function preg_match;
use function preg_match_all;
use function preg_replace_callback;
use function property_exists;
use function strlen;

/**
 * @see https://github.com/fent/ret.js
 */
class RegexTokenizer
{
    // types
    public const TYPE_ROOT = 0;
    public const TYPE_GROUP = 1;
    public const TYPE_POSITION = 2;
    public const TYPE_SET = 3;
    public const TYPE_RANGE = 4;
    public const TYPE_REPETITION = 5;
    public const TYPE_REFERENCE = 6;
    public const TYPE_CHAR = 7;

    // positions
    public const POSITION_WORD_BOUNDARY = ['type' => self::TYPE_POSITION, 'value' => 'b'];
    public const POSITION_NON_WORD_BOUNDARY = ['type' => self::TYPE_POSITION, 'value' => 'B'];
    public const POSITION_BEGIN = ['type' => self::TYPE_POSITION, 'value' => '^'];
    public const POSITION_END = ['type' => self::TYPE_POSITION, 'value' => '$'];

    // sets
    public const SET_INT = [['type' => self::TYPE_RANGE, 'from' => 48, 'to' => 57]];
    public const SET_WORD = [
        ['type' => self::TYPE_CHAR, 'value' => 95],
        ['type' => self::TYPE_RANGE, 'from' => 97, 'to' => 122],
        ['type' => self::TYPE_RANGE, 'from' => 65, 'to' => 90],
        ['type' => self::TYPE_RANGE, 'from' => 48, 'to' => 57],
    ];
    public const SET_WHITESPACE = [
        ['type' => self::TYPE_CHAR, 'value' => 9],
        ['type' => self::TYPE_CHAR, 'value' => 10],
        ['type' => self::TYPE_CHAR, 'value' => 11],
        ['type' => self::TYPE_CHAR, 'value' => 12],
        ['type' => self::TYPE_CHAR, 'value' => 13],
        ['type' => self::TYPE_CHAR, 'value' => 32],
        ['type' => self::TYPE_CHAR, 'value' => 160],
        ['type' => self::TYPE_CHAR, 'value' => 5760],
        ['type' => self::TYPE_RANGE, 'from' => 8192, 'to' => 8202],
        ['type' => self::TYPE_CHAR, 'value' => 8232],
        ['type' => self::TYPE_CHAR, 'value' => 8233],
        ['type' => self::TYPE_CHAR, 'value' => 8239],
        ['type' => self::TYPE_CHAR, 'value' => 8287],
        ['type' => self::TYPE_CHAR, 'value' => 12288],
        ['type' => self::TYPE_CHAR, 'value' => 65279],
    ];
    public const SET_NOT_ANY_CHAR = [
        ['type' => self::TYPE_CHAR, 'value' => 10],
        ['type' => self::TYPE_CHAR, 'value' => 13],
        ['type' => self::TYPE_CHAR, 'value' => 8232],
        ['type' => self::TYPE_CHAR, 'value' => 8233],
    ];

    // tokens
    public const TOKEN_WORD = ['type' => self::TYPE_SET, 'set' => self::SET_WORD, 'not' => false];
    public const TOKEN_NOT_WORD = ['type' => self::TYPE_SET, 'set' => self::SET_WORD, 'not' => true];
    public const TOKEN_INT = ['type' => self::TYPE_SET, 'set' => self::SET_INT, 'not' => false];
    public const TOKEN_NOT_INT = ['type' => self::TYPE_SET, 'set' => self::SET_INT, 'not' => true];
    public const TOKEN_WHITESPACE = ['type' => self::TYPE_SET, 'set' => self::SET_WHITESPACE, 'not' => false];
    public const TOKEN_NOT_WHITESPACE = ['type' => self::TYPE_SET, 'set' => self::SET_WHITESPACE, 'not' => true];
    public const TOKEN_ANY_CHAR = ['type' => self::TYPE_SET, 'set' => self::SET_NOT_ANY_CHAR, 'not' => true];

    // util
    public const CTRL = '@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\\\\]^ ?';
    public const SLSH = ['0' => 0, 't' => 9, 'n' => 10, 'v' => 11, 'f' => 12, 'r' => 13];

    /**
     * Finds character representations in str and convert all to
     * their respective characters
     *
     * @param string $str
     *
     * @return string
     */
    public static function strToChars(string $str): string
    {
        return preg_replace_callback(
            '/(\\[\\\\b])|(\\\\)?\\\\(?:u([A-F0-9]{4})|x([A-F0-9]{2})|(0?[0-7]{2})|c([@A-Z[\\\\\\]^?])|([0tnvfr]))/u',
            static function ($matches) {
                $s = $matches[0] ?? false;
                $b = $matches[1] ?? false;
                $lbs = $matches[2] ?? false;
                $a16 = $matches[3] ?? false;
                $b16 = $matches[4] ?? false;
                $c8 = $matches[5] ?? false;
                $dctrl = $matches[6] ?? false;
                $eslsh = $matches[7] ?? false;
                if ($lbs) {
                    return $s;
                }

                if ($b) {
                    $code = 8;
                } elseif ($a16) {
                    return mb_convert_encoding(pack('H*', $a16), 'UTF-8', 'UCS-2BE');
                } elseif ($b16) {
                    $code = hexdec($b16);
                } elseif ($c8) {
                    $code = octdec($c8);
                } elseif ($dctrl) {
                    $code = mb_strpos(self::CTRL, $dctrl);
                } else {
                    $code = self::SLSH[$eslsh];
                }

                $c = chr($code);
                if (preg_match('/[[\\]{}^$.|?*+()]/u', $c)) {
                    $c = '\\' . $c;
                }

                return $c;
            },
            $str
        );
    }

    /**
     * turns class into tokens
     * reads str until it encounters a ']'
     *
     * @param string  $str
     * @param ?string $regexpStr
     *
     * @return array
     * @throws RegexException
     */
    public static function tokenizeClass(string $str, ?string $regexpStr = null): array
    {
        if ($regexpStr === null) {
            $regexpStr = $str;
        }
        $tokens = [];
        $regexp = '/\\\\(?:(w)|(d)|(s)|(W)|(D)|(S))|((?:(?:\\\\)(.)|([^]\\\\]))-(?:\\\\)?([^]]))|(])|(?:\\\\)?(.)/su';

        if (preg_match_all($regexp, $str, $matches, PREG_OFFSET_CAPTURE)) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $rs = array_column($matches, $i);
                $rsValues = array_column($rs, 0);
                $rsOffsets = array_column($rs, 1);
                if (($rsOffsets[1] ?? -1) >= 0) {
                    $tokens[] = self::TOKEN_WORD;
                } elseif (($rsOffsets[2] ?? -1) >= 0) {
                    $tokens[] = self::TOKEN_INT;
                } elseif (($rsOffsets[3] ?? -1) >= 0) {
                    $tokens[] = self::TOKEN_WHITESPACE;
                } elseif (($rsOffsets[4] ?? -1) >= 0) {
                    $tokens[] = self::TOKEN_NOT_WORD;
                } elseif (($rsOffsets[5] ?? -1) >= 0) {
                    $tokens[] = self::TOKEN_NOT_INT;
                } elseif (($rsOffsets[6] ?? -1) >= 0) {
                    $tokens[] = self::TOKEN_NOT_WHITESPACE;
                } elseif (($rsOffsets[7] ?? -1) >= 0) {
                    $tokens[] = [
                        'type' => self::TYPE_RANGE,
                        'from' => mb_ord($rsValues[8] ?: $rsValues[9]),
                        'to'   => mb_ord($rsValues[10]),
                    ];
                } elseif (($rsOffsets[12] ?? -1) >= 0) {
                    $tokens[] = ['type' => self::TYPE_CHAR, 'value' => mb_ord($rsValues[12])];
                } else {
                    return [$tokens, max($rsOffsets) + 1];
                }
            }
        }

        throw new RegexException($regexpStr, 'Unterminated character class');
    }

    /**
     * @param string $regexpStr
     *
     * @return array
     * @throws RegexException
     */
    public static function exports(string $regexpStr): array
    {
        $i = 0;
        $root = (object)['type' => self::TYPE_ROOT, 'stack' => []];

        // Keep track of last clause/group and stack.
        $group = &$root;
        $stack = &$root->stack;
        $groupStack = [];

        // Decode a few escaped characters.
        $str = self::strToChars($regexpStr);
        $l = mb_strlen($str);

        // Iterate through each character in string.
        while ($i < $l) {
            $c = mb_substr($str, $i++, 1);
            switch ($c) {
                case '\\':
                    // Handle escaped characters, includes a few sets.
                    $c = mb_substr($str, $i++, 1);
                    switch ($c) {
                        case 'b':
                            $stack[] = self::POSITION_WORD_BOUNDARY;
                            break;
                        case 'B':
                            $stack[] = self::POSITION_NON_WORD_BOUNDARY;
                            break;
                        case 'w':
                            $stack[] = self::TOKEN_WORD;
                            break;
                        case 'W':
                            $stack[] = self::TOKEN_NOT_WORD;
                            break;
                        case 'd':
                            $stack[] = self::TOKEN_INT;
                            break;
                        case 'D':
                            $stack[] = self::TOKEN_NOT_INT;
                            break;
                        case 's':
                            $stack[] = self::TOKEN_WHITESPACE;
                            break;
                        case 'S':
                            $stack[] = self::TOKEN_NOT_WHITESPACE;
                            break;
                        default:
                            // Check if c is integer.
                            if (preg_match('/\d/', $c)) {
                                // In which case it's a reference.
                                $stack[] = ['type' => self::TYPE_REFERENCE, 'value' => (int)$c];
                            } else {
                                // Escaped character.
                                $stack[] = ['type' => self::TYPE_CHAR, 'value' => mb_ord($c)];
                            }
                    }

                    break;
                case '^':
                    // Positional
                    $stack[] = self::POSITION_BEGIN;
                    break;
                case '$':
                    // Positional
                    $stack[] = self::POSITION_END;
                    break;
                case '[':
                    // Handle custom sets.
                    // Check if this class is 'anti' i.e. [^abc].
                    if (mb_substr($str, $i, 1) === '^') {
                        $not = true;
                        $i++;
                    } else {
                        $not = false;
                    }

                    // Get all the characters in class.
                    $classTokens = self::tokenizeClass(mb_substr($str, $i), $regexpStr);

                    // Increase index by length of class.
                    $i += $classTokens[1];
                    $stack[] = ['type' => self::TYPE_SET, 'set' => $classTokens[0], 'not' => $not];
                    break;
                case '.':
                    // Class of any character except \n.
                    $stack[] = self::TOKEN_ANY_CHAR;
                    break;
                case '(':
                    // Push group onto stack.
                    // Create group.
                    $newGroup = (object)['type' => self::TYPE_GROUP, 'stack' => [], 'remember' => true];

                    // Check if this is a special kind of group.
                    $c = mb_substr($str, $i, 1);
                    if ($c === '?') {
                        $c = mb_substr($str, $i + 1, 1);
                        $i += 2;

                        if ($c === '=') {
                            // Match if followed by.
                            $newGroup->followedBy = true;
                        } elseif ($c === '!') {
                            // Match if not followed by.
                            $newGroup->notFollowedBy = true;
                        } elseif ($c !== ':') {
                            $pos = $i - 1;
                            throw new RegexException(
                                $regexpStr,
                                "Invalid group, character '$c' after '?' at column $pos"
                            );
                        }
                        $newGroup->remember = false;
                    }

                    // Remember the current group for when the group closes.
                    $groupStack[] = $group;
                    // Insert subgroup into current group stack.
                    $stack[] = $newGroup;

                    // Make this new group the current group.
                    $group = $newGroup;
                    $stack = &$newGroup->stack;

                    break;
                case ')':
                    // Pop group out of stack.
                    if (count($groupStack) === 0) {
                        $pos = $i - 1;
                        throw new RegexException($regexpStr, "Unmatched ) at column $pos");
                    }
                    $group = array_pop($groupStack);

                    // Check if this group has a PIPE.
                    // To get back the correct last stack.
                    if (property_exists($group, 'options')) {
                        $stack = &$group->options[count($group->options) - 1];
                    } else {
                        $stack = &$group->stack;
                    }
                    break;
                case '|':
                    // Use pipe character to give more choices.
                    // Create array where options are if this is the first PIPE
                    // in this clause.
                    if (!property_exists($group, 'options')) {
                        $group->options = [];
                        $group->options[] = $group->stack;
                        unset($group->stack);
                    }

                    // Create a new stack and add to options for rest of clause.
                    $group->options[] = [];
                    end($group->options);
                    $stack = &$group->options[key($group->options)];
                    break;
                case '{':
                    // Repetition.
                    // For every repetition, remove last element from last stack
                    // then insert back a RANGE object.
                    // This design is chosen because there could be more than
                    // one repetition symbols in a regex i.e. `a?+{2,3}`.
                    $rs = preg_match('/^(\d+)(,(\d+)?)?}/u', mb_substr($str, $i), $matches);
                    if ($rs) {
                        if (count($stack) === 0) {
                            $pos = $i - 1;
                            throw new RegexException($regexpStr, "Nothing to repeat at column $pos");
                        }
                        $min = (int)$matches[1];
                        $max = $min;
                        if (isset($matches[2])) {
                            $max = 'INF';
                        }
                        if (isset($matches[3])) {
                            $max = (int)$matches[3];
                        }
                        $i += strlen($matches[0]);

                        $stack[] = [
                            'type'  => self::TYPE_REPETITION,
                            'min'   => $min,
                            'max'   => $max,
                            'value' => array_pop($stack),
                        ];
                    } else {
                        $stack[] = ['type' => self::TYPE_CHAR, 'value' => 123];
                    }
                    break;
                case '?':
                    // Repetition.
                    // For every repetition, remove last element from last stack
                    // then insert back a RANGE object.
                    // This design is chosen because there could be more than
                    // one repetition symbols in a regex i.e. `a?+{2,3}`.
                    if (count($stack) === 0) {
                        $pos = $i - 1;
                        throw new RegexException($regexpStr, "Nothing to repeat at column $pos");
                    }
                    $stack[] = ['type' => self::TYPE_REPETITION, 'min' => 0, 'max' => 1, 'value' => array_pop($stack)];

                    break;
                case '+':
                    // Repetition.
                    // For every repetition, remove last element from last stack
                    // then insert back a RANGE object.
                    // This design is chosen because there could be more than
                    // one repetition symbols in a regex i.e. `a?+{2,3}`.
                    if (count($stack) === 0) {
                        $pos = $i - 1;
                        throw new RegexException($regexpStr, "Nothing to repeat at column $pos");
                    }
                    $stack[] = [
                        'type'  => self::TYPE_REPETITION,
                        'min'   => 1,
                        'max'   => 'INF',
                        'value' => array_pop($stack),
                    ];

                    break;
                case '*':
                    // Repetition.
                    // For every repetition, remove last element from last stack
                    // then insert back a RANGE object.
                    // This design is chosen because there could be more than
                    // one repetition symbols in a regex i.e. `a?+{2,3}`.
                    if (count($stack) === 0) {
                        $pos = $i - 1;
                        throw new RegexException($regexpStr, "Nothing to repeat at column $pos");
                    }
                    $stack[] = [
                        'type'  => self::TYPE_REPETITION,
                        'min'   => 0,
                        'max'   => 'INF',
                        'value' => array_pop($stack),
                    ];

                    break;
                default:
                    // Default is a character that is not `\[](){}?+*^$`.
                    $stack[] = ['type' => self::TYPE_CHAR, 'value' => mb_ord($c)];
            }
        }

        // Check if any groups have not been closed.
        if (count($groupStack) !== 0) {
            throw new RegexException($regexpStr, 'Unterminated group');
        }

        // Encode and decode to convert stdClass to array
        /** @noinspection JsonEncodingApiUsageInspection */
        return json_decode(json_encode($root), true);
    }
}
