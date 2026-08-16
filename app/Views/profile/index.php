<?php

declare(strict_types=1);

/** @var array<string, mixed> $user */
/** @var array<int, string> $notificationLocales */
/** @var string $notificationLocale */
/** @var string $websiteLocale */
/** @var array<int, array<string, mixed>> $passkeys */
/** @var string $activeTab */
/** @var string $base_url */

ob_start();
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

		<div>
			<label for="email"><?= e__('profile.data.email') ?></label><br>
			<input
				type="email"
				id="email"
				name="email"
				value="<?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
				required
			>
		</div>

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
require __DIR__ . '/../layouts/main.php';
