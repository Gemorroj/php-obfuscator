<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;

/**
 * Модуль шифрования вызовов функций.
 */
class SilentCalls implements ModuleInterface
{
    /**
     * Токены, при которых текущий T_STRING не является вызовом функции.
     *
     * @var list<int>
     */
    private const NOT_A_CALL = [
        \T_OBJECT_OPERATOR,
        \T_NULLSAFE_OBJECT_OPERATOR,
        \T_DOUBLE_COLON,
        \T_FUNCTION,
        \T_FN,
        \T_NEW,
        \T_CLASS,
        \T_INTERFACE,
        \T_TRAIT,
        \T_ENUM,
        \T_CONST,
        \T_USE,
        \T_EXTENDS,
        \T_IMPLEMENTS,
        \T_NAMESPACE,
        \T_GOTO,
        \T_INSTEADOF,
        \T_AS,
        \T_ATTRIBUTE,
        \T_CATCH,
        \T_INSTANCEOF,
    ];

    /**
     * Функции, которые нельзя вызвать динамически.
     *
     * @var list<string>
     */
    private const NOT_DYNAMIC = [
        'compact',
        'extract',
        'get_defined_vars',
        'func_get_args',
        'func_num_args',
        'func_get_arg',
    ];

    public function process(string|array $token, array $tokens, int $position): string
    {
        if (!\is_array($token)) {
            return $token;
        }

        if (!\in_array($token[0], [\T_STRING, \T_NAME_FULLY_QUALIFIED, \T_NAME_QUALIFIED], true)) {
            return $token[1];
        }

        $name = \ltrim($token[1], '\\');

        if ('' === $name || !\function_exists($name)) {
            return $token[1];
        }

        if (\in_array(\strtolower($name), self::NOT_DYNAMIC, true)) {
            return $token[1];
        }

        // Шифруем только вызовы функций: далее должен идти "(".
        $next = TokenContext::nextSignificant($tokens, $position);

        if (null === $next || '(' !== $tokens[$next]) {
            return $token[1];
        }

        $previous = TokenContext::previousSignificant($tokens, $position);

        if (null !== $previous
            && \is_array($tokens[$previous])
            && \in_array($tokens[$previous][0], self::NOT_A_CALL, true)
        ) {
            return $token[1];
        }

        return Encoder::encode($name);
    }
}
