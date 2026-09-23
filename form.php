<?php
/**
 * Kodukontroll — form endpoint.
 *
 * Takes the #contact form (in all three languages), mails the entry to info@kodukontroll.ee and appends it to storage/leads.csv so a
 * lead is never lost even if mail delivery fails. Attached files go into the mail and are also kept
 * in storage/uploads/, which is never served.
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
    'rate_max'  => 100,    // submissions per IP …
    'rate_win'  => 3600,  // … per this many seconds
    'min_fill'  => 3,     // a human needs at least this many seconds to fill it in
    'max_len'   => 2000,  // per field
    'uploads'   => __DIR__ . '/storage/uploads',
    'max_files' => 5,
    'max_file'  => 10 * 1024 * 1024,   // per file
    'max_total' => 20 * 1024 * 1024,   // all files; base64 makes the mail about a third bigger
];

/* Attachments we accept, by extension, with the type the mail announces them as. */
$FILE_TYPES = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    'heic' => 'image/heic', 'heif' => 'image/heif', 'pdf' => 'application/pdf', 'txt' => 'text/plain',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'odt' => 'application/vnd.oasis.opendocument.text',
    'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
];

/* The form: which fields are accepted, which are required. */
$FORMS = [
    'contact' => [
        'fields'   => ['name', 'phone', 'email', 'address', 'what', 'date', 'language'],
        'required' => ['name', 'phone', 'email'],
    ],
];

/* Field labels for the e-mail body, so it reads the way the visitor saw it. */
$LABELS = [
    'et' => [
        'name' => 'Nimi', 'phone' => 'Telefon', 'email' => 'E-post',
        'address' => 'Objekti aadress või piirkond', 'what' => 'Mida on vaja kontrollida',
        'date' => 'Soovitud kuupäev', 'language' => 'Eelistatud suhtluskeel', 'files' => 'Lisatud failid',
    ],
    'en' => [
        'name' => 'Name', 'phone' => 'Phone', 'email' => 'E-mail',
        'address' => 'Object address or area', 'what' => 'What needs checking',
        'date' => 'Preferred date', 'language' => 'Preferred language', 'files' => 'Attached files',
    ],
    'ru' => [
        'name' => 'Имя', 'phone' => 'Телефон', 'email' => 'Эл. почта',
        'address' => 'Адрес или район объекта', 'what' => 'Что нужно проверить',
        'date' => 'Желаемая дата', 'language' => 'Предпочитаемый язык', 'files' => 'Приложенные файлы',
    ],
];

$SUBJECTS = [
    'contact' => ['et' => 'Uus päring', 'en' => 'New request', 'ru' => 'Новый запрос'],
];

/* Copy for the no-JS response page and the JSON messages. */
$TEXT = [
    'et' => [
        'ok_title'   => 'Aitäh! Päring on saadetud.',
        'ok_text'    => 'Vastame tavaliselt ühe tööpäeva jooksul. Kui vastust ei tule, kirjutage otse aadressile info@kodukontroll.ee.',
        'err_title'  => 'Päringut ei õnnestunud saata.',
        'err_text'   => 'Palun proovige uuesti.',
        'err_fields' => 'Palun täitke kohustuslikud väljad.',
        'err_email'  => 'Palun kontrollige e-posti aadressi.',
        'err_rate'   => 'Liiga palju päringuid. Proovige hiljem uuesti või kirjutage aadressile info@kodukontroll.ee.',
        'err_count'  => 'Korraga saab lisada kuni 5 faili.',
        'err_size'   => 'Failid on liiga suured: kokku kuni 20 MB, üks fail kuni 10 MB. Suuremad failid saatke aadressile info@kodukontroll.ee.',
        'err_type'   => 'Seda failitüüpi ei saa lisada. Sobivad fotod, PDF, Word ja Excel.',
        'back'       => 'Tagasi avalehele',
        'home'       => '/',
    ],
    'en' => [
        'ok_title'   => 'Thank you. Your request has been sent.',
        'ok_text'    => 'We normally reply within one working day. If you hear nothing, write to info@kodukontroll.ee directly.',
        'err_title'  => 'The request could not be sent.',
        'err_text'   => 'Please try again.',
        'err_fields' => 'Please fill in the required fields.',
        'err_email'  => 'Please check the e-mail address.',
        'err_rate'   => 'Too many requests. Try again later or write to info@kodukontroll.ee.',
        'err_count'  => 'You can attach up to 5 files.',
        'err_size'   => 'The files are too large: 20 MB in total, 10 MB per file. Send larger files to info@kodukontroll.ee.',
        'err_type'   => 'This file type cannot be attached. Photos, PDF, Word and Excel work.',
        'back'       => 'Back to the home page',
        'home'       => '/en/',
    ],
    'ru' => [
        'ok_title'   => 'Спасибо! Запрос отправлен.',
        'ok_text'    => 'Обычно отвечаем в течение одного рабочего дня. Если ответа нет, напишите напрямую на info@kodukontroll.ee.',
        'err_title'  => 'Не удалось отправить запрос.',
        'err_text'   => 'Попробуйте ещё раз.',
        'err_fields' => 'Пожалуйста, заполните обязательные поля.',
        'err_email'  => 'Проверьте адрес электронной почты.',
        'err_rate'   => 'Слишком много запросов. Попробуйте позже или напишите на info@kodukontroll.ee.',
        'err_count'  => 'Можно приложить не более 5 файлов.',
        'err_size'   => 'Файлы слишком большие: всего до 20 МБ, один файл до 10 МБ. Большие файлы пришлите на info@kodukontroll.ee.',
        'err_type'   => 'Этот тип файла нельзя приложить. Подходят фото, PDF, Word и Excel.',
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

/* A body over post_max_size arrives with $_POST and $_FILES both empty; say so instead of "fill in the fields". */
if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond(413, false, $t['err_title'], $t['err_size'], $wantsJson, $t);
}

/* Reject a cross-site post; browsers send Origin on same-origin posts too.
   Compare the bare hosts — HTTP_HOST carries the port, an Origin's host does not. */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && (string) parse_url($origin, PHP_URL_HOST) !== host_only($_SERVER['HTTP_HOST'] ?? '')) {
    respond(403, false, $t['err_title'], $t['err_text'], $wantsJson, $t);
}

