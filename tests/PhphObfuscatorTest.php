<?php

declare(strict_types=1);

namespace PhpObfuscator\Tests;

use PhpObfuscator\Module\License;
use PhpObfuscator\PhpObfuscator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhphObfuscatorTest extends TestCase
{
    #[DataProvider('provideCode')]
    public function testPhphObfuscatorDefault(string $code): void
    {
        $phpObfuscator = new PhpObfuscator();
        $phpObfuscator->addDefaultModules();

        $obfuscatedCode = $phpObfuscator->obfuscate($code);
        self::assertNotEmpty($obfuscatedCode);
        self::assertNotSame($code, $obfuscatedCode);

        $obfuscatedCode = \substr($obfuscatedCode, \strlen('<?php'));
        eval($obfuscatedCode);
    }

    public static function provideCode(): \Generator
    {
        yield ['<?php return "Hello World!";'];
        yield [\str_replace('PhphObfuscatorTest', 'PhphObfuscatorTest'.\uniqid('', false), \file_get_contents(__FILE__))];
        yield [\file_get_contents(__DIR__.'/fixtures/test.php')];
    }

    public function testPhphObfuscatorLicenseInvalid(): void
    {
        $code = '<?php return "Hello World!";';
        $key = 'keystr';
        $licenseObj = new License($key, 'throw new \Exception("invalid license");');

        $phpObfuscator = new PhpObfuscator();
        $phpObfuscator->addDefaultModules();
        $phpObfuscator->addModule($licenseObj);

        $obfuscatedCode = $phpObfuscator->obfuscate($code);
        self::assertStringContainsString('$license=', $obfuscatedCode);
        $obfuscatedCode = \substr($obfuscatedCode, \strlen('<?php'));

        $this->expectExceptionMessage('invalid license');
        eval($obfuscatedCode);
    }

    public function testPhphObfuscatorLicenseExpired(): void
    {
        $code = '<?php return "Hello World!";';
        $key = 'keystr';
        $licenseTemplate = 'license goes here';
        $licenseObj = new License($key, 'throw new \Exception("invalid license");', $licenseTemplate);
        $license = $licenseObj->getLicense(\time() - 1);

        $phpObfuscator = new PhpObfuscator();
        $phpObfuscator->addDefaultModules();
        $phpObfuscator->addModule($licenseObj);

        $obfuscatedCode = $phpObfuscator->obfuscate($code);
        self::assertStringContainsString('$license=', $obfuscatedCode);
        $obfuscatedCode = \substr($obfuscatedCode, \strlen('<?php'));
        $obfuscatedCode = \str_replace($licenseTemplate, $license, $obfuscatedCode);

        $this->expectExceptionMessage('invalid license');
        eval($obfuscatedCode);
    }

    public function testPhphObfuscatorLicenseValid(): void
    {
        $code = '<?php return "Hello World!";';
        $key = 'keystr';
        $licenseTemplate = 'license goes here';
        $licenseObj = new License($key, 'throw new \Exception("invalid license");', $licenseTemplate);
        $license = $licenseObj->getLicense(\time() + 10000000);

        $phpObfuscator = new PhpObfuscator();
        $phpObfuscator->addDefaultModules();
        $phpObfuscator->addModule($licenseObj);

        $obfuscatedCode = $phpObfuscator->obfuscate($code);
        self::assertStringContainsString('$license=', $obfuscatedCode);

        $obfuscatedCode = \substr($obfuscatedCode, \strlen('<?php'));
        $obfuscatedCode = \str_replace($licenseTemplate, $license, $obfuscatedCode);

        self::assertNotEmpty($obfuscatedCode);
        self::assertNotSame($code, $obfuscatedCode);
        $result = eval($obfuscatedCode);
        self::assertSame('Hello World!', $result);
    }
}
