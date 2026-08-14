<?php

declare(strict_types=1);

/** @var array<string, mixed> $user */
/** @var array<int, string> $notificationLocales */
/** @var string $notificationLocale */
/** @var string $websiteLocale */
/** @var string $base_url */

ob_start();
?>

<h1><?= e__('profile.heading') ?></h1>

<section class="stack">
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

<hr>

<section class="stack">
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


<?php
$content = ob_get_clean();
$title = __('profile.title');
require __DIR__ . '/../layouts/main.php';
