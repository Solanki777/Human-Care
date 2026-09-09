<?php

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

return [
    'host'       => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
    'username'   => $_ENV['MAIL_USERNAME'] ?? '',
    'password'   => $_ENV['MAIL_PASSWORD'] ?? '',
    'port'       => (int)($_ENV['MAIL_PORT'] ?? 587),
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
    'from_email' => $_ENV['MAIL_FROM_EMAIL'] ?? '',
    'from_name'  => $_ENV['MAIL_FROM_NAME'] ?? 'Human Care'
];