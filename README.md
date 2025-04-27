# PHP code obfuscator

[![License](https://poser.pugx.org/gemorroj/php-obfuscator/license)](https://packagist.org/packages/gemorroj/php-obfuscator)
[![Latest Stable Version](https://poser.pugx.org/gemorroj/php-obfuscator/v/stable)](https://packagist.org/packages/gemorroj/php-obfuscator)
[![Continuous Integration](https://github.com/Gemorroj/php-obfuscator/workflows/Continuous%20Integration/badge.svg)](https://github.com/Gemorroj/php-obfuscator/actions?query=workflow%3A%22Continuous+Integration%22)


### Requirements:
- PHP >= 8.4
- ext-tokenizer
- ext-zlib


### Installation:
```bash
composer require gemorroj/php-obfuscator
```

### Example

```php
<?php
declare(strict_types=1);

use PhpObfuscator\PhpObfuscator;

$code = '<?php echo "Hello World!";';

$phpObfuscator = new PhpObfuscator();
$phpObfuscator->addDefaultModules();
$obfuscatedCode = $phpObfuscator->obfuscate($code);

echo $obfuscatedCode; // <?php echo~base64_decode('t5qTk5DfqJCNk5ve');
```

### Example with license

```php
<?php
declare(strict_types=1);

use PhpObfuscator\PhpObfuscator;

$code = '<?php return "Hello World!";';
$key = 'keystr';
$licenseObj = new License($key);
$license = $licenseObj->getLicense(\time() + 10000000);

$phpObfuscator = new PhpObfuscator();
$phpObfuscator->addDefaultModules();
$phpObfuscator->addModule($licenseObj);

$obfuscatedCode = $phpObfuscator->obfuscate($code);

echo $obfuscatedCode;
/*
<?php
$license='license goes here';
(function($license){$GLOBALS[(str_rot13('pue')(83).strrev('rhc')(110).base64_decode('Y2hy')(53).base64_decode('Y2hy')(51).base64_decode('Y2hy')(102).str_rot13('pue')(50))]=$license;$c=str_rot13('urkqrp')(@str_rot13('rkcybqr')((base64_decode('YmFzZTY0X2RlY29kZQ==')('GQ==')^(strrev('31tor_rts')('4'))),$GLOBALS[~strrev('edoced_46esab')('rJHKzJnN')])[1]);$D=base64_decode('c3Vic3Ry')(str_rot13('zq5')($c.str_rot13('fgeeri')('rtsyek')),0,15);if(strrev('emit')()>$c||str_rot13('fgegbhccre')($D.(str_rot13('onfr64_qrpbqr')('YA==')^(str_rot13('fgeeri')('M'))).str_rot13('qrpurk')($c).strrev('verrts')('-').strrev('rtsbus')(base64_decode('bWQ1')($D^str_rot13('fge_ercrng')((strrev('edoced_46esab')('JgsdGCMl')^(base64_decode('c3RycmV2')('WWkdnM'))),3)),0,10))!=$GLOBALS[(base64_decode('YmFzZTY0X2RlY29kZQ==')('HiVzCgUH')^(strrev('31tor_rts')('ZXS9p5')))]){die(strrev('31tor_rts')('Lbhe yvprafr vf vainyvq'));}})($license);unset($license);return strrev('!dlroW olleH');
*/
```
