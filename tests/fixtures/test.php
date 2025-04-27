<?php

//declare(strict_types=1);

use PhpObfuscator\PhpObfuscator;
use PHPUnit\Framework\Attributes\Group;

class test implements \Stringable
{
    private const int TEST = 33;

    #[Group(33)]
    #[Group('33')]
    public function testPhphObfuscatorDefault(string $code): void
    {
        $phpObfuscator = new PhpObfuscator();
        $phpObfuscator->addDefaultModules();

        $obfuscatedCode = $phpObfuscator->obfuscate($code);
        self::st($obfuscatedCode);
    }

    protected static function st(...$args): self
    {
        return new self();
    }

    public function __toString(): string
    {
        return '';
    }
}
