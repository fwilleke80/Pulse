<?php

declare(strict_types=1);

ob_start();
?>

<div class="card">
	<h2>Add contact</h2>

	<form method="post" action="/contacts/create">
		<label for="name">Name</label>
		<input type="text" id="name" name="name" required>

		<label for="email">Email</label>
		<input type="email" id="email" name="email" required>

		<label for="cell_phone">Cell phone</label>
		<input type="text" id="cell_phone" name="cell_phone">

		<label for="notes">Notes</label>
		<input type="text" id="notes" name="notes">

		<button type="submit">Create contact</button>
	</form>
</div>

<?php

$content = ob_get_clean();
$title = 'Add contact';

require __DIR__ . '/../layouts/main.php';