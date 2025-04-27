<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;

/**
 * Модуль оптимизации имён переменных.
 */
class VarOptimizer implements ModuleInterface
{
    /**
     * @var array ассоциативный массив название переменных
     */
    private array $vars = [];
    /**
     * @var array стек токенов
     */
    private array $tokensStack = [];

    /**
     * @param string[] $whitelist список переменных, которые нельзя переименовывать
     */
    public function __construct(private readonly array $whitelist = ['$GLOBALS', '$_SERVER', '$_ENV', '$_GET', '$_POST', '$_REQUEST', '$_FILES', '$_COOKIE', '$_SESSION', '$this'])
    {
    }

    public function process(string|array $token, array $tokens): string
    {
        if (!\is_array($token)) {
            return $token;
        }

        if (\T_VARIABLE === $token[0]) {
            if (\in_array($token[1], $this->whitelist, true)) {
                return $token[1];
            }

            $part = \array_slice($this->tokensStack, -2);

            if (\in_array($part[0], [
                \T_PRIVATE,
                \T_PROTECTED,
                \T_PUBLIC,
                \T_STATIC,
                \T_VAR,
            ], true)) {
                return $token[1];
            }

            if (!isset($this->vars[$token[1]])) {
                $this->vars[$token[1]] = '$'.$this->getVarName();
            }

            return $this->vars[$token[1]];
        }

        $this->tokensStack[] = $token[0];

        return $token[1];
    }

    /**
     * Генератор названия переменной.
     */
    private function getVarName(): string
    {
        $firstLetters = \array_merge(\range('a', 'z'), \range('A', 'Z'), ['_']);
        $secondLetters = \array_merge($firstLetters, \range(0, 9));

        $varsSize = \count($this->vars);

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
