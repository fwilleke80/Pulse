<?php

declare(strict_types=1);

$password = 'SuperSecretPulsePassword1980to9999!';
$hash = '$2y$12$P7NdHvKZfM7GeZ6i0Lk3/eAa8zgix2mwNvpQDXj.cMwxc0ZvEgEty';

echo '<pre>';
var_dump(password_verify($password, $hash));
echo '</pre>';