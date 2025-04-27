<?php

namespace PhpObfuscator;

interface ModuleInterface
{
    public function process(string|array $token, array $tokens): string;
}
