<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;

/**
 * Модуль оптимизации имён переменных.
 */
class VarOptimizer implements ModuleInterface
{
    /**
     * @var array<string, string> ассоциативный массив название переменных
     */
    private array $vars = [];

    /**
     * @var array<int, true> позиции переменных, которые нельзя переименовывать
     */
    private array $protected = [];

    /**
     * Переменные-переменные ($$x, ${x}) несовместимы с переименованием.
     */
    private bool $disabled = false;

    /**
     * @param list<string> $whitelist список переменных, которые нельзя переименовывать
     */
    public function __construct(private readonly array $whitelist = ['$GLOBALS', '$_SERVER', '$_ENV', '$_GET', '$_POST', '$_REQUEST', '$_FILES', '$_COOKIE', '$_SESSION', '$this', '$argv', '$argc', '$http_response_header'])
    {
    }

    public function process(string|array $token, array $tokens, int $position): string
    {
        if (0 === $position) {
            $this->vars = [];
            $this->disabled = $this->hasVariableVariables($tokens)
                || $this->hasNameBasedFunctions($tokens)
                || $this->hasNamedArguments($tokens)
                || $this->hasPropertyHooks($tokens);
            $this->protected = $this->findProtectedPositions($tokens);
        }

        if (!\is_array($token) || \T_VARIABLE !== $token[0]) {
            return \is_array($token) ? $token[1] : $token;
        }

        if ($this->disabled || \in_array($token[1], $this->whitelist, true)) {
            return $token[1];
        }

        if (isset($this->protected[$position]) || $this->isStaticOrDynamicAccess($tokens, $position)) {
            return $token[1];
        }

        if (!isset($this->vars[$token[1]])) {
            $this->vars[$token[1]] = '$'.$this->getVarName(\count($this->vars));
        }

        return $this->vars[$token[1]];
    }

    /**
     * Позиции переменных, которые являются объявлениями свойств или
     * продвинутыми (promoted) параметрами конструктора.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     *
     * @return array<int, true>
     */
    private function findProtectedPositions(array $tokens): array
    {
        $context = TokenContext::analyze($tokens);
        $protected = [];

        foreach ($tokens as $position => $token) {
            if (!\is_array($token) || \T_VARIABLE !== $token[0]) {
                continue;
            }

            // свойство класса, объявленное вне метода
            if ('class' === $context['brace'][$position]
                && 0 === $context['paren'][$position]
                && 0 === $context['bracket'][$position]
            ) {
                $protected[$position] = true;

                continue;
            }

            // продвинутый параметр конструктора: private string $x
            if ($context['paren'][$position] > 0 && $this->hasPromotionModifier($tokens, $position)) {
                $protected[$position] = true;
            }
        }

        return $protected;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function hasPromotionModifier(array $tokens, int $position): bool
    {
        for ($i = $position - 1; $i >= 0; --$i) {
            $token = $tokens[$i];

            if (\is_array($token)) {
                if (\in_array($token[0], [\T_WHITESPACE, \T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }

                if (\in_array($token[0], [\T_PUBLIC, \T_PROTECTED, \T_PRIVATE, \T_READONLY], true)) {
                    return true;
                }

                continue;
            }

            if (\in_array($token, ['(', ',', ';', '{', '}', '=', ')'], true)) {
                return false;
            }
        }

        return false;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function isStaticOrDynamicAccess(array $tokens, int $position): bool
    {
        $previous = TokenContext::previousSignificant($tokens, $position);

        if (null === $previous) {
            return false;
        }

        $prevToken = $tokens[$previous];

        if ('$' === $prevToken) {
            // $$var
            return true;
        }

        // $obj::$prop, self::$prop, static::$prop
        return \is_array($prevToken) && \T_DOUBLE_COLON === $prevToken[0];
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function hasVariableVariables(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (\is_array($token)) {
                if (\T_DOLLAR_OPEN_CURLY_BRACES === $token[0]) {
                    return true;
                }

                continue;
            }

            if ('$' === $token) {
                return true;
            }
        }

        return false;
    }

    /**
     * compact()/extract()/get_defined_vars() обращаются к переменным по имени.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function hasNameBasedFunctions(array $tokens): bool
    {
        $names = ['compact', 'extract', 'get_defined_vars'];

        foreach ($tokens as $position => $token) {
            if (!\is_array($token) || \T_STRING !== $token[0]) {
                continue;
            }

            if (!\in_array($token[1], $names, true)) {
                continue;
            }

            $next = TokenContext::nextSignificant($tokens, $position);

            if (null !== $next && '(' === $tokens[$next]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Именованные аргументы ссылаются на имена параметров.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function hasNamedArguments(array $tokens): bool
    {
        foreach ($tokens as $position => $token) {
            if (!\is_array($token) || \T_STRING !== $token[0]) {
                continue;
            }

            $next = TokenContext::nextSignificant($tokens, $position);

            if (null === $next || ':' !== $tokens[$next]) {
                continue;
            }

            $previous = TokenContext::previousSignificant($tokens, $position);

            if (null !== $previous && \in_array($tokens[$previous], ['(', ','], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Property hooks (get/set) используют неявную переменную $value.
     *
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function hasPropertyHooks(array $tokens): bool
    {
        foreach ($tokens as $position => $token) {
            if (!\is_array($token) || \T_STRING !== $token[0]) {
                continue;
            }

            if ('get' !== $token[1] && 'set' !== $token[1]) {
                continue;
            }

            $next = TokenContext::nextSignificant($tokens, $position);

            if (null !== $next && \is_array($tokens[$next]) && \T_DOUBLE_ARROW === $tokens[$next][0]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Генератор названия переменной.
     */
    private function getVarName(int $varsSize): string
    {
        $firstLetters = \array_merge(\range('a', 'z'), \range('A', 'Z'), ['_']);
        $secondLetters = \array_merge($firstLetters, \range(0, 9));

        $flSize = \count($firstLetters);
        $slSize = \count($secondLetters);

        if ($varsSize < $flSize) {
            return $firstLetters[$varsSize];
        }

        $name = $firstLetters[$varsSize % $flSize];
        $varsSize = \intdiv($varsSize, $flSize);

        do {
            $name .= $secondLetters[$varsSize % $slSize];
            $varsSize = \intdiv($varsSize, $slSize);
        } while ($varsSize > 0);

        return $name;
    }
}
