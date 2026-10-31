<?php

/**
 * @file index.php
 * @brief Administrator-only application settings and mail operations.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<string, mixed> $user */
/** @var string $activeTab */
/** @var array<string, string> $settings */
/** @var array<int, string> $availableLocales */
/** @var array<int, string> $availableTimezones */
/** @var bool $environmentWritable */
/** @var bool $environmentExists */
/** @var array<int, string> $processOverrides */
/** @var array<int, array{tab:string,key:string,type:string}> $configurationIssues */
/** @var bool $mailEnabled */
/** @var array<string, int> $mailQueueCounts */
/** @var array<int, array<string, mixed>> $mailQueueEntries */
/** @var array<string, mixed>|null $latestTestNotification */
/** @var bool $debugEnabled */
/** @var array<string, mixed> $databaseConfig */
/** @var string|null $lastSuccessfulCronRun */
/** @var string|null $cronTokenChangedAt */
/** @var array<int, array<string, mixed>> $failedCronCalls */
/** @var string $cronStatus */
/** @var bool $maintenancePaused */
/** @var array<string,mixed>|null $updatePreflight */
/** @var array<string,mixed>|null $restorePreflight */
/** @var bool $maintenanceCanResume */
/** @var string $base_url */
/** @var string $logLevel */
/** @var string $logSearch */
/** @var int $logPage */
/** @var array<string,mixed>|null $logResult */

/** @var string $section One of overview, configuration, backups, updates. */
/** @var array{server:string,applied:int,total:int,current:bool}|null $databaseSummary */
$tabs = [
	'general' => 'administration.tabs.general',
	'security' => 'administration.tabs.security',
	'files' => 'administration.tabs.files',
	'mail' => 'administration.tabs.mail',
	'cron' => 'administration.tabs.cron',
];
$issueTabs = [];
foreach ($configurationIssues as $issue)
{
	$issueTabs[(string)$issue['tab']] = true;
}
$sectionHeading = $section === 'backups' || $section === 'updates' || $section === 'logs' ? 'administration.section.maintenance' :
	($section === 'configuration' ? 'administration.section.configuration' : 'administration.heading');
ob_start();
?>
<div class="editor-heading">
	<div><h1><?= e__($sectionHeading) ?></h1><p class="form-hint"><?= e__('administration.intro') ?></p></div>
	<?php if ($section === 'overview' || $section === 'configuration'): ?>
		<span class="status-badge status-<?= $configurationIssues === [] ? 'verified' : 'due' ?>"><?= e__($configurationIssues === [] ? 'administration.health.ready' : 'administration.health.attention', ['count' => count($configurationIssues)]) ?></span>
	<?php endif; ?>
</div>
<?php require __DIR__ . '/navigation.php'; ?>
<?php if ($section === 'overview'): ?>
<div class="administration-status-grid" aria-label="<?= e__('administration.overview.status_heading') ?>">
	<div class="administration-status-card">
		<span><?= e__('administration.overview.pulse_version') ?></span>
		<strong><?= e($appVersion) ?></strong>
		<small><?= e__('administration.overview.installed') ?></small>
	</div>
	<div class="administration-status-card">
		<span><?= e__('administration.overview.database') ?></span>
		<strong><?= e__('administration.overview.connected') ?></strong>
		<small><?= e__($databaseSummary !== null && $databaseSummary['current'] ? 'administration.overview.schema_current' : 'administration.overview.schema_attention') ?><?= $databaseSummary !== null ? ' (' . (int)$databaseSummary['applied'] . '/' . (int)$databaseSummary['total'] . ')' : '' ?></small>
	</div>
	<div class="administration-status-card">
		<span><?= e__('administration.overview.cron') ?></span>
		<strong><?= e__($cronStatus === 'recent' ? 'administration.overview.healthy' : ($cronStatus === 'stale' ? 'administration.cron.stale' : 'administration.cron.never')) ?></strong>
		<small><?= $lastSuccessfulCronRun !== null ? e(format_datetime($lastSuccessfulCronRun)) : e__('administration.overview.no_cron_run') ?></small>
	</div>
	<div class="administration-status-card">
		<span><?= e__('administration.overview.maintenance') ?></span>
		<strong><?= e__($maintenancePaused ? 'administration.overview.paused' : 'administration.overview.normal') ?></strong>
		<small><?= e__($maintenancePaused ? 'administration.overview.monitoring_paused' : 'administration.overview.monitoring_active') ?></small>
	</div>
</div>
<?php if ($configurationIssues !== []): ?>
<div class="administration-health-summary" role="status">
	<strong><?= e__('administration.health.heading') ?></strong>
	<ul><?php foreach ($configurationIssues as $issue): ?>
		<li><a href="<?= e($base_url) ?>/administration/configuration?tab=<?= e((string)$issue['tab']) ?>"><?= e__((string)$issue['key']) ?></a></li>
	<?php endforeach; ?></ul>
