<?php

namespace PhpObfuscator;

use PhpParser\NodeVisitor;

/**
 * Модуль обфускации — посетитель AST (nikic/php-parser).
 */
interface ModuleInterface extends NodeVisitor
{
}
