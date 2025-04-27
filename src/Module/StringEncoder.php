<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;

/**
 * Модуль шифрования строк.
 */
class StringEncoder implements ModuleInterface
{
    /**
     * @var array Стек токенов
     */
    private array $tokensStack = [];

    /**
     * @param int $compressLevel Степень сжатия строки с помощью gzdeflate. -1 - будет использоваться уровень сжатия по умолчанию библиотеки zlib. 0 - отключение сжатия.
     */
    public function __construct(private readonly int $compressLevel = -1)
    {
    }

    public function process(string|array $token, array $tokens): string
    {
        if (!\is_array($token)) {
            return $token;
        }

        if (\T_WHITESPACE === $token[0]) {
            return $token[1];
        }

        if (\T_CONSTANT_ENCAPSED_STRING === $token[0]) {
            $part = \array_slice($this->tokensStack, -2);

            if (\T_CONST === $part[0] && \T_STRING === $part[1]) {
                return $token[1];
            }
            if (\T_ATTRIBUTE === $part[0] && \T_STRING === $part[1]) {
                return $token[1];
            }

            return $this->encodeString(\substr($token[1], 1, -1));
        }

        $this->tokensStack[] = $token[0];

        return $token[1];
    }

    /**
     * Шифрование строки.
     */
    private function encodeString(string $string): string
    {
        if ('' === $string) {
            return '\'\'';
        }

        if ($this->compressLevel) {
            $compressed = \base64_encode(\gzdeflate($string, $this->compressLevel));
            $decompresser = 'gzinflate(base64_decode(\''.$compressed.'\'))';

            if (\strlen($decompresser) <= \strlen($string)) {
                return $decompresser;
            }
        }

        // Функции шифрования
        $encoders = [
            'base64_encode' => 'base64_decode',
            'str_rot13' => 'str_rot13',
            'strrev' => 'strrev',
        ];

        // Кастомные функции шифрования
        $advancedEncoders = [
            function (string $string): string {
                $return = [];
                $length = \strlen($string);

                for ($i = 0; $i < $length; ++$i) {
                    $return[] = 'chr('.\ord($string[$i]).')';
                }

                return '('.\implode('.', $return).')';
            },

            function (string $string): string {
                return '~base64_decode(\''.\base64_encode(~$string).'\')';
            },

            function (string $string): string {
                $key = \base64_encode(\md5(\microtime(true), true));
                $key = \substr(\str_repeat($key, \ceil(($s = \strlen($string)) / \strlen($key))), 0, $s);

                return '(base64_decode(\''.\base64_encode($string ^ $key).'\')^('.$this->encodeString($key).'))';
            },
        ];

        $encSize = \count($encoders);
        $advSize = \count($advancedEncoders);

        $encoder = \random_int(0, $encSize + $advSize - 1);

        return $encoder < $encSize ?
            \array_values($encoders)[$encoder].'(\''.\addslashes(\array_keys($encoders)[$encoder]($string)).'\')' :
            $advancedEncoders[$encoder - $encSize]($string);
    }
}
