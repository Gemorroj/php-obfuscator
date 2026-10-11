<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;

/**
 * Модуль шифрования чисел.
 */
class IntegerEncoder implements ModuleInterface
{
    public function process(string|array $token, array $tokens, int $position): string
    {
        if (!\is_array($token)) {
            return $token;
        }

        return match ($token[0]) {
            \T_LNUMBER => $this->encodeInteger($token[1]),
            \T_DNUMBER => $this->encodeFloat($token[1]),
            default => $token[1],
        };
    }

    /**
     * Шифрование целочисленного литерала.
     */
    private function encodeInteger(string $value): string
    {
        // 0 и 1 не трогаем: используются в declare(strict_types=1) и подобном.
        if ('0' === $value || '1' === $value) {
            return $value;
        }

        // Только обычные десятичные литералы без разделителей.
        // octal (012, 0o12), hex (0x1A), binary (0b101) и 1_000 не трогаем,
        // чтобы не изменить их значение.
        if (1 !== \preg_match('/^[1-9][0-9]*$/', $value)) {
            return $value;
        }

        $number = (int) $value;

        if ((string) $number !== $value) {
            // переполнение или потеря точности
            return $value;
        }

        $delta = \random_int(2, \max($number, 3));

        return match (\random_int(0, 3)) {
            0 => '('.($number * $delta).'/'.$delta.')',
            1 => '('.($number - $delta).'+'.$delta.')',
            2 => '('.($number + $delta).'-'.$delta.')',
            default => '0x'.\dechex($number),
        };
    }

    /**
     * Шифрование числа с плавающей точкой.
     */
    private function encodeFloat(string $value): string
    {
        // Только простая десятичная запись (без экспоненты, точки в начале/конце).
        if (1 !== \preg_match('/^([0-9]+)\.([0-9]+)$/', $value, $matches)) {
            return $value;
        }

        $digits = $matches[1].$matches[2];

        if (\strlen(\ltrim($digits, '0')) > 15) {
            // слишком длинное число: (int) потеряет точность
            return $value;
        }

        // Знаменатель делаем float-литералом, чтобы результат остался float.
        return '('.((int) $digits).'/'.(10 ** \strlen($matches[2])).'.0)';
    }
}
