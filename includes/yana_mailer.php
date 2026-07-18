<?php
declare(strict_types=1);

function yana_mail_config(): array
{
    $config = [
        'app_name' => 'Party4U',
        'base_url' => '',
        'from_email' => 'party4ucarrentals@gmail.com',
        'from_name' => 'Party4U',
        'show_verification_link_on_failure' => false,
        'smtp' => [
            'enabled' => false,
            'host' => '',
            'username' => '',
            'password' => 'nowb orwk ikez qdqn',
            'port' => 587,
            'encryption' => 'tls',
        ],
    ];

    $local_config_path = __DIR__ . '/yana_mail_config.php';
    if (is_file($local_config_path)) {
        $local_config = require $local_config_path;
        if (is_array($local_config)) {
            $config = array_replace_recursive($config, $local_config);
        }
    }

    return $config;
}

function yana_site_url(string $path = ''): string
{
    $config = yana_mail_config();
    $base_url = trim((string) $config['base_url']);

    if ($base_url === '') {
        $is_https = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
        );
        $scheme = $is_https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $base_url = $scheme . '://' . $host . rtrim($script_dir, '/');
    }

    return rtrim($base_url, '/') . '/' . ltrim($path, '/');
}

function yana_send_verification_email(string $to_email, string $to_name, string $verify_link): array
{
    $config = yana_mail_config();
    $config_error = yana_validate_mail_config($config);
    if ($config_error !== '') {
        return ['sent' => false, 'method' => 'config', 'error' => $config_error];
    }

    $subject = 'Verify your ' . $config['app_name'] . ' account';
    $safe_name = htmlspecialchars($to_name, ENT_QUOTES, 'UTF-8');
    $safe_link = htmlspecialchars($verify_link, ENT_QUOTES, 'UTF-8');
    $plain_body = "Hi {$to_name},\n\nPlease verify your {$config['app_name']} account by opening this link:\n{$verify_link}\n\nIf you did not create this account, you can ignore this email.";
    $html_body = "
        <h2>Welcome to {$config['app_name']}, {$safe_name}!</h2>
        <p>Please confirm your registration by clicking the button below.</p>
        <p><a href=\"{$safe_link}\" style=\"display:inline-block;padding:10px 16px;background:#0d6efd;color:#ffffff;text-decoration:none;border-radius:6px;\">Verify Email</a></p>
        <p>If the button does not work, copy and paste this link into your browser:</p>
        <p><a href=\"{$safe_link}\">{$safe_link}</a></p>
    ";

    if (!empty($config['smtp']['enabled'])) {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        }

        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return yana_send_with_phpmailer($config, $to_email, $to_name, $subject, $html_body, $plain_body);
        }

        return yana_send_with_smtp_socket($config, $to_email, $to_name, $subject, $html_body, $plain_body);
    }

    return yana_send_with_php_mail($config, $to_email, $subject, $html_body);
}

function yana_validate_mail_config(array $config): string
{
    if (empty($config['smtp']['enabled'])) {
        return '';
    }

    $from_email = (string) $config['from_email'];
    $username = (string) $config['smtp']['username'];
    $password = yana_normalize_app_password((string) $config['smtp']['password']);

    if ($from_email === 'your-email@gmail.com' || $username === 'your-email@gmail.com') {
        return 'Gmail SMTP is not configured yet. Replace your-email@gmail.com in includes/yana_mail_config.php.';
    }

    if ($password === 'your-google-app-password') {
        return 'Gmail SMTP is not configured yet. Replace your-google-app-password with a Google App Password.';
    }

    if (!filter_var($from_email, FILTER_VALIDATE_EMAIL) || !filter_var($username, FILTER_VALIDATE_EMAIL)) {
        return 'Gmail SMTP email address is invalid in includes/yana_mail_config.php.';
    }

    return '';
}

