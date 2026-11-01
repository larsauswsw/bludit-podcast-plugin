<?php
/**
 * Konstanten, die Bludit zur Laufzeit in bl-kernel/boot/init.php definiert –
 * für PHPStan, das Bludit nur liest. Werte sind Platzhalter.
 */
foreach ([
    'BLUDIT' => true, 'DS' => '/', 'CHARSET' => 'UTF-8', 'LOG_SEP' => ' | ',
    'DOMAIN_BASE' => '', 'DOMAIN_ADMIN' => '', 'DOMAIN_UPLOADS' => '', 'DOMAIN_PLUGINS' => '',
    'HTML_PATH_ROOT' => '/', 'HTML_PATH_ADMIN_ROOT' => '/admin/',
    'PATH_UPLOADS' => '', 'PATH_PLUGINS' => '', 'PATH_WORKSPACES' => '', 'PATH_CONTENT' => '',
    'PATH_PLUGINS_DATABASES' => '', 'DB_DATE_FORMAT' => 'Y-m-d H:i:s', 'DIR_PERMISSIONS' => 0755,
    'ALERT_STATUS_OK' => 0, 'ALERT_STATUS_FAIL' => 1, 'LOG_TYPE_ERROR' => 'error',
] as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}
