<?php

namespace PhpObfuscator;

use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\PrettyPrinter\Standard;

/**
 * Минифицирующий pretty printer.
 *
 * Печатает код без переводов строк и отступов, где они не обязательны.
 * Содержимое строк, heredoc/nowdoc и inline HTML сохраняется как есть.
 */
final class MinifiedPrettyPrinter extends Standard
{
    public function __construct()
    {
        parent::__construct();

        $this->newline = '';
        $this->nl = '';
    }

    public function prettyPrintFile(array $stmts): string
    {
        if ([] === $stmts) {
            return '<?php ';
        }

        $print = '<?php '.$this->prettyPrint($stmts);

        if ($stmts[0] instanceof Stmt\InlineHTML) {
            $print = \preg_replace('/^<\?php\s+\?>\r?\n?/', '', $print) ?? $print;
        }

        if ($stmts[\count($stmts) - 1] instanceof Stmt\InlineHTML) {
            $print = \preg_replace('/<\?php$/', '', \rtrim($print)) ?? $print;
        }

        return $print;
    }

    protected function indent(): void
    {
    }

    protected function outdent(): void
    {
    }

    protected function pScalar_String(Scalar\String_ $node): string
    {
        return $this->withContentNewlines(fn (): string => parent::pScalar_String($node));
    }

    protected function pScalar_InterpolatedString(Scalar\InterpolatedString $node): string
    {
        return $this->withContentNewlines(fn (): string => parent::pScalar_InterpolatedString($node));
    }

    /**
     * На время печати строки возвращаем перевод строки: heredoc/nowdoc
     * требуют настоящих новых строк внутри содержимого.
     *
     * @param \Closure(): string $callback
     */
    private function withContentNewlines(\Closure $callback): string
    {
        $nl = $this->nl;
        $newline = $this->newline;
        $this->nl = "\n";
        $this->newline = "\n";

        try {
            return $callback();
        } finally {
            $this->nl = $nl;
            $this->newline = $newline;
        }
    }
}