</div>
<?php endif; ?>
<details class="administration-system-details">
	<summary><?= e__('administration.overview.technical_details') ?></summary>
	<section class="administration-page-panel">
		<div class="section-heading"><h2><?= e__('administration.installation.heading') ?></h2><p><?= e__('administration.installation.hint') ?></p></div>
		<div class="configuration-block">
			<h3><?= e__('administration.section.system') ?></h3>
			<dl class="administration-definition-list">
				<div><dt><?= e__('administration.preflight.version') ?></dt><dd><?= e($appVersion) ?></dd></div>
				<div><dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd></div>
				<div><dt><?= e__('administration.overview.db_engine') ?></dt><dd><?= e((string)($databaseSummary['server'] ?? '—')) ?></dd></div>
				<div><dt><?= e__('administration.overview.schema_migrations') ?></dt><dd><?= $databaseSummary !== null ? (int)$databaseSummary['applied'] . ' / ' . (int)$databaseSummary['total'] : '—' ?></dd></div>
			</dl>
		</div>
		<div class="configuration-block">
			<h3><?= e__('administration.installation.env_heading') ?></h3>
			<dl class="administration-definition-list">
				<div><dt><?= e__('administration.installation.env_exists') ?></dt><dd><?= e__($environmentExists ? 'administration.value.yes' : 'administration.value.no') ?></dd></div>
				<div><dt><?= e__('administration.installation.env_writable') ?></dt><dd><?= e__($environmentWritable ? 'administration.value.yes' : 'administration.value.no') ?></dd></div>
			</dl>
			<?php if ($processOverrides !== []): ?><div class="review-warning"><strong><?= e__('administration.installation.overrides_heading') ?></strong><p><?= e__('administration.installation.overrides_hint') ?></p><code><?= e(implode(', ', $processOverrides)) ?></code></div><?php endif; ?>
		</div>

		<div class="configuration-block">
			<h3><?= e__('administration.installation.base_url_heading') ?></h3>
			<p><?= e__('administration.installation.base_url_hint') ?></p>
			<dl class="administration-definition-list">
				<div><dt><?= e__('administration.field.base_url') ?></dt><dd><code><?= e($settings['PULSE_BASE_URL']) ?></code></dd></div>
			</dl>
		</div>

		<div class="configuration-block">
			<h3><?= e__('administration.installation.database_heading') ?></h3>
			<p><?= e__('administration.installation.database_hint') ?></p>
			<dl class="administration-definition-list">
				<div><dt><?= e__('administration.installation.database_host') ?></dt><dd><code><?= e((string)($databaseConfig['host'] ?? '')) ?></code></dd></div>
				<div><dt><?= e__('administration.installation.database_port') ?></dt><dd><code><?= (int)($databaseConfig['port'] ?? 0) ?></code></dd></div>
				<div><dt><?= e__('administration.installation.database_name') ?></dt><dd><code><?= e((string)($databaseConfig['database'] ?? '')) ?></code></dd></div>
				<div><dt><?= e__('administration.installation.database_username') ?></dt><dd><code><?= e((string)($databaseConfig['username'] ?? '')) ?></code></dd></div>
				<div><dt><?= e__('administration.installation.database_password') ?></dt><dd><?= e__((string)($databaseConfig['password'] ?? '') !== '' ? 'administration.secret.configured' : 'administration.secret.not_configured') ?></dd></div>
			</dl>
		</div>
	</section>
</details>
<?php elseif ($section === 'configuration'): ?>
<?php if ($configurationIssues !== []): ?>
<div class="administration-health-summary" role="status">
	<strong><?= e__('administration.health.heading') ?></strong>
	<ul><?php foreach ($configurationIssues as $issue): ?>
		<li class="configuration-issue configuration-issue-<?= e((string)$issue['type']) ?>"><a href="<?= e($base_url) ?>/administration/configuration?tab=<?= e((string)$issue['tab']) ?>"><?= e__((string)$issue['key']) ?></a></li>
	<?php endforeach; ?></ul>
</div>
<?php endif; ?>
<form id="administration-settings-form" data-administration-configuration method="post" action="<?= e($base_url) ?>/administration/update" class="form-carrier">
	<?= csrf_field() ?>
	<input type="hidden" name="active_tab" value="<?= e($activeTab) ?>" data-active-tab-input>
