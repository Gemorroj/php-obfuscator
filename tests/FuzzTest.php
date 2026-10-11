<?php

declare(strict_types=1);

namespace PhpObfuscator\Tests;

use PhpObfuscator\PhpObfuscator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Поведенческий fuzz: обфусцированный код должен вести себя так же, как исходный.
 *
 * Каждый сниппет выполняется в отдельном процессе (изоляция деклараций) и
 * прогоняется несколько раз, чтобы покрыть случайный выбор энкодеров.
 */
class FuzzTest extends TestCase
{
    private const ITERATIONS = 2;

    #[DataProvider('provideCorpus')]
    public function testBehaviourMatchesOriginal(string $code): void
    {
        $name = $this->dataName();

        [$origRc, $origOut] = $this->serializedRun($code);
        self::assertSame(0, $origRc, "original failed for [$name]: $origOut");

        for ($iteration = 0; $iteration < self::ITERATIONS; ++$iteration) {
            $phpObfuscator = new PhpObfuscator();
            $phpObfuscator->addDefaultModules();

            [$rc, $out] = $this->serializedRun($phpObfuscator->obfuscate($code));

            self::assertSame(0, $rc, "obfuscated failed for [$name] (iteration $iteration): $out");
            self::assertSame($origOut, $out, "behaviour mismatch for [$name] (iteration $iteration)");
        }
    }

    /**
     * @return array{int, string}
     */
    private function serializedRun(string $code): array
    {
        $tmp = \tempnam(\sys_get_temp_dir(), 'fuzz_').'.php';
        \file_put_contents($tmp, $code);

        $runner = 'echo serialize(@require '.\var_export($tmp, true).');';

        $process = \proc_open(
            [\PHP_BINARY, '-d', 'error_reporting=-1', '-r', $runner],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );

        if (false === $process) {
            \unlink($tmp);

            return [1, 'proc_open failed'];
        }

        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $rc = \proc_close($process);

        \unlink($tmp);

        return [$rc, $stdout.$stderr];
    }

