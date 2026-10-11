<?php

namespace PhpObfuscator;

use PhpObfuscator\Module\CommentStrip;
use PhpObfuscator\Module\IntegerEncoder;
use PhpObfuscator\Module\License;
use PhpObfuscator\Module\SilentCalls;
use PhpObfuscator\Module\StringEncoder;
use PhpObfuscator\Module\VarOptimizer;
use PhpParser\NodeTraverser;
use PhpParser\Parser;
use PhpParser\ParserFactory;

class PhpObfuscator
{
    /**
     * @var list<ModuleInterface>
     */
    public array $modules = [];

    private Parser $parser;

    private MinifiedPrettyPrinter $printer;

    public function __construct()
    {
        $this->parser = new ParserFactory()->createForHostVersion();
        $this->printer = new MinifiedPrettyPrinter();
    }

    public function obfuscate(string $code): string
    {
        if ([] === $this->modules) {
            return $code;
        }

        $ast = $this->parser->parse($code) ?? [];

        foreach ($this->modules as $module) {
            $traverser = new NodeTraverser();
            $traverser->addVisitor($module);
            $ast = $traverser->traverse($ast);
        }

        return $this->printer->prettyPrintFile($ast);
    }

    public function addDefaultModules(): self
    {
        $this->modules[] = new VarOptimizer();
        $this->modules[] = new SilentCalls();
        $this->modules[] = new StringEncoder();
        $this->modules[] = new IntegerEncoder();
        $this->modules[] = new CommentStrip();

        return $this;
    }

    public function addModule(ModuleInterface $module): self
    {
        foreach ($this->modules as $existedModule) {
            if ($existedModule instanceof License) {
                throw new \LogicException('License module is already in use. You cannot add new modules.');
            }
        }

        $this->modules[] = $module;

        return $this;
    }
}