</form>
<div class="monitor-editor administration-editor" data-monitor-tabs data-active-tab="<?= e($activeTab) ?>">
	<div class="monitor-tabs monitor-primary-tabs administration-configuration-tabs" role="tablist" aria-label="<?= e__('administration.tabs.label') ?>">
		<?php $tabNumber = 0; ?>
		<?php foreach ($tabs as $tabName => $translationKey): ?>
			<?php $tabNumber++; ?>
			<a href="<?= e($base_url) ?>/administration/configuration?tab=<?= e($tabName) ?>"
			   class="monitor-tab-link<?= $activeTab === $tabName ? ' is-active' : '' ?>"
			   role="tab" data-tab-target="<?= e($tabName) ?>"
			   aria-controls="administration-tab-<?= e($tabName) ?>"
			   aria-selected="<?= $activeTab === $tabName ? 'true' : 'false' ?>"
			   tabindex="<?= $activeTab === $tabName ? '0' : '-1' ?>">
				<span class="tab-number"><?= $tabNumber ?></span>
				<span class="tab-label"><?= e__($translationKey) ?></span>
				<?php if (isset($issueTabs[$tabName])): ?>
					<span class="tab-warning-indicator" title="<?= e__('administration.tabs.configuration_warning') ?>" aria-label="<?= e__('administration.tabs.configuration_warning') ?>">!</span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
	<section id="administration-tab-general" class="monitor-tab-panel<?= $activeTab === 'general' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="general"<?= $activeTab === 'general' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('administration.general.heading') ?></h2>
			<p><?= e__('administration.general.hint') ?></p>
		</div>

		<div class="field-grid field-grid-two">
			<label>
				<?= e__('administration.field.environment') ?>
				<select name="PULSE_ENV" form="administration-settings-form">
					<?php foreach (['production', 'development', 'testing'] as $environment): ?>
						<option value="<?= e($environment) ?>"<?= $settings['PULSE_ENV'] === $environment ? ' selected' : '' ?>><?= e($environment) ?></option>
					<?php endforeach; ?>
				</select>
				<small><?= e__('administration.field.environment_hint') ?></small>
			</label>
			<label>
				<?= e__('administration.field.default_locale') ?>
				<select name="PULSE_DEFAULT_LOCALE" form="administration-settings-form">
					<?php foreach ($availableLocales as $localeOption): ?>
						<option value="<?= e($localeOption) ?>"<?= $settings['PULSE_DEFAULT_LOCALE'] === $localeOption ? ' selected' : '' ?>><?= e(language_name($localeOption)) ?></option>
					<?php endforeach; ?>
				</select>
				<small><?= e__('administration.field.default_locale_hint') ?></small>
			</label>
		</div>

		<label>
			<?= e__('administration.field.display_timezone') ?>
			<select name="PULSE_DISPLAY_TIMEZONE" form="administration-settings-form">
				<?php foreach ($availableTimezones as $timezoneOption): ?>
					<option value="<?= e($timezoneOption) ?>"<?= $settings['PULSE_DISPLAY_TIMEZONE'] === $timezoneOption ? ' selected' : '' ?>><?= e($timezoneOption) ?></option>
				<?php endforeach; ?>
			</select>
			<small><?= e__('administration.field.display_timezone_hint') ?></small>
		</label>

		<label>
			<?= e__('administration.field.trusted_hosts') ?>
			<input type="text" name="PULSE_TRUSTED_HOSTS" form="administration-settings-form" value="<?= e($settings['PULSE_TRUSTED_HOSTS']) ?>" placeholder="pulse.example.com">
			<small><?= e__('administration.field.trusted_hosts_hint') ?></small>
		</label>

		<div class="checkbox-row">
			<label>
				<input type="checkbox" name="PULSE_DEBUG" form="administration-settings-form" value="1"<?= $settings['PULSE_DEBUG'] === 'true' ? ' checked' : '' ?>>
				<?= e__('administration.field.debug') ?>
			</label>
			<small><?= e__('administration.field.debug_hint') ?></small>
		</div>
	</section>

	<section id="administration-tab-security" class="monitor-tab-panel<?= $activeTab === 'security' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="security"<?= $activeTab === 'security' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('administration.security.heading') ?></h2>
			<p><?= e__('administration.security.hint') ?></p>
		</div>

		<div class="field-grid field-grid-two">
			<label>
				<?= e__('administration.field.session_name') ?>
				<input type="text" name="PULSE_SESSION_NAME" form="administration-settings-form" value="<?= e($settings['PULSE_SESSION_NAME']) ?>" required>
				<small><?= e__('administration.field.session_name_hint') ?></small>
			</label>
			<label>
				<?= e__('administration.field.cookie_samesite') ?>
				<select name="PULSE_COOKIE_SAMESITE" form="administration-settings-form">
					<?php foreach (['Strict', 'Lax', 'None'] as $sameSite): ?>
						<option value="<?= e($sameSite) ?>"<?= $settings['PULSE_COOKIE_SAMESITE'] === $sameSite ? ' selected' : '' ?>><?= e($sameSite) ?></option>
					<?php endforeach; ?>
				</select>
				<small><?= e__('administration.field.cookie_samesite_hint') ?></small>
			</label>
		</div>

		<div class="field-grid field-grid-three">
			<label><?= e__('administration.field.session_idle') ?><input type="number" min="300" name="PULSE_SESSION_IDLE_TIMEOUT" form="administration-settings-form" value="<?= e($settings['PULSE_SESSION_IDLE_TIMEOUT']) ?>" required><small><?= e__('administration.field.session_idle_hint') ?></small></label>
			<label><?= e__('administration.field.session_absolute') ?><input type="number" min="1800" name="PULSE_SESSION_ABSOLUTE_TIMEOUT" form="administration-settings-form" value="<?= e($settings['PULSE_SESSION_ABSOLUTE_TIMEOUT']) ?>" required><small><?= e__('administration.field.session_absolute_hint') ?></small></label>
			<label><?= e__('administration.field.session_regeneration') ?><input type="number" min="60" name="PULSE_SESSION_REGENERATION_INTERVAL" form="administration-settings-form" value="<?= e($settings['PULSE_SESSION_REGENERATION_INTERVAL']) ?>" required><small><?= e__('administration.field.session_regeneration_hint') ?></small></label>
		</div>

		<div class="configuration-block">
			<h3><?= e__('administration.security.passkeys.heading') ?></h3>
			<div class="checkbox-row">
				<label><input type="checkbox" name="PULSE_PASSKEY_QUICK_CHECKIN_ENABLED" form="administration-settings-form" value="1"<?= $settings['PULSE_PASSKEY_QUICK_CHECKIN_ENABLED'] === 'true' ? ' checked' : '' ?>><?= e__('administration.field.passkey_quick_checkin') ?></label>
				<small><?= e__('administration.field.passkey_quick_checkin_hint') ?></small>
			</div>
		</div>

		<div class="field-grid field-grid-two">
			<div class="checkbox-row">
				<label><input type="checkbox" name="PULSE_COOKIE_SECURE" form="administration-settings-form" value="1"<?= $settings['PULSE_COOKIE_SECURE'] === 'true' ? ' checked' : '' ?>><?= e__('administration.field.cookie_secure') ?></label>
				<small><?= e__('administration.field.cookie_secure_hint') ?></small>
			</div>
			<div class="checkbox-row">
				<label><input type="checkbox" name="PULSE_HSTS_ENABLED" form="administration-settings-form" value="1"<?= $settings['PULSE_HSTS_ENABLED'] === 'true' ? ' checked' : '' ?>><?= e__('administration.field.hsts') ?></label>
				<small><?= e__('administration.field.hsts_hint') ?></small>
			</div>
		</div>

		<h3 class="subsection-heading"><?= e__('administration.security.login_heading') ?></h3>
		<div class="field-grid field-grid-four">
			<label><?= e__('administration.field.login_attempts') ?><input type="number" min="2" max="50" name="PULSE_LOGIN_MAX_ATTEMPTS" form="administration-settings-form" value="<?= e($settings['PULSE_LOGIN_MAX_ATTEMPTS']) ?>" required><small><?= e__('administration.field.login_attempts_hint') ?></small></label>
			<label><?= e__('administration.field.login_window') ?><input type="number" min="60" name="PULSE_LOGIN_WINDOW_SECONDS" form="administration-settings-form" value="<?= e($settings['PULSE_LOGIN_WINDOW_SECONDS']) ?>" required><small><?= e__('administration.field.login_window_hint') ?></small></label>
			<label><?= e__('administration.field.login_block') ?><input type="number" min="60" name="PULSE_LOGIN_BLOCK_SECONDS" form="administration-settings-form" value="<?= e($settings['PULSE_LOGIN_BLOCK_SECONDS']) ?>" required><small><?= e__('administration.field.login_block_hint') ?></small></label>
			<label><?= e__('administration.field.password_minimum') ?><input type="number" min="8" max="72" name="PULSE_PASSWORD_MINIMUM_LENGTH" form="administration-settings-form" value="<?= e($settings['PULSE_PASSWORD_MINIMUM_LENGTH']) ?>" required><small><?= e__('administration.field.password_minimum_hint') ?></small></label>
		</div>
