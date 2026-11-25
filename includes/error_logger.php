<?php

require_once __DIR__ . '/env.php';

$errorLogPath = env('ERROR_LOG_PATH', dirname(__DIR__) . '/logs/error_log');
if (!is_string($errorLogPath) || trim($errorLogPath) === '' || strpos($errorLogPath, "\0") !== false) {
    $errorLogPath = dirname(__DIR__) . '/logs/error_log';
}
$errorLogPath = trim($errorLogPath);

if (!preg_match('~^(?:[A-Za-z]:[\\\\/]|/|\\\\\\\\)~', $errorLogPath)) {
    $errorLogPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $errorLogPath;
}

$errorLogDirectory = dirname($errorLogPath);
if (
    (!is_dir($errorLogDirectory) && !@mkdir($errorLogDirectory, 0700, true) && !is_dir($errorLogDirectory))
    || !is_writable($errorLogDirectory)
) {
    error_log('Unable to create or write to the configured PHP error log directory: ' . $errorLogDirectory);
    return;
}

$logFile = @fopen($errorLogPath, 'ab');
if ($logFile === false) {
    error_log('Unable to open the configured PHP error log: ' . $errorLogPath);
    return;
}
fclose($logFile);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', $errorLogPath);
