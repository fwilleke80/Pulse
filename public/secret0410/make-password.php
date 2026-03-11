<?php

declare(strict_types=1);

$password = 'SuperSecretPulsePassword1980to9999!';

echo '<pre>';
echo htmlspecialchars(password_hash($password, PASSWORD_DEFAULT), ENT_QUOTES, 'UTF-8');
echo '</pre>';