<section class="configuration-block">
<h2><?= e__('accounts.settings') ?></h2>
<p><?= e__('accounts.settings_hint') ?></p>
<form method="post" action="<?= e($base_url) ?>/administration/users/settings" class="stack">
<?= csrf_field() ?>
<label><input type="checkbox" name="registration_enabled" value="1"<?= !empty($registrationSettings['registration_enabled']) ? ' checked' : '' ?>> <?= e__('accounts.public_registration') ?></label>
<label><input type="checkbox" name="registration_requires_approval" value="1"<?= !empty($registrationSettings['registration_requires_approval']) ? ' checked' : '' ?>> <?= e__('accounts.approval_required') ?></label>
<button type="submit"><?= e__('accounts.save') ?></button>
</form>
</section>
	</section>

	<section id="administration-tab-files" class="monitor-tab-panel<?= $activeTab === 'files' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="files"<?= $activeTab === 'files' ? '' : ' hidden' ?>>
		<div class="section-heading">
			<h2><?= e__('administration.files.heading') ?></h2>
			<p><?= e__('administration.files.hint') ?></p>
		</div>

		<label>
			<?= e__('administration.field.upload_maximum') ?>
			<input type="number" min="1024" name="PULSE_UPLOAD_MAXIMUM_BYTES" form="administration-settings-form" value="<?= e($settings['PULSE_UPLOAD_MAXIMUM_BYTES']) ?>" required>
			<small><?= e__('administration.field.upload_maximum_hint') ?></small>
		</label>

		<label>
			<?= e__('administration.field.upload_mime_types') ?>
			<textarea name="PULSE_UPLOAD_ALLOWED_MIME_TYPES" form="administration-settings-form" rows="5" required><?= e($settings['PULSE_UPLOAD_ALLOWED_MIME_TYPES']) ?></textarea>
			<small><?= e__('administration.field.upload_mime_types_hint') ?></small>
		</label>
	</section>

	<section id="administration-tab-mail" class="monitor-tab-panel<?= $activeTab === 'mail' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="mail"<?= $activeTab === 'mail' ? '' : ' hidden' ?>>
		<div class="section-title-row">
			<div class="section-heading">
				<h2><?= e__('administration.mail.heading') ?></h2>
				<p><?= e__('administration.mail.hint') ?></p>
			</div>
			<span class="status-badge status-mail-<?= $mailEnabled ? 'enabled' : 'disabled' ?>"><?= e__($mailEnabled ? 'profile.notifications.enabled' : 'profile.notifications.disabled') ?></span>
		</div>

		<div class="checkbox-row">
			<label><input type="checkbox" name="PULSE_MAIL_ENABLED" form="administration-settings-form" value="1"<?= $settings['PULSE_MAIL_ENABLED'] === 'true' ? ' checked' : '' ?>><?= e__('administration.field.mail_enabled') ?></label>
			<small><?= e__('administration.field.mail_enabled_hint') ?></small>
		</div>

		<div class="field-grid field-grid-three">
			<label><?= e__('administration.field.smtp_host') ?><input type="text" name="PULSE_SMTP_HOST" form="administration-settings-form" value="<?= e($settings['PULSE_SMTP_HOST']) ?>"><small><?= e__('administration.field.smtp_host_hint') ?></small></label>
			<label><?= e__('administration.field.smtp_port') ?><input type="number" min="1" max="65535" name="PULSE_SMTP_PORT" form="administration-settings-form" value="<?= e($settings['PULSE_SMTP_PORT']) ?>" required><small><?= e__('administration.field.smtp_port_hint') ?></small></label>
			<label><?= e__('administration.field.smtp_encryption') ?><select name="PULSE_SMTP_ENCRYPTION" form="administration-settings-form"><?php foreach (['starttls', 'tls', 'none'] as $encryption): ?><option value="<?= e($encryption) ?>"<?= $settings['PULSE_SMTP_ENCRYPTION'] === $encryption ? ' selected' : '' ?>><?= e(strtoupper($encryption)) ?></option><?php endforeach; ?></select><small><?= e__('administration.field.smtp_encryption_hint') ?></small></label>
		</div>

		<div class="field-grid field-grid-two">
			<label><?= e__('administration.field.smtp_username') ?><input type="text" autocomplete="off" name="PULSE_SMTP_USERNAME" form="administration-settings-form" value="<?= e($settings['PULSE_SMTP_USERNAME']) ?>"><small><?= e__('administration.field.smtp_username_hint') ?></small></label>
			<label>
				<?= e__('administration.field.smtp_password') ?>
				<input type="password" autocomplete="new-password" name="PULSE_SMTP_PASSWORD" form="administration-settings-form" value="" placeholder="<?= e__($settings['PULSE_SMTP_PASSWORD'] === '__configured__' ? 'administration.secret.configured_placeholder' : 'administration.secret.empty_placeholder') ?>">
				<small><?= e__('administration.secret.keep_hint') ?></small>
			</label>
		</div>
		<div class="checkbox-row"><label><input type="checkbox" name="clear_smtp_password" form="administration-settings-form" value="1"><?= e__('administration.secret.clear_smtp') ?></label></div>

		<div class="field-grid field-grid-two">
			<label><?= e__('administration.field.mail_from_address') ?><input type="email" name="PULSE_MAIL_FROM_ADDRESS" form="administration-settings-form" value="<?= e($settings['PULSE_MAIL_FROM_ADDRESS']) ?>"><small><?= e__('administration.field.mail_from_address_hint') ?></small></label>
			<label><?= e__('administration.field.mail_from_name') ?><input type="text" name="PULSE_MAIL_FROM_NAME" form="administration-settings-form" value="<?= e($settings['PULSE_MAIL_FROM_NAME']) ?>"><small><?= e__('administration.field.mail_from_name_hint') ?></small></label>
		</div>

		<h3 class="subsection-heading"><?= e__('administration.mail.delivery_heading') ?></h3>
		<div class="field-grid field-grid-four">
			<label><?= e__('administration.field.smtp_timeout') ?><input type="number" min="2" max="120" name="PULSE_SMTP_TIMEOUT_SECONDS" form="administration-settings-form" value="<?= e($settings['PULSE_SMTP_TIMEOUT_SECONDS']) ?>" required><small><?= e__('administration.field.smtp_timeout_hint') ?></small></label>
			<label><?= e__('administration.field.mail_max_attempts') ?><input type="number" min="1" max="20" name="PULSE_MAIL_MAX_ATTEMPTS" form="administration-settings-form" value="<?= e($settings['PULSE_MAIL_MAX_ATTEMPTS']) ?>" required><small><?= e__('administration.field.mail_max_attempts_hint') ?></small></label>
			<label><?= e__('administration.field.mail_lease') ?><input type="number" min="30" max="1800" name="PULSE_MAIL_LEASE_SECONDS" form="administration-settings-form" value="<?= e($settings['PULSE_MAIL_LEASE_SECONDS']) ?>" required><small><?= e__('administration.field.mail_lease_hint') ?></small></label>
			<label><?= e__('administration.field.mail_batch') ?><input type="number" min="1" max="250" name="PULSE_MAIL_WORKER_BATCH_SIZE" form="administration-settings-form" value="<?= e($settings['PULSE_MAIL_WORKER_BATCH_SIZE']) ?>" required><small><?= e__('administration.field.mail_batch_hint') ?></small></label>
		</div>
		<label><?= e__('administration.field.mail_retry_delays') ?><input type="text" name="PULSE_MAIL_RETRY_DELAYS_SECONDS" form="administration-settings-form" value="<?= e($settings['PULSE_MAIL_RETRY_DELAYS_SECONDS']) ?>" required><small><?= e__('administration.field.mail_retry_delays_hint') ?></small></label>

		<hr>
		<div id="mail-operations" class="stack">
			<h3><?= e__('administration.mail.operations_heading') ?></h3>
			<p><?= e__('profile.notifications.hint') ?></p>

			<div class="notification-status-grid">
				<div><span><?= e__('profile.notifications.queue.pending') ?></span><strong><?= (int)$mailQueueCounts['queued'] + (int)$mailQueueCounts['retrying'] + (int)$mailQueueCounts['processing'] ?></strong></div>
				<div><span><?= e__('profile.notifications.queue.failed') ?></span><strong><?= (int)$mailQueueCounts['failed'] ?></strong></div>
				<div><span><?= e__('profile.notifications.queue.sent') ?></span><strong><?= (int)$mailQueueCounts['sent'] ?></strong></div>
			</div>

			<?php if (is_array($latestTestNotification)): ?>
				<div class="notification-last-test">
					<strong><?= e__('profile.notifications.last_test') ?></strong>
					<span class="status-badge status-mail-<?= e((string)$latestTestNotification['status']) ?>"><?= e__('profile.notifications.status.' . (string)$latestTestNotification['status']) ?></span>
					<time datetime="<?= e((string)$latestTestNotification['created_at']) ?>"><?= e(format_datetime((string)$latestTestNotification['created_at'])) ?></time>
					<?php if (!empty($latestTestNotification['last_error'])): ?><small><?= e((string)$latestTestNotification['last_error']) ?></small><?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ($mailEnabled): ?>
				<p><?= e__('profile.notifications.test.hint') ?></p>
				<form method="post" action="<?= e($base_url) ?>/administration/mail/test"><?= csrf_field() ?><button type="submit"><?= e__('profile.notifications.test.submit') ?></button></form>
			<?php else: ?>
				<div class="notification-disabled-warning" id="mail-disabled-help" role="alert"><strong><?= e__('administration.mail.disabled.heading') ?></strong><span><?= e__('administration.mail.disabled.message') ?></span></div>
				<button type="button" disabled aria-describedby="mail-disabled-help"><?= e__('profile.notifications.test.submit') ?></button>
			<?php endif; ?>

			<?php if ((int)$mailQueueCounts['failed'] > 0): ?>
				<form method="post" action="<?= e($base_url) ?>/administration/mail/retry"><?= csrf_field() ?><button type="submit" class="btn-secondary"<?= $mailEnabled ? '' : ' disabled' ?>><?= e__('profile.notifications.retry.submit') ?></button></form>
			<?php endif; ?>

			<?php $activeQueueCount = (int)$mailQueueCounts['queued'] + (int)$mailQueueCounts['retrying'] + (int)$mailQueueCounts['processing'] + (int)$mailQueueCounts['failed']; ?>
			<details class="mail-queue-panel" id="mail-queue"<?= $activeQueueCount > 0 ? ' open' : '' ?>>
				<summary><?= e__('profile.notifications.queue_details.heading', ['count' => count($mailQueueEntries)]) ?></summary>
				<p class="muted"><?= e__('administration.mail.queue_hint') ?></p>
				<?php if ($mailQueueEntries === []): ?>
					<p><?= e__('profile.notifications.queue_details.empty') ?></p>
				<?php else: ?>
					<div class="table-scroll"><table class="mail-queue-table"><thead><tr>
						<th><?= e__('profile.notifications.queue_details.id') ?></th><th><?= e__('profile.notifications.queue_details.time') ?></th><th><?= e__('profile.notifications.queue_details.type') ?></th><th><?= e__('profile.notifications.queue_details.recipient') ?></th><th><?= e__('profile.notifications.queue_details.status') ?></th><th><?= e__('profile.notifications.queue_details.attempts') ?></th><th><?= e__('profile.notifications.queue_details.next_attempt') ?></th><th><?= e__('profile.notifications.queue_details.last_error') ?></th>
					</tr></thead><tbody>
					<?php foreach ($mailQueueEntries as $queueEntry): ?>
						<?php $status = (string)$queueEntry['status']; $typeKey = 'profile.notifications.mail_type.' . (string)$queueEntry['mail_type']; $isWaiting = in_array($status, ['queued', 'retrying', 'processing'], true); ?>
						<tr>
							<td><code>#<?= (int)$queueEntry['id'] ?></code></td><td><time datetime="<?= e((string)$queueEntry['created_at']) ?>"><?= e(format_datetime((string)$queueEntry['created_at'])) ?></time></td><td><?= e__($typeKey) ?></td><td><code><?= e((string)$queueEntry['recipient_email']) ?></code></td>
							<td><?php if ($status === 'retrying'): ?><span class="status-badge status-mail-retrying"><?= e__('profile.notifications.status.retrying_wait', ['wait' => format_retry_wait((string)$queueEntry['available_at'])]) ?></span><?php elseif ($status === 'failed'): ?><span class="status-badge status-mail-failed"><?= e__('profile.notifications.status.failed_terminal') ?></span><?php else: ?><span class="status-badge status-mail-<?= e($status) ?>"><?= e__('profile.notifications.status.' . $status) ?></span><?php endif; ?></td>
							<td><?= (int)$queueEntry['attempt_count'] ?> / <?= (int)$queueEntry['max_attempts'] ?></td>
							<td><?php if ($isWaiting): ?><time datetime="<?= e((string)$queueEntry['available_at']) ?>"><?= e(format_datetime((string)$queueEntry['available_at'])) ?></time><?php else: ?><span aria-hidden="true">—</span><?php endif; ?></td>
							<td class="mail-queue-error"><?php if (!empty($queueEntry['last_error'])): ?><details><summary><?= e__('profile.notifications.queue_details.show_error') ?></summary><small><?= e((string)$queueEntry['last_error']) ?></small></details><?php else: ?><span aria-hidden="true">—</span><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody></table></div>
				<?php endif; ?>

				<?php if ($debugEnabled): ?>
					<div class="debug-queue-controls"><strong><?= e__('profile.notifications.clear.heading') ?></strong><p><?= e__('profile.notifications.clear.hint') ?></p><form method="post" action="<?= e($base_url) ?>/administration/mail/clear" data-confirm="<?= e__('profile.notifications.clear.confirm') ?>"><?= csrf_field() ?><button type="submit" class="btn-danger"><?= e__('profile.notifications.clear.submit') ?></button></form></div>
				<?php endif; ?>
			</details>
		</div>
	</section>

	<section id="administration-tab-cron" class="monitor-tab-panel<?= $activeTab === 'cron' ? ' is-active' : '' ?>" role="tabpanel" data-tab-panel="cron"<?= $activeTab === 'cron' ? '' : ' hidden' ?>>
		<div class="section-heading"><h2><?= e__('administration.cron.heading') ?></h2><p><?= e__('administration.cron.hint') ?></p></div>
		<label>
			<?= e__('administration.field.cron_token') ?>
			<input type="password" autocomplete="new-password" name="PULSE_CRON_TOKEN" form="administration-settings-form" value="" data-cron-token placeholder="<?= e__($settings['PULSE_CRON_TOKEN'] === '__configured__' ? 'administration.secret.configured_placeholder' : 'administration.secret.empty_placeholder') ?>">
			<small><?= e__('administration.secret.keep_hint') ?></small>
		</label>
		<div class="field-grid field-grid-two">
			<div class="cron-token-generator"><button type="button" class="btn-secondary" data-generate-cron-token><?= e__('administration.secret.generate_cron') ?></button><small class="form-hint"><?= e__('administration.secret.generate_cron_hint') ?></small></div>
			<div class="checkbox-row"><label><input type="checkbox" name="clear_cron_token" form="administration-settings-form" value="1"><?= e__('administration.secret.clear_cron') ?></label></div>
		</div>
		<div class="configuration-block">
			<h3><?= e__('administration.cron.status_heading') ?></h3>
			<dl class="administration-definition-list">
				<div>
					<dt><?= e__('administration.cron.last_successful') ?></dt>
					<dd>
						<?php if ($lastSuccessfulCronRun === null): ?>
							<strong><?= e__('administration.cron.never') ?></strong>
						<?php else: ?>
							<time datetime="<?= e($lastSuccessfulCronRun) ?>"><?= e(format_datetime($lastSuccessfulCronRun)) ?></time>
							<?php if ($cronStatus === 'stale'): ?> <span class="status-badge status-due"><?= e__('administration.cron.stale') ?></span><?php endif; ?>
						<?php endif; ?>
					</dd>
				</div>
				<div>
					<dt><?= e__('administration.cron.token_changed') ?></dt>
					<dd>
						<?php if ($cronTokenChangedAt === null): ?>
							<?= e__('administration.cron.token_changed_unknown') ?>
						<?php else: ?>
							<time datetime="<?= e($cronTokenChangedAt) ?>"><?= e(format_datetime($cronTokenChangedAt)) ?></time>
						<?php endif; ?>
					</dd>
				</div>
			</dl>
			<small class="form-hint"><?= e__('administration.cron.status_hint') ?></small>
		</div>

		<div class="configuration-block">
			<h3><?= e__('administration.cron.failures_heading') ?></h3>
			<p><?= e__('administration.cron.failures_hint') ?></p>
			<?php if ($failedCronCalls === []): ?>
				<p class="form-hint"><?= e__('administration.cron.failures_none') ?></p>
			<?php else: ?>
				<div class="table-scroll"><table class="cron-failure-table"><thead><tr>
					<th><?= e__('administration.cron.failures_time') ?></th>
					<th><?= e__('administration.cron.failures_reason') ?></th>
					<th><?= e__('administration.cron.failures_method') ?></th>
					<th><?= e__('administration.cron.failures_token') ?></th>
				</tr></thead><tbody>
				<?php foreach ($failedCronCalls as $failedCronCall): ?>
					<?php
						$failureCode = (string)($failedCronCall['failure_code'] ?? '');
						$failureTranslationKey = match ($failureCode)
						{
							'invalid_token' => 'administration.cron.failures_reason_invalid_token',
							'invalid_method' => 'administration.cron.failures_reason_invalid_method',
							'token_not_configured' => 'administration.cron.failures_reason_token_not_configured',
							'mail_disabled' => 'administration.cron.failures_reason_mail_disabled',
							'execution_error' => 'administration.cron.failures_reason_execution_error',
							default => 'administration.cron.failures_reason_unknown',
						};
						$tokenFailure = in_array($failureCode, ['invalid_token', 'token_not_configured'], true);
						$failedToken = $failedCronCall['provided_token'] ?? null;
					?>
					<tr>
						<td><time datetime="<?= e((string)$failedCronCall['attempted_at']) ?>"><?= e(format_datetime((string)$failedCronCall['attempted_at'])) ?></time></td>
						<td><?= e__($failureTranslationKey) ?></td>
						<td><code><?= e((string)$failedCronCall['request_method']) ?></code></td>
						<td>
							<?php if (!$tokenFailure): ?>
								<span aria-hidden="true">—</span>
							<?php elseif ($failedToken === null): ?>
								<span class="form-hint"><?= e__('administration.cron.failures_no_token') ?></span>
							<?php elseif ((string)$failedToken === ''): ?>
								<code><?= e__('administration.cron.failures_empty_token') ?></code>
							<?php else: ?>
								<code class="cron-failure-token"><?= e((string)$failedToken) ?></code><?php if ((bool)$failedCronCall['token_truncated']): ?> <small><?= e__('administration.cron.failures_truncated') ?></small><?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>
		</div>

		<div class="configuration-block">
			<h3><?= e__('administration.cron.endpoint_heading') ?></h3>
			<p><?= e__('administration.cron.endpoint_hint') ?></p>
			<code><?= e(rtrim($settings['PULSE_BASE_URL'], '/')) ?>/cron/cron.php?token=…</code>
		</div>
	</section>

