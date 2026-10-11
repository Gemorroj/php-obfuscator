<?php

namespace PhpObfuscator\Module;

use PhpObfuscator\ModuleInterface;
use PhpObfuscator\PhpObfuscator;

/**
 * Модуль лицензирования кода.
 *
 * ! Вызывать в конце pipeline
 * Создаёт в начале кода строку $license = '';
 * Если в строку передана не действительная лицензия - код не будет работать
 */
class License implements ModuleInterface
{
    private bool $firstCall = true;

    private int $skippedTextLength = 0;

    /**
     * Глубина вложенности скобок при вставке периодической проверки.
     */
    private int $depth = 0;

    private ?int $namespacePosition = null;

    /**
     * @var string код, вызываемый для проверки лицензии
     */
    private string $licenseBreakout;
    /**
     * @var string код, находящийся в начале кода (первая проверка лицензии)
     */
    private string $licenseVerifier;

    /**
     * @param string            $key                Секретный ключ
     * @param string            $invalidLicense     код, вызываемый при окончании лицензии
     * @param ModuleInterface[] $obfuscatorPipeline модули объекта PhpObfuscator, которым будут обработаны вставки кода проверки лицензии
     * @param int               $verifierFrequency  соотношение длины кода к длине модуля проверки лицензии
     */
    public function __construct(
        private readonly string $key = '',
        string $invalidLicense = 'die(\'Your license is invalid\');',
        string $licenseTemplate = 'license goes here',
        array $obfuscatorPipeline = [new StringEncoder(), new SilentCalls()],
        private readonly int $verifierFrequency = 7,
    ) {
        if ('' === $this->key) {
            throw new \InvalidArgumentException('License key must not be empty.');
        }

        [$globalKey, $expireVar, $hashVar] = $this->getRandomStrings(3);
        $licenseVar = 'license';

        $escapedKey = \addslashes($this->key);
        $keyRepeat = (int) \ceil(15 / \strlen($this->key));

        $breakout = '$'.$expireVar.'=hexdec(explode(\'-\',$GLOBALS[\''.$globalKey.'\']??\'\')[1]??\'0\');'
            .'$'.$hashVar.'=substr(md5($'.$expireVar.'.\''.$escapedKey.'\'),0,15);'
            .'if(time()>$'.$expireVar.'||strtoupper($'.$hashVar.'.\'-\'.dechex($'.$expireVar.').\'-\''
            .'.substr(md5($'.$hashVar.'^str_repeat(\''.$escapedKey.'\','.$keyRepeat.')),0,10))'
            .'!=$GLOBALS[\''.$globalKey.'\']){'.$invalidLicense.'}';

        $verifier = '(function($'.$licenseVar.'){$GLOBALS[\''.$globalKey.'\']=$'.$licenseVar.';'
            .$breakout.'})($'.$licenseVar.');unset($'.$licenseVar.');';

        $this->licenseBreakout = \substr($this->obfuscateSnippet($breakout, $obfuscatorPipeline), 6);
        $this->licenseVerifier = \substr($this->obfuscateSnippet($verifier, $obfuscatorPipeline), 6);
        $this->licenseVerifier = "\n$".$licenseVar."='$licenseTemplate';\n".$this->licenseVerifier;
    }

    public function process(string|array $token, array $tokens, int $position): string
    {
        if (0 === $position) {
            $this->firstCall = true;
            $this->skippedTextLength = 0;
            $this->depth = 0;
            $this->namespacePosition = $this->findNamespace($tokens);
        }

        if (!$this->firstCall) {
            $return = \is_array($token) ? $token[1] : $token;

            $this->updateDepth($token);

            $this->skippedTextLength += \strlen($return);

            if (';' === $return
                && 0 === $this->depth
                && $this->skippedTextLength >= \strlen($this->licenseBreakout) * $this->verifierFrequency
            ) {
                $this->skippedTextLength = 0;

                $return .= $this->licenseBreakout;
            }

            return $return;
        }

        if (null !== $this->namespacePosition) {
            if (';' === $token && $position > $this->namespacePosition) {
                $this->firstCall = false;

                return ';'.$this->licenseVerifier;
            }

            return \is_array($token) ? $token[1] : $token;
        }

        $this->firstCall = false;

        return '<?php'.$this->licenseVerifier;
    }

    /**
     * Получение ключа лицензии.
     *
     * @param int $expireTimestamp - временная метка, до которой действует лицензия
     *
     * @return string - возвращает лицензионный ключ
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
     *
     * @param string $license - лицензионный ключ
     */
    public function checkLicense(string $license): bool
    {
        $expireTimestamp = (int) \hexdec(\explode('-', $license)[1] ?? '0');

        return $expireTimestamp > \time() && $this->getLicense($expireTimestamp) === $license;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function findNamespace(array $tokens): ?int
    {
        foreach ($tokens as $position => $token) {
            if (\is_array($token) && \T_NAMESPACE === $token[0]) {
                return $position;
            }
        }

        return null;
    }

    /**
     * @param array{0: int, 1: string, 2: int}|string $token
     */
    private function updateDepth(string|array $token): void
    {
        if (\is_array($token)) {
            if (\in_array($token[0], [\T_CURLY_OPEN, \T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                ++$this->depth;
            }

            return;
        }

        $this->depth += match ($token) {
            '(', '[', '{' => 1,
            ')', ']', '}' => -1,
            default => 0,
        };
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
     * Получение набора уникальных случайных строк.
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
     * Получение рандомной строки. Первый символ всегда буква или "_",
     * чтобы строка была валидным именем переменной.
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
