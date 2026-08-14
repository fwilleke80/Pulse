<?php

/**
 * @file install.php
 * @brief Guided first-run installer for Pulse.
 * @author Frank Willeke
 */

declare(strict_types=1);

use Pulse\Installation\InstallationService;

$projectRoot = dirname(__DIR__);
$composerAutoloader = $projectRoot . '/vendor/autoload.php';

if (is_file($composerAutoloader))
{
	require_once $composerAutoloader;
}
else
{
	spl_autoload_register(function (string $class) use ($projectRoot): void
	{
		$prefix = 'Pulse\\';

		if (!str_starts_with($class, $prefix))
		{
			return;
		}

		$file = $projectRoot . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

		if (is_file($file))
		{
			require_once $file;
		}
	});
}

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'none'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('X-Robots-Tag: noindex, nofollow');

$secureRequest = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
	|| (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;

session_name('pulse_installer');
session_set_cookie_params([
	'lifetime' => 0,
	'path' => '/',
	'secure' => $secureRequest,
	'httponly' => true,
	'samesite' => 'Strict',
]);
session_start();

if (!isset($_SESSION['installer_csrf']) || !is_string($_SESSION['installer_csrf']))
{
	$_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = (string)$_SESSION['installer_csrf'];
$installer = new InstallationService($projectRoot, __FILE__);

/** @brief Escapes a value for HTML output. */
function h(mixed $value): string
{
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @brief Returns a scalar POST value. */
function post_value(string $name, string $fallback = ''): string
{
	$value = $_POST[$name] ?? $fallback;
	return is_string($value) ? $value : $fallback;
}

/** @brief Returns the browser path of this installer, including an installation subdirectory. */
function installer_web_path(): string
{
	$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/install.php'));
	$directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
	return ($directory === '' || $directory === '.') ? '/install.php' : $directory . '/install.php';
}

/** @brief Returns the URL of an installer step. */
function installer_step_url(string $step): string
{
	return installer_web_path() . '?step=' . rawurlencode($step);
}

/** @brief Redirects to another installer step. */
function redirect_step(string $step): never
{
	header('Location: ' . installer_step_url($step));
	exit;
}

/** @brief Returns the public URL suggested by the current installer request. */
function suggested_public_base_url(): string
{
	$scheme = 'http';
	$directHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
		|| (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
	$forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

	if ($directHttps)
	{
		$scheme = 'https';
	}
	elseif (in_array($forwardedProto, ['http', 'https'], true))
	{
		$scheme = $forwardedProto;
	}

	$host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));

	if ($host === '' || preg_match('/^[A-Za-z0-9.\-\[\]:]+$/', $host) !== 1)
	{
		return '';
	}

	$installerPath = installer_web_path();
	$directory = rtrim(str_replace('\\', '/', dirname($installerPath)), '/');
	$path = ($directory === '' || $directory === '.') ? '' : $directory;

	return $scheme . '://' . $host . $path;
}

/** @brief Returns the base URL that should initially appear in the installer field. */
function installer_base_url_default(InstallationService $installer): string
{
	$configured = trim($installer->FileValue('PULSE_BASE_URL'));

	if ($configured !== '' && !in_array($configured, ['https://pulse.example.com', 'http://pulse.example.com'], true))
	{
		return $configured;
	}

	$detected = suggested_public_base_url();
	return $detected !== '' ? $detected : $configured;
}

/** @brief Validates the installer CSRF token. */
function require_csrf(string $expected): void
{
	$provided = post_value('_csrf_token');

	if ($provided === '' || !hash_equals($expected, $provided))
	{
		http_response_code(419);
		throw new RuntimeException('The installation form expired. Reload this page and try again.');
	}
}

/** @brief Returns a selected attribute for a matching option. */
function selected(string $value, string $expected): string
{
	return $value === $expected ? ' selected' : '';
}

/**
 * @brief Renders the standalone installer shell.
 * @param string $title Page title.
 * @param string $step Active step.
 * @param string $content Intentional HTML content.
 * @param bool $showSteps Whether to show progress.
 */
function render_page(string $title, string $step, string $content, bool $showSteps = true): never
{
	$steps = [
		'system' => 'System',
		'database' => 'Database',
		'application' => 'Pulse',
		'administrator' => 'Administrator',
		'mail' => 'Mail',
		'finish' => 'Finish',
	];
	$keys = array_keys($steps);
	$currentIndex = array_search($step, $keys, true);
	$currentIndex = is_int($currentIndex) ? $currentIndex : 0;
	?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?= h($title) ?> · Pulse</title>
	<link rel="icon" href="/favicon.png">
	<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="installer-body">
	<main class="installer-shell">
		<header class="installer-header">
			<img class="installer-logo" src="/assets/logo.png" alt="Pulse">
			<p class="installer-kicker">Installation</p>
		</header>
		<?php if ($showSteps): ?>
			<ol class="installer-steps" aria-label="Installation progress">
				<?php foreach ($steps as $name => $label): ?>
					<?php $index = array_search($name, $keys, true); ?>
					<li class="<?= $name === $step ? 'is-active' : ((is_int($index) && $index < $currentIndex) ? 'is-complete' : '') ?>">
						<span><?= h((is_int($index) ? $index : 0) + 1) ?></span>
						<strong><?= h($label) ?></strong>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
		<section class="card installer-card">
			<?= $content ?>
		</section>
	</main>
</body>
</html>
	<?php
	exit;
}

$existing = $installer->DetectExistingInstallation();

if (!$installer->IsInProgress() && $existing['installed'])
{
	if ($installer->RemoveInstaller())
	{
		header('Location: /');
		exit;
	}

	$content = '<div class="status-banner status-warning"><strong>Pulse is already installed.</strong><p>The installer did not change your configuration or database, but it could not remove itself automatically.</p></div>'
		. '<h1>Remove the installer</h1><p>Delete <code>public/install.php</code> from the server. Pulse deliberately refuses normal operation while that file is present.</p>';
	render_page('Installer removal required', 'finish', $content, false);
}

if (!$installer->IsInProgress() && $existing['error'] !== '')
{
	$content = '<div class="status-banner status-danger"><strong>Existing configuration could not be verified.</strong><p>' . h($existing['error']) . '</p></div>'
		. '<h1>Installation stopped safely</h1><p>Pulse found database settings but could not verify the configured database. To avoid damaging an existing installation, this installer will not overwrite those settings.</p>'
		. '<p>Fix the existing database configuration or remove the old <code>.env</code> only if you intentionally want to perform a new installation.</p>';
	render_page('Existing configuration detected', 'system', $content, false);
}

if (!$installer->IsInProgress())
{
	try
	{
		$installer->Begin();
	}
	catch (Throwable $throwable)
	{
		render_page('Installer cannot start', 'system', '<div class="status-banner status-danger"><strong>Installer cannot start.</strong><p>' . h($throwable->getMessage()) . '</p></div>', false);
	}
}

$allowedSteps = ['system', 'database', 'application', 'administrator', 'mail', 'finish'];
$requestedStep = isset($_GET['step']) && is_string($_GET['step']) ? $_GET['step'] : '';
$suggestedStep = $installer->SuggestedStep();
$step = in_array($requestedStep, $allowedSteps, true) ? $requestedStep : $suggestedStep;
$order = array_flip($allowedSteps);

if (($order[$step] ?? 0) > ($order[$suggestedStep] ?? 0))
{
	$step = $suggestedStep;
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')
{
	try
	{
		require_csrf($csrfToken);
		$action = post_value('action');
		$allowedActions = match ($step)
		{
			'system' => ['system'],
			'database' => ['database'],
			'application' => ['application'],
			'administrator' => ['administrator'],
			'mail' => ['mail_skip', 'mail_save'],
			default => [],
		};

		if (!in_array($action, $allowedActions, true))
		{
			throw new RuntimeException('Invalid or out-of-order installation action.');
		}

		if ($action === 'system')
		{
			$checks = $installer->SystemChecks();

			if ($installer->HasBlockingSystemFailure($checks))
			{
				throw new RuntimeException('Resolve the failed system checks before continuing.');
			}

			$installer->MarkComplete('system_checked');
			redirect_step('database');
		}

		if ($action === 'database')
		{
			$installer->SaveDatabase([
				'PULSE_DB_HOST' => post_value('PULSE_DB_HOST'),
				'PULSE_DB_PORT' => post_value('PULSE_DB_PORT'),
				'PULSE_DB_DATABASE' => post_value('PULSE_DB_DATABASE'),
				'PULSE_DB_USERNAME' => post_value('PULSE_DB_USERNAME'),
				'PULSE_DB_PASSWORD' => post_value('PULSE_DB_PASSWORD'),
			]);
			redirect_step('application');
		}

		if ($action === 'application')
		{
			$availableLocales = [];

			foreach (glob($projectRoot . '/app/Lang/*.php') ?: [] as $languageFile)
			{
				$availableLocales[] = basename($languageFile, '.php');
			}

			sort($availableLocales, SORT_STRING);
			$installer->SaveApplicationSettings(post_value('PULSE_BASE_URL'), post_value('PULSE_DISPLAY_TIMEZONE'), post_value('PULSE_DEFAULT_LOCALE'), $availableLocales);
			$installer->RunMigrations();
			redirect_step('administrator');
		}

		if ($action === 'administrator')
		{
			$password = post_value('password');

			if ($password !== post_value('password_confirmation'))
			{
				throw new RuntimeException('The administrator passwords do not match.');
			}

			$installer->CreateAdministrator(post_value('display_name'), post_value('email'), $password, $installer->FileValue('PULSE_DEFAULT_LOCALE', 'de'));
			redirect_step('mail');
		}

		if ($action === 'mail_skip')
		{
			$installer->SkipMail();
			redirect_step('finish');
		}

		if ($action === 'mail_save')
		{
			$installer->SaveMail([
				'PULSE_SMTP_HOST' => post_value('PULSE_SMTP_HOST'),
				'PULSE_SMTP_PORT' => post_value('PULSE_SMTP_PORT'),
				'PULSE_SMTP_ENCRYPTION' => post_value('PULSE_SMTP_ENCRYPTION'),
				'PULSE_SMTP_USERNAME' => post_value('PULSE_SMTP_USERNAME'),
				'PULSE_SMTP_PASSWORD' => post_value('PULSE_SMTP_PASSWORD'),
				'PULSE_MAIL_FROM_ADDRESS' => post_value('PULSE_MAIL_FROM_ADDRESS'),
				'PULSE_MAIL_FROM_NAME' => post_value('PULSE_MAIL_FROM_NAME'),
			]);
			redirect_step('finish');
		}
	}
	catch (Throwable $throwable)
	{
		$error = $throwable->getMessage();
		$step = match (post_value('action'))
		{
			'system' => 'system',
			'database' => 'database',
			'application' => 'application',
			'administrator' => 'administrator',
			'mail_skip', 'mail_save' => 'mail',
			default => $step,
		};
	}
}

$errorHtml = $error !== '' ? '<div class="status-banner status-danger"><strong>Could not continue.</strong><p>' . h($error) . '</p></div>' : '';

if ($step === 'system')
{
	$checks = $installer->SystemChecks();
	$blocking = $installer->HasBlockingSystemFailure($checks);
	$rows = '';

	foreach ($checks as $check)
	{
		$statusLabel = $check['status'] === 'ok' ? 'Ready' : ($check['status'] === 'warning' ? 'Warning' : 'Failed');
		$rows .= '<li class="installer-check ' . h($check['status']) . '"><div><strong>' . h($check['label']) . '</strong><span>' . h($check['detail']) . '</span></div><b>' . h($statusLabel) . '</b></li>';
	}

	$content = $errorHtml . '<h1>Welcome to Pulse</h1><p>This installer will create the Pulse configuration, initialize the database, create the first administrator, and then remove itself.</p>'
		. '<h2>System check</h2><ul class="installer-checks">' . $rows . '</ul>'
		. ($blocking ? '<p class="field-hint">Resolve the failed requirements and reload this page.</p>' : '')
		. '<form method="post"><input type="hidden" name="_csrf_token" value="' . h($csrfToken) . '"><input type="hidden" name="action" value="system"><div class="installer-actions"><span></span><button class="btn-primary" type="submit"' . ($blocking ? ' disabled' : '') . '>Continue</button></div></form>';
	render_page('System check', 'system', $content);
}

if ($step === 'database')
{
	$host = post_value('PULSE_DB_HOST', $installer->FileValue('PULSE_DB_HOST', 'localhost'));
	$port = post_value('PULSE_DB_PORT', $installer->FileValue('PULSE_DB_PORT', '3306'));
	$database = post_value('PULSE_DB_DATABASE', $installer->FileValue('PULSE_DB_DATABASE'));
	$username = post_value('PULSE_DB_USERNAME', $installer->FileValue('PULSE_DB_USERNAME'));
	$content = $errorHtml . '<h1>Database</h1><p>Enter the MySQL or MariaDB database that Pulse should use. The database itself must already exist; Pulse will create and update its own tables.</p>'
		. '<form method="post"><input type="hidden" name="_csrf_token" value="' . h($csrfToken) . '"><input type="hidden" name="action" value="database"><div class="form-grid two-column">'
		. '<div><label for="db_host">Host</label><input id="db_host" name="PULSE_DB_HOST" value="' . h($host) . '" autocomplete="off" required><p class="field-hint">Usually <code>localhost</code> or the database host supplied by your provider.</p></div>'
		. '<div><label for="db_port">Port</label><input id="db_port" name="PULSE_DB_PORT" inputmode="numeric" value="' . h($port) . '" required><p class="field-hint">MySQL and MariaDB normally use port 3306.</p></div>'
		. '<div><label for="db_name">Database name</label><input id="db_name" name="PULSE_DB_DATABASE" value="' . h($database) . '" autocomplete="off" required></div>'
		. '<div><label for="db_user">Database username</label><input id="db_user" name="PULSE_DB_USERNAME" value="' . h($username) . '" autocomplete="username" required></div></div>'
		. '<label for="db_password">Database password</label><input id="db_password" type="password" name="PULSE_DB_PASSWORD" autocomplete="new-password"><p class="field-hint">The password is written only to the server-side <code>.env</code> file. If a password is already stored, leave this field empty to keep it.</p>'
		. '<div class="installer-actions"><a class="button-link" href="' . h(installer_step_url('system')) . '">Back</a><button class="btn-primary" type="submit">Test connection and continue</button></div></form>';
	render_page('Database', 'database', $content);
}

if ($step === 'application')
{
	$baseUrl = post_value('PULSE_BASE_URL', installer_base_url_default($installer));
	$timezone = post_value('PULSE_DISPLAY_TIMEZONE', $installer->FileValue('PULSE_DISPLAY_TIMEZONE', 'Europe/Berlin'));
	$locale = post_value('PULSE_DEFAULT_LOCALE', $installer->FileValue('PULSE_DEFAULT_LOCALE', 'de'));
	$timezoneOptions = '';

	foreach (DateTimeZone::listIdentifiers() as $identifier)
	{
		$timezoneOptions .= '<option value="' . h($identifier) . '"' . selected($timezone, $identifier) . '>' . h($identifier) . '</option>';
	}

	$localeLabels = ['de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'it' => 'Italiano'];
	$localeOptions = '';

	foreach (glob($projectRoot . '/app/Lang/*.php') ?: [] as $languageFile)
	{
		$code = basename($languageFile, '.php');
		$localeOptions .= '<option value="' . h($code) . '"' . selected($locale, $code) . '>' . h($localeLabels[$code] ?? $code) . '</option>';
	}

	$content = $errorHtml . '<h1>Pulse settings</h1><p>These are the few installation-level settings Pulse needs before its first normal start.</p>'
		. '<form method="post"><input type="hidden" name="_csrf_token" value="' . h($csrfToken) . '"><input type="hidden" name="action" value="application">'
		. '<label for="base_url">Public base URL</label><input id="base_url" name="PULSE_BASE_URL" type="url" value="' . h($baseUrl) . '" required><p class="field-hint">Detected from the address used to open this installer. Change it only if Pulse will later be available at a different public address. Do not include a trailing slash.</p>'
		. '<div class="form-grid two-column"><div><label for="timezone">Time zone</label><select id="timezone" name="PULSE_DISPLAY_TIMEZONE">' . $timezoneOptions . '</select><p class="field-hint">Used when Pulse displays dates and times to you.</p></div>'
		. '<div><label for="locale">Default language</label><select id="locale" name="PULSE_DEFAULT_LOCALE">' . $localeOptions . '</select><p class="field-hint">Used until a user or contact has a more specific language preference.</p></div></div>'
		. '<div class="status-banner status-info"><strong>Security defaults are automatic.</strong><p>Pulse will configure secure cookies, trusted hosts, login throttling, upload limits, and a strong random cron token. You can review application settings later under Administration.</p></div>'
		. '<div class="installer-actions"><a class="button-link" href="' . h(installer_step_url('database')) . '">Back</a><button class="btn-primary" type="submit">Initialize Pulse</button></div></form>';
	render_page('Pulse settings', 'application', $content);
}

if ($step === 'administrator')
{
	$content = $errorHtml . '<h1>First administrator</h1><p>Create the account that will administer this Pulse installation. Future multi-user support can add ordinary users without changing this administrator role.</p>'
		. '<form method="post"><input type="hidden" name="_csrf_token" value="' . h($csrfToken) . '"><input type="hidden" name="action" value="administrator"><div class="form-grid two-column">'
		. '<div><label for="display_name">Name</label><input id="display_name" name="display_name" value="' . h(post_value('display_name')) . '" autocomplete="name" required></div>'
		. '<div><label for="email">Email address</label><input id="email" type="email" name="email" value="' . h(post_value('email')) . '" autocomplete="email" required></div></div>'
		. '<div class="form-grid two-column"><div><label for="password">Password</label><input id="password" type="password" name="password" autocomplete="new-password" minlength="12" required><p class="field-hint">At least 12 characters.</p></div>'
		. '<div><label for="password_confirmation">Confirm password</label><input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></div></div>'
		. '<div class="installer-actions"><a class="button-link" href="' . h(installer_step_url('application')) . '">Back</a><button class="btn-primary" type="submit">Create administrator</button></div></form>';
	render_page('First administrator', 'administrator', $content);
}

if ($step === 'mail')
{
	$host = post_value('PULSE_SMTP_HOST', $installer->FileValue('PULSE_SMTP_HOST'));
	$port = post_value('PULSE_SMTP_PORT', $installer->FileValue('PULSE_SMTP_PORT', '587'));
	$encryption = post_value('PULSE_SMTP_ENCRYPTION', $installer->FileValue('PULSE_SMTP_ENCRYPTION', 'starttls'));
	$username = post_value('PULSE_SMTP_USERNAME', $installer->FileValue('PULSE_SMTP_USERNAME'));
	$fromAddress = post_value('PULSE_MAIL_FROM_ADDRESS', $installer->FileValue('PULSE_MAIL_FROM_ADDRESS'));
	$fromName = post_value('PULSE_MAIL_FROM_NAME', $installer->FileValue('PULSE_MAIL_FROM_NAME', 'Pulse'));
	$content = $errorHtml . '<h1>Mail delivery</h1><p>Mail is how Pulse sends reminders and eventually contacts the people you selected. Configure SMTP now or skip this step and do it later in Administration → Mail.</p>'
		. '<form method="post"><input type="hidden" name="_csrf_token" value="' . h($csrfToken) . '"><div class="form-grid two-column">'
		. '<div><label for="smtp_host">SMTP host</label><input id="smtp_host" name="PULSE_SMTP_HOST" value="' . h($host) . '" autocomplete="off"></div><div><label for="smtp_port">Port</label><input id="smtp_port" name="PULSE_SMTP_PORT" inputmode="numeric" value="' . h($port) . '"></div>'
		. '<div><label for="smtp_encryption">Encryption</label><select id="smtp_encryption" name="PULSE_SMTP_ENCRYPTION"><option value="starttls"' . selected($encryption, 'starttls') . '>STARTTLS</option><option value="tls"' . selected($encryption, 'tls') . '>TLS</option><option value="none"' . selected($encryption, 'none') . '>None</option></select></div>'
		. '<div><label for="smtp_username">Username</label><input id="smtp_username" name="PULSE_SMTP_USERNAME" value="' . h($username) . '" autocomplete="username"></div></div>'
		. '<label for="smtp_password">Password</label><input id="smtp_password" type="password" name="PULSE_SMTP_PASSWORD" autocomplete="new-password"><p class="field-hint">Passwords are never redisplayed. If SMTP credentials were already saved and the username is unchanged, leave this blank to keep the stored password.</p>'
		. '<div class="form-grid two-column"><div><label for="from_address">Sender address</label><input id="from_address" type="email" name="PULSE_MAIL_FROM_ADDRESS" value="' . h($fromAddress) . '"></div><div><label for="from_name">Sender name</label><input id="from_name" name="PULSE_MAIL_FROM_NAME" value="' . h($fromName) . '"></div></div>'
		. '<p class="field-hint">After installation, use Administration → Mail to send a test message and review the queue.</p>'
		. '<div class="installer-actions"><button class="button-link" type="submit" name="action" value="mail_skip" formnovalidate>Skip for now</button><button class="btn-primary" type="submit" name="action" value="mail_save">Save mail settings</button></div></form>';
	render_page('Mail delivery', 'mail', $content);
}

if ($step === 'finish')
{
	try
	{
		$result = $installer->VerifyInstallation();
		$installer->ClearState();
		$removed = $installer->RemoveInstaller();
		$cronUrl = rtrim($result['base_url'], '/') . '/cron/cron.php?token=' . rawurlencode($result['cron_token']);
		$removeHtml = $removed
			? '<div class="status-banner status-success"><strong>Installer removed automatically.</strong><p><code>public/install.php</code> has been deleted from the server.</p></div>'
			: '<div class="status-banner status-danger"><strong>Delete public/install.php manually.</strong><p>Pulse is installed, but the web server could not remove the installer. Pulse will refuse normal operation until you delete that file from the server.</p></div>';
		$mailHtml = $result['mail_enabled']
			? '<p>Mail delivery is enabled. After logging in, open <strong>Administration → Mail</strong> and send a test message before relying on Pulse.</p>'
			: '<p>Mail delivery is currently disabled. Configure and test it under <strong>Administration → Mail</strong> before activating monitors that depend on notifications.</p>';
		$content = '<div class="status-banner status-success"><strong>Pulse is installed.</strong><p>The configuration, database schema, and administrator account were verified successfully.</p></div>'
			. '<h1>Installation complete</h1><p>Administrator: <strong>' . h($result['administrator_email']) . '</strong></p>' . $mailHtml
			. '<h2>Web cron</h2><p>Configure your hosting provider to request this URL on a regular schedule. Keep the token private.</p><div class="installer-secret"><code>' . h($cronUrl) . '</code></div>'
			. $removeHtml
			. ($removed ? '<div class="installer-actions"><span></span><a class="btn-primary button-link-primary" href="' . h(rtrim($result['base_url'], '/') . '/login') . '">Log in to Pulse</a></div>' : '');
		render_page('Installation complete', 'finish', $content);
	}
	catch (Throwable $throwable)
	{
		$content = '<div class="status-banner status-danger"><strong>Final verification failed.</strong><p>' . h($throwable->getMessage()) . '</p></div><h1>Pulse has not been finalized</h1>'
			. '<p>The installer has deliberately kept itself in place. Return to the relevant step, correct the problem, and continue again.</p><div class="installer-actions"><a class="button-link" href="' . h(installer_step_url($installer->SuggestedStep())) . '">Return to installer</a><span></span></div>';
		render_page('Verification failed', 'finish', $content);
	}
}

render_page('Pulse installation', 'system', '<p>Unknown installation state.</p>');