</div>
<div class="editor-save-bar" data-settings-save-bar data-settings-tabs="general,security,files,mail,cron">
	<span><?= e__('administration.save_hint') ?></span>
	<div class="editor-save-actions"><button type="submit" form="administration-settings-form"><?= e__('administration.save') ?></button></div>
</div>
<?php elseif (!empty($isSuperUser) && in_array($section, ['backups', 'updates', 'logs'], true)): ?>
<nav class="monitor-tabs administration-maintenance-tabs" aria-label="<?= e__('administration.section.maintenance') ?>">
	<a class="monitor-tab-link<?= $section === 'backups' ? ' is-active' : '' ?>" href="<?= e($base_url) ?>/administration/maintenance/backups"<?= $section === 'backups' ? ' aria-current="page"' : '' ?>><?= e__('administration.tabs.backup') ?></a>
	<a class="monitor-tab-link<?= $section === 'updates' ? ' is-active' : '' ?>" href="<?= e($base_url) ?>/administration/maintenance/updates"<?= $section === 'updates' ? ' aria-current="page"' : '' ?>><?= e__('administration.tabs.updates') ?></a>
	<a class="monitor-tab-link<?= $section === 'logs' ? ' is-active' : '' ?>" href="<?= e($base_url) ?>/administration/maintenance/logs"<?= $section === 'logs' ? ' aria-current="page"' : '' ?>><?= e__('administration.logs.tab') ?></a>
