<?php /** @brief Escaped configuration for live validation; never embeds executable inline code. */
$diagnosticLabels = [];
foreach (['subject_missing','body_missing','url_missing','url_subject'] as $code)
{
	$diagnosticLabels[$code] = __('validation.' . $code);
}
?>
<div hidden data-mail-diagnostics data-warning-label="<?= e__('validation.attention') ?>" data-groups="<?= e(json_encode($mailDraftGroups, JSON_THROW_ON_ERROR)) ?>" data-labels="<?= e(json_encode($diagnosticLabels, JSON_THROW_ON_ERROR)) ?>"></div>
