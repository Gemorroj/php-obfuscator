<?php

namespace PhpObfuscator\Module;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;

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
     * Случайный базовый шифровальщик.
     *
     * @return Expr выражение вида base64_decode('...')
     */
    public static function encodeBasic(string $value): Expr
    {
        $encoders = self::ENCODERS;
        $encode = \array_keys($encoders)[\random_int(0, \count($encoders) - 1)];

        return self::call($encoders[$encode], $encode($value));
    }

    /**
     * Вызов функции с одним строковым аргументом.
     */
    public static function call(string $function, string $argument): Expr
    {
        return new FuncCall(new Name($function), [new Arg(new String_($argument))]);
    }
}
