<?php

namespace PhpObfuscator\Module;

/**
 * Разбор контекста токенов: тип охватывающего блока и глубины скобок.
 */
final class TokenContext
{
    /**
     * Индекс следующего значимого (не пробел/комментарий) токена.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    public static function nextSignificant(array $tokens, int $position): ?int
    {
        $count = \count($tokens);

        for ($i = $position + 1; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (\is_array($token) && \in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /**
     * Индекс предыдущего значимого (не пробел/комментарий) токена.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    public static function previousSignificant(array $tokens, int $position): ?int
    {
        for ($i = $position - 1; $i >= 0; --$i) {
            $token = $tokens[$i];

            if (\is_array($token) && \in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }

            return $i;
        }

        return null;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array{
     *     brace: array<int, string>,
     *     paren: array<int, int>,
     *     bracket: array<int, int>
     * }
     */
    public static function analyze(array $tokens): array
    {
        $brace = [];
        $paren = [];
        $bracket = [];

        /** @var list<string> $braceStack */
        $braceStack = [];
        $parenDepth = 0;
        $bracketDepth = 0;
        $pendingBrace = null;
        $previous = null;

        foreach ($tokens as $position => $token) {
            $brace[$position] = [] === $braceStack ? 'root' : $braceStack[\count($braceStack) - 1];
            $paren[$position] = $parenDepth;
            $bracket[$position] = $bracketDepth;

            if (\is_array($token)) {
                switch ($token[0]) {
                    case \T_ENUM:
                    case \T_INTERFACE:
                    case \T_TRAIT:
                        $pendingBrace = 'class';
                        break;

                    case \T_CLASS:
                        // `Foo::class` не открывает тело класса.
                        if (!\is_array($previous) || \T_DOUBLE_COLON !== $previous[0]) {
                            $pendingBrace = 'class';
                        }
                        break;

                    case \T_FUNCTION:
                    case \T_FN:
                        $pendingBrace = 'function';
                        break;
                }

                if (!\in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
                    $previous = $token;
                }

                continue;
            }

            switch ($token) {
                case '{':
                    $braceStack[] = $pendingBrace ?? 'other';
                    $pendingBrace = null;
                    break;

                case '}':
                    \array_pop($braceStack);
                    $pendingBrace = null;
                    break;

                case '(':
                    ++$parenDepth;
                    break;

                case ')':
                    --$parenDepth;
                    break;

                case '[':
                    ++$bracketDepth;
                    break;

                case ']':
                    --$bracketDepth;
                    break;

                case ';':
                    $pendingBrace = null;
                    break;
            }

            $previous = $token;
        }

        return [
            'brace' => $brace,
            'paren' => $paren,
            'bracket' => $bracket,
        ];
    }
}
