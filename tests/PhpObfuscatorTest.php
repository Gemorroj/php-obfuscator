<?php

declare(strict_types=1);

namespace PhpObfuscator\Tests;

use PhpObfuscator\Module\License;
use PhpObfuscator\Module\SilentCalls;
use PhpObfuscator\Module\StringEncoder;
use PhpObfuscator\PhpObfuscator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhpObfuscatorTest extends TestCase
{
    #[DataProvider('provideCode')]
    public function testPhpObfuscatorDefault(string $code): void
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

        $content = (string) \file_get_contents(__FILE__);
        yield [\str_replace('PhpObfuscatorTest', 'PhpObfuscatorTest'.\uniqid('', false), $content)];

        yield [(string) \file_get_contents(__DIR__.'/fixtures/test.php')];
    }

    #[DataProvider('provideValidCode')]
    public function testValidCode(string $code, mixed $expected): void
    {
        $phpObfuscator = new PhpObfuscator();
        $phpObfuscator->addDefaultModules();

        $obfuscatedCode = \substr($phpObfuscator->obfuscate($code), \strlen('<?php'));

        self::assertSame($expected, eval($obfuscatedCode));
    }

    public static function provideValidCode(): \Generator
    {
        yield 'heredoc' => [
            <<<'PHP'
                <?php
                $x = 'w';
                return <<<EOT
                line1  $x  line2
                EOT;
                PHP,
            'line1  w  line2',
        ];

        yield 'interpolated_whitespace' => [
            '<?php $b = "X"; return "a $b c ";',
            'a X c ',
        ];

        yield 'property_default' => [
            '<?php class ObfProp { private string $x = "default"; public function get() { return $this->x; } } return (new ObfProp())->get();',
            'default',
        ];

        yield 'parameter_default' => [
            '<?php function obfParamDefault($a = "hi") { return $a; } return obfParamDefault();',
            'hi',
        ];

        yield 'static_property' => [
            '<?php class ObfStaticProp { public static $p = 5; public static function g() { return self::$p; } } return ObfStaticProp::g();',
            5,
        ];

        yield 'static_property_outside' => [
            '<?php class ObfStaticOutside { public static $p = 5; } return ObfStaticOutside::$p;',
            5,
        ];

        yield 'static_local_default' => [
            '<?php function obfStaticLocal() { static $x = "s"; return $x; } return obfStaticLocal();',
            's',
        ];

        yield 'method_named_like_builtin' => [
            '<?php class ObfMethod { public function count() { return 42; } } return (new ObfMethod())->count();',
            42,
        ];

        yield 'static_method_named_like_builtin' => [
            '<?php class ObfStaticMethod { public static function count() { return 7; } } return ObfStaticMethod::count();',
            7,
        ];

        yield 'constant_multiple' => [
            '<?php class ObfConst { public const X = "a", Y = "b"; } return ObfConst::X.ObfConst::Y;',
            'ab',
        ];

        yield 'enum_case' => [
            '<?php enum ObfEnum: string { case A = "a"; case B = "b"; } return ObfEnum::A->value.ObfEnum::B->value;',
            'ab',
        ];

        yield 'attribute_arguments' => [
            '<?php #[Attribute] class ObfAttr { public function __construct(public string $a = \'\', public string $b = \'\') {} } #[ObfAttr("a", "b")] function obfAttribute() { return "x"; } return obfAttribute();',
            'x',
        ];

        yield 'octal_literal' => ['<?php return 012;', 10];
        yield 'hex_literal' => ['<?php return 0x1A;', 26];
        yield 'binary_literal' => ['<?php return 0b101;', 5];
        yield 'underscore_literal' => ['<?php return 1_000_000;', 1000000];
        yield 'float_literal' => ['<?php return 1.5 + 0.25;', 1.75];

        yield 'strict_types' => ['<?php declare(strict_types=1); return 1 + 1;', 2];

        yield 'variable_variables' => ['<?php $n = "v"; $v = 7; return $$n;', 7];

        yield 'namespaced_call' => ['<?php return \strlen("abcd");', 4];

        yield 'string_with_quotes' => ['<?php return "it\'s \"fine\"";', 'it\'s "fine"'];

        yield 'string_with_backslash' => [
            <<<'PHP'
                <?php return "back\\slash";
                PHP,
            'back\\slash',
        ];

        yield 'string_with_single_quote' => [
            <<<'PHP'
                <?php return 'it\'s fine';
                PHP,
            "it's fine",
        ];

        yield 'dot_concat_numbers' => ['<?php return 1 . 2;', '12'];
        yield 'negative_number' => ['<?php return -5 + 0;', -5];
        yield 'integer_precedence' => ['<?php return (2 ** 10) + (17 % 5);', 1026];
        yield 'neg_float' => ['<?php return -1.5 * 2;', -3.0];
        yield 'float_pow' => ['<?php return 2 ** 1.5;', 2 ** 1.5];
        yield 'float_type_preserved' => ['<?php return is_float(2.0) && is_float(0.5) && is_float(1.5);', true];
        yield 'list_destructure' => ['<?php [$a, $b] = [3, 4]; return $a + $b;', 7];
        yield 'array_destructure_keys' => ['<?php ["a" => $x, "b" => $y] = ["a" => 1, "b" => 2]; return $x + $y;', 3];
        yield 'const_arith' => ['<?php class ObfConstArith { const int A = 2; const int B = self::A * 3; } return ObfConstArith::B;', 6];
        yield 'compact' => ['<?php $foo = 1; $bar = 2; return array_sum(compact("foo", "bar"));', 3];
        yield 'extract' => ['<?php $arr = ["a" => 5]; extract($arr); return $a;', 5];
        yield 'get_defined_vars' => ['<?php $q = 9; return get_defined_vars()["q"];', 9];
        yield 'named_args' => ['<?php function obfNamed($value) { return $value; } return obfNamed(value: 7);', 7];
        yield 'named_arg_method' => ['<?php class ObfNamedMethod { public function m(int $count) { return $count; } } return (new ObfNamedMethod())->m(count: 4);', 4];
        yield 'property_hook' => ['<?php class ObfHook { private string $x = "a"; public string $y { get => strtoupper($this->x); set => $this->x = strtolower($value); } } $h = new ObfHook(); $h->y = "ABC"; return $h->y;', 'ABC'];
        yield 'first_class_callable' => ['<?php $f = strlen(...); return $f("abcd");', 4];
        yield 'goto_loop' => ['<?php $i = 0; start: ++$i; if ($i < 3) { goto start; } return $i;', 3];
        yield 'generator' => ['<?php function obfGen() { yield 1; yield 2; } return iterator_to_array(obfGen())[1];', 2];
        yield 'by_reference' => ['<?php function obfInc(&$n) { ++$n; } $v = 1; obfInc($v); return $v;', 2];
        yield 'closure_use' => ['<?php $x = 3; $f = function () use ($x) { return $x; }; return $f();', 3];
        yield 'nullsafe' => ['<?php $o = null; return $o?->foo() ?? "d";', 'd'];
        yield 'spread' => ['<?php function obfSpread(int ...$n) { return array_sum($n); } return obfSpread(1, 2, 3);', 6];
        yield 'enum_interface' => ['<?php interface ObfI {} enum ObfEnum2: string implements ObfI { case A = "a"; } return ObfEnum2::A->value;', 'a'];
    }

    public function testLicenseInvalid(): void
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

    public function testLicenseExpired(): void
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

    public function testLicenseValid(): void
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

    public function testLicenseValidWithNamespace(): void
    {
        $code = '<?php namespace Foo; return "Hello World!";';
        $key = 'keystr';
        $licenseTemplate = 'license goes here';
        $licenseObj = new License($key, 'throw new \Exception("invalid license");', $licenseTemplate);
        $license = $licenseObj->getLicense(\time() + 10000000);

        $phpObfuscator = new PhpObfuscator();
        $phpObfuscator->addDefaultModules();
        $phpObfuscator->addModule($licenseObj);

        $obfuscatedCode = $phpObfuscator->obfuscate($code);
        $obfuscatedCode = \str_replace($licenseTemplate, $license, $obfuscatedCode);

        self::assertSame('Hello World!', eval(\substr($obfuscatedCode, \strlen('<?php'))));
    }

    public function testLicensePeriodicVerificationInsideLongCode(): void
    {
        $key = 'keystr';
        $licenseTemplate = 'license goes here';
        $invalid = 'throw new \Exception("invalid license");';
        $licenseObj = new License($key, $invalid, $licenseTemplate, [new StringEncoder(), new SilentCalls()], 1);
        $license = $licenseObj->getLicense(\time() + 10000000);

        $code = '<?php '.\str_repeat('$p = 1;', 500).'$s = 0; for ($i = 0; $i < 3; ++$i) { $s += $i; } return $s;';

        $phpObfuscator = new PhpObfuscator();
        $phpObfuscator->addDefaultModules();
        $phpObfuscator->addModule($licenseObj);

        $obfuscatedCode = \str_replace($licenseTemplate, $license, $phpObfuscator->obfuscate($code));

        self::assertSame(3, eval(\substr($obfuscatedCode, \strlen('<?php'))));
    }

    public function testLicenseEmptyKeyThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new License();
    }

    public function testCheckLicense(): void
    {
        $license = new License('key');

        self::assertTrue($license->checkLicense($license->getLicense(\time() + 1000)));
        self::assertFalse($license->checkLicense($license->getLicense(\time() - 1)));
        self::assertFalse($license->checkLicense('garbage'));
    }

    public function testAddModuleAfterLicenseThrows(): void
    {
        $phpObfuscator = new PhpObfuscator();
        $phpObfuscator->addModule(new License('key'));

        $this->expectException(\LogicException::class);
        $phpObfuscator->addModule(new StringEncoder());
    }

    public function testObfuscateWithoutModulesReturnsInput(): void
    {
        $phpObfuscator = new PhpObfuscator();

        self::assertSame('<?php return 1;', $phpObfuscator->obfuscate('<?php return 1;'));
    }

    public function testObfuscatorIsReusable(): void
    {
        $phpObfuscator = new PhpObfuscator();
        $phpObfuscator->addDefaultModules();

        $first = $phpObfuscator->obfuscate('<?php return 1;');
        $second = $phpObfuscator->obfuscate('<?php return 2;');

        self::assertSame(1, eval(\substr($first, \strlen('<?php'))));
        self::assertSame(2, eval(\substr($second, \strlen('<?php'))));
    }
}
