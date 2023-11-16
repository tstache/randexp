<?php
declare(strict_types=1);

namespace Tests\RandExp;

use RandExp\RegexException;
use RandExp\RegexTokenizer;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_merge;
use function mb_ord;
use function str_split;

class RegexTokenizerTest extends TestCase
{
    private function assertRegexException(string $regexp, string $message): void
    {
        $exception = null;
        try {
            RegexTokenizer::exports($regexp);
        } catch (RegexException $e) {
            $exception = $e;
        }
        $message = 'Invalid regular expression: /' . $regexp . '/: ' . $message;
        static::assertInstanceOf(RegexException::class, $exception);
        static::assertEquals($message, $exception->getMessage());
    }

    public function testInvalidRegexWillThrowException(): void
    {
        $this->assertRegexException('?what',             'Nothing to repeat at column 0');
        $this->assertRegexException('foo(*\\w)',         'Nothing to repeat at column 4');
        $this->assertRegexException('foo|+bar',          'Nothing to repeat at column 4');
        $this->assertRegexException('ok({3}no)',         'Nothing to repeat at column 3');
        $this->assertRegexException('hey(yoo))',         'Unmatched ) at column 8');
        $this->assertRegexException('(',                 'Unterminated group');
        $this->assertRegexException('abcde(?>hellow)',   'Invalid group, character \'>\' after \'?\' at column 7');
        $this->assertRegexException('[abc',              'Unterminated character class');
    }

    /**
     * @return void
     * @throws RegexException
     */
    public function testCharacterTokensConversion(): void
    {
        $str = RegexTokenizer::strToChars("\\xFF hellow \\u00A3 \\50 there \\cB \\n \\w [\\b]");
        static::assertEquals("\xFF hellow \u{00A3} \\( there  \n \\w \u{0008}", $str);

        $tokens = RegexTokenizer::tokenizeClass("\\w\\d$\\s\\]\\B\\W\\D\\S.+-] will ignore");
        static::assertIsArray($tokens[0]);
        static::assertEquals(RegexTokenizer::TOKEN_WORD, $tokens[0][0]);
        static::assertEquals(RegexTokenizer::TOKEN_INT, $tokens[0][1]);
        static::assertEquals(['type' => RegexTokenizer::TYPE_CHAR, 'value' => 36], $tokens[0][2]);
        static::assertEquals(RegexTokenizer::TOKEN_WHITESPACE, $tokens[0][3]);
        static::assertEquals(['type' => RegexTokenizer::TYPE_CHAR, 'value' => 93], $tokens[0][4]);
        static::assertEquals(['type' => RegexTokenizer::TYPE_CHAR, 'value' => 66], $tokens[0][5]);
        static::assertEquals(RegexTokenizer::TOKEN_NOT_WORD, $tokens[0][6]);
        static::assertEquals(RegexTokenizer::TOKEN_NOT_INT, $tokens[0][7]);
        static::assertEquals(RegexTokenizer::TOKEN_NOT_WHITESPACE, $tokens[0][8]);
        static::assertEquals(['type' => RegexTokenizer::TYPE_CHAR, 'value' => 46], $tokens[0][9]);
        static::assertEquals(['type' => RegexTokenizer::TYPE_CHAR, 'value' => 43], $tokens[0][10]);
        static::assertEquals(['type' => RegexTokenizer::TYPE_CHAR, 'value' => 45], $tokens[0][11]);
        static::assertIsInt($tokens[1]);
        static::assertEquals(21, $tokens[1]);

        $tokens = RegexTokenizer::tokenizeClass('a-z0-9]');
        static::assertIsArray($tokens[0]);
        static::assertEquals(['type' => RegexTokenizer::TYPE_RANGE, 'from' => 97, 'to' => 122], $tokens[0][0]);
        static::assertEquals(['type' => RegexTokenizer::TYPE_RANGE, 'from' => 48, 'to' => 57], $tokens[0][1]);

        $tokens = RegexTokenizer::tokenizeClass('\\\\-~]');
        static::assertIsArray($tokens[0]);
        static::assertEquals(['type' => RegexTokenizer::TYPE_RANGE, 'from' => 92, 'to' => 126], $tokens[0][0]);
    }

