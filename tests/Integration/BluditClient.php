<?php

/**
 * Minimaler HTTP-Client für die Integrationstests: eigene Sitzung (Cookie-Datei),
 * keine automatischen Weiterleitungen, damit Umleitungen prüfbar bleiben.
 */
class BluditClient
{
    /** @var string */
    private $base;
    /** @var string */
    private $cookieJar;
    /** @var int */
    public $status = 0;
    /** @var string|null */
    public $location;

    public function __construct(string $base)
    {
        $this->base      = rtrim($base, '/');
        $this->cookieJar = (string) tempnam(sys_get_temp_dir(), 'bludit-cookies-');
    }

    public function get(string $path): string
    {
        return $this->request($path, null);
    }

    /** @param array<string, mixed> $fields Werte vom Typ CURLFile werden als Datei gesendet */
    public function post(string $path, array $fields): string
    {
        $multipart = false;
        array_walk_recursive($fields, function ($value) use (&$multipart) {
            $multipart = $multipart || $value instanceof CURLFile;
        });
        return $this->request($path, $multipart ? $fields : http_build_query($fields));
    }

    /** @param array<string, mixed>|string|null $body */
    private function request(string $path, $body): string
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_PROXY          => '',
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = (string) curl_exec($ch);
        if (curl_errno($ch) !== 0) {
            throw new RuntimeException('HTTP-Fehler bei ' . $path . ': ' . curl_error($ch));
        }
        $headerSize     = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headers        = substr($response, 0, $headerSize);
        $this->location = preg_match('/^Location:\s*(\S+)/mi', $headers, $m) === 1 ? $m[1] : null;
        curl_close($ch);
        return substr($response, $headerSize);
    }

    /** Hat die letzte Antwort weitergeleitet? (Bludit nutzt 301, siehe helpers/redirect.class.php) */
    public function redirected(): bool
    {
        return in_array($this->status, [301, 302, 303], true) && $this->location !== null;
    }

    /** CSRF-Token aus einer Admin-Seite (Formularfeld oder Bludits JS-Variable). */
    public static function token(string $html): string
    {
        if (preg_match('/name="tokenCSRF"\s+value="([^"]+)"/', $html, $m) === 1
            || preg_match('/var tokenCSRF = "([^"]+)"/', $html, $m) === 1) {
            return $m[1];
        }
        throw new RuntimeException('Kein CSRF-Token auf der Seite gefunden. Anfang der Seite: '
            . substr(trim((string) preg_replace('/\s+/', ' ', strip_tags($html))), 0, 300));
    }

    public function login(string $username, string $password): void
    {
        $page = $this->get('/admin/login');
        if ($this->status !== 200) {
            throw new RuntimeException('Anmeldeseite: HTTP ' . $this->status . ', Weiterleitung nach ' . (string) $this->location);
        }
        $token = self::token($page);
        $this->post('/admin/login', ['tokenCSRF' => $token, 'username' => $username, 'password' => $password]);
        if (!$this->redirected() || strpos((string) $this->location, 'dashboard') === false) {
            throw new RuntimeException('Anmeldung als ' . $username . ' fehlgeschlagen (HTTP ' . $this->status . ').');
        }
    }

    /** Ruft die Episoden-API auf und liefert die JSON-Antwort. @return array<string, mixed> */
    public function api(array $fields, bool $withToken = true): array
    {
        if ($withToken) {
            $fields['tokenCSRF'] = self::token($this->get('/admin/plugin/podcast'));
        }
        $body = $this->post('/admin/plugin/podcast?podcast_api=1', $fields);
        $data = json_decode($body, true);
        return is_array($data) ? $data : ['_raw' => $body, '_status' => $this->status];
    }
}
