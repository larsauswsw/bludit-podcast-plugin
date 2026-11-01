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
// Bludits index.php prüft die Installation mit einem relativen Pfad
// (bl-content/databases/site.php) – wie unter Apache im Bludit-Ordner arbeiten
chdir($_SERVER['DOCUMENT_ROOT']);
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
