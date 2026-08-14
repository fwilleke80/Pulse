<?php
/**
 * @file close-confirm.php
 * @brief Guarded irreversible recipient portal closure confirmation.
 * @author Frank Willeke
 */
declare(strict_types=1);

/** @var array<string, mixed> $delivery */
/** @var string $token */
/** @var string $confirmationCode */
/** @var int $availableDocumentCount */
/** @var int $totalDownloadBytes */
/** @var string|null $validationError */
/** @var string $base_url */

$formatSize = static function (int $bytes): string
{
	$bytes = max(0, $bytes);

	if ($bytes < 1024)
	{
		return $bytes . ' B';
	}

	if ($bytes < 1024 * 1024)
	{
		return number_format($bytes / 1024, 1) . ' KB';
	}

	if ($bytes < 1024 * 1024 * 1024)
	{
		return number_format($bytes / (1024 * 1024), 1) . ' MB';
	}

	return number_format($bytes / (1024 * 1024 * 1024), 1) . ' GB';
};

ob_start();
?>
<header class="portal-delivery-hero portal-close-hero">
	<p class="portal-delivery-eyebrow"><?= e__('portal.close.eyebrow') ?></p>
	<h1><?= e__('portal.close.heading') ?></h1>
	<p class="portal-delivery-intro"><?= e__('portal.close.intro') ?></p>
</header>

<?php if (isset($validationError) && is_string($validationError) && $validationError !== ''): ?>
	<div class="flash flash-error" role="alert"><?= e($validationError) ?></div>
<?php endif; ?>

<section class="portal-close-warning" aria-labelledby="portal-close-warning-heading">
	<h2 id="portal-close-warning-heading"><?= e__('portal.close.warning.heading') ?></h2>
	<p><strong><?= e__('portal.close.warning.irreversible') ?></strong></p>
	<ul>
		<li><?= e__('portal.close.warning.link') ?></li>
		<li><?= e__('portal.close.warning.codes') ?></li>
		<li><?= e__('portal.close.warning.session') ?></li>
		<li><?= e__('portal.close.warning.files') ?></li>
		<li><?= e__('portal.close.warning.storage') ?></li>
	</ul>
</section>

<?php if ($availableDocumentCount > 0): ?>
	<section class="configuration-block portal-close-download">
		<h2><?= e__('portal.close.download.heading') ?></h2>
		<p><?= e__('portal.close.download.message') ?></p>
		<a class="btn-primary portal-download-all" href="<?= e($base_url) ?>/portal/documents/download-all?token=<?= e(rawurlencode($token)) ?>">
			<span><?= e__('portal.documents.download_all') ?></span>
			<small><?= e__('portal.documents.download_all_summary', [
				'count' => $availableDocumentCount,
				'size' => $formatSize($totalDownloadBytes),
			]) ?></small>
		</a>
	</section>
<?php endif; ?>

<section class="configuration-block portal-close-confirmation">
	<h2><?= e__('portal.close.confirm.heading') ?></h2>
	<p><?= e__('portal.close.confirm.instructions') ?></p>
	<p class="portal-close-code" aria-label="<?= e__('portal.close.code.label') ?>"><?= e($confirmationCode) ?></p>

	<form method="post" action="<?= e($base_url) ?>/portal/close">
		<?= csrf_field() ?>
		<input type="hidden" name="token" value="<?= e($token) ?>">

		<label class="checkbox-row portal-close-acknowledgement">
			<input type="checkbox" name="confirm_downloaded" value="1" required>
			<span><?= e__('portal.close.acknowledge') ?></span>
		</label>

		<label for="portal_close_confirmation_code"><?= e__('portal.close.code.prompt') ?></label>
		<input type="text" id="portal_close_confirmation_code" name="confirmation_code" autocomplete="off" autocapitalize="none" spellcheck="false" maxlength="16" required>

		<div class="portal-close-actions">
			<a class="button-link" href="<?= e($base_url) ?>/portal/access?token=<?= e(rawurlencode($token)) ?>"><?= e__('portal.close.cancel') ?></a>
			<button type="submit" class="portal-close-submit"><?= e__('portal.close.submit') ?></button>
		</div>
	</form>
</section>
<?php
$content = ob_get_clean();
$title = e__('portal.close.title');
require __DIR__ . '/../layouts/main.php';
