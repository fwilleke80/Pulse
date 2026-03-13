<?php

declare(strict_types=1);

/** @var array<string, mixed> $user */

ob_start();
?>

<h1><?= e__('profile.heading') ?></h1>

<section class="stack">
	<h2><?= e__('profile.data.heading') ?></h2>

	<form method="post" action="/profile/update" class="stack">
		<div>
			<label for="display_name"><?= e__('profile.data.display_name') ?></label><br>
			<input
				type="text"
				id="display_name"
				name="display_name"
				value="<?= htmlspecialchars((string)($user['display_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
				required
			>
		</div>

		<div>
			<label for="email"><?= e__('profile.data.email') ?></label><br>
			<input
				type="email"
				id="email"
				name="email"
				value="<?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
				required
			>
		</div>

		<div>
			<button type="submit"><?= e__('profile.data.submit') ?></button>
		</div>
	</form>
</section>

<hr>

<section class="stack">
	<h2><?= e__('profile.password.heading') ?></h2>

	<form method="post" action="/profile/password" class="stack">
		<div>
			<label for="current_password"><?= e__('profile.password.current') ?></label><br>
			<input
				type="password"
				id="current_password"
				name="current_password"
				required
			>
		</div>

		<div>
			<label for="new_password"><?= e__('profile.password.new') ?></label><br>
			<input
				type="password"
				id="new_password"
				name="new_password"
				required
			>
		</div>

		<div>
			<label for="confirm_password"><?= e__('profile.password.confirm') ?></label><br>
			<input
				type="password"
				id="confirm_password"
				name="confirm_password"
				required>
		</div>
		<div id="password_mismatch_warning" class="password-warning" style="display:none;">
			<?= e__('profile.password.mismatch_warning') ?>
		</div>
		<div class="password-toggle">
			<label for="show_passwords">
				<?= e__('profile.password.show') ?>
				<input type="checkbox" id="show_passwords">
			</label>
		</div>
		<div>
			<button type="submit"><?= e__('profile.password.submit') ?></button>
		</div>
	</form>
	<script>
		document.addEventListener('DOMContentLoaded', function ()
		{
			const toggle = document.getElementById('show_passwords');
			const fields = [
				document.getElementById('current_password'),
				document.getElementById('new_password'),
				document.getElementById('confirm_password')
			];

			if (!toggle)
			{
				return;
			}

			toggle.addEventListener('change', function ()
			{
				const newType = toggle.checked ? 'text' : 'password';

				for (const field of fields)
				{
					if (field)
					{
						field.type = newType;
					}
				}
			});
		});

		document.addEventListener("DOMContentLoaded", function ()
		{
			const newPassword = document.getElementById("new_password");
			const confirmPassword = document.getElementById("confirm_password");
			const warning = document.getElementById("password_mismatch_warning");

			if (!newPassword || !confirmPassword)
			{
				return;
			}

			function checkPasswords()
			{
				const p1 = newPassword.value;
				const p2 = confirmPassword.value;

				if (p1 === "" && p2 === "")
				{
					warning.style.display = "none";
					newPassword.classList.remove("password-error");
					confirmPassword.classList.remove("password-error");
					return;
				}

				if (p1 !== p2)
				{
					warning.style.display = "block";
					newPassword.classList.add("password-error");
					confirmPassword.classList.add("password-error");
				}
				else
				{
					warning.style.display = "none";
					newPassword.classList.remove("password-error");
					confirmPassword.classList.remove("password-error");
				}
			}

			newPassword.addEventListener("input", checkPasswords);
			confirmPassword.addEventListener("input", checkPasswords);
		});
	</script>
</section>

<?php
$content = ob_get_clean();
$title = __('profile.title');
require __DIR__ . '/../layouts/main.php';