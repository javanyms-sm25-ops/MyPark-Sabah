<?php

function send_registration_link(string $recipient, string $token): void {
    send_account_email($recipient, $token, 'Verify your email address', 'Confirm that {email} is your email address to finish setting up your MyPark Sabah account.', 'pages/register.php');
}

function send_email_change_link(string $recipient, string $token): void {
    send_account_email($recipient, $token, 'Verify your new email address', 'Confirm that {email} is the new email address for your MyPark Sabah account.', 'pages/change-email.php');
}

function send_password_reset_code(string $recipient, string $code): void {
    send_account_email($recipient, $code, 'Reset your password', 'Enter this code on the MyPark Sabah password recovery page to choose a new password.');
}

function account_verification_url(array $config, string $token, string $page): string {
    $appUrl = trim((string)($config['app_url'] ?? ''));
    if ($appUrl === '') {
        // Derive the URL only for local development. Set app_url for any other host.
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!preg_match('/\A(?:localhost|127\.0\.0\.1|\[::1\])(?::([0-9]{1,5}))?\z/i', $host, $parts)
            || (isset($parts[1]) && ((int)$parts[1] < 1 || (int)$parts[1] > 65535))) {
            throw new RuntimeException('Email verification is temporarily unavailable. Please try again later.', 503);
        }
        $scheme = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
        $prefix = trim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/pages/register.php')), '/\\');
        $appUrl = $scheme . '://' . $host;
        if ($prefix !== '') {
            $segments = array_map(static fn(string $part): string => rawurlencode(rawurldecode($part)), explode('/', $prefix));
            $appUrl .= '/' . implode('/', $segments);
        }
    } else {
        $url = parse_url($appUrl);
        if (!filter_var($appUrl, FILTER_VALIDATE_URL)
            || !in_array(strtolower($url['scheme'] ?? ''), ['http', 'https'], true)
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
            throw new RuntimeException('Email verification is temporarily unavailable. Please try again later.', 503);
        }
    }

    return rtrim($appUrl, '/') . '/' . $page . '?verify=' . rawurlencode($token);
}

function account_email_message(string $sender, string $recipient, string $detail, string $title, string $instructions, bool $useLink, bool $sameBrowser = true): string {
    $notice = $useLink ? 'This link expires in 10 minutes and can be used once.' : 'This code expires in 10 minutes.';
    $guidance = 'If you did not request this email, you can ignore it.';
    if ($useLink && $sameBrowser) $guidance = 'Open the link in the browser where you requested it. ' . $guidance;
    $plain = implode("\r\n\r\n", ['MyPark Sabah', $title, str_replace('{email}', $recipient, $instructions), $detail, $notice, $guidance]);
    $heading = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($instructions, ENT_QUOTES, 'UTF-8');
    $detail = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
    $footer = htmlspecialchars($notice, ENT_QUOTES, 'UTF-8');
    $guidance = htmlspecialchars($guidance, ENT_QUOTES, 'UTF-8');

    $logo = @file_get_contents(dirname(__DIR__) . '/assets/images/mypark-email-logo.png') ?: null;
    $brand = 'MyPark Sabah';
    $brandPadding = 29;
    $headingPadding = 18;
    if ($logo !== null) {
        $logoId = 'mypark-logo-' . bin2hex(random_bytes(12)) . '@mypark-sabah.local';
        $brand = '<img src="cid:' . $logoId . '" alt="MyPark Sabah" width="220" style="display:block;width:220px;max-width:100%;height:auto;margin:0 auto;border:0">';
        $brandPadding = 8;
        $headingPadding = 6;
    }

    if ($useLink) {
        $address = htmlspecialchars($recipient, ENT_QUOTES, 'UTF-8');
        $description = str_replace('{email}', '<strong style="color:#172d49">' . $address . '</strong>', $description);
        $content = <<<HTML
<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
  <tr><td align="center" bgcolor="#163d68" style="border-radius:8px">
    <a href="{$detail}" style="display:block;padding:13px 20px;border-radius:8px;color:#fff;font-size:16px;font-weight:bold;text-decoration:none">Verify email</a>
  </td></tr>
</table>
HTML;
    } else {
        $content = <<<HTML
<div style="padding:14px;background:#ebf3fc;border:1px solid #d8e6f4;border-radius:10px;text-align:center">
  <strong style="color:#163d68;font-family:Consolas,monospace;font-size:29px;letter-spacing:5px">{$detail}</strong>
</div>
HTML;
    }
    $html = <<<HTML
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{$heading}</title></head>
<body style="margin:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#172d49">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb">
    <tr><td align="center" style="padding:28px 12px">
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:480px;background:#fff;border:1px solid #dbe5f1;border-radius:14px">
        <tr><td align="center" style="padding:{$brandPadding}px 24px 0;font-size:17px;font-weight:bold;color:#163d68">{$brand}</td></tr>
        <tr><td align="center" style="padding:{$headingPadding}px 24px 0">
          <h1 style="margin:0 0 10px;color:#172d49;font-size:23px;line-height:1.25">{$heading}</h1>
          <p style="margin:0;color:#52677f;font-size:15px;line-height:1.6">{$description}</p>
        </td></tr>
        <tr><td align="center" style="padding:21px 24px">{$content}</td></tr>
        <tr><td align="center" style="padding:0 24px 27px;color:#52677f;font-size:13px;line-height:1.6">
          <p style="margin:0 0 12px">{$footer}</p>
          <p style="margin:0;padding-top:12px;border-top:1px solid #dbe5f1">{$guidance}</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    $boundary = 'mypark-' . bin2hex(random_bytes(12));
    $encode = static fn(string $body): string => rtrim(chunk_split(base64_encode($body), 76, "\r\n"), "\r\n");
    $htmlPart = [
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        $encode($html),
    ];
    if ($logo !== null) {
        $relatedBoundary = 'mypark-related-' . bin2hex(random_bytes(12));
        $htmlPart = [
            'Content-Type: multipart/related; type="text/html"; boundary="' . $relatedBoundary . '"',
            '',
            '--' . $relatedBoundary,
            ...$htmlPart,
            '--' . $relatedBoundary,
            'Content-Type: image/png',
            'Content-Transfer-Encoding: base64',
            'Content-ID: <' . $logoId . '>',
            'Content-Disposition: inline; filename="mypark-logo.png"',
            '',
            $encode($logo),
            '--' . $relatedBoundary . '--',
        ];
    }
    return implode("\r\n", [
        'Date: ' . date(DATE_RFC2822),
        'From: MyPark Sabah <' . $sender . '>',
        'To: <' . $recipient . '>',
        'Subject: MyPark Sabah - ' . $title,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        '',
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        $encode($plain),
        '--' . $boundary,
        ...$htmlPart,
        '--' . $boundary . '--',
        '',
    ]);
}

