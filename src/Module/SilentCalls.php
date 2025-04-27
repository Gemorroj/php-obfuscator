<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;

/**
 * Модуль шифрования вызовов функций.
 */
class SilentCalls implements ModuleInterface
{
    public function process(string|array $token, array $tokens): string
    {
        if (!\is_array($token)) {
            return $token;
        }

        return match ($token[0]) {
            \T_STRING => \function_exists($token[1]) ? $this->encodeString($token[1]) : $token[1],
            default => $token[1],
        };
    }

    /**
     * Шифрование названий функций.
     */
    private function encodeString(string $string): string
    {
        $encoders = [
            'base64_encode' => 'base64_decode',
            'str_rot13' => 'str_rot13',
            'strrev' => 'strrev',
        ];

        $encoder = \random_int(0, \count($encoders) - 1);

        return \array_values($encoders)[$encoder].'(\''.\addslashes(\array_keys($encoders)[$encoder]($string)).'\')';
    }
}
