<?php
/**
 * Unit-Tests: Nachbildung der Bludit-Umgebung, soweit plugin.php sie braucht
 * (Konstanten, Sanitize, Log, Plugin-Basisklasse, checkRole). Verhalten wie in
 * Bludit 3.22 (bl-kernel/helpers/sanitize.class.php, abstract/plugin.class.php).
 */

define('BLUDIT', true);
define('DS', DIRECTORY_SEPARATOR);
define('CHARSET', 'UTF-8');
define('DOMAIN_BASE', 'https://example.org/blog/');
define('DOMAIN_UPLOADS', DOMAIN_BASE . 'bl-content/uploads/');
define('DOMAIN_ADMIN', DOMAIN_BASE . 'admin/');
define('DOMAIN_PLUGINS', DOMAIN_BASE . 'bl-plugins/');
define('HTML_PATH_ROOT', '/blog/');
define('HTML_PATH_ADMIN_ROOT', '/blog/admin/');
define('DB_DATE_FORMAT', 'Y-m-d H:i:s');
define('LOG_SEP', ' | ');
define('DIR_PERMISSIONS', 0755);
define('PATH_PLUGINS', dirname(__DIR__) . DS . 'bl-plugins' . DS);

// Upload- und Workspace-Ordner nur für diesen Testlauf
$testTmp = sys_get_temp_dir() . DS . 'podcast-tests-' . getmypid() . DS;
define('PATH_UPLOADS', $testTmp . 'uploads' . DS);
define('PATH_WORKSPACES', $testTmp . 'workspaces' . DS);
register_shutdown_function(function () use ($testTmp) {
    if (!is_dir($testTmp)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testTmp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($testTmp);
});

class Sanitize
{
    public static function html($text)
    {
        return htmlspecialchars($text, ENT_COMPAT | ENT_HTML5, CHARSET);
    }

    public static function htmlDecode($text)
    {
        return htmlspecialchars_decode($text, ENT_COMPAT | ENT_HTML5);
    }
}

class Log
{
    /** @var string[] */
    public static $messages = [];

    public static function set($text, $type = 0)
    {
        self::$messages[] = (string) $text;
    }
}

abstract class Plugin
{
    /** @var array<string, mixed> */
    public $db = [];
    /** @var array<string, mixed> */
    public $dbFields = [];

    public function __construct()
    {
        $this->dbFields = [];
        $this->init();
        $this->db = $this->dbFields;
    }

    public function init()
    {
    }

    public function getValue($field, $html = true)
    {
        if (isset($this->db[$field])) {
            return $html ? $this->db[$field] : Sanitize::htmlDecode($this->db[$field]);
        }
        return $this->dbFields[$field];
    }

    public function setField($field, $value)
    {
        $this->db[$field] = Sanitize::html($value);
        return true;
    }

    public function save()
    {
        return true;
    }

    public function post()
    {
        foreach ($this->dbFields as $field => $value) {
            if (isset($_POST[$field])) {
                $final = Sanitize::html($_POST[$field]);
                settype($final, gettype($value));
                $this->db[$field] = $final;
            }
        }
        return true;
    }

    public function installed()
    {
        return true;
    }

    public function domainPath()
    {
        return DOMAIN_PLUGINS . 'podcast/';
    }

    public function phpPath()
    {
        return PATH_PLUGINS . 'podcast' . DS;
    }

    public function workspace()
    {
        return PATH_WORKSPACES . 'podcast' . DS;
    }

    public function version()
    {
        return '1.0.0';
    }

    public function webhook($URI = false, $returnsAfterURI = false, $fixed = true)
    {
        return false;
    }
}

/** Rolle des angemeldeten Nutzers im Test: $GLOBALS['testRole'] (Standard admin). */
function checkRole($allowRoles, $redirect = true)
{
    return in_array($GLOBALS['testRole'] ?? 'admin', $allowRoles, true);
}

require PATH_PLUGINS . 'podcast' . DS . 'plugin.php';
require __DIR__ . DS . 'PodcastTestCase.php';
