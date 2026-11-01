<?php
/**
 * Router für den PHP-Testserver (php -S … tests/Integration/router.php):
 * vorhandene Dateien direkt ausliefern, alles andere an Bludits index.php –
 * wie die .htaccess von Bludit unter Apache.
 */
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file($_SERVER['DOCUMENT_ROOT'] . $path)) {
    return false;
}
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
