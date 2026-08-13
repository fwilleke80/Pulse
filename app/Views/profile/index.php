<?php

declare(strict_types=1);

/** @var array<string, mixed> $user */
/** @var array<int, string> $notificationLocales */
/** @var string $notificationLocale */
/** @var bool $mailEnabled */
/** @var array<string, int> $mailQueueCounts */
/** @var array<string, mixed>|null $latestTestNotification */
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

		<div>
			<label for="notification_locale"><?= e__('profile.data.notification_language') ?></label><br>
			<select id="notification_locale" name="notification_locale" required>
				<?php foreach ($notificationLocales as $localeOption): ?>
					<option value="<?= e($localeOption) ?>" <?= $localeOption === $notificationLocale ? 'selected' : '' ?>>
						<?= e(notification_language_name($localeOption)) ?>
					</option>
				<?php endforeach; ?>
			</select>
			<small><?= e__('profile.data.notification_language_hint') ?></small>
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

<hr>

<section class="stack" id="notifications">
	<div class="section-title-row">
		<div>
			<h2><?= e__('profile.notifications.heading') ?></h2>
			<p><?= e__('profile.notifications.hint') ?></p>
		</div>
		<span class="status-badge status-mail-<?= $mailEnabled ? 'enabled' : 'disabled' ?>">
			<?= e__($mailEnabled ? 'profile.notifications.enabled' : 'profile.notifications.disabled') ?>
		</span>
	</div>

	<div class="notification-status-grid">
		<div>
			<span><?= e__('profile.notifications.queue.pending') ?></span>
			<strong><?= (int)$mailQueueCounts['queued'] + (int)$mailQueueCounts['retrying'] + (int)$mailQueueCounts['processing'] ?></strong>
		</div>
		<div>
			<span><?= e__('profile.notifications.queue.failed') ?></span>
			<strong><?= (int)$mailQueueCounts['failed'] ?></strong>
		</div>
		<div>
			<span><?= e__('profile.notifications.queue.sent') ?></span>
			<strong><?= (int)$mailQueueCounts['sent'] ?></strong>
		</div>
	</div>

	<?php if (is_array($latestTestNotification)): ?>
		<div class="notification-last-test">
			<strong><?= e__('profile.notifications.last_test') ?></strong>
			<span class="status-badge status-mail-<?= e((string)$latestTestNotification['status']) ?>">
				<?= e__('profile.notifications.status.' . (string)$latestTestNotification['status']) ?>
			</span>
			<time datetime="<?= e((string)$latestTestNotification['created_at']) ?>">
				<?= e(format_datetime((string)$latestTestNotification['created_at'])) ?>
			</time>
			<?php if (!empty($latestTestNotification['last_error'])): ?>
				<small><?= e((string)$latestTestNotification['last_error']) ?></small>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ($mailEnabled): ?>
		<p><?= e__('profile.notifications.test.hint', ['email' => (string)$user['email']]) ?></p>
		<form method="post" action="<?= e($base_url) ?>/profile/notifications/test">
			<?= csrf_field() ?>
			<button type="submit"><?= e__('profile.notifications.test.submit') ?></button>
		</form>
	<?php else: ?>
		<div class="notification-disabled-warning" id="mail-disabled-help" role="alert">
			<strong><?= e__('profile.notifications.disabled.heading') ?></strong>
			<span><?= e__('profile.notifications.disabled.message') ?></span>
		</div>
		<button type="button" disabled aria-describedby="mail-disabled-help"><?= e__('profile.notifications.test.submit') ?></button>
	<?php endif; ?>
	<?php if ((int)$mailQueueCounts['failed'] > 0): ?>
		<?php if ($mailEnabled): ?>
			<form method="post" action="<?= e($base_url) ?>/profile/notifications/retry">
				<?= csrf_field() ?>
				<button type="submit" class="btn-secondary"><?= e__('profile.notifications.retry.submit') ?></button>
			</form>
		<?php else: ?>
			<button type="button" class="btn-secondary" disabled aria-describedby="mail-disabled-help"><?= e__('profile.notifications.retry.submit') ?></button>
		<?php endif; ?>
	<?php endif; ?>
</section>

<?php
$content = ob_get_clean();
$title = __('profile.title');
require __DIR__ . '/../layouts/main.php';
