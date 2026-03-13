<?php

declare(strict_types=1);

$contactsText = '';
if ($contactCount === 0)
{
	$contactsText = e__('dashboard.contacts.none');
}
elseif ($contactCount === 1)
{
	$contactsText = e__('dashboard.contacts.one');
}
else
{
	$contactsText = e__('dashboard.contacts.many', [
		'count' => (string)$contactCount,
	]);
}

ob_start();
?>

<h1><?= e__('dashboard.heading') ?></h1>
<p><?= e__('dashboard.message.1', ['name' => $user['display_name']]) ?></p>
<p><?= e__('dashboard.message.2') ?></p>
<p><?= $contactsText ?></p>

<?php
$content = ob_get_clean();
$title = e__('dashboard.title');
require __DIR__ . '/../layouts/main.php';