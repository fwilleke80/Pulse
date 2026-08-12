<?php

/**
 * @file confirm.php
 * @brief Explicit safety-contact response form.
 * @author Frank Willeke
 */

declare(strict_types=1);

/** @var array<string, mixed> $safetyRequest */
/** @var string $token */
/** @var string|null $validationError */
/** @var string $base_url */

ob_start();
?>

<div class="safety-response-page">
	<h1><?= e__('safety.confirm.heading') ?></h1>
	<p><?= e__('safety.confirm.intro', ['owner' => (string)$safetyRequest['owner_name']]) ?></p>
	<div class="privacy-note">
		<strong><?= e__('safety.confirm.scanner_heading') ?></strong>
		<?= e__('safety.confirm.scanner_message') ?>
	</div>

	<?php if (!empty($validationError)): ?>
		<div class="flash flash-error"><?= e((string)$validationError) ?></div>
	<?php endif; ?>

	<section class="configuration-block">
		<h2><?= e__('safety.confirm.yes_heading') ?></h2>
		<p><?= e__('safety.confirm.yes_hint', ['owner' => (string)$safetyRequest['owner_name']]) ?></p>
		<form method="post" action="<?= e($base_url) ?>/safety/respond">
			<?= csrf_field() ?>
			<input type="hidden" name="token" value="<?= e($token) ?>">
			<input type="hidden" name="decision" value="confirm">
			<label class="compact-check safety-confirmation-check">
				<input type="checkbox" name="direct_contact" value="1" required>
				<?= e__('safety.confirm.checkbox', ['owner' => (string)$safetyRequest['owner_name']]) ?>
			</label>
			<button type="submit" class="btn-primary"><?= e__('safety.confirm.yes_submit') ?></button>
		</form>
	</section>

	<section class="configuration-block">
		<h2><?= e__('safety.confirm.no_heading') ?></h2>
		<p><?= e__('safety.confirm.no_hint') ?></p>
		<form method="post" action="<?= e($base_url) ?>/safety/respond">
			<?= csrf_field() ?>
			<input type="hidden" name="token" value="<?= e($token) ?>">
			<input type="hidden" name="decision" value="decline">
			<button type="submit"><?= e__('safety.confirm.no_submit') ?></button>
		</form>
	</section>
</div>

<?php
$content = ob_get_clean();
$title = e__('safety.confirm.title');
require __DIR__ . '/../layouts/main.php';
