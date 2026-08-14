<?php
/**
 * @file index.php
 * @brief Public recipient portal access-code landing page.
 * @author Frank Willeke
 */
declare(strict_types=1);

/** @var array<string, mixed> $delivery */
/** @var string $token */
/** @var bool $codeRequested */
/** @var bool $isAuthenticatedForDelivery */
/** @var string|null $validationError */
/** @var string $base_url */

ob_start();
?>
<h1><?= e__('portal.heading') ?></h1>
<p><?= e__('portal.intro', ['owner' => (string)$delivery['owner_name']]) ?></p>

<?php if ($codeRequested): ?>
	<div class="flash flash-success" role="status">
		<strong><?= e__('portal.code.requested.heading') ?></strong><br>
		<?= e__('portal.code.requested.message') ?>
	</div>
<?php endif; ?>

<?php if (isset($validationError) && is_string($validationError) && $validationError !== ''): ?>
	<div class="flash flash-error" role="alert"><?= e($validationError) ?></div>
<?php endif; ?>

<?php if ($isAuthenticatedForDelivery): ?>
	<p><a class="button-link" href="<?= e($base_url) ?>/portal/access?token=<?= e(rawurlencode($token)) ?>"><?= e__('portal.continue') ?></a></p>
<?php endif; ?>

<div class="portal-access-grid">
	<section class="configuration-block">
		<h2><?= e__('portal.request.heading') ?></h2>
		<p><?= e__('portal.request.message') ?></p>
		<form method="post" action="<?= e($base_url) ?>/portal/code/request">
			<?= csrf_field() ?>
			<input type="hidden" name="token" value="<?= e($token) ?>">
			<button type="submit" class="btn-primary"><?= e__('portal.request.submit') ?></button>
		</form>
	</section>

	<section class="configuration-block">
		<h2><?= e__('portal.enter.heading') ?></h2>
		<p><?= e__('portal.enter.message') ?></p>
		<form method="post" action="<?= e($base_url) ?>/portal/code/verify">
			<?= csrf_field() ?>
			<input type="hidden" name="token" value="<?= e($token) ?>">
			<label for="recipient_access_code"><?= e__('portal.enter.label') ?></label>
			<input type="text" id="recipient_access_code" name="access_code" inputmode="text" autocomplete="one-time-code" maxlength="16" required>
			<button type="submit" class="btn-primary"><?= e__('portal.enter.submit') ?></button>
		</form>
	</section>
</div>
<?php
$content = ob_get_clean();
$title = e__('portal.title');
require __DIR__ . '/../layouts/main.php';
