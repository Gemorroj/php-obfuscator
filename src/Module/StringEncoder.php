<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\MinifiedPrettyPrinter;
use PhpObfuscator\ModuleInterface;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BitwiseXor;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\BitwiseNot;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyHook;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeVisitorAbstract;

/**
 * Модуль шифрования строк.
 */
class StringEncoder extends NodeVisitorAbstract implements ModuleInterface
{
    /**
     * Стек признака «находимся в константном выражении».
     *
     * @var list<bool>
     */
    private array $stack = [];

    private bool $constant = false;

    private MinifiedPrettyPrinter $printer;

    /**
     * @param int $compressLevel Степень сжатия строки с помощью gzdeflate. -1 - уровень zlib по умолчанию. 0 - отключение сжатия.
     */
    public function __construct(private readonly int $compressLevel = -1)
    {
        $this->printer = new MinifiedPrettyPrinter();
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->stack = [];
        $this->constant = false;

        return null;
    }

    public function enterNode(Node $node): ?int
    {
        $this->stack[] = $this->constant;

        if ($node instanceof PropertyHook) {
            // Тело хука — рантайм, а не константное выражение.
            $this->constant = false;
        } elseif ($this->isConstantContext($node)) {
            $this->constant = true;
        }

        return null;
    }

    public function leaveNode(Node $node): Node|int|array|null
    {
        $constant = $this->constant;
        $this->constant = (bool) \array_pop($this->stack);

        if ($node instanceof String_ && !$constant) {
            return $this->encode($node->value);
        }

        return null;
    }

    /**
     * Контексты, где допустимы только константные выражения (вызовы функций нельзя).
     */
    private function isConstantContext(Node $node): bool
    {
        return $node instanceof ClassConst
            || $node instanceof Const_
            || $node instanceof EnumCase
            || $node instanceof Property
            || $node instanceof Param
            || $node instanceof StaticVar
            || $node instanceof Attribute;
    }

    private function encode(string $string): Expr
    {
        if ('' === $string) {
            return new String_('');
        }

        if (0 !== $this->compressLevel) {
            $deflated = \gzdeflate($string, $this->compressLevel);

            if (false !== $deflated) {
                $decompressor = new FuncCall(new Name('gzinflate'), [
                    new Arg(Encoder::call('base64_decode', \base64_encode($deflated))),
                ]);

                if (\strlen($this->printer->prettyPrintExpr($decompressor)) <= \strlen($string)) {
                    return $decompressor;
                }
            }
        }

        $advancedEncoders = [
            static function (string $string): Expr {
                $result = null;

                for ($i = 0, $length = \strlen($string); $i < $length; ++$i) {
                    $char = new FuncCall(new Name('chr'), [new Arg(new Int_(\ord($string[$i])))]);
                    $result = null === $result ? $char : new Concat($result, $char);
                }

                return $result ?? new String_('');
            },

            static function (string $string): Expr {
                return new BitwiseNot(Encoder::call('base64_decode', \base64_encode(~$string)));
            },

            function (string $string): Expr {
                $key = \base64_encode(\md5((string) \microtime(true), true));
                $size = \strlen($string);
                $key = \substr(\str_repeat($key, (int) \ceil($size / \strlen($key))), 0, $size);

                return new BitwiseXor(
                    Encoder::call('base64_decode', \base64_encode($string ^ $key)),
                    $this->encode($key),
                );
            },
        ];

        $basicCount = \count(Encoder::ENCODERS);
        $advancedCount = \count($advancedEncoders);
        $encoder = \random_int(0, $basicCount + $advancedCount - 1);

        if ($encoder < $basicCount) {
            return Encoder::encodeBasic($string);
        }

        return $advancedEncoders[$encoder - $basicCount]($string);
    }
}
