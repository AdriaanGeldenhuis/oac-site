<?php
declare(strict_types=1);

function auth_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function auth_require(): void {
    if (!auth_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function auth_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function auth_user_name(): string {
    return $_SESSION['user_name'] ?? 'Gebruiker';
}