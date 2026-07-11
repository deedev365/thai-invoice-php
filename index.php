<?php
// Invoice DL-260503001 - Theyama Enterprise

$defaultDataFile = __DIR__ . '/invoice_data.json';
$storageDir = __DIR__ . '/invoice';
$invoice = [];

date_default_timezone_set('Asia/Bangkok');

function clean_data_key($value)
{
    return trim((string) $value);
}

function normalize_data_key($value, $defaultDataFile, $storageDir)
{
    $value = str_replace('\\', '/', clean_data_key($value));

    if ($value === '' || $value === 'invoice_data.json') {
        return [
            'key' => 'invoice_data.json',
            'path' => $defaultDataFile,
        ];
    }

    if (preg_match('/^invoice\/([A-Za-z0-9._-]+\.json)$/', $value, $matches)) {
        return [
            'key' => 'invoice/' . $matches[1],
            'path' => $storageDir . DIRECTORY_SEPARATOR . $matches[1],
        ];
    }

    return [
        'key' => 'invoice_data.json',
        'path' => $defaultDataFile,
    ];
}

function parse_invoice_date($value)
{
    $value = trim((string) $value);
    $date = DateTimeImmutable::createFromFormat('!d/m/Y', $value);

    if ($date instanceof DateTimeImmutable && $date->format('d/m/Y') === $value) {
        return $date;
    }

    return new DateTimeImmutable('today');
}

function format_invoice_date(DateTimeImmutable $date)
{
    return $date->format('d/m/Y');
}

function credit_days($value)
{
    if (preg_match('/-?\d+/', (string) $value, $matches)) {
        return max(0, (int) $matches[0]);
    }

    return 0;
}

function calculate_due_date($issueDate, $credit)
{
    return format_invoice_date(parse_invoice_date($issueDate)->modify('+' . credit_days($credit) . ' days'));
}

function invoice_date_code($issueDate)
{
    return parse_invoice_date($issueDate)->format('ymd');
}

function saved_invoice_files($storageDir)
{
    $files = is_dir($storageDir) ? glob($storageDir . DIRECTORY_SEPARATOR . 'invoice*.json') : [];
    if (!is_array($files)) {
        return [];
    }

    return array_values(array_filter($files, function ($path) {
        return basename($path) !== 'invoice_data.json';
    }));
}

function next_document_number($storageDir, $issueDate)
{
    $dateCode = invoice_date_code($issueDate);
    $count = 0;
    $maxSequence = 0;

    foreach (saved_invoice_files($storageDir) as $path) {
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            continue;
        }

        $number = (string) ($decoded['document']['number'] ?? '');
        $savedIssueDate = (string) ($decoded['document']['issue_date'] ?? '');
        $filename = basename($path);
        $matchesDate = false;

        if (preg_match('/^invoice-' . preg_quote($dateCode, '/') . '(\d+)\.json$/', $filename, $matches)) {
            $matchesDate = true;
            $maxSequence = max($maxSequence, (int) $matches[1]);
        } elseif (preg_match('/^DL-' . preg_quote($dateCode, '/') . '(\d+)$/', $number, $matches)) {
            $matchesDate = true;
            $maxSequence = max($maxSequence, (int) $matches[1]);
        } elseif ($savedIssueDate !== '' && invoice_date_code($savedIssueDate) === $dateCode) {
            $matchesDate = true;
        }

        if ($matchesDate) {
            $count++;
        }
    }

    $nextSequence = max($count, $maxSequence) + 1;

    return 'DL-' . $dateCode . str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
}

$selectedData = normalize_data_key($_GET['data'] ?? '', $defaultDataFile, $storageDir);
if (!is_file($selectedData['path'])) {
    $selectedData = normalize_data_key('', $defaultDataFile, $storageDir);
}

if (is_file($selectedData['path'])) {
    $decoded = json_decode((string) file_get_contents($selectedData['path']), true);
    if (is_array($decoded)) {
        $invoice = $decoded;
    }
}

$invoice['document']['issue_date'] = format_invoice_date(parse_invoice_date($invoice['document']['issue_date'] ?? ''));
$invoice['document']['credit'] = (string) credit_days($invoice['document']['credit'] ?? 0);
$invoice['document']['due_date'] = calculate_due_date($invoice['document']['issue_date'], $invoice['document']['credit']);
if ($selectedData['key'] === 'invoice_data.json') {
    $invoice['document']['issue_date'] = format_invoice_date(new DateTimeImmutable('today'));
    $invoice['document']['due_date'] = calculate_due_date($invoice['document']['issue_date'], $invoice['document']['credit']);
    $invoice['document']['number'] = next_document_number($storageDir, $invoice['document']['issue_date']);
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function lines_html($value)
{
    $lines = preg_split("/\r\n|\n|\r/", (string) $value);
    $html = '';

    foreach ($lines as $line) {
        $html .= '<div>' . h($line) . '</div>';
    }

    return $html;
}

$title = h($invoice['document']['title']) . ' - ' . h($invoice['document']['number']);
include ('frontend.php');
?>
