<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;

/**
 * Модуль очистки пробельных символов.
 */
class WhitespaceStrip implements ModuleInterface
{
    /**
     * Предыдущий токен.
     *
     * @var array{0: int, 1: string, 2: int}|string
     */
    private string|array $prevToken = '';

    /**
     * Токены, содержимое которых нельзя изменять.
     *
     * @var list<int>
     */
    private const RAW = [
        \T_ENCAPSED_AND_WHITESPACE,
        \T_START_HEREDOC,
        \T_END_HEREDOC,
        \T_INLINE_HTML,
    ];

    /**
     * Токены, после которых нужен пробел перед следующим токеном.
     *
     * @var list<int>
     */
    private const LEADING_SPACE = [
        \T_ABSTRACT,
        \T_FINAL,
        \T_PRIVATE,
        \T_PROTECTED,
        \T_PUBLIC,
        \T_STATIC,
        \T_READONLY,
        \T_CLASS,
        \T_FUNCTION,
        \T_INTERFACE,
        \T_TRAIT,
        \T_CONST,
        \T_ECHO,
        \T_NAMESPACE,
        \T_NEW,
        \T_THROW,
        \T_USE,
        \T_VAR,
        \T_YIELD,
        \T_YIELD_FROM,
        \T_REQUIRE,
        \T_REQUIRE_ONCE,
        \T_RETURN,
        \T_CASE,
        \T_MATCH,
        \T_ENUM,
        \T_CLASS_C,
        \T_FUNC_C,
        \T_DIR,
        \T_FILE,
        \T_LINE,
        \T_METHOD_C,
        \T_NS_C,
        \T_TRAIT_C,
        \T_LOGICAL_AND,
        \T_LOGICAL_OR,
        \T_LOGICAL_XOR,
        \T_OPEN_TAG,
        \T_CLOSE_TAG,
        \T_GOTO,
    ];

    /**
     * Токены, которые должны быть окружены пробелами.
     *
     * @var list<int>
     */
    private const SURROUND_SPACE = [
        \T_AS,
        \T_INSTANCEOF,
        \T_INSTEADOF,
        \T_IMPLEMENTS,
        \T_EXTENDS,
    ];

    public function process(string|array $token, array $tokens, int $position): string
    {
        if (0 === $position) {
            $this->prevToken = '';
        }

        if (!\is_array($token)) {
            $this->prevToken = $token;

            // "." — всегда конкатенация; пробелы нужны, чтобы не слить с числами (1 . 2).
            if ('.' === $token) {
                return ' . ';
            }

            return \trim($token);
        }

        switch ($token[0]) {
            case \T_WHITESPACE:
            case \T_COMMENT:
            case \T_DOC_COMMENT:
                return '';
        }

        if (\in_array($token[0], self::RAW, true)) {
            $this->prevToken = $token;

            return $token[1];
        }

        $return = \trim($token[1]);

        if (\in_array($token[0], self::SURROUND_SPACE, true)) {
            $return = ' '.$return.' ';
        } elseif ($this->requiresLeadingSpace($token)) {
            $return = ' '.$return;
        }

        // Открывающий тег требует пробел/перевод строки после себя.
        if (\T_OPEN_TAG === $token[0]) {
            $return .= ' ';
        }

        $this->prevToken = $token;

        return $return;
    }

    /**
     * Нужен ли пробел перед текущим токеном.
     *
     * @param array{0: int, 1: string, 2: int} $token
     */
    private function requiresLeadingSpace(array $token): bool
    {
        $prev = $this->prevToken;

        if (!\is_array($prev)) {
            return false;
        }

        if (\in_array($prev[0], self::LEADING_SPACE, true)) {
            return true;
        }

        if (\T_STRING === $prev[0] && \T_STRING === $token[0]) {
            // private const int TEST = 'val';
            return true;
        }

        return (\T_DO === $prev[0] || \T_ELSE === $prev[0]) && '{' !== $token[1];
    }
}
