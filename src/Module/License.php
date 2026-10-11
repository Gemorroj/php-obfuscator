<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;
use PhpObfuscator\PhpObfuscator;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\HaltCompiler;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Модуль лицензирования кода.
 *
 * ! Вызывать в конце pipeline.
 * Создаёт в начале кода строку $license = '';
 * Если в строку передана не действительная лицензия - код не будет работать.
 */
class License extends NodeVisitorAbstract implements ModuleInterface
{
    /**
     * @var string код, вызываемый для проверки лицензии (обфусцированный)
     */
    private string $licenseBreakout;
    /**
     * @var string код первой проверки лицензии (обфусцированный, без присваивания шаблона)
     */
    private string $licenseVerifier;

    private Parser $parser;

    /**
     * @param string            $key                Секретный ключ
     * @param string            $invalidLicense     код, вызываемый при недействительной лицензии
     * @param string            $licenseTemplate    шаблон лицензии в коде
     * @param ModuleInterface[] $obfuscatorPipeline модули, которым будут обработаны вставки кода проверки лицензии
     * @param int               $verifierFrequency  как часто вставлять периодическую проверку (раз в N инструкций)
     */
    public function __construct(
        private readonly string $key = '',
        string $invalidLicense = 'die(\'Your license is invalid\');',
        private readonly string $licenseTemplate = 'license goes here',
        array $obfuscatorPipeline = [new StringEncoder(), new SilentCalls()],
        private readonly int $verifierFrequency = 7,
    ) {
        if ('' === $this->key) {
            throw new \InvalidArgumentException('License key must not be empty.');
        }

        [$globalKey, $expireVar, $hashVar] = $this->getRandomStrings(3);

        $keyLiteral = \var_export($this->key, true);
        $globalKeyLiteral = \var_export($globalKey, true);
        $keyRepeat = (int) \ceil(15 / \strlen($this->key));

        $breakout = '$'.$expireVar.'=hexdec(explode(\'-\',$GLOBALS['.$globalKeyLiteral.']??\'\')[1]??\'0\');'
            .'$'.$hashVar.'=substr(md5($'.$expireVar.'.'.$keyLiteral.'),0,15);'
            .'if(time()>$'.$expireVar.'||strtoupper($'.$hashVar.'.\'-\'.dechex($'.$expireVar.').\'-\''
            .'.substr(md5($'.$hashVar.'^str_repeat('.$keyLiteral.','.$keyRepeat.')),0,10))'
            .'!=$GLOBALS['.$globalKeyLiteral.']){'.$invalidLicense.'}';

        $verifier = '(function($license){$GLOBALS['.$globalKeyLiteral.']=$license;'.$breakout.'})($license);unset($license);';

        $this->licenseBreakout = $this->obfuscateSnippet($breakout, $obfuscatorPipeline);
        $this->licenseVerifier = $this->obfuscateSnippet($verifier, $obfuscatorPipeline);
        $this->parser = new ParserFactory()->createForHostVersion();
    }

    public function beforeTraverse(array $nodes): ?array
    {
        foreach ($nodes as $node) {
            if ($node instanceof Namespace_ && null !== $node->stmts) {
                $this->inject($node->stmts);

                return $nodes;
            }
        }

        $stmts = [];

        foreach ($nodes as $node) {
            if ($node instanceof Stmt) {
                $stmts[] = $node;
            }
        }

        $this->inject($stmts);

        return $stmts;
    }

    /**
     * Получение ключа лицензии.
     *
     * @param int $expireTimestamp - временная метка, до которой действует лицензия
     */
    public function getLicense(int $expireTimestamp): string
    {
        $repeat = (int) \ceil(15 / \strlen($this->key));
        $hash = \substr(\md5($expireTimestamp.$this->key), 0, 15);
        $mask = \substr(\md5($hash ^ \str_repeat($this->key, $repeat)), 0, 10);

        return \strtoupper($hash.'-'.\dechex($expireTimestamp).'-'.$mask);
    }

    /**
     * Проверка лицензии.
     */
    public function checkLicense(string $license): bool
    {
        $expireTimestamp = (int) \hexdec(\explode('-', $license)[1] ?? '0');

        return $expireTimestamp > \time() && $this->getLicense($expireTimestamp) === $license;
    }

    /**
     * @param array<Stmt> $stmts
     *
     * @param-out array<\PhpParser\Node\Stmt> $stmts
     */
    private function inject(array &$stmts): void
    {
        $setup = \array_merge(
            [new Expression(new Assign(new Variable('license'), new String_($this->licenseTemplate)))],
            $this->parser->parse($this->licenseVerifier) ?? [],
        );

        $index = 0;
        while ($index < \count($stmts) && $stmts[$index] instanceof Declare_) {
            ++$index;
        }

        \array_splice($stmts, $index, 0, $setup);

        $breakout = $this->parser->parse($this->licenseBreakout) ?? [];

        if ([] === $breakout || $this->verifierFrequency <= 0) {
            return;
        }

        $setupEnd = $index + \count($setup);
        $result = [];
        $count = 0;

        foreach ($stmts as $position => $stmt) {
            $result[] = $stmt;

            // не вставляем внутрь setup (шаблон + verifier) и служебных инструкций
            if ($position < $setupEnd) {
                continue;
            }

            if ($stmt instanceof HaltCompiler) {
                break;
            }

            if ($stmt instanceof Declare_ || $stmt instanceof Namespace_) {
                continue;
            }

            if (0 === ++$count % $this->verifierFrequency) {
                foreach ($breakout as $breakoutStmt) {
                    $result[] = clone $breakoutStmt;
                }
            }
        }

        $stmts = $result;
    }

    /**
     * @param ModuleInterface[] $obfuscatorPipeline
     */
    private function obfuscateSnippet(string $code, array $obfuscatorPipeline): string
    {
        $phpObfuscator = new PhpObfuscator();

        foreach ($obfuscatorPipeline as $module) {
            $phpObfuscator->addModule($module);
        }

        return $phpObfuscator->obfuscate('<?php '.$code);
    }

    /**
     * Набор уникальных случайных имён (валидных идентификаторов).
     *
     * @return list<string>
     */
    private function getRandomStrings(int $count): array
    {
        $strings = [];

        while (\count($strings) < $count) {
            $string = $this->getRandomString();

            if ('license' === $string || \in_array($string, $strings, true)) {
                continue;
            }

            $strings[] = $string;
        }

        return $strings;
    }

    /**
     * Случайное имя: первый символ — буква или "_".
     */
    private function getRandomString(int $min = 3, int $max = 67): string
    {
        $firstChars = \array_merge(\range('a', 'z'), \range('A', 'Z'), ['_']);
        $otherChars = \array_merge($firstChars, \range('0', '9'));

        $length = \random_int($min, $max);

        $string = $firstChars[\array_rand($firstChars)];

        for ($i = 1; $i < $length; ++$i) {
            $string .= $otherChars[\array_rand($otherChars)];
        }

        return $string;
    }
}