</nav>
<?php if ($section === 'backups'): ?>
	<section class="administration-page-panel">
		<div class="section-heading"><h2><?= e__('administration.backup.heading') ?></h2><p><?= e__('administration.backup.intro') ?></p></div>
		<div class="configuration-block">
			<h3><?= e__('administration.backup.create') ?></h3>
			<p><?= e__('administration.backup.create_hint') ?></p>
			<form method="post" action="<?= e($base_url) ?>/administration/backup/create">
				<?= csrf_field() ?>
				<div class="checkbox-row"><label><input type="checkbox" name="encrypt_backup" value="1" checked data-backup-encrypt><?= e__('administration.backup.encrypt') ?></label></div>
				<label><?= e__('administration.backup.password') ?><input type="password" name="backup_password" minlength="12" autocomplete="new-password" data-backup-password><small><?= e__('administration.backup.password_hint') ?></small></label>
				<p class="form-hint"><?= e__('administration.backup.unencrypted_warning') ?></p>
				<div class="editor-save-actions"><button type="submit"><?= e__('administration.backup.download') ?></button></div>
			</form>
		</div>
		<div class="configuration-block">
			<h3><?= e__('administration.backup.text_heading') ?></h3>
			<p><?= e__('administration.backup.text_hint') ?></p>
			<form method="post" action="<?= e($base_url) ?>/administration/text-documents/export">
				<?= csrf_field() ?>
				<button type="submit" class="button-link"><?= e__('administration.backup.text_download') ?></button>
			</form>
		</div>
		<div class="configuration-block">
			<h3><?= e__('administration.backup.restore_heading') ?></h3>
			<p><?= e__('administration.backup.restore_hint') ?></p>
			<div class="review-warning"><strong><?= e__('administration.backup.restore_warning') ?></strong></div>
			<form method="post" action="<?= e($base_url) ?>/administration/backup/preflight" enctype="multipart/form-data">
				<?= csrf_field() ?>
				<label><?= e__('administration.backup.restore_file') ?><input type="file" name="backup_file" accept=".zip,application/zip" required></label>
				<label><?= e__('administration.backup.password_optional') ?><input type="password" name="restore_password" autocomplete="off"></label>
				<button type="submit"><?= e__('administration.preflight.button') ?></button>
			</form>
			<?php if ($restorePreflight !== null): ?>
				<div class="configuration-block pulse-preflight-report">
					<h3><?= e__('administration.preflight.passed') ?></h3>
					<p><?= e__('administration.preflight.backup_from') ?>: <strong><?= e((string)$restorePreflight['created_at']) ?></strong></p>
					<p><?= e__('administration.preflight.version') ?>: <?= e((string)$restorePreflight['pulse_version']) ?> · <?= (int)$restorePreflight['file_count'] ?> <?= e__('administration.preflight.files') ?></p>
					<ul class="pulse-preflight-checks"><?php foreach ($restorePreflight['checks'] as $check): ?><li><?= e((string)$check) ?></li><?php endforeach; ?></ul>
					<?php if ($restorePreflight['grant_warning'] ?? false): ?><p class="form-hint"><?= e__('administration.preflight.grant_warning') ?></p><?php endif; ?>
					<p class="form-hint"><?= e__('administration.preflight.limits') ?></p>
					<form method="post" action="<?= e($base_url) ?>/administration/backup/restore" data-confirm="<?= e__('administration.backup.final_question') ?>">
						<?= csrf_field() ?>
						<label><?= e__('administration.backup.password_optional') ?><input type="password" name="restore_password" autocomplete="off"></label>
						<label><?= e__('administration.backup.admin_password') ?><input type="password" name="administrator_password" autocomplete="current-password" required></label>
						<div class="checkbox-row"><label><input type="checkbox" name="confirm_restore" value="1" required><?= e__('administration.backup.acknowledge') ?></label></div>
						<div class="editor-save-actions"><button type="submit" class="button-danger"><?= e__('administration.backup.restore_button') ?></button></div>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php if ($maintenancePaused): ?>
			<div class="review-warning"><strong><?= e__('administration.backup.paused') ?></strong><p><?= e__('administration.backup.paused_hint') ?></p>
				<?php if ($maintenanceCanResume): ?>
				<form method="post" action="<?= e($base_url) ?>/administration/backup/resume">
					<?= csrf_field() ?>
					<input type="hidden" name="resume_confirmation" value="RESUME">
					<button type="submit"><?= e__('administration.backup.resume') ?></button>
				</form>
				<?php else: ?><p><?= e__('administration.backup.manual_recovery') ?></p><?php endif; ?>
			</div>
		<?php endif; ?>
	</section>

