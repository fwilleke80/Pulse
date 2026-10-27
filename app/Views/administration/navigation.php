<?php
/** @brief Shared Administration navigation for every section and account editor. */
declare(strict_types=1);
$isSuperUser = !empty($currentUser['is_super_user']);
$sections = [
	'overview' => ['administration.section.overview', '/administration'],
	'configuration' => ['administration.section.configuration', '/administration/configuration'],
	'backups' => ['administration.section.maintenance', '/administration/maintenance/backups'],
];
if (empty($isSuperUser)) { unset($sections['backups']); }
$sections['users'] = ['accounts.heading', '/administration/users'];
?>
<nav class="administration-section-nav" aria-label="<?= e__('administration.sections.label') ?>">
	<?php foreach ($sections as $sectionKey => $item): ?>
		<a href="<?= e($base_url . $item[1]) ?>" class="administration-section-link<?= ($section === $sectionKey || ($section === 'updates' && $sectionKey === 'backups')) ? ' is-active' : '' ?>"<?= ($section === $sectionKey) ? ' aria-current="page"' : '' ?>><?= e__($item[0]) ?></a>
	<?php endforeach; ?>
</nav>
