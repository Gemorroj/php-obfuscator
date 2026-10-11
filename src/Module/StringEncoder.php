<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;

/**
 * Модуль шифрования строк.
 */
class StringEncoder implements ModuleInterface
{
    /**
     * @var array<int, true> индексы токенов, которые нельзя шифровать
     *                       (константные выражения: const, enum case, значения
     *                       по умолчанию свойств/параметров/статических переменных,
     *                       аргументы атрибутов)
     */
    private array $forbidden = [];

    /**
     * @param int $compressLevel Степень сжатия строки с помощью gzdeflate. -1 - будет использоваться уровень сжатия по умолчанию библиотеки zlib. 0 - отключение сжатия.
     */
    public function __construct(private readonly int $compressLevel = -1)
    {
    }

    public function process(string|array $token, array $tokens, int $position): string
    {
        if (0 === $position) {
            $this->forbidden = $this->computeForbidden($tokens);
        }

        if (!\is_array($token)) {
            return $token;
        }

        if (\T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
            return $token[1];
        }

        if (isset($this->forbidden[$position])) {
            return $token[1];
        }

        return $this->encodeString($this->decodeStringLiteral($token[1]));
    }

    /**
     * Получение фактического значения строкового литерала.
     *
     * Токен T_CONSTANT_ENCAPSED_STRING всегда является обычной строкой,
     * поэтому безопасно вычисляется через eval.
     */
    private function decodeStringLiteral(string $literal): string
    {
        $value = eval('return '.$literal.';');

        return \is_string($value) ? $value : '';
    }

    /**
     * Поиск строк, находящихся в константных выражениях.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array<int, true>
     */
    private function computeForbidden(array $tokens): array
    {
        $forbidden = [];
        $context = TokenContext::analyze($tokens);
        $count = \count($tokens);
        $awaitingParams = false;

        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (!\is_array($token)) {
                if ('(' === $token && $awaitingParams) {
                    $end = $this->findClosing($tokens, $i, '(');
                    $this->forbidRange($forbidden, $i, $end);
                    $awaitingParams = false;
                    $i = $end;
                } elseif ('=' === $token
                    && 'class' === $context['brace'][$i]
                    && 0 === $context['paren'][$i]
                    && 0 === $context['bracket'][$i]
                ) {
                    $end = $this->findTerminator($tokens, $i, ';');
                    $this->forbidRange($forbidden, $i, $end);
                    $i = $end;
                } elseif (';' === $token) {
                    $awaitingParams = false;
                }

                continue;
            }

            switch ($token[0]) {
                case \T_CONST:
                    $end = $this->findTerminator($tokens, $i, ';');
                    $this->forbidRange($forbidden, $i, $end);
                    $i = $end;
                    break;

                case \T_ATTRIBUTE:
                    $end = $this->findClosing($tokens, $i, '[');
                    $this->forbidRange($forbidden, $i, $end);
                    $i = $end;
                    break;

                case \T_STATIC:
                    $next = TokenContext::nextSignificant($tokens, $i);
                    if (null !== $next && \is_array($tokens[$next]) && \T_VARIABLE === $tokens[$next][0]) {
                        $end = $this->findTerminator($tokens, $i, ';');
                        $this->forbidRange($forbidden, $i, $end);
                        $i = $end;
                    }
                    break;

                case \T_FUNCTION:
                case \T_FN:
                    $awaitingParams = true;
                    break;
            }
        }

        return $forbidden;
    }

    /**
     * @param array<int, true> $forbidden
     */
    private function forbidRange(array &$forbidden, int $start, int $end): void
    {
        for ($i = $start; $i <= $end; ++$i) {
            $forbidden[$i] = true;
        }
    }

    /**
     * Поиск завершающего токена (например ";") на нулевой глубине скобок.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function findTerminator(array $tokens, int $start, string $terminator): int
    {
        $count = \count($tokens);
        $paren = 0;
        $bracket = 0;
        $brace = 0;

        for ($i = $start + 1; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (\is_array($token)) {
                continue;
            }

            switch ($token) {
                case '(':
                    ++$paren;
                    break;
                case ')':
                    --$paren;
                    break;
                case '[':
                    ++$bracket;
                    break;
                case ']':
                    --$bracket;
                    break;
                case '{':
                    ++$brace;
                    break;
                case '}':
                    --$brace;
                    break;
                default:
                    if ($token === $terminator && 0 === $paren && 0 === $bracket && 0 === $brace) {
                        return $i;
                    }
            }
        }

        return $count - 1;
    }

    /**
     * Поиск закрывающей скобки для "(" или "[" (T_ATTRIBUTE трактуется как "[").
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function findClosing(array $tokens, int $start, string $open): int
    {
        $close = '(' === $open ? ')' : ']';
        $count = \count($tokens);
        $depth = 0;

        for ($i = $start; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (\is_array($token)) {
                if (\T_ATTRIBUTE === $token[0]) {
                    ++$depth;
                }

                continue;
            }

            if ($open === $token) {
                ++$depth;
            } elseif ($close === $token) {
                --$depth;

                if (0 === $depth) {
                    return $i;
                }
            }
        }

        return $count - 1;
    }

    /**
     * Шифрование строки.
     */
    private function encodeString(string $string): string
    {
        if ('' === $string) {
            return '\'\'';
        }

        if (0 !== $this->compressLevel) {
            $deflated = \gzdeflate($string, $this->compressLevel);

            if (false !== $deflated) {
                $decompressor = 'gzinflate(base64_decode(\''.\base64_encode($deflated).'\'))';

                if (\strlen($decompressor) <= \strlen($string)) {
                    return $decompressor;
                }
            }
        }

        // Кастомные функции шифрования
        $advancedEncoders = [
            static function (string $string): string {
                $return = [];
                $length = \strlen($string);

                for ($i = 0; $i < $length; ++$i) {
                    $return[] = 'chr('.\ord($string[$i]).')';
                }

                return '('.\implode('.', $return).')';
            },

            static function (string $string): string {
                return '~base64_decode(\''.\base64_encode(~$string).'\')';
            },

            function (string $string): string {
                $key = \base64_encode(\md5((string) \microtime(true), true));
                $size = \strlen($string);
                $key = \substr(\str_repeat($key, (int) \ceil($size / \strlen($key))), 0, $size);

                return '(base64_decode(\''.\base64_encode($string ^ $key).'\')^('.$this->encodeString($key).'))';
            },
        ];

        $basicCount = \count(Encoder::ENCODERS);
        $advancedCount = \count($advancedEncoders);
        $encoder = \random_int(0, $basicCount + $advancedCount - 1);

        if ($encoder < $basicCount) {
            return Encoder::encode($string);
        }

        return $advancedEncoders[$encoder - $basicCount]($string);
    }
}
