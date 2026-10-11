<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Модуль удаления комментариев.
 */
class CommentStrip extends NodeVisitorAbstract implements ModuleInterface
{
    public function leaveNode(Node $node): Node|int|array|null
    {
        $node->setAttribute('comments', []);

        return null;
    }
}