$okText = $t['ok_text'];

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

$files = collect_files($CONFIG, $FILE_TYPES);
if (is_string($files)) {
    respond(422, false, $t['err_title'], $t[$files], $wantsJson, $t);
}

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

$meta['files'] = store_files($CONFIG['uploads'], $files);
$logged = log_lead($CONFIG['log'], $values, $meta);
$sent   = send_mail($CONFIG, $SUBJECTS[$formId][$lang], $LABELS[$lang], $values, $meta, $files);

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

/**
 * The uploaded files[] as a list of ['name', 'ext', 'type', 'size', 'tmp'], or the $TEXT key of the
 * problem. Accepts by extension only; nothing in storage/ is ever served, so a mislabelled file
 * can only reach the inbox, where the mail client treats it like any other attachment.
 */
function collect_files(array $config, array $types): array|string
{
    $in = $_FILES['files'] ?? null;
    if (!is_array($in) || !is_array($in['name'] ?? null)) {
        return [];
    }
    $files = [];
    $total = 0;
    foreach ($in['name'] as $i => $name) {
        $error = (int) ($in['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            return 'err_size';
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file((string) $in['tmp_name'][$i])) {
            return 'err_text';
        }
        $name = clean_filename((string) $name);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset($types[$ext])) {
            return 'err_type';
        }
        $size   = (int) $in['size'][$i];
        $total += $size;
        if ($size > $config['max_file'] || $total > $config['max_total']) {
            return 'err_size';
        }
        $files[] = ['name' => $name, 'ext' => $ext, 'type' => $types[$ext], 'size' => $size, 'tmp' => (string) $in['tmp_name'][$i]];
    }
    return count($files) > $config['max_files'] ? 'err_count' : $files;
}

/** A file name safe to store and to put in a mail header: no path, no control characters. */
function clean_filename(string $name): string
{
    $name = str_replace('\\', '/', $name);
    $name = substr($name, (int) strrpos('/' . $name, '/'));   // basename() is locale-dependent with UTF-8
    $name = preg_replace('/[\x00-\x1F\x7F"\/:*?<>|]+/u', '_', $name) ?? '';
    $name = trim($name, " .\t");
    if (!mb_check_encoding($name, 'UTF-8') || $name === '') {
        $name = 'fail';
    }
    return mb_substr($name, -120);
}

/** Moves the uploads into their own folder under storage/uploads/; returns that folder's name for the log. */
function store_files(string $root, array &$files): string
{
    if ($files === [] || !ensure_storage($root)) {
        return '';
    }
    $folder = date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    $dir    = $root . '/' . $folder;
    if (!@mkdir($dir, 0700)) {
        return '';
    }
    $used = [];
    foreach ($files as $i => $f) {
        $name = $f['name'];
        for ($n = 2; isset($used[strtolower($name)]); $n++) {
            $name = pathinfo($f['name'], PATHINFO_FILENAME) . "-$n." . $f['ext'];
        }
        $used[strtolower($name)] = true;
        if (@move_uploaded_file($f['tmp'], $dir . '/' . $name)) {
            @chmod($dir . '/' . $name, 0600);
            $files[$i]['tmp']  = $dir . '/' . $name;   // the mail reads it from here
            $files[$i]['name'] = $name;
        }
    }
    return 'uploads/' . $folder;
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
    $columns = ['time', 'form', 'lang', 'name', 'email', 'phone', 'address', 'what', 'date', 'language', 'page', 'ip', 'ua', 'files'];
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

function send_mail(array $config, string $subject, array $labels, array $values, array $meta, array $files = []): bool
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

    if ($files !== []) {
        $lines[] = '';
        $lines[] = ($labels['files'] ?? 'Files') . ':';
        foreach ($files as $f) {
            $lines[] = '- ' . $f['name'] . ' (' . max(1, (int) round($f['size'] / 1024)) . ' KB)';
        }
        if ($meta['files'] !== '') {
            $lines[] = 'Koopia serveris: storage/' . $meta['files'];
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
        'X-Mailer: kodukontroll-form',
    ];

    if ($files === []) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
    } else {
        /* multipart/mixed: the text first, then each file as a base64 attachment. */
        $boundary  = '=_kk_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
        $parts     = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n" . $body . "\r\n";
        foreach ($files as $f) {
            $data = @file_get_contents($f['tmp']);
            if ($data === false) {
                continue;
            }
            $parts .= "--$boundary\r\n"
                . 'Content-Type: ' . $f['type'] . '; name="' . mime_header($f['name']) . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-Disposition: attachment; filename="' . mime_header($f['name']) . '"; filename*=UTF-8\'\'' . rawurlencode($f['name']) . "\r\n\r\n"
                . chunk_split(base64_encode($data), 76, "\r\n");
        }
        $body = $parts . "--$boundary--\r\n";
    }

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
<meta name="theme-color" content="#F5F4F1">
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