function send_account_email(string $recipient, string $detail, string $title, string $instructions, ?string $verificationPage = null): void {
    $path = dirname(__DIR__) . '/config/smtp.local.php';
    $config = is_file($path) ? require $path : [];
    $host = trim((string)($config['host'] ?? ''));
    $port = filter_var($config['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    $sender = trim((string)($config['email'] ?? ''));
    $password = preg_replace('/\s+/', '', (string)($config['app_password'] ?? ''));
    if (!extension_loaded('curl') || !in_array('smtp', curl_version()['protocols'], true)
        || !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || $port === false
        || !filter_var($sender, FILTER_VALIDATE_EMAIL) || $password === '') {
        throw new RuntimeException('Email verification is temporarily unavailable. Please try again later.', 503);
    }
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter an email address like name@example.com.', 422);
    }

    $useLink = $verificationPage !== null;
    if ($useLink) $detail = account_verification_url($config, $detail, $verificationPage);
    $message = account_email_message($sender, $recipient, $detail, $title, $instructions, $useLink,
        $verificationPage !== 'pages/register.php');
    $offset = 0;
    $smtp = curl_init('smtp://' . $host . ':' . $port);
    if ($smtp === false) {
        throw new RuntimeException('Email verification is temporarily unavailable. Please try again later.', 503);
    }
    $configured = curl_setopt_array($smtp, [
        CURLOPT_USERNAME => $sender,
        CURLOPT_PASSWORD => $password,
        CURLOPT_USE_SSL => CURLUSESSL_ALL,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_MAIL_FROM => '<' . $sender . '>',
        CURLOPT_MAIL_RCPT => ['<' . $recipient . '>'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_UPLOAD => true,
        CURLOPT_INFILESIZE => strlen($message),
        CURLOPT_READFUNCTION => static function ($handle, $stream, int $length) use ($message, &$offset): string {
            $chunk = substr($message, $offset, $length);
            $offset += strlen($chunk);
            return $chunk;
        },
    ]);
    if (!$configured || curl_exec($smtp) === false) {
        error_log('MyPark account SMTP delivery failed. cURL error ' . curl_errno($smtp) . '.');
        throw new RuntimeException($useLink
            ? 'We could not send your verification link. Please try again.'
            : 'We could not send your verification code. Please try again.', 503);
    }
}
