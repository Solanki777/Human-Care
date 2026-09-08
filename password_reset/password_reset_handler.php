<?php

function generatePasswordResetToken(): string
{
    return bin2hex(random_bytes(32));
}

function getPasswordResetExpiry(): string
{
    return date('Y-m-d H:i:s', time() + (3 * 60));
}

function hashPasswordResetToken(string $token): string
{
    return hash('sha256', $token);
}