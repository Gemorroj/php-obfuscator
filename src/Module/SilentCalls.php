<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * Модуль шифрования вызовов функций.
 */
class SilentCalls extends NodeVisitorAbstract implements ModuleInterface
{
    /**
     * Функции, которые нельзя вызвать динамически.
     *
     * @var list<string>
     */
    private const NOT_DYNAMIC = [
        'compact',
        'extract',
        'get_defined_vars',
        'func_get_args',
        'func_num_args',
        'func_get_arg',
    ];

    public function leaveNode(Node $node): Node|int|array|null
    {
        if (!$node instanceof FuncCall || !$node->name instanceof Name) {
            return null;
        }

        $name = $node->name->toString();

        if (!\function_exists($name)) {
            return null;
        }

        if (\in_array(\strtolower($name), self::NOT_DYNAMIC, true)) {
            return null;
        }

        $node->name = Encoder::encodeBasic($name);

        return null;
    }
}