<?php elseif ($section === 'logs'): ?>
	<section class="administration-page-panel">
		<div class="section-heading"><h2><?= e__('administration.logs.heading') ?></h2><p><?= e__('administration.logs.intro') ?></p></div>
		<div class="configuration-block">
			<form class="administration-log-filters" action="<?= e($base_url) ?>/administration/maintenance/logs" method="get">
				<label><?= e__('administration.logs.level') ?>
					<select name="level">
						<?php foreach (['' => 'administration.logs.all', 'ERROR' => 'ERROR', 'WARNING' => 'WARNING', 'INFO' => 'INFO', 'DEBUG' => 'DEBUG'] as $value => $caption): ?>
							<option value="<?= e($value) ?>"<?= $logLevel === $value ? ' selected' : '' ?>><?= $value === '' ? e__($caption) : e($caption) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?= e__('administration.logs.search') ?><input type="search" name="search" value="<?= e($logSearch) ?>" maxlength="200"></label>
				<button type="submit" class="button-link"><?= e__('administration.logs.filter') ?></button>
				<button type="button" class="button-link" data-log-refresh><?= e__('administration.logs.refresh') ?></button>
				<a class="button-link" href="<?= e($base_url) ?>/administration/maintenance/logs/download"><?= e__('administration.logs.download') ?></a>
			</form>
			<form method="post" action="<?= e($base_url) ?>/administration/maintenance/logs/clear" data-confirm="<?= e__('administration.logs.clear_confirm') ?>" class="administration-log-clear">
				<?= csrf_field() ?>
				<button type="submit" class="btn-danger"><?= e__('administration.logs.clear') ?></button>
			</form>
			<div data-log-results aria-live="polite">
				<?php require __DIR__ . '/log-results.php'; ?>
			</div>
		</div>
	</section>
