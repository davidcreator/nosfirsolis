<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be executed from CLI." . PHP_EOL);
    exit(1);
}

$bytes = random_bytes(48);
$key = bin2hex($bytes);

echo '# Copy these values to your .env' . PHP_EOL;
echo 'TOKEN_CIPHER_KEY=' . $key . PHP_EOL;
echo 'TOKEN_CIPHER_KEY_PREVIOUS=' . PHP_EOL;
