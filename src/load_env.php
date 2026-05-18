<?php
// Minimal .env loader for development only.
// Prefer a PHP config file `config.local.php` at project root when present.
// Loads `config.local.php` (array) into environment variables when present.
// Falls back to project-root `.env` if no PHP config is found.
// Does not override existing environment values.

// Load PHP config first if available
$phpConfig = realpath(__DIR__ . '/../config.local.php');
if ($phpConfig && is_readable($phpConfig)) {
    $cfg = include $phpConfig;
    if (is_array($cfg)) {
        foreach ($cfg as $name => $value) {
            if (getenv($name) === false && !isset($_ENV[$name]) && !isset($_SERVER[$name])) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
} else {
    // Fallback to legacy .env loader
    $dotenv = realpath(__DIR__ . '/../.env');
    if ($dotenv && is_readable($dotenv)) {
        $lines = file($dotenv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // strip surrounding quotes
            if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === '\'' && substr($value, -1) === '\''))) {
                $value = substr($value, 1, -1);
            }
            // only set if not already present in env
            if (getenv($name) === false && !isset($_ENV[$name]) && !isset($_SERVER[$name])) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
