<?php
/**
 * Kodukontroll — form endpoint.
 *
 * Takes both site forms (#contact and #report, in all three languages), mails
 * the entry to info@kodukontroll.ee and appends it to storage/leads.csv so a
 * lead is never lost even if mail delivery fails.
 *
 * Answers JSON when the page posts with fetch (main.js), and a small HTML page
 * when a browser posts the form directly (no JS).
 *
 * Mail goes out with PHP mail(), which on Hostinger hands it to the local MTA.
 * Nothing here needs credentials, so nothing secret lives in this repo.
 */

declare(strict_types=1);

/* A stray notice in the body would break the JSON the page parses. */
ini_set('display_errors', '0');

date_default_timezone_set('Europe/Tallinn');
mb_internal_encoding('UTF-8');

/* ---------------------------------------------------------------- config -- */

$CONFIG = [
    'to'        => 'info@kodukontroll.ee',
    'from'      => 'Kodukontroll <info@kodukontroll.ee>',
    'log'       => __DIR__ . '/storage/leads.csv',
    'rate_file' => __DIR__ . '/storage/rate.json',
    'rate_max'  => 10,    // submissions per IP …
    'rate_win'  => 3600,  // … per this many seconds
    'min_fill'  => 3,     // a human needs at least this many seconds to fill it in
    'max_len'   => 2000,  // per field
];

/* The two forms: which fields are accepted, which are required. */
$FORMS = [
    'contact' => [
        'fields'   => ['name', 'phone', 'email', 'address', 'what', 'date', 'language'],
        'required' => ['name', 'phone', 'email'],
    ],
    'report' => [
        'fields'   => ['name', 'email'],
        'required' => ['name', 'email'],
    ],
];

/* Field labels for the e-mail body, so it reads the way the visitor saw it. */
$LABELS = [
    'et' => [
        'name' => 'Nimi', 'phone' => 'Telefon', 'email' => 'E-post',
        'address' => 'Objekti aadress või piirkond', 'what' => 'Mida on vaja kontrollida',
        'date' => 'Soovitud kuupäev', 'language' => 'Eelistatud suhtluskeel',
    ],
    'en' => [
        'name' => 'Name', 'phone' => 'Phone', 'email' => 'E-mail',
        'address' => 'Object address or area', 'what' => 'What needs checking',
        'date' => 'Preferred date', 'language' => 'Preferred language',
    ],
    'ru' => [
        'name' => 'Имя', 'phone' => 'Телефон', 'email' => 'Эл. почта',
        'address' => 'Адрес или район объекта', 'what' => 'Что нужно проверить',
        'date' => 'Желаемая дата', 'language' => 'Предпочитаемый язык',
    ],
];

$SUBJECTS = [
    'contact' => ['et' => 'Uus päring', 'en' => 'New request', 'ru' => 'Новый запрос'],
    'report'  => ['et' => 'Näidisaruande päring', 'en' => 'Sample report request', 'ru' => 'Запрос примера отчёта'],
];

/* Copy for the no-JS response page and the JSON messages. */
$TEXT = [
    'et' => [
        'ok_title'   => 'Aitäh! Päring on saadetud.',
        'ok_text'    => 'Vastame tavaliselt ühe tööpäeva jooksul. Kui vastust ei tule, kirjutage otse aadressile info@kodukontroll.ee.',
        'ok_report'  => 'Aitäh! Saadame näidisaruande e-postiga lähiajal.',
        'err_title'  => 'Päringut ei õnnestunud saata.',
        'err_text'   => 'Palun proovige uuesti.',
        'err_fields' => 'Palun täitke kohustuslikud väljad.',
        'err_email'  => 'Palun kontrollige e-posti aadressi.',
        'err_rate'   => 'Liiga palju päringuid. Proovige hiljem uuesti või kirjutage aadressile info@kodukontroll.ee.',
        'back'       => 'Tagasi avalehele',
        'home'       => '/',
    ],
    'en' => [
        'ok_title'   => 'Thank you. Your request has been sent.',
        'ok_text'    => 'We normally reply within one working day. If you hear nothing, write to info@kodukontroll.ee directly.',
        'ok_report'  => 'Thank you. We will send the sample report by e-mail shortly.',
        'err_title'  => 'The request could not be sent.',
        'err_text'   => 'Please try again.',
        'err_fields' => 'Please fill in the required fields.',
        'err_email'  => 'Please check the e-mail address.',
        'err_rate'   => 'Too many requests. Try again later or write to info@kodukontroll.ee.',
        'back'       => 'Back to the home page',
        'home'       => '/en/',
    ],
    'ru' => [
        'ok_title'   => 'Спасибо! Запрос отправлен.',
        'ok_text'    => 'Обычно отвечаем в течение одного рабочего дня. Если ответа нет, напишите напрямую на info@kodukontroll.ee.',
        'ok_report'  => 'Спасибо! В ближайшее время пришлём пример отчёта по электронной почте.',
        'err_title'  => 'Не удалось отправить запрос.',
        'err_text'   => 'Попробуйте ещё раз.',
        'err_fields' => 'Пожалуйста, заполните обязательные поля.',
        'err_email'  => 'Проверьте адрес электронной почты.',
        'err_rate'   => 'Слишком много запросов. Попробуйте позже или напишите на info@kodukontroll.ee.',
        'back'       => 'На главную',
        'home'       => '/ru/',
    ],
];

