#!/usr/bin/env php
<?php
/** Run: php tests/security/authentication_regression.php (no Composer or database required). */
declare(strict_types=1);

// Minimal production PSR-4 loader also works on PHP 8.3 when Composer's platform
// check requires 8.4; the authentication code under test uses no 8.4-only syntax.
spl_autoload_register(static function (string $class): void {
	if (str_starts_with($class, 'Pulse\\'))
	{
		$path = dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 6)) . '.php';
		if (is_file($path)) { require $path; }
	}
});
require dirname(__DIR__, 2) . '/app/Core/helpers.php';
require dirname(__DIR__) . '/Support/AuthenticationRegressionCases.php';

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$passed = 0;
$failed = 0;
foreach (\Pulse\Tests\Support\AuthenticationRegressionCases::cases() as $name => $test)
{
	try { $test(); ++$passed; echo "PASS $name\n"; }
	catch (Throwable $error) { ++$failed; fwrite(STDERR, "FAIL $name: {$error->getMessage()}\n"); }
}
echo "$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
