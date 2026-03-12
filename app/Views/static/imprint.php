<?php

declare(strict_types=1);

ob_start();
?>

<div class="card">
	<h1>Impressum</h1>

	<h2>Über Pulse</h2>
	<p>Pulse ist ein Notfall-Benachrichtigungs-System.</p>
	<p>Es ermöglicht Benutzern, im Falle eines Notfalls oder gar Unglücks automatisch eine Nachricht an vordefinierte Kontakte zu senden.</p>

	<h2>Kontaktinformationen</h2>
	<p>
		Frank Willeke<br>
		<u>frank[at]frankwilleke[dot]de</u>
	</p>
</div>

<?php

$content = ob_get_clean();
$title = 'Imprint';

require __DIR__ . '/../layouts/main.php';