<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/session.php';

echo "<h1>Session & CSRF Diagnostics</h1>";
echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";

if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
    echo "<p>Started new session counter.</p>";
} else {
    $_SESSION['test_counter']++;
    echo "<p>Session counter: " . $_SESSION['test_counter'] . "</p>";
}

echo "<h3>Cookies:</h3><pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<h3>Session Data:</h3><pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>CSRF Token:</h3>";
echo "<p>Current Tab: " . getTabId() . "</p>";
echo "<p>CSRF Token: " . getCsrfToken() . "</p>";
echo "<p><a href='check_session.php'>Reload Page</a></p>";
