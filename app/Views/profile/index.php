<?php

declare(strict_types=1);

/** @var array<string, mixed> $user */
/** @var array<int, string> $notificationLocales */
/** @var string $notificationLocale */
/** @var string $websiteLocale */
/** @var array<int, array<string, mixed>> $passkeys */
/** @var array<string, mixed>|null $totpStatus */
/** @var array{secret:string,formatted_secret:string,provisioning_uri:string}|null $totpSetup */
/** @var array<int, string> $totpRecoveryCodes */
/** @var string $activeTab */
/** @var string $base_url */

ob_start();
$ownerAddresses = \Pulse\Core\EmailAddressCollection::FromRow($user);
?>

<h1><?= e__('profile.heading') ?></h1>

<div class="monitor-editor profile-editor" data-monitor-tabs data-active-tab="<?= e($activeTab) ?>">
	<nav class="monitor-tabs" role="tablist" aria-label="<?= e__('profile.tabs.label') ?>">
		<a href="<?= e($base_url) ?>/profile?tab=profile" id="profile-tab-profile" class="monitor-tab-link<?= $activeTab === 'profile' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'profile' ? 'true' : 'false' ?>" aria-controls="profile-panel-profile" data-tab-target="profile"><?= e__('profile.tabs.profile_data') ?></a>
		<a href="<?= e($base_url) ?>/profile?tab=security" id="profile-tab-security" class="monitor-tab-link<?= $activeTab === 'security' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'security' ? 'true' : 'false' ?>" aria-controls="profile-panel-security" data-tab-target="security"><?= e__('profile.tabs.account_security') ?></a>
		<a href="<?= e($base_url) ?>/profile?tab=password" id="profile-tab-password" class="monitor-tab-link<?= $activeTab === 'password' ? ' is-active' : '' ?>" role="tab" aria-selected="<?= $activeTab === 'password' ? 'true' : 'false' ?>" aria-controls="profile-panel-password" data-tab-target="password"><?= e__('profile.tabs.change_password') ?></a>
	</nav>

<section id="profile-panel-profile" class="stack monitor-tab-panel<?= $activeTab === 'profile' ? ' is-active' : '' ?>" role="tabpanel" aria-labelledby="profile-tab-profile" data-tab-panel="profile"<?= $activeTab === 'profile' ? '' : ' hidden' ?>>
	<h2><?= e__('profile.data.heading') ?></h2>

	<form method="post" action="<?= e($base_url) ?>/profile/update" class="stack">
		<?= csrf_field() ?>
		<div>
			<label for="display_name"><?= e__('profile.data.display_name') ?></label><br>
			<input
				type="text"
				id="display_name"
				name="display_name"
				value="<?= htmlspecialchars((string)($user['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
				required
			>
		</div>

		<div class="email-address-grid">
			<?php for ($slot = 1; $slot <= \Pulse\Core\EmailAddressCollection::MAX_ADDRESSES; $slot++): ?>
				<?php
				$emailField = \Pulse\Core\EmailAddressCollection::EmailField($slot);
				$checkedField = $slot === 1 ? 'email_checked' : 'email_' . $slot . '_checked';
				$address = $ownerAddresses[$slot - 1] ?? ['email' => '', 'checked' => false];
				?>
				<div class="email-address-card" data-email-address-card>
					<label for="profile_<?= e($emailField) ?>"><?= e__('profile.data.email') ?></label>
					<input type="email" id="profile_<?= e($emailField) ?>" name="<?= e($emailField) ?>" data-contact-email data-original-email="<?= e((string)$address['email']) ?>" value="<?= e((string)$address['email']) ?>"<?= $slot === 1 ? ' required' : '' ?>>
					<p class="email-suggestion is-hidden" data-email-suggestion data-suggestion-template="<?= e__('contacts.email.suggestion') ?>" role="status"></p>
					<label class="compact-check"><input type="checkbox" name="<?= e($checkedField) ?>" data-email-checked<?= !empty($address['checked']) ? ' checked' : '' ?>> <?= e__('contacts.email_checked.label') ?></label>
				</div>
			<?php endfor; ?>
		</div>
		<small><?= e__('profile.data.email_hint') ?></small>
		<?php if (\Pulse\Core\EmailAddressCollection::Checked($user) === []): ?><div class="template-validation-warning" role="alert"><?= e__('profile.data.no_checked_email_warning') ?></div><?php endif; ?>

		<div class="field-grid field-grid-two">
			<div>
				<label for="website_locale"><?= e__('profile.data.website_language') ?></label><br>
				<select id="website_locale" name="website_locale" required>
					<?php foreach ($notificationLocales as $localeOption): ?>
						<option value="<?= e($localeOption) ?>" <?= $localeOption === $websiteLocale ? 'selected' : '' ?>><?= e(notification_language_name($localeOption)) ?></option>
					<?php endforeach; ?>
				</select>
				<small><?= e__('profile.data.website_language_hint') ?></small>
			</div>
			<div>
				<label for="notification_locale"><?= e__('profile.data.notification_language') ?></label><br>
				<select id="notification_locale" name="notification_locale" required>
					<?php foreach ($notificationLocales as $localeOption): ?>
						<option value="<?= e($localeOption) ?>" <?= $localeOption === $notificationLocale ? 'selected' : '' ?>><?= e(notification_language_name($localeOption)) ?></option>
					<?php endforeach; ?>
				</select>
				<small><?= e__('profile.data.notification_language_hint') ?></small>
			</div>
		</div>

		<div>
			<button type="submit"><?= e__('profile.data.submit') ?></button>
		</div>
	</form>
