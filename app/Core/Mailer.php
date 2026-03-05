<?php
declare(strict_types=1);

namespace App\Core;

final class Mailer
{
    private array $config;
    private $socket = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $host = (string)($this->config['host'] ?? '');
        $port = (int)($this->config['port'] ?? 587);
        $user = (string)($this->config['user'] ?? '');
        $pass = (string)($this->config['pass'] ?? '');
        $secure = strtolower((string)($this->config['secure'] ?? 'tls'));
        $timeout = (int)($this->config['timeout'] ?? 10);
        $fromEmail = trim((string)($this->config['from_email'] ?? ''));
        $fromName = trim((string)($this->config['from_name'] ?? 'TacoMap France'));

        if ($host === '' || $port <= 0 || $fromEmail === '') {
            return false;
        }

        $transportHost = $secure === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @fsockopen($transportHost, $port, $errno, $errstr, $timeout);
        if ($socket === false) {
            return false;
        }

        $this->socket = $socket;

        if (!$this->expect([220])) {
            return $this->close(false);
        }

        if (!$this->sendCommand('EHLO tacomap.local', [250])) {
            return $this->close(false);
        }

        if ($secure === 'tls') {
            if (!$this->sendCommand('STARTTLS', [220])) {
                return $this->close(false);
            }
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return $this->close(false);
            }
            if (!$this->sendCommand('EHLO tacomap.local', [250])) {
                return $this->close(false);
            }
        }

        if ($user !== '' || $pass !== '') {
            if (!$this->sendCommand('AUTH LOGIN', [334])) {
                return $this->close(false);
            }
            if (!$this->sendCommand(base64_encode($user), [334])) {
                return $this->close(false);
            }
            if (!$this->sendCommand(base64_encode($pass), [235])) {
                return $this->close(false);
            }
        }

        if (!$this->sendCommand('MAIL FROM:<' . $fromEmail . '>', [250])) {
            return $this->close(false);
        }
        if (!$this->sendCommand('RCPT TO:<' . $to . '>', [250, 251])) {
            return $this->close(false);
        }
        if (!$this->sendCommand('DATA', [354])) {
            return $this->close(false);
        }

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $this->formatAddress($fromName, $fromEmail),
            'To: <' . $to . '>',
            'Subject: ' . $this->encodeSubject($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $this->normalizeBody($body) . "\r\n.\r\n";
        if (@fwrite($this->socket, $message) === false) {
            return $this->close(false);
        }
        if (!$this->expect([250])) {
            return $this->close(false);
        }

        @fwrite($this->socket, "QUIT\r\n");
        return $this->close(true);
    }

    private function sendCommand(string $command, array $expectedCodes): bool
    {
        if (@fwrite($this->socket, $command . "\r\n") === false) {
            return false;
        }
        return $this->expect($expectedCodes);
    }

    private function expect(array $expectedCodes): bool
    {
        if (!is_resource($this->socket)) {
            return false;
        }

        $line = '';
        while (($chunk = fgets($this->socket, 515)) !== false) {
            $line = $chunk;
            if (strlen($chunk) < 4 || $chunk[3] !== '-') {
                break;
            }
        }

        if ($line === '') {
            return false;
        }

        $code = (int)substr($line, 0, 3);
        return in_array($code, $expectedCodes, true);
    }

    private function close(bool $result): bool
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->socket = null;
        return $result;
    }

    private function formatAddress(string $name, string $email): string
    {
        $cleanName = trim(str_replace(['"', "\r", "\n"], '', $name));
        if ($cleanName === '') {
            return '<' . $email . '>';
        }
        return '"' . $cleanName . '" <' . $email . '>';
    }

    private function encodeSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private function normalizeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);

        $lines = explode("\r\n", $body);
        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }

        return implode("\r\n", $lines);
    }
}