function yana_send_with_phpmailer(
    array $config,
    string $to_email,
    string $to_name,
    string $subject,
    string $html_body,
    string $plain_body
): array {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = (string) $config['smtp']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = (string) $config['smtp']['username'];
        $mail->Password = yana_normalize_app_password((string) $config['smtp']['password']);
        $mail->Port = (int) $config['smtp']['port'];

        $encryption = strtolower((string) $config['smtp']['encryption']);
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom((string) $config['from_email'], (string) $config['from_name']);
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $plain_body;

        $mail->send();

        return ['sent' => true, 'method' => 'smtp', 'error' => ''];
    } catch (Throwable $exception) {
        error_log('Verification email failed: ' . $exception->getMessage());

        return ['sent' => false, 'method' => 'smtp', 'error' => $exception->getMessage()];
    }
}

function yana_send_with_php_mail(array $config, string $to_email, string $subject, string $html_body): array
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
    ];

    $sent = mail($to_email, $subject, $html_body, implode("\r\n", $headers));

    if (!$sent) {
        error_log('Verification email failed using PHP mail(). Configure SMTP in includes/yana_mail_config.php.');
    }

    return ['sent' => $sent, 'method' => 'mail', 'error' => $sent ? '' : 'PHP mail() failed'];
}

function yana_send_with_smtp_socket(
    array $config,
    string $to_email,
    string $to_name,
    string $subject,
    string $html_body,
    string $plain_body
): array {
    $host = (string) $config['smtp']['host'];
    $port = (int) $config['smtp']['port'];
    $username = (string) $config['smtp']['username'];
    $password = yana_normalize_app_password((string) $config['smtp']['password']);
    $encryption = strtolower((string) $config['smtp']['encryption']);

    if ($host === '' || $username === '' || $password === '') {
        return ['sent' => false, 'method' => 'smtp_socket', 'error' => 'SMTP settings are incomplete'];
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
    $socket = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 20);

    if (!$socket) {
        error_log("SMTP connection failed: {$errstr} ({$errno})");
        return ['sent' => false, 'method' => 'smtp_socket', 'error' => $errstr];
    }

    stream_set_timeout($socket, 20);

    try {
        yana_smtp_expect($socket, [220]);
        yana_smtp_command($socket, 'EHLO localhost', [250]);

        if ($encryption === 'tls') {
            yana_smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to start TLS encryption.');
            }
            yana_smtp_command($socket, 'EHLO localhost', [250]);
        }

        yana_smtp_command($socket, 'AUTH LOGIN', [334]);
        yana_smtp_command($socket, base64_encode($username), [334]);
        yana_smtp_command($socket, base64_encode($password), [235]);

        yana_smtp_command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', [250]);
        yana_smtp_command($socket, 'RCPT TO:<' . $to_email . '>', [250, 251]);
        yana_smtp_command($socket, 'DATA', [354]);

        $boundary = 'party4u_' . bin2hex(random_bytes(12));
        $headers = [
            'From: ' . yana_mime_header((string) $config['from_name']) . ' <' . $config['from_email'] . '>',
            'To: ' . yana_mime_header($to_name) . ' <' . $to_email . '>',
            'Subject: ' . yana_mime_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= $plain_body . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $html_body . "\r\n\r\n";
        $message .= "--{$boundary}--";

        fwrite($socket, str_replace("\n.", "\n..", $message) . "\r\n.\r\n");
        yana_smtp_expect($socket, [250]);
        yana_smtp_command($socket, 'QUIT', [221]);
        fclose($socket);

        return ['sent' => true, 'method' => 'smtp_socket', 'error' => ''];
    } catch (Throwable $exception) {
        fclose($socket);
        error_log('Verification email failed using SMTP socket: ' . $exception->getMessage());

        return ['sent' => false, 'method' => 'smtp_socket', 'error' => $exception->getMessage()];
    }
}

function yana_smtp_command($socket, string $command, array $expected_codes): string
{
    fwrite($socket, $command . "\r\n");

    return yana_smtp_expect($socket, $expected_codes);
}

function yana_smtp_expect($socket, array $expected_codes): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expected_codes, true)) {
        throw new RuntimeException('Unexpected SMTP response: ' . trim($response));
    }

    return $response;
}

function yana_mime_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function yana_normalize_app_password(string $password): string
{
    return preg_replace('/\s+/', '', $password) ?? $password;
}