</section>


<section class="stack monitor-tab-panel<?= $activeTab === 'security' ? ' is-active' : '' ?>" id="profile-panel-security" role="tabpanel" aria-labelledby="profile-tab-security" data-tab-panel="security"<?= $activeTab === 'security' ? '' : ' hidden' ?>>
	<h2><?= e__('security.heading') ?></h2>
	<p><?= e__('security.hint') ?></p>

	<?php if ($totpRecoveryCodes !== []): ?>
		<div class="configuration-block totp-recovery-display" role="region" aria-labelledby="totp-recovery-heading">
			<h3 id="totp-recovery-heading"><?= e__('security.totp.recovery.heading') ?></h3>
			<div class="template-validation-warning" role="alert"><?= e__('security.totp.recovery.warning') ?></div>
			<ul class="totp-recovery-code-grid" data-totp-recovery-code-list>
				<?php foreach ($totpRecoveryCodes as $recoveryCode): ?>
					<li><code><?= e($recoveryCode) ?></code></li>
				<?php endforeach; ?>
			</ul>
			<div class="button-row">
				<button type="button" data-copy-totp-recovery-codes data-copy-success="<?= e__('security.totp.recovery.copied') ?>"><?= e__('security.totp.recovery.copy') ?></button>
				<form method="post" action="<?= e($base_url) ?>/security/totp/recovery/acknowledge">
					<?= csrf_field() ?>
					<button type="submit" class="btn-primary"><?= e__('security.totp.recovery.saved') ?></button>
				</form>
			</div>
			<p class="security-method-status" data-totp-recovery-copy-status hidden></p>
		</div>
	<?php endif; ?>

	<div class="configuration-block">
		<div class="security-method-heading">
			<div>
				<h3><?= e__('security.totp.heading') ?></h3>
				<p><?= e__('security.totp.hint') ?></p>
			</div>
			<span class="status-badge <?= is_array($totpStatus) ? 'status-verified' : 'status-muted' ?>"><?= e__(is_array($totpStatus) ? 'security.totp.status.enabled' : 'security.totp.status.disabled') ?></span>
		</div>
		<p class="form-hint"><?= e__('security.totp.passkey_hint') ?></p>

		<?php if (is_array($totpStatus)): ?>
			<div class="security-method-summary">
				<span><?= e__('security.totp.enabled_at', ['date' => format_datetime((string)$totpStatus['enabled_at'])]) ?></span>
				<span><?= e__('security.totp.recovery.remaining', ['count' => (int)$totpStatus['recovery_codes_remaining']]) ?></span>
				<?php if (!empty($totpStatus['last_used_at'])): ?><span><?= e__('security.totp.last_used', ['date' => format_datetime((string)$totpStatus['last_used_at'])]) ?></span><?php endif; ?>
			</div>

			<details class="security-method-management">
				<summary><?= e__('security.totp.recovery.regenerate') ?></summary>
				<p class="form-hint"><?= e__('security.totp.reauthentication_hint') ?></p>
				<form method="post" action="<?= e($base_url) ?>/security/totp/recovery/regenerate" class="stack compact-form" data-confirm="<?= e__('security.totp.recovery.regenerate_confirm') ?>">
					<?= csrf_field() ?>
					<label><?= e__('security.totp.current_password') ?><input type="password" name="current_password" autocomplete="current-password" required></label>
					<label><?= e__('security.totp.code_or_recovery') ?><input type="text" name="code" autocomplete="one-time-code" maxlength="64" required></label>
					<button type="submit"><?= e__('security.totp.recovery.regenerate') ?></button>
				</form>
			</details>

			<details class="security-method-management security-method-danger">
				<summary><?= e__('security.totp.disable') ?></summary>
				<p class="form-hint"><?= e__('security.totp.disable_hint') ?></p>
				<form method="post" action="<?= e($base_url) ?>/security/totp/disable" class="stack compact-form" data-confirm="<?= e__('security.totp.disable_confirm') ?>">
					<?= csrf_field() ?>
					<label><?= e__('security.totp.current_password') ?><input type="password" name="current_password" autocomplete="current-password" required></label>
					<label><?= e__('security.totp.code_or_recovery') ?><input type="text" name="code" autocomplete="one-time-code" maxlength="64" required></label>
					<button type="submit" class="btn-danger"><?= e__('security.totp.disable') ?></button>
				</form>
			</details>
		<?php else: ?>
			<?php if (is_array($totpSetup)): ?>
				<div class="totp-setup-grid">
					<div class="totp-qr-panel">
						<h4><?= e__('security.totp.setup.scan_heading') ?></h4>
						<canvas class="totp-qr-code" data-totp-qr-code data-totp-uri="<?= e($totpSetup['provisioning_uri']) ?>" role="img" aria-label="<?= e__('security.totp.setup.qr_label') ?>"></canvas>
					</div>
					<div>
						<h4><?= e__('security.totp.setup.manual_heading') ?></h4>
						<p><?= e__('security.totp.setup.manual_hint') ?></p>
						<code class="totp-manual-secret"><?= e($totpSetup['formatted_secret']) ?></code>
					</div>
				</div>
				<p><?= e__('security.totp.setup.confirm_hint') ?></p>
				<form method="post" action="<?= e($base_url) ?>/security/totp/confirm" class="stack compact-form totp-confirm-form">
					<?= csrf_field() ?>
					<label><?= e__('security.totp.code') ?><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,12}" maxlength="12" required autofocus></label>
					<button type="submit" class="btn-primary"><?= e__('security.totp.setup.confirm') ?></button>
				</form>
				<form method="post" action="<?= e($base_url) ?>/security/totp/cancel" class="compact-form">
					<?= csrf_field() ?>
					<button type="submit"><?= e__('actions.cancel') ?></button>
				</form>
			<?php else: ?>
				<form method="post" action="<?= e($base_url) ?>/security/totp/setup" class="stack compact-form">
					<?= csrf_field() ?>
					<label><?= e__('security.totp.current_password') ?><input type="password" name="current_password" autocomplete="current-password" required></label>
					<div><button type="submit"><?= e__('security.totp.setup.start') ?></button></div>
				</form>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="configuration-block">
		<h3><?= e__('security.passkeys.heading') ?></h3>
		<p><?= e__('security.passkeys.hint') ?></p>
		<p class="form-hint"><?= e__('security.passkeys.device_hint') ?></p>

		<?php if ($passkeys === []): ?>
			<p class="muted"><?= e__('security.passkeys.empty') ?></p>
		<?php else: ?>
			<div class="security-credential-list">
				<?php foreach ($passkeys as $passkey): ?>
					<div class="security-credential-item">
						<div>
							<strong><?= e((string)$passkey['label']) ?></strong>
							<small><?= e__('security.passkeys.created', ['date' => format_datetime((string)$passkey['created_at'])]) ?></small>
							<?php if (!empty($passkey['last_used_at'])): ?><small><?= e__('security.passkeys.last_used', ['date' => format_datetime((string)$passkey['last_used_at'])]) ?></small><?php endif; ?>
						</div>
						<details class="security-credential-remove">
							<summary><?= e__('security.passkeys.remove') ?></summary>
							<form method="post" action="<?= e($base_url) ?>/security/passkeys/delete" class="stack compact-form" data-confirm="<?= e__('security.passkeys.remove_confirm') ?>">
								<?= csrf_field() ?>
								<input type="hidden" name="credential_id" value="<?= (int)$passkey['id'] ?>">
								<label><?= e__('security.passkeys.current_password') ?><input type="password" name="current_password" autocomplete="current-password" required></label>
								<button type="submit" class="btn-danger"><?= e__('security.passkeys.remove') ?></button>
							</form>
						</details>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php $existingPasskeyMessage = count($passkeys) === 1
			? __('security.passkeys.already_available_named', ['name' => (string)$passkeys[0]['label']])
			: __('security.passkeys.already_available'); ?>
		<form class="stack passkey-registration-form" data-passkey-register-form data-passkey-options-url="<?= e($base_url) ?>/security/passkeys/register/options" data-passkey-verify-url="<?= e($base_url) ?>/security/passkeys/register/verify" data-passkey-unavailable="<?= e__('security.passkeys.browser_unavailable') ?>" data-passkey-cancelled="<?= e__('security.passkeys.cancelled') ?>" data-passkey-already-available="<?= e($existingPasskeyMessage) ?>">
			<?= csrf_field() ?>
			<div class="field-grid field-grid-two">
				<label><?= e__('security.passkeys.name') ?><input type="text" name="label" maxlength="255" placeholder="<?= e__('security.passkeys.name_placeholder') ?>" required></label>
				<label><?= e__('security.passkeys.current_password') ?><input type="password" name="current_password" autocomplete="current-password" required></label>
			</div>
			<div><button type="button" data-passkey-register><?= e__('security.passkeys.add') ?></button></div>
			<div class="security-method-status" data-passkey-status hidden></div>
		</form>
	</div>
