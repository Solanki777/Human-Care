<?php

$envFile = dirname(__DIR__) . '/.env';

if (!file_exists($envFile)) {
    die('.env file not found.');
}

$env = parse_ini_file($envFile);

if ($env === false) {
    die('Unable to load .env file.');
}

function env(string $key, mixed $default = null): mixed
{
    global $env;

    return $env[$key] ?? $default;
}