    public static function provideCorpus(): \Generator
    {
        // --- общий корпус ---

        yield 'first_class_callable' => ['<?php $f = strlen(...); return $f("abcd");'];
        yield 'compact' => ['<?php $foo = 1; $bar = 2; return array_sum(compact("foo", "bar"));'];
        yield 'extract' => ['<?php $arr = ["a" => 5]; extract($arr); return $a;'];
        yield 'get_defined_vars' => ['<?php $q = 9; return get_defined_vars()["q"];'];
        yield 'named_args' => ['<?php function make($value) { return $value; } return make(value: 7);'];
        yield 'dot_concat_numbers' => ['<?php return 1 . 2;'];
        yield 'negative_number' => ['<?php return -5 + 0;'];
        yield 'modulo' => ['<?php return 17 % 5;'];
        yield 'pow' => ['<?php return 2 ** 10;'];
        yield 'array_key_access' => ['<?php $a = [5 => "x"]; return $a[5];'];
        yield 'typed_property' => ['<?php class P { public int|string $x = 3; } return (new P())->x;'];
        yield 'readonly_class' => ['<?php readonly class R { public function __construct(public int $x = 4) {} } return (new R())->x;'];
        yield 'constructor_promotion' => ['<?php class C { public function __construct(private string $v = "p") {} public function get() { return $this->v; } } return (new C())->get();'];
        yield 'static_closure' => ['<?php $f = static function (int $n) { return $n + 1; }; return $f(1);'];
        yield 'arrow_fn' => ['<?php $f = fn(int $n) => $n * 2; return $f(3);'];
        yield 'closure_use' => ['<?php $x = 3; $f = function () use ($x) { return $x; }; return $f();'];
        yield 'by_reference' => ['<?php function inc(&$n) { ++$n; } $v = 1; inc($v); return $v;'];
        yield 'list_destructure' => ['<?php [$a, $b] = [3, 4]; return $a + $b;'];
        yield 'foreach_ref' => ['<?php $arr = [1, 2]; foreach ($arr as &$v) { $v *= 2; } return $arr[1];'];
        yield 'match_expr' => ['<?php $x = 2; return match($x) { 1 => "a", 2 => "b", default => "c" };'];
        yield 'switch_case' => ['<?php $x = "b"; switch ($x) { case "a": return 1; case "b": return 2; } return 0;'];
        yield 'goto_label' => ['<?php $i = 0; start: ++$i; if ($i < 3) { goto start; } return $i;'];
        yield 'try_finally' => ['<?php $x = 0; try { $x = 1; } finally { $x += 1; } return $x;'];
        yield 'do_while' => ['<?php $i = 0; do { ++$i; } while ($i < 4); return $i;'];
        yield 'generator' => ['<?php function g() { yield 1; yield 2; } return iterator_to_array(g())[1];'];
        yield 'heredoc_escape' => ["<?php \$x = 5; return <<<EOT\nval=\$x\\tend\nEOT;"];
        yield 'nowdoc' => ["<?php return <<<'EOT'\nno \$interp here\nEOT;"];
        yield 'double_escape' => ['<?php return "tab\there\nnew";'];
        yield 'null_byte' => ['<?php return strlen("a\0b");'];
        yield 'unicode_escape' => ['<?php return "\u{1F600}";'];
        yield 'short_echo' => ['<?php $x = 5; ?>val=<?= $x ?>'];
        yield 'inline_html' => ['<?php $x = 1; ?>  <b>hi</b>  <?php return $x;'];
        yield 'enum_interface' => ['<?php interface I {} enum E: string implements I { case A = "a"; } return E::A->value;'];
        yield 'enum_method' => ['<?php enum E: int { case A = 1; public function label(): string { return "a"; } } return E::A->label();'];
        yield 'static_local' => ['<?php function c() { static $n = 0; return ++$n; } c(); return c();'];
        yield 'global_var' => ['<?php $g = 4; function f() { global $g; return $g; } return f();'];
        yield 'superglobals' => ['<?php $_GET["k"] = 3; return $_GET["k"];'];
        yield 'class_const_array' => ['<?php class K { const A = ["x", "y"]; } return K::A[1];'];
        yield 'const_expr_arith' => ['<?php class K { const int A = 2; const int B = self::A * 3; } return K::B;'];
        yield 'variable_class' => ['<?php $c = "stdClass"; $o = new $c(); return $o instanceof stdClass ? 1 : 0;'];
        yield 'new_in_default' => ['<?php class Box { public function __construct(public string $v = "z") {} } class HB { public ?Box $b = null; } return (new HB())->b === null ? 1 : 0;'];
        yield 'namespace_call' => ['<?php namespace App; function len($s) { return \strlen($s); } return len("hello");'];
        yield 'nullsafe' => ['<?php $o = null; return $o?->foo() ?? "d";'];
        yield 'spread' => ['<?php function s(int ...$n) { return array_sum($n); } return s(1, 2, 3);'];

        // --- дополнительные ---

        yield 'property_hook' => ['<?php class HA { public string $v = "a" { get => $this->v; set => strtoupper($value); } } return (new HA())->v;'];
        yield 'property_hook_get' => ['<?php class HGetOnly { private string $x = "hi"; public string $y { get => $this->x . "!"; } } return (new HGetOnly())->y;'];
        yield 'halt_compiler' => ['<?php return 5; __halt_compiler(); this is raw data;'];
        yield 'shebang' => ["#!/usr/bin/env php\n<?php return 3;"];
        yield 'nested_ternary' => ['<?php $x = 2; return $x === 1 ? "a" : ($x === 2 ? "b" : "c");'];
        yield 'coalesce_assign' => ['<?php $a = null; $a ??= 7; return $a;'];
        yield 'string_number_concat' => ['<?php return "x" . 5 . "y";'];
        yield 'float_precision' => ['<?php return 0.1 + 0.2;'];
        yield 'big_number' => ['<?php return 9007199254740993 > 0;'];
        yield 'bitwise' => ['<?php return (6 & 3) | (8 ^ 1);'];
        yield 'spaceship' => ['<?php return 2 <=> 3;'];
        yield 'null_coalesce' => ['<?php $o = new stdClass(); return $o->x ?? "d";'];
        yield 'array_unpack' => ['<?php $a = [1, 2]; $b = [...$a, 3]; return array_sum($b);'];
        yield 'attribute_repeat' => ['<?php #[Attribute] class Attr { public function __construct(public array $v = []) {} } #[Attr([1, 2]), Attr(v: [3])] function f() { return "z"; } return f();'];
        yield 'named_arg_method' => ['<?php class C2 { public function m(int $count) { return $count; } } return (new C2())->m(count: 4);'];
        yield 'match_strings' => ['<?php $x = "b"; return match($x) { "a" => 1, "b" => 2, default => 3 };'];
        yield 'neg_float' => ['<?php return -1.5 * 2;'];
        yield 'float_concat' => ['<?php return "n=" . 1.5;'];
        yield 'pow_float' => ['<?php return 2 ** 1.5;'];
        yield 'intdiv' => ['<?php return intdiv(17, 5);'];
        yield 'array_destructure_keys' => ['<?php ["a" => $x, "b" => $y] = ["a" => 1, "b" => 2]; return $x + $y;'];
        yield 'first_class_static' => ['<?php class C3 { public static function m(): int { return 9; } } $f = C3::m(...); return $f();'];
        yield 'first_class_method' => ['<?php class C4 { public function m(): int { return 8; } } $f = (new C4())->m(...); return $f();'];
        yield 'call_user_func' => ['<?php return call_user_func("strlen", "abcd");'];
        yield 'static_prop_typed' => ['<?php class C5 { public static int $n = 12; } return C5::$n;'];
        yield 'class_const_string_concat' => ['<?php class C6 { const P = "a"; const Q = self::P . "b"; } return C6::Q;'];
        yield 'default_const_expr' => ['<?php class C7 { public string $v = self::X . "!"; const X = "hi"; } return (new C7())->v;'];
        yield 'string_in_match_arm' => ['<?php return match(true) { true => "yes", false => "no" };'];
    }
}
