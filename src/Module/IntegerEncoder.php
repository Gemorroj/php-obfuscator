<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;

/**
 * Модуль шифрования чисел.
 */
class IntegerEncoder implements ModuleInterface
{
    public function process(string|array $token, array $tokens): string
    {
        if (!\is_array($token) || \T_LNUMBER !== $token[0]) {
            return \is_array($token) ? $token[1] : $token;
        }

        $token = $token[1];

        // for declared_types
        if ('0' === $token || '1' === $token) {
            return $token;
        }

        switch (\random_int(0, 3)) {
            case 0:
                $delta = \random_int(2, \max($token, 3));

                return ($token * $delta).'/'.$delta;

            case 1:
                $delta = \random_int(2, \max($token, 3));

                return ($token - $delta).'+'.$delta;

            case 2:
                $delta = \random_int(2, \max($token, 3));

                return ($token + $delta).'-'.$delta;

            case 3:
                return '0x'.\dechex($token);
        }
    }
}