/* ----------------------------------------------------------------- input -- */

$lang = post('lang');
$lang = isset($TEXT[$lang]) ? $lang : 'et';
$t    = $TEXT[$lang] + ['lang' => $lang];

$formId = post('form');
$formId = isset($FORMS[$formId]) ? $formId : 'contact';
$form   = $FORMS[$formId];

$wantsJson = post('ajax') === '1'
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, false, $t['err_title'], $t['err_text'], $wantsJson, $t);
}

/* Reject a cross-site post; browsers send Origin on same-origin posts too.
   Compare the bare hosts — HTTP_HOST carries the port, an Origin's host does not. */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && (string) parse_url($origin, PHP_URL_HOST) !== host_only($_SERVER['HTTP_HOST'] ?? '')) {
    respond(403, false, $t['err_title'], $t['err_text'], $wantsJson, $t);
}

$okText = $formId === 'report' ? $t['ok_report'] : $t['ok_text'];

/* Honeypot: a field no human sees. Bots fill it in; answer them with a smile. */
if (post('website') !== '') {
    respond(200, true, $t['ok_title'], $okText, $wantsJson, $t);
}

/* Submitted faster than a human can type? Only checked when JS set the stamp. */
$started = (int) post('started');
if ($started > 0 && (time() - intdiv($started, 1000)) < $CONFIG['min_fill']) {
    respond(200, true, $t['ok_title'], $okText, $wantsJson, $t);
}

$ip = client_ip();
if (!rate_ok($CONFIG, $ip)) {
    respond(429, false, $t['err_title'], $t['err_rate'], $wantsJson, $t);
}

/* Collect and trim the fields this form is allowed to send. */
$values = [];
foreach ($form['fields'] as $field) {
    $values[$field] = mb_substr(post($field), 0, $CONFIG['max_len']);
}

foreach ($form['required'] as $field) {
    if ($values[$field] === '') {
        respond(422, false, $t['err_title'], $t['err_fields'], $wantsJson, $t);
    }
}

$email = filter_var($values['email'], FILTER_VALIDATE_EMAIL);
if ($email === false) {
    respond(422, false, $t['err_title'], $t['err_email'], $wantsJson, $t);
}
$values['email'] = $email;

/* ------------------------------------------------------------------ send -- */

$page = post('page');
$meta = [
    'form' => $formId,
    'lang' => $lang,
    'page' => $page,
    'time' => date('d.m.Y H:i'),
    'ip'   => $ip,
    'ua'   => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300),
];

$logged = log_lead($CONFIG['log'], $values, $meta);
$sent   = send_mail($CONFIG, $SUBJECTS[$formId][$lang], $LABELS[$lang], $values, $meta);

if (!$sent && !$logged) {
    error_log('kodukontroll: form entry lost (mail and log both failed) ' . json_encode($values, JSON_UNESCAPED_UNICODE));
    respond(500, false, $t['err_title'], $t['err_text'], $wantsJson, $t);
}
if (!$sent) {
    /* The lead is safe in the CSV; tell the visitor it went through. */
    error_log('kodukontroll: mail() failed, entry kept in ' . $CONFIG['log']);
}

respond(200, true, $t['ok_title'], $okText, $wantsJson, $t);

/* ------------------------------------------------------------- functions -- */

function host_only(string $host): string
{
    $host = strtolower(trim($host));
    if (($pos = strrpos($host, ':')) !== false && strpos($host, ']') === false) {
        $host = substr($host, 0, $pos);   // strip :port (bare IPv6 never reaches us)
    }
    return $host;
}

