<?php

declare(strict_types=1);

ob_start();
?>

<div class="card">
	<h2>Imprint</h2>

	<p>
		This website is operated by:
	</p>

	<p>
		Frank Willeke<br>
		[Your postal address here]<br>
		[City, postal code]<br>
		Germany
	</p>

	<p>
		Email: [your email address here]
	</p>
</div>

<?php

$content = ob_get_clean();
$title = 'Imprint';

require __DIR__ . '/../layouts/main.php';