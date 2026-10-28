<?php /** @brief Saved issues link to an exact template, language and field. */ ?>
<?php if ($savedMailIssues !== []): ?>
<section class="configuration-block mail-readiness-summary" role="status">
<h2><?= e__('validation.attention') ?></h2><p><?= e__('validation.saved_hint') ?></p>
<ul>
<?php foreach ($savedMailIssues as $issue): ?>
<li><a href="<?= e($issue['group']['url'] . '&field=' . rawurlencode($issue['field']) . '#' . $issue['field']) ?>"><strong><?= e($issue['group']['label']) ?></strong>: <?= e__('validation.' . $issue['code']) ?></a>
<?php if ($issue['group']['affected'] !== []): ?><small><?= e__('validation.affected', ['names' => implode(', ', $issue['group']['affected'])]) ?></small><?php endif; ?>
</li>
<?php endforeach; ?>
</ul></section>
<?php endif; ?>
