<?php
declare(strict_types=1);

/**
 * Clean URL forwarder for exam module.
 * Forwards /exam/ or /exam/index.php to join.php while preserving query parameters.
 */
$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: join.php' . $query, true, 302);
exit;
