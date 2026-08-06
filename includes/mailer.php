<?php
/**
 * Outgoing email for the contact form and password resets.
 *
 * Reads settings from config/config.php (MAIL_METHOD etc — see
 * config/config.sample.php). No email credentials live in public files.
 *
 * MAIL_METHOD = 'mail' uses PHP's built-in mail() (works on most cPanel
 * hosts out of the box). MAIL_METHOD = 'smtp' uses the small SMTP client
 * below — no external library or Composer install required.
 */

function send_email(string $to, string $subject, string $bodyText, ?string $replyTo = null): bool
{
    $fromAddress = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@example.com';
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Website';

    try {
        if (defined('MAIL_METHOD') && MAIL_METHOD === 'smtp') {
            return smtp_send($to, $subject, $bodyText, $fromAddress, $fromName, $replyTo);
        }
        return mail_via_php($to, $subject, $bodyText, $fromAddress, $fromName, $replyTo);
    } catch (Throwable $e) {
        error_log('Email send failed: ' . $e->getMessage());
        return false;
    }
}

function mail_via_php(string $to, string $subject, string $bodyText, string $fromAddress, string $fromName, ?string $replyTo): bool
{
    $headers = [];
    $headers[] = 'From: ' . mail_encode_header($fromName) . ' <' . $fromAddress . '>';
    if ($replyTo) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return mail($to, $encodedSubject, wordwrap($bodyText, 70), implode("\r\n", $headers));
}

function mail_encode_header(string $text): string
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

/**
 * Minimal SMTP client (STARTTLS/SSL + AUTH LOGIN). No external dependency.
 * Sufficient for transactional email volumes (contact form, password resets).
 */
function smtp_send(string $to, string $subject, string $bodyText, string $fromAddress, string $fromName, ?string $replyTo): bool
{
    $host = SMTP_HOST;
    $port = SMTP_PORT ?: 587;
    $encryption = SMTP_ENCRYPTION ?: 'tls';

    $transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
    $socket = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 15);
    if (!$socket) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }

    $expect = function (int $code) use ($socket): string {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        if ((int) substr($response, 0, 3) !== $code) {
            throw new RuntimeException('Unexpected SMTP response: ' . $response);
        }
        return $response;
    };

    $send = function (string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };

    try {
        $expect(220);
        $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $expect(250);

        if ($encryption === 'tls') {
            $send('STARTTLS');
            $expect(220);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
            $expect(250);
        }

        if (SMTP_USERNAME) {
            $send('AUTH LOGIN');
            $expect(334);
            $send(base64_encode(SMTP_USERNAME));
            $expect(334);
            $send(base64_encode(SMTP_PASSWORD));
            $expect(235);
        }

        $send('MAIL FROM:<' . $fromAddress . '>');
        $expect(250);
        $send('RCPT TO:<' . $to . '>');
        $expect(250);
        $send('DATA');
        $expect(354);

        $headers = [];
        $headers[] = 'From: ' . mail_encode_header($fromName) . ' <' . $fromAddress . '>';
        $headers[] = 'To: <' . $to . '>';
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Date: ' . date('r');

        $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $bodyText) . "\r\n.";
        $send($message);
        $expect(250);

        $send('QUIT');
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('SMTP error: ' . $e->getMessage());
        fclose($socket);
        return false;
    }
}
