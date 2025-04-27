<?php

namespace PhpObfuscator;

use PhpObfuscator\Module\IntegerEncoder;
use PhpObfuscator\Module\License;
use PhpObfuscator\Module\SilentCalls;
use PhpObfuscator\Module\StringEncoder;
use PhpObfuscator\Module\VarOptimizer;
use PhpObfuscator\Module\WhitespaceStrip;

class PhpObfuscator
{
    /**
     * @var ModuleInterface[]
     */
    public array $modules = [];

    public function obfuscate(string $code): string
    {
        $obfuscated = '';
        foreach ($this->modules as $module) {
            $obfuscated = '';
            $tokens = \token_get_all($code);

            /*
             * token: "[char]" or [TOKEN_ID, "[char(s)]", [line]]
             */
            foreach ($tokens as $token) {
                $obfuscated .= $module->process($token, $tokens);
            }

            $code = $obfuscated;
        }

        return $obfuscated;
    }

    public function addDefaultModules(): self
    {
        $this->modules[] = new VarOptimizer();
        $this->modules[] = new SilentCalls();
        $this->modules[] = new StringEncoder();
        $this->modules[] = new IntegerEncoder();
        $this->modules[] = new WhitespaceStrip();

        return $this;
    }

    public function addModule(ModuleInterface $module): self
    {
        foreach ($this->modules as $existedModule) {
            if ($existedModule instanceof License) {
                throw new \Exception('License module is already in use. You cannot add new modules.');
            }
        }

        $this->modules[] = $module;

        return $this;
    }
}
