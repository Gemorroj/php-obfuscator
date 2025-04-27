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
    private int $tokenPosition = -1;

    /**
     * @var string код, вызываемый для проверки лицензии
     */
    private string $licenseBreakout;
    /**
     * @var string код, находящийся в начале кода (первая проверка лицензии)
     */
    private string $licenseVerifier;

    private int $skippedTextLength = 0;

    /**
     * @param string            $key                Секретный ключ
     * @param string            $invalidLicense     код, вызываемый при окончании лицензии
     * @param ModuleInterface[] $obfuscatorPipeline модули объекта PhpObfuscator, которым будут обработаны вставки кода проверки лицензии
     * @param int               $verifyerFrequency  соотношение длины кода к длине модуля проверки лицензии
     */
    public function __construct(
        private readonly string $key = '',
        string $invalidLicense = 'die(\'Your license is invalid\');',
        string $licenseTemplate = 'license goes here',
        array $obfuscatorPipeline = [new StringEncoder(), new SilentCalls()],
        private readonly int $verifyerFrequency = 7,
    ) {
        $chars = \array_merge(\range('a', 'z'), \range('A', 'Z'), ['_']);
        $licenseTags = [
            'license',
            $this->getRandomString(),
            $chars[\array_rand($chars)],
            $chars[\array_rand($chars)],
        ];

        $this->licenseBreakout = "\${$licenseTags[2]}=hexdec(@explode('-',\$GLOBALS['{$licenseTags[1]}'])[1]);\${$licenseTags[3]}=substr(md5(\${$licenseTags[2]}.'".\addslashes($this->key)."'),0,15);if(time()>\${$licenseTags[2]}||strtoupper(\${$licenseTags[3]}.'-'.dechex(\${$licenseTags[2]}).'-'.substr(md5(\${$licenseTags[3]}^str_repeat('".\addslashes($this->key)."',".\ceil(15 / \strlen(\addslashes($this->key))).")),0,10))!=\$GLOBALS['{$licenseTags[1]}']){".$invalidLicense.'}';
        $this->licenseVerifier = "(function(\${$licenseTags[0]}){\$GLOBALS['{$licenseTags[1]}']=\${$licenseTags[0]};".$this->licenseBreakout."})(\${$licenseTags[0]});unset(\${$licenseTags[0]});";

        $phpObfuscatorLicenseVerifier = new PhpObfuscator();
        foreach ($obfuscatorPipeline as $module) {
            $phpObfuscatorLicenseVerifier->addModule($module);
        }
        $this->licenseVerifier = \substr($phpObfuscatorLicenseVerifier->obfuscate('<?php '.$this->licenseVerifier), 6);
        $this->licenseVerifier = "\n\${$licenseTags[0]}='$licenseTemplate';\n".$this->licenseVerifier;

        $phpObfuscatorLicenseBreakout = new PhpObfuscator();
        foreach ($obfuscatorPipeline as $module) {
            $phpObfuscatorLicenseBreakout->addModule($module);
        }
        $this->licenseBreakout = \substr($phpObfuscatorLicenseBreakout->obfuscate('<?php '.$this->licenseBreakout), 6);
    }

    public function process(string|array $token, array $tokens): string
    {
        ++$this->tokenPosition;

        if (!$this->firstCall) {
            $return = \is_array($token) ? $token[1] : $token;

            $this->skippedTextLength += \strlen($return);

            if (';' === $return && $this->skippedTextLength >= \strlen($this->licenseBreakout) * $this->verifyerFrequency) {
                $this->skippedTextLength = 0;

                $return .= $this->licenseBreakout;
            }

            return $return;
        }

        $namespace = false;

        foreach ($tokens as $id => $t) {
            if (\T_NAMESPACE === $t[0]) {
                $namespace = true;

                break;
            }
        }

        if ($namespace) {
            if (';' === $token && $this->tokenPosition > $id) {
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
        $hash = \substr(\md5($expireTimestamp.$this->key), 0, 15);
        $mask = \substr(\md5($hash ^ \str_repeat($this->key, \ceil(15 / \strlen($this->key)))), 0, 10);

        return \strtoupper($hash.'-'.\dechex($expireTimestamp).'-'.$mask);
    }

    /**
     * Проверка лицензии.
     *
     * @param string $license - лицензионный ключ
     */
    public function checkLicense(string $license): bool
    {
        $expireTimestamp = \hexdec(@\explode('-', $license)[1]);

        return $expireTimestamp > \time() && $this->getLicense($expireTimestamp) === $license;
    }

    /**
     * Получение рандомной строки.
     */
    private function getRandomString(int $min = 3, int $max = 67): string
    {
        $chars = \array_merge(\range('a', 'z'), \range('A', 'Z'), \range(0, 9));

        $length = \random_int($min, $max);
        $string = '';

        for ($i = 0; $i < $length; ++$i) {
            $string .= $chars[\array_rand($chars)];
        }

        return $string;
    }
}
