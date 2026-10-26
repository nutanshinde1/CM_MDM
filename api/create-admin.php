<?php
// Run once from a terminal: php api/create-admin.php admin@example.com 'LongUniquePassword'
// This script is intentionally CLI-only; it cannot be invoked through a browser.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/config.php';
if ($argc < 3 || strlen($argv[2]) < 12) { fwrite(STDERR, "Usage: php api/create-admin.php email 'PasswordAtLeast12Chars'\n"); exit(1); }
$pdo=db();$email=strtolower(trim($argv[1]));$password=$argv[2];
$s=$pdo->prepare("INSERT INTO users(name,email,phone,password_hash,role,status) VALUES('Cafeteria Administrator',?,'',?,'ADMIN','ACTIVE')");
$s->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);
echo "Admin created: $email\n";