<?php else: ?>
	<section class="administration-page-panel">
		<div class="section-heading"><h2><?= e__('administration.updates.heading') ?></h2><p><?= e__('administration.updates.intro') ?></p></div>
		<div class="configuration-block">
			<h3><?= e__('administration.updates.install') ?></h3>
			<p><?= e__('administration.updates.install_hint') ?></p>
			<?php if ($updatePreflight === null): ?>
			<form method="post" action="<?= e($base_url) ?>/administration/updates/preflight" enctype="multipart/form-data">
				<?= csrf_field() ?>
				<label><?= e__('administration.updates.file') ?><input type="file" name="update_file" accept=".zip,application/zip" required></label>
				<div class="editor-save-actions"><button type="submit"><?= e__('administration.preflight.button') ?></button></div>
			</form>
			<?php else: ?>
				<div class="configuration-block pulse-preflight-report">
					<h3><?= e__('administration.preflight.passed') ?></h3>
					<p><?= e((string)$updatePreflight['installed_version']) ?> → <strong><?= e((string)$updatePreflight['new_version']) ?></strong> · <?= (int)$updatePreflight['file_count'] ?> <?= e__('administration.preflight.files') ?></p>
					<ul class="pulse-preflight-checks"><?php foreach ($updatePreflight['checks'] as $check): ?><li><?= e((string)$check) ?></li><?php endforeach; ?></ul>
					<p class="form-hint"><?= e__('administration.preflight.limits') ?></p>
					<h3><?= e__('administration.updates.release_notes') ?></h3>
					<div class="markdown-content"><?= $updatePreflight['notes'] !== '' ? markdown_html((string)$updatePreflight['notes']) : '<p>' . e__('administration.updates.no_release_notes') . '</p>' ?></div>
					<form method="post" action="<?= e($base_url) ?>/administration/updates/install">
						<?= csrf_field() ?>
						<label><?= e__('administration.backup.admin_password') ?><input type="password" name="administrator_password" autocomplete="current-password" required></label>
						<div class="editor-save-actions"><button type="submit"><?= e__('administration.updates.button') ?></button></div>
					</form>
					<form method="post" action="<?= e($base_url) ?>/administration/updates/change" class="administration-change-package">
						<?= csrf_field() ?>
						<button type="submit" class="button-link"><?= e__('administration.updates.choose_different') ?></button>
					</form>
				</div>
			<?php endif; ?>
		</div>
	</section>

<?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
$title = __('administration.title');
require __DIR__ . '/../layouts/main.php';
