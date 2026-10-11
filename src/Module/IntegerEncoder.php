<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Div;
use PhpParser\Node\Expr\BinaryOp\Minus;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\NodeVisitorAbstract;

/**
 * Модуль шифрования чисел.
 */
class IntegerEncoder extends NodeVisitorAbstract implements ModuleInterface
{
    private int $declareDepth = 0;

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Declare_) {
            ++$this->declareDepth;
        }

        return null;
    }

    public function leaveNode(Node $node): Node|int|array|null
    {
        if ($node instanceof Declare_) {
            --$this->declareDepth;
        }

        if (0 !== $this->declareDepth) {
            return null;
        }

        if ($node instanceof Int_) {
            return $this->encodeInt($node->value);
        }

        if ($node instanceof Float_) {
            return $this->encodeFloat($node->value);
        }

        return null;
    }

    /**
     * Шифрование целочисленного литерала.
     */
    private function encodeInt(int $value): ?Node
    {
        // 0/1 не трогаем, слишком большие — во избежание переполнения.
        if ($value < 2 || $value > \intdiv(\PHP_INT_MAX, 3)) {
            return null;
        }

        $delta = \random_int(2, \max($value, 3));

        switch (\random_int(0, 3)) {
            case 0:
                $small = \random_int(2, 3);

                return new Div(new Int_($value * $small), new Int_($small));

            case 1:
                return new Plus(new Minus(new Int_($value), new Int_($delta)), new Int_($delta));

            case 2:
                return new Minus(new Plus(new Int_($value), new Int_($delta)), new Int_($delta));

            default:
                return new Int_($value, ['kind' => Int_::KIND_HEX]);
        }
    }

    /**
     * Шифрование числа с плавающей точкой.
     */
    private function encodeFloat(float $value): ?Node
    {
        if (!\is_finite($value)) {
            return null;
        }

        $repr = \var_export($value, true);

        if (1 === \preg_match('/^([0-9]+)\.([0-9]+)$/', $repr, $matches)
            && \strlen(\ltrim($matches[1].$matches[2], '0')) <= 15
        ) {
            // Деление на float-литерал, чтобы результат остался float.
            return new Div(
                new Int_((int) ($matches[1].$matches[2])),
                new Float_((float) (10 ** \strlen($matches[2]))),
            );
        }

        // Запасной вариант: значение и тип сохраняются, добавляется шум.
        return new Plus(new Float_($value), new Minus(new Int_(1), new Int_(1)));
    }
}