</section>

<section id="profile-panel-password" class="stack monitor-tab-panel<?= $activeTab === 'password' ? ' is-active' : '' ?>" role="tabpanel" aria-labelledby="profile-tab-password" data-tab-panel="password"<?= $activeTab === 'password' ? '' : ' hidden' ?>>
	<h2><?= e__('profile.password.heading') ?></h2>

	<form method="post" action="<?= e($base_url) ?>/profile/password" class="stack">
		<?= csrf_field() ?>
		<div>
			<label for="current_password"><?= e__('profile.password.current') ?></label><br>
			<input
				type="password"
				id="current_password"
				name="current_password"
				required
			>
		</div>

		<div>
			<label for="new_password"><?= e__('profile.password.new') ?></label><br>
			<input
				type="password"
				id="new_password"
				name="new_password"
				required
			>
		</div>

		<div>
			<label for="confirm_password"><?= e__('profile.password.confirm') ?></label><br>
			<input
				type="password"
				id="confirm_password"
				name="confirm_password"
				required>
		</div>
		<div id="password_mismatch_warning" class="password-warning is-hidden">
			<?= e__('profile.password.mismatch_warning') ?>
		</div>
		<div class="password-toggle">
			<label for="show_passwords">
				<?= e__('profile.password.show') ?>
				<input type="checkbox" id="show_passwords">
			</label>
		</div>
		<div>
			<button type="submit"><?= e__('profile.password.submit') ?></button>
		</div>
	</form>
</section>

</div>


<?php
$content = ob_get_clean();
$title = __('profile.title');
$needsQrCode = is_array($totpSetup);
require __DIR__ . '/../layouts/main.php';