function post(string $key): string
{
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function client_ip(): string
{
    $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($fwd !== '') {
        $first = trim(explode(',', $fwd)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/** Best-effort per-IP throttle. X-Forwarded-For can be spoofed; this only has
 *  to slow down the ordinary bot that hammers the endpoint. */
function rate_ok(array $config, string $ip): bool
{
    if ($ip === '' || !ensure_storage(dirname($config['rate_file']))) {
        return true;
    }
    $fh = @fopen($config['rate_file'], 'c+');
    if ($fh === false) {
        return true;
    }
    $ok = true;
    if (flock($fh, LOCK_EX)) {
        $raw  = stream_get_contents($fh);
        $data = json_decode($raw ?: '[]', true);
        $data = is_array($data) ? $data : [];

        $now   = time();
        $key   = hash('sha256', $ip);
        $stamps = array_values(array_filter(
            $data[$key] ?? [],
            static fn($s) => is_int($s) && $s > $now - $config['rate_win']
        ));

        if (count($stamps) >= $config['rate_max']) {
            $ok = false;
        } else {
            $stamps[] = $now;
        }
        $data[$key] = $stamps;

        /* Drop entries whose window has passed, so the file cannot grow forever. */
        foreach ($data as $k => $v) {
            if (!is_array($v) || $v === [] || max($v) < $now - $config['rate_win']) {
                unset($data[$k]);
            }
        }

        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($data));
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    return $ok;
}

function ensure_storage(string $dir): bool
{
    if (is_dir($dir)) {
        return is_writable($dir);
    }
    return @mkdir($dir, 0755, true);
}

/** Appends the entry to a CSV. This is the copy that survives a mail failure. */
function log_lead(string $path, array $values, array $meta): bool
{
    if (!ensure_storage(dirname($path))) {
        return false;
    }
    $columns = ['time', 'form', 'lang', 'name', 'email', 'phone', 'address', 'what', 'date', 'language', 'page', 'ip', 'ua'];
    $row     = [];
    foreach ($columns as $c) {
        $row[] = $meta[$c] ?? $values[$c] ?? '';
    }

    $isNew = !file_exists($path);
    $fh    = @fopen($path, 'a');
    if ($fh === false) {
        return false;
    }
    $ok = false;
    if (flock($fh, LOCK_EX)) {
        if ($isNew) {
            fwrite($fh, "\xEF\xBB\xBF");           // BOM, so Excel reads the UTF-8
            fputcsv($fh, $columns, ',', '"', '');
        }
        $ok = fputcsv($fh, $row, ',', '"', '') !== false;
        fflush($fh);
        flock($fh, LOCK_UN);
    }
    fclose($fh);
    @chmod($path, 0600);
    return $ok;
}

function send_mail(array $config, string $subject, array $labels, array $values, array $meta): bool
{
    $lines = [];
    foreach ($values as $field => $value) {
        if ($value === '') {
            continue;
        }
        $label = $labels[$field] ?? $field;
        if ($field === 'what') {
            $lines[] = '';
            $lines[] = $label . ':';
            $lines[] = $value;
        } else {
            $lines[] = $label . ': ' . $value;
        }
    }

    $lines[] = '';
    $lines[] = str_repeat('-', 48);
    $lines[] = 'Vorm: ' . $meta['form'] . ' · keel: ' . $meta['lang'] . ($meta['page'] !== '' ? ' · leht: ' . $meta['page'] : '');
    $lines[] = 'Aeg: ' . $meta['time'] . ' (Europe/Tallinn)';
    $lines[] = 'IP: ' . $meta['ip'];
    $lines[] = 'Brauser: ' . $meta['ua'];

    /* Normalise every line ending the textarea may carry, then use CRLF throughout. */
    $body = str_replace("\n", "\r\n", preg_replace('/\r\n|\r/', "\n", implode("\n", $lines)));

    $full = 'Kodukontroll: ' . $subject;
    if (($values['address'] ?? '') !== '') {
        $full .= ' — ' . $values['address'];
    }

    $headers = [
        'From: ' . $config['from'],
        'Reply-To: ' . mime_name($values['name']) . ' <' . $values['email'] . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: kodukontroll-form',
    ];

    return @mail(
        $config['to'],
        mime_header(header_safe($full)),
        $body,
        implode("\r\n", $headers)
    );
}

/** Strips anything that could inject an extra mail header. */
function header_safe(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $value));
}

function mime_header(string $value): string
{
    return mb_check_encoding($value, 'ASCII') ? $value : '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/** A display name in a Reply-To: quoted, header-safe, encoded when non-ASCII. */
function mime_name(string $name): string
{
    $name = header_safe(str_replace('"', '', $name));
    return mb_check_encoding($name, 'ASCII') ? '"' . $name . '"' : mime_header($name);
}

/** Sends the answer and stops: JSON for fetch, a small page for a plain post. */
function respond(int $status, bool $ok, string $title, string $text, bool $json, array $t): void
{
    http_response_code($status);
    header('Cache-Control: no-store');

    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'title' => $title, 'message' => $text], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html>
<html lang="' . $e($t['lang']) . '">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>' . $e($title) . ' — Kodukontroll</title>
<meta name="theme-color" content="#F8F6F2">
<link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700;800&family=Arimo:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="nf-body">
<main id="main"><section class="nf"><div class="container">
  <p class="meta">Kodukontroll</p>
  <h1>' . $e($title) . '</h1>
  <p class="lead">' . $e($text) . '</p>
  <div class="actions"><a class="btn" href="' . $e($t['home']) . '"><span>' . $e($t['back']) . '</span></a><a href="mailto:info@kodukontroll.ee">info@kodukontroll.ee</a></div>
</div></section></main>
</body>
</html>';
    exit;
}
