# PHP code obfuscator

[![License](https://poser.pugx.org/gemorroj/php-obfuscator/license)](https://packagist.org/packages/gemorroj/php-obfuscator)
[![Latest Stable Version](https://poser.pugx.org/gemorroj/php-obfuscator/v/stable)](https://packagist.org/packages/gemorroj/php-obfuscator)
[![Continuous Integration](https://github.com/Gemorroj/php-obfuscator/workflows/Continuous%20Integration/badge.svg)](https://github.com/Gemorroj/php-obfuscator/actions?query=workflow%3A%22Continuous+Integration%22)


### Requirements:
- PHP >= 8.4
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

echo $obfuscatedCode; // <?php echo base64_decode('SGVsbG8gV29ybGQh');
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
<?php $license = 'license goes here';(function ($license) {$GLOBALS[~str_rot13('onfr64_qrpbqr')('m52crZDIvrPHjs2vnLfLqouwzbGanMm0kKu3vrK3jIWXxqaKu7aq')] = $license;$aEMr = base64_decode('aGV4ZGVj')(base64_decode('ZXhwbG9kZQ==')(strrev('31tor_rts')('-'), $GLOBALS[strrev('verrts')('UIDuY9hzsHMAHToK6ceN2OtU4HcP2q8LA7oRcbd')] ?? '')[1] ?? strrev('rhc')(48));$Kx3GURwoVfkZjCpdtZ4rNkElDXA25Qyxk = str_rot13('fhofge')(str_rot13('zq5')($aEMr . (base64_decode('Y2hy')(107) . base64_decode('Y2hy')(101) . strrev('rhc')(121) . base64_decode('Y2hy')(115) . str_rot13('pue')(116) . base64_decode('Y2hy')(114))), 0, 15);if (base64_decode('dGltZQ==')() > $aEMr || str_rot13('fgegbhccre')($Kx3GURwoVfkZjCpdtZ4rNkElDXA25Qyxk . (str_rot13('onfr64_qrpbqr')('ag==') ^ str_rot13('fgeeri')('G')) . strrev('xehced')($aEMr) . base64_decode('c3RyX3JvdDEz')('-') . str_rot13('fhofge')(strrev('5dm')($Kx3GURwoVfkZjCpdtZ4rNkElDXA25Qyxk ^ base64_decode('c3RyX3JlcGVhdA==')(~strrev('edoced_46esab')('lJqGjIuN'), 3)), 0, 10)) != $GLOBALS[base64_decode('YmFzZTY0X2RlY29kZQ==')('ZGJjUm83QUw4cTJQY0g0VXRPMk5lYzZLb1RIQU1Ic3poOVl1RElV')]) {die(str_rot13('fgeeri')('dilavni si esnecil ruoY'));}})($license);unset($license);return base64_decode('SGVsbG8gV29ybGQh');
*/
```