    /**
     * @return void
     * @throws RegexException
     */
    public function testRegexToTokensConversion(): void
    {
        function str_to_tokens(string $str): array
        {
            return array_map(
                static function($char) {
                    return ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord($char)];
                },
                str_split($str)
            );
        }

        static::assertEquals(
            ['type' => RegexTokenizer::TYPE_ROOT, 'stack' => str_to_tokens('walnuts')],
            RegexTokenizer::exports('walnuts')
        );

        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    ['type' => RegexTokenizer::TYPE_POSITION, 'value' => '^'],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('y')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('e')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('s')],
                    ['type' => RegexTokenizer::TYPE_POSITION, 'value' => '$'],
                ],
            ],
            RegexTokenizer::exports('^yes$')
        );

        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    ['type' => RegexTokenizer::TYPE_POSITION, 'value' => 'b'],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('b')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('e')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('g')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('i')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('n')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('n')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('i')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('n')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('g')],
                    ['type' => RegexTokenizer::TYPE_POSITION, 'value' => 'B'],
                ],
            ],
            RegexTokenizer::exports('\\bbeginning\\B')
        );

        $tokens = RegexTokenizer::exports('\\w\\W\\d\\D\\s\\S.');
        static::assertIsArray($tokens['stack']);
        static::assertEquals(RegexTokenizer::TOKEN_WORD, $tokens['stack'][0]);
        static::assertEquals(RegexTokenizer::TOKEN_NOT_WORD, $tokens['stack'][1]);
        static::assertEquals(RegexTokenizer::TOKEN_INT, $tokens['stack'][2]);
        static::assertEquals(RegexTokenizer::TOKEN_NOT_INT, $tokens['stack'][3]);
        static::assertEquals(RegexTokenizer::TOKEN_WHITESPACE, $tokens['stack'][4]);
        static::assertEquals(RegexTokenizer::TOKEN_NOT_WHITESPACE, $tokens['stack'][5]);
        static::assertEquals(RegexTokenizer::TOKEN_ANY_CHAR, $tokens['stack'][6]);

        $tokens = RegexTokenizer::exports('[$!a-z123] thing [^0-9]');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    [
                        'type' => RegexTokenizer::TYPE_SET,
                        'set' => [
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('$')],
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('!')],
                            ['type' => RegexTokenizer::TYPE_RANGE, 'from' => mb_ord('a'), 'to' => mb_ord('z')],
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('1')],
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('2')],
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('3')],
                        ],
                        'not' => false,
                    ],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord(' ')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('t')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('h')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('i')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('n')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('g')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord(' ')],
                    [
                        'type' => RegexTokenizer::TYPE_SET,
                        'set' => [['type' => RegexTokenizer::TYPE_RANGE, 'from' => mb_ord('0'), 'to' => mb_ord('9')]],
                        'not' => true,
                    ],
                ],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports("[\t\r\n\u{2028}\u{2029} ]");
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    [
                        'type' => RegexTokenizer::TYPE_SET,
                        'set' => [
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord("\t")],
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord("\r")],
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord("\n")],
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => 8232], // "\u{2028}"
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => 8233], // "\u{2029}"
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord(' ')],
                        ],
                        'not' => false,
                    ],
                ],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('[01]-[ab]');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    ['type' => RegexTokenizer::TYPE_SET, 'set' => str_to_tokens('01'), 'not' => false],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('-')],
                    ['type' => RegexTokenizer::TYPE_SET, 'set' => str_to_tokens('ab'), 'not' => false],
                ],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('foo|bar|za');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'options' => [str_to_tokens('foo'), str_to_tokens('bar'), str_to_tokens('za')],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('hey (there)');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('h')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('e')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('y')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord(' ')],
                    ['type' => RegexTokenizer::TYPE_GROUP, 'remember' => true, 'stack' => str_to_tokens('there')],
                ],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('(?:loner)');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [['type' => RegexTokenizer::TYPE_GROUP, 'remember' => false, 'stack' => str_to_tokens('loner')]],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('what(?!ever)');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('w')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('h')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('a')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('t')],
                    ['type' => RegexTokenizer::TYPE_GROUP, 'remember' => false, 'notFollowedBy' => true, 'stack' => str_to_tokens('ever')],
                ],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('hello(?= there)');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('h')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('e')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('l')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('l')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('o')],
                    ['type' => RegexTokenizer::TYPE_GROUP, 'remember' => false, 'followedBy' => true, 'stack' => str_to_tokens(' there')],
                ],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('a(b(c|(?:d))fg) @_@');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('a')],
                    [
                        'type' => RegexTokenizer::TYPE_GROUP,
                        'remember' => true,
                        'stack' => [
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('b')],
                            [
                                'type' => RegexTokenizer::TYPE_GROUP,
                                'remember' => true,
                                'options' => [
                                    [['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('c')]],
                                    [['type' => RegexTokenizer::TYPE_GROUP, 'remember' => false, 'stack' => str_to_tokens('d')]],
                                ]],
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('f')],
                            ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('g')],
                        ]],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord(' ')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('@')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('_')],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('@')],
                ],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('(?:pika){2}');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    [
                        'type' => RegexTokenizer::TYPE_REPETITION,
                        'min' => 2,
                        'max' => 2,
                        'value' => [
                            'type' => RegexTokenizer::TYPE_GROUP,
                            'remember' => false,
                            'stack' => str_to_tokens('pika'),
                        ],
                    ],
                ],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('NO{6,}');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('N')],
                    [
                        'type'  => RegexTokenizer::TYPE_REPETITION, 'min' => 6, 'max' => 'INF',
                        'value' => ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('O')]
                    ],
                ],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('pika\\.\\.\\. chu{3,20}!{1,2}');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => array_merge(
                    str_to_tokens('pika... ch'),
                    [
                        ['type' => RegexTokenizer::TYPE_REPETITION, 'min' => 3, 'max' => 20, 'value' => ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('u')]],
                        ['type' => RegexTokenizer::TYPE_REPETITION, 'min' => 1, 'max' => 2, 'value' => ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('!')]],
                    ]
                ),
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('a{mustache}');
        static::assertEquals(['type' => RegexTokenizer::TYPE_ROOT, 'stack' => str_to_tokens('a{mustache}')], $tokens);

        $tokens = RegexTokenizer::exports('hey(?: you)?');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => array_merge(
                    str_to_tokens('hey'),
                    [
                        [
                            'type' => RegexTokenizer::TYPE_REPETITION,
                            'min' => 0,
                            'max' => 1,
                            'value' => ['type' => RegexTokenizer::TYPE_GROUP, 'remember' => false, 'stack' => str_to_tokens(' you')]
                        ],
                    ]
                )
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('(no )+');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [[
                    'type'  => RegexTokenizer::TYPE_REPETITION, 'min' => 1, 'max' => 'INF',
                    'value' => ['type' => RegexTokenizer::TYPE_GROUP, 'remember' => true, 'stack' => str_to_tokens('no ')],
                ]],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('XF*D');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('X')],
                    ['type' => RegexTokenizer::TYPE_REPETITION, 'min' => 0, 'max' => 'INF', 'value' => ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('F')]],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('D')],
                ],
            ],
            $tokens
        );

        $tokens = RegexTokenizer::exports('<(\\w+)>\\w*<\\1>');
        static::assertEquals(
            [
                'type' => RegexTokenizer::TYPE_ROOT,
                'stack' => [
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('<')],
                    ['type' => RegexTokenizer::TYPE_GROUP, 'remember' => true, 'stack' => [['type' => RegexTokenizer::TYPE_REPETITION, 'min' => 1, 'max' => 'INF', 'value' => RegexTokenizer::TOKEN_WORD]]],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('>')],
                    ['type' => RegexTokenizer::TYPE_REPETITION, 'min' => 0, 'max' => 'INF', 'value' => RegexTokenizer::TOKEN_WORD],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('<')],
                    ['type' => RegexTokenizer::TYPE_REFERENCE, 'value' => 1],
                    ['type' => RegexTokenizer::TYPE_CHAR, 'value' => mb_ord('>')],
                ],
            ],
            $tokens
        );
    }
}
