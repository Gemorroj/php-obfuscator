<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyHook;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

/**
 * Модуль оптимизации имён переменных.
 */
class VarOptimizer extends NodeVisitorAbstract implements ModuleInterface
{
    /**
     * @var array<string, string> исходное имя => новое имя (без "$")
     */
    private array $map = [];

    /**
     * @var array<string, true> имена, которые нельзя переименовывать
     */
    private array $reserved = [];

    private int $counter = 0;

    /**
     * Переменные-переменные, именованные аргументы, property hooks и функции,
     * обращающиеся к переменным по имени, несовместимы с переименованием.
     */
    private bool $disabled = false;

    /**
     * @param list<string> $whitelist список переменных, которые нельзя переименовывать
     */
    public function __construct(private readonly array $whitelist = ['GLOBALS', '_SERVER', '_ENV', '_GET', '_POST', '_REQUEST', '_FILES', '_COOKIE', '_SESSION', 'this', 'argv', 'argc', 'http_response_header'])
    {
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->map = [];
        $this->reserved = [];
        $this->counter = 0;
        $this->disabled = false;

        $finder = new NodeFinder();

        foreach ($this->whitelist as $name) {
            $this->reserved[$name] = true;
        }

        foreach ($finder->findInstanceOf($nodes, Variable::class) as $variable) {
            if (!\is_string($variable->name)) {
                $this->disabled = true;

                continue;
            }

            $this->reserved[$variable->name] = true;
        }

        foreach ($finder->findInstanceOf($nodes, Param::class) as $param) {
            if ($param->isPromoted() && $param->var instanceof Variable && \is_string($param->var->name)) {
                // promoted-параметр становится свойством — имя фиксировано
                $this->reserved[$param->var->name] = true;
            }
        }

        if ([] !== $finder->findInstanceOf($nodes, PropertyHook::class)) {
            $this->disabled = true;
        }

        if ($this->hasNamedArguments($finder, $nodes) || $this->hasNameBasedCalls($finder, $nodes)) {
            $this->disabled = true;
        }

        return null;
    }

    public function leaveNode(Node $node): Node|int|array|null
    {
        if (!$node instanceof Variable || !\is_string($node->name)) {
            return null;
        }

        if ($this->disabled || isset($this->reserved[$node->name])) {
            return null;
        }

        $name = $node->name;

        if (!isset($this->map[$name])) {
            $this->map[$name] = $this->nextName();
        }

        $node->name = $this->map[$name];

        return null;
    }

    /**
     * @param Node[] $nodes
     */
    private function hasNamedArguments(NodeFinder $finder, array $nodes): bool
    {
        foreach ($finder->findInstanceOf($nodes, Arg::class) as $arg) {
            if (null !== $arg->name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Имена переменных в compact()/extract()/get_defined_vars().
     *
     * @param Node[] $nodes
     */
    private function hasNameBasedCalls(NodeFinder $finder, array $nodes): bool
    {
        foreach ($finder->findInstanceOf($nodes, FuncCall::class) as $call) {
            if (!$call->name instanceof Name) {
                continue;
            }

            if (\in_array(\strtolower($call->name->toString()), ['compact', 'extract', 'get_defined_vars'], true)) {
                return true;
            }
        }

        return false;
    }

    private function nextName(): string
    {
        do {
            $name = $this->nameForIndex($this->counter++);
        } while (isset($this->reserved[$name]));

        return $name;
    }

    /**
     * Генератор названия переменной по индексу.
     */
    private function nameForIndex(int $index): string
    {
        $firstLetters = \array_merge(\range('a', 'z'), \range('A', 'Z'), ['_']);
        $secondLetters = \array_merge($firstLetters, \range(0, 9));

        $flSize = \count($firstLetters);
        $slSize = \count($secondLetters);

        if ($index < $flSize) {
            return $firstLetters[$index];
        }

        $name = $firstLetters[$index % $flSize];
        $index = \intdiv($index, $flSize);

        do {
            $name .= $secondLetters[$index % $slSize];
            $index = \intdiv($index, $slSize);
        } while ($index > 0);

        return $name;
    }
}
