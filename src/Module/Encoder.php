<?php

namespace PhpObfuscator\Module;

/**
 * Общий набор базовых функций шифрования строк.
 */
final class Encoder
{
    /**
     * @var array<string, string> функция шифрования => функция дешифрования
     */
    public const ENCODERS = [
        'base64_encode' => 'base64_decode',
        'str_rot13' => 'str_rot13',
        'strrev' => 'strrev',
    ];

    /**
     * Шифрование строки случайным базовым шифровальщиком.
     *
     * @return string выражение вида base64_decode('...')
     */
    public static function encode(string $string): string
    {
        $encoder = \random_int(0, \count(self::ENCODERS) - 1);
        $encode = \array_keys(self::ENCODERS)[$encoder];
        $decode = \array_values(self::ENCODERS)[$encoder];

        return $decode.'(\''.self::escape($encode($string)).'\')';
    }

    /**
     * Экранирование для вставки в строку в одинарных кавычках.
     */
    private static function escape(string $string): string
    {
        return \str_replace(['\\', "'"], ['\\\\', "\\'"], $string);
    }
}
