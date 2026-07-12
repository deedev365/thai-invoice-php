<?php
// lib.php — shared backend logic + JSON storage for the invoice service.
// Single source of truth used by api.php (and any server-side page).

date_default_timezone_set('Asia/Bangkok');

function invoice_paths()
{
    return [
        'default' => __DIR__ . '/invoice_data.json',
        'storage' => __DIR__ . '/invoice',
    ];
}

function clean_string($value)
{
    return trim((string) $value);
}

function parse_invoice_date($value)
{
    $value = clean_string($value);
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

function normalize_document_dates($document)
{
    $document['issue_date'] = format_invoice_date(parse_invoice_date($document['issue_date'] ?? ''));
    $document['credit'] = (string) credit_days($document['credit'] ?? 0);
    $document['due_date'] = calculate_due_date($document['issue_date'], $document['credit']);

    return $document;
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

function next_invoice_identity($storageDir, $issueDate, $excludePath = '')
{
    $dateCode = invoice_date_code($issueDate);
    $count = 0;
    $maxSequence = 0;
    $excludePath = $excludePath ? realpath($excludePath) : '';

    foreach (saved_invoice_files($storageDir) as $path) {
        if ($excludePath && realpath($path) === $excludePath) {
            continue;
        }

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
    $code = $dateCode . str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);

    return [
        'number' => 'DL-' . $code,
        'filename' => 'invoice-' . $code . '.json',
        'key' => 'invoice/invoice-' . $code . '.json',
        'path' => $storageDir . DIRECTORY_SEPARATOR . 'invoice-' . $code . '.json',
    ];
}

function next_document_number($storageDir, $issueDate, $excludePath = '')
{
    return next_invoice_identity($storageDir, $issueDate, $excludePath)['number'];
}

function normalize_data_key($value, $defaultDataFile, $storageDir)
{
    $value = str_replace('\\', '/', clean_string($value));

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

function list_invoice_files($defaultDataFile, $storageDir)
{
    $files = [[
        'key' => 'invoice_data.json',
        'label' => 'New invoice - invoice_data.json',
    ]];

    $savedFiles = saved_invoice_files($storageDir);

    usort($savedFiles, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    foreach ($savedFiles as $path) {
        $label = basename($path);
        $decoded = json_decode((string) file_get_contents($path), true);

        if (is_array($decoded)) {
            $docNumber = $decoded['document']['number'] ?? '';
            $customer = $decoded['customer']['name'] ?? '';
            $parts = array_filter([$docNumber, $customer]);
            if ($parts) {
                $label .= ' - ' . implode(' / ', $parts);
            }
        }

        $files[] = [
            'key' => 'invoice/' . basename($path),
            'label' => $label,
        ];
    }

    return $files;
}

function load_invoice_data($dataFile)
{
    if (!is_file($dataFile)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($dataFile), true);
    if (!is_array($decoded)) {
        return [];
    }

    return $decoded;
}

// Canonical field labels. Saved invoices store data only; the backend is the
// single source of truth for how each field is labelled on the printed page.
function default_translations()
{
    return [
        'seller' => 'Seller',
        'address' => 'Address',
        'tax_id' => 'Tax ID',
        'number' => 'Number',
        'issue_date' => 'Issue Date',
        'credit' => 'Credit',
        'due_date' => 'Due Date',
        'reference' => 'Reference',
        'project_name' => 'Project Name',
        'contancts' => 'Please contact me back',
        'customer_name' => 'Name',
        'customer_address' => 'Address',
        'customer_tax_id' => 'Tax ID',
        'items_description' => 'Description',
        'items_quantity' => 'Quantity',
        'items_price' => 'Price',
        'items_discount' => 'Discount',
        'items_vat' => 'VAT',
        'items_amount_before_tax' => 'Before Tax',
        'summary' => 'Summary',
        'taxable_amount_7' => 'Taxable Amount (7% VAT)',
        'vat_amount_7' => 'VAT (7%)',
        'total_amount' => 'Total Amount',
        'withholding_tax' => 'Withholding Tax',
        'amount_payable' => 'Amount Payable',
        'payment' => 'Payment',
    ];
}

function invoice_defaults()
{
    return [
        'document' => [
            'title' => '',
            'page_label' => '',
            'number' => '',
            'issue_date' => '',
            'credit' => '0',
            'due_date' => '',
            'reference' => '',
            'project_name' => '',
        ],
        'seller' => ['name' => '', 'address' => '', 'tax_id' => '', 'logo_url' => ''],
        'customer' => ['name' => '', 'address' => '', 'tax_id' => '', 'phone' => '', 'email' => ''],
        'contact' => ['name' => '', 'phone' => '', 'email' => '', 'line_icon_url' => '', 'line_id' => ''],
        'items' => [],
        'summary' => [
            'taxable_amount' => '',
            'vat_amount' => '',
            'total_words' => '',
            'total_amount' => '',
            'withholding' => '',
            'payable' => '',
        ],
        'banks' => [],
        'language' => 'th',
        'currency' => '',
        'withholding_rate' => '0',
        'note' => '',
    ];
}

// ---- money & number-to-words ------------------------------------------------

// Parse a loose numeric input ("15,000", "10%", "1 200.50") into a float.
function parse_number($value)
{
    $value = str_replace(',', '', clean_string($value));
    if (preg_match('/-?\d+(\.\d+)?/', $value, $matches)) {
        return (float) $matches[0];
    }

    return 0.0;
}

// Plain money amount used for line totals: "9,600.00" (no currency suffix).
function format_amount($number)
{
    return number_format((float) $number, 2, '.', ',');
}

// Money amount with the invoice currency suffix: "9,600.00 บาท".
function format_money($number, $currency = '')
{
    $currency = clean_string($currency);

    return $currency === '' ? format_amount($number) : format_amount($number) . ' ' . $currency;
}

// Read a non-negative integer (as string/int) in Thai words. No unit suffix.
function thai_read_integer($number)
{
    $number = ltrim((string) (int) $number, '0');
    if ($number === '') {
        return '';
    }

    $digitWords = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
    $placeWords = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];
    $length = strlen($number);

    // Numbers of 7+ digits are read in "million" (ล้าน) groups.
    if ($length > 6) {
        $head = substr($number, 0, $length - 6);
        $tail = substr($number, $length - 6);
        $tailWords = ltrim($tail, '0') === '' ? '' : thai_read_integer($tail);

        return thai_read_integer($head) . 'ล้าน' . $tailWords;
    }

    $words = '';
    for ($i = 0; $i < $length; $i++) {
        $digit = (int) $number[$i];
        $place = $length - $i - 1;
        if ($digit === 0) {
            continue;
        }

        if ($place === 0) {
            $words .= ($digit === 1 && $length > 1) ? 'เอ็ด' : $digitWords[$digit];
        } elseif ($place === 1) {
            $words .= $digit === 1 ? 'สิบ' : ($digit === 2 ? 'ยี่สิบ' : $digitWords[$digit] . 'สิบ');
        } else {
            $words .= $digitWords[$digit] . $placeWords[$place];
        }
    }

    return $words;
}

// Full Thai baht text, e.g. "สี่หมื่นสามพันห้าร้อยบาทถ้วน".
function thai_baht_text($amount)
{
    $amount = number_format((float) $amount, 2, '.', '');
    [$baht, $satang] = explode('.', $amount);
    $baht = (int) $baht;
    $satang = (int) $satang;

    if ($baht === 0 && $satang === 0) {
        return 'ศูนย์บาทถ้วน';
    }

    $text = '';
    if ($baht > 0) {
        $text .= thai_read_integer($baht) . 'บาท';
    }
    $text .= $satang > 0 ? thai_read_integer($satang) . 'สตางค์' : 'ถ้วน';

    return $text;
}

// Read a non-negative integer in English words ("forty-four thousand ...").
function english_read_integer($number)
{
    $number = (int) $number;
    if ($number === 0) {
        return 'zero';
    }

    $ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen',
        'seventeen', 'eighteen', 'nineteen'];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    $scales = ['', 'thousand', 'million', 'billion', 'trillion'];

    $chunks = [];
    while ($number > 0) {
        $chunks[] = $number % 1000;
        $number = intdiv($number, 1000);
    }

    $parts = [];
    for ($i = count($chunks) - 1; $i >= 0; $i--) {
        $chunk = $chunks[$i];
        if ($chunk === 0) {
            continue;
        }

        $words = '';
        $hundreds = intdiv($chunk, 100);
        $rest = $chunk % 100;
        if ($hundreds > 0) {
            $words .= $ones[$hundreds] . ' hundred';
        }
        if ($rest > 0) {
            $words .= $words !== '' ? ' ' : '';
            if ($rest < 20) {
                $words .= $ones[$rest];
            } else {
                $words .= $tens[intdiv($rest, 10)];
                if ($rest % 10 > 0) {
                    $words .= '-' . $ones[$rest % 10];
                }
            }
        }

        $parts[] = $scales[$i] !== '' ? $words . ' ' . $scales[$i] : $words;
    }

    return implode(' ', $parts);
}

// Full English baht text, e.g. "Forty-Four Thousand Five Hundred Twelve Baht Only".
function english_baht_text($amount)
{
    $amount = number_format((float) $amount, 2, '.', '');
    [$baht, $satang] = explode('.', $amount);

    $text = ucwords(english_read_integer((int) $baht), " -") . ' Baht';
    if ((int) $satang > 0) {
        $text .= ' and ' . ucwords(english_read_integer((int) $satang), " -") . ' Satang';
    } else {
        $text .= ' Only';
    }

    return $text;
}

// Spell out a total in the invoice's language ('th' default, 'en' otherwise).
function amount_in_words($amount, $language = 'th')
{
    return $language === 'en' ? english_baht_text($amount) : thai_baht_text($amount);
}

// Derive every line total and the summary block from the raw item inputs.
// Discount and VAT are percentages; withholding is a percentage of the taxable
// (pre-VAT) base. With no items the summary stays blank (nothing to total).
function compute_invoice_totals($invoice)
{
    $items = array_values(array_filter((array) ($invoice['items'] ?? []), 'is_array'));
    $currency = clean_string($invoice['currency'] ?? '');
    $language = ($invoice['language'] ?? 'th') === 'en' ? 'en' : 'th';
    $withholdingRate = parse_number($invoice['withholding_rate'] ?? '0');

    if (count($items) === 0) {
        // Nothing to total. Keep any manually-supplied summary so legacy
        // invoices (which predate auto-calc and stored no line items) still
        // display their totals; otherwise leave the summary blank.
        $invoice['items'] = $items;
        $invoice['summary'] = is_array($invoice['summary'] ?? null)
            ? array_merge(invoice_defaults()['summary'], $invoice['summary'])
            : invoice_defaults()['summary'];

        return $invoice;
    }

    $taxable = 0.0;
    $vatAmount = 0.0;

    foreach ($items as $index => $item) {
        $qty = parse_number($item['qty'] ?? '');
        $price = parse_number($item['price'] ?? '');
        $discount = parse_number($item['discount'] ?? '');
        $vat = parse_number($item['vat'] ?? '');

        $lineNet = round($qty * $price * (1 - $discount / 100), 2);
        $items[$index]['pre_tax'] = format_amount($lineNet);

        $taxable += $lineNet;
        $vatAmount += $lineNet * $vat / 100;
    }

    $taxable = round($taxable, 2);
    $vatAmount = round($vatAmount, 2);
    $total = round($taxable + $vatAmount, 2);
    $withholding = round($taxable * $withholdingRate / 100, 2);
    $payable = round($total - $withholding, 2);

    $invoice['items'] = $items;
    $invoice['summary'] = [
        'taxable_amount' => format_money($taxable, $currency),
        'vat_amount' => format_money($vatAmount, $currency),
        'total_words' => amount_in_words($total, $language),
        'total_amount' => format_money($total, $currency),
        'withholding' => format_money($withholding, $currency),
        'payable' => format_money($payable, $currency),
    ];

    return $invoice;
}

// Build a clean, canonical invoice array from arbitrary (JSON) input.
function sanitize_invoice($input)
{
    $input = is_array($input) ? $input : [];
    $document = is_array($input['document'] ?? null) ? $input['document'] : [];
    $seller = is_array($input['seller'] ?? null) ? $input['seller'] : [];
    $customer = is_array($input['customer'] ?? null) ? $input['customer'] : [];
    $contact = is_array($input['contact'] ?? null) ? $input['contact'] : [];
    $summary = is_array($input['summary'] ?? null) ? $input['summary'] : [];

    $items = [];
    foreach ((array) ($input['items'] ?? []) as $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $item = [
            'name' => clean_string($raw['name'] ?? ''),
            'detail' => clean_string($raw['detail'] ?? ''),
            'qty' => clean_string($raw['qty'] ?? ''),
            'price' => clean_string($raw['price'] ?? ''),
            'discount' => clean_string($raw['discount'] ?? ''),
            'vat' => clean_string($raw['vat'] ?? ''),
            'pre_tax' => clean_string($raw['pre_tax'] ?? ''),
        ];
        // pre_tax is computed, not entered, so it never keeps an otherwise-empty row.
        if ($item['name'] . $item['detail'] . $item['qty'] . $item['price'] . $item['discount'] !== '') {
            $items[] = $item;
        }
    }

    $banks = [];
    foreach ((array) ($input['banks'] ?? []) as $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $bank = [
            'logo_class' => clean_string($raw['logo_class'] ?? 'other') ?: 'other',
            'logo_text' => clean_string($raw['logo_text'] ?? ''),
            'bank_name' => clean_string($raw['bank_name'] ?? ''),
            'account_number' => clean_string($raw['account_number'] ?? ''),
            'account_name' => clean_string($raw['account_name'] ?? ''),
        ];
        if ($bank['logo_text'] . $bank['bank_name'] . $bank['account_number'] . $bank['account_name'] !== '') {
            $banks[] = $bank;
        }
    }

    $invoice = [
        'document' => [
            'title' => clean_string($document['title'] ?? ''),
            'page_label' => clean_string($document['page_label'] ?? ''),
            'number' => clean_string($document['number'] ?? ''),
            'issue_date' => clean_string($document['issue_date'] ?? ''),
            'credit' => clean_string($document['credit'] ?? ''),
            'due_date' => clean_string($document['due_date'] ?? ''),
            'reference' => clean_string($document['reference'] ?? ''),
            'project_name' => clean_string($document['project_name'] ?? ''),
        ],
        'seller' => [
            'name' => clean_string($seller['name'] ?? ''),
            'address' => clean_string($seller['address'] ?? ''),
            'tax_id' => clean_string($seller['tax_id'] ?? ''),
            'logo_url' => clean_string($seller['logo_url'] ?? ''),
        ],
        'customer' => [
            'name' => clean_string($customer['name'] ?? ''),
            'address' => clean_string($customer['address'] ?? ''),
            'tax_id' => clean_string($customer['tax_id'] ?? ''),
            'phone' => clean_string($customer['phone'] ?? ''),
            'email' => clean_string($customer['email'] ?? ''),
        ],
        'contact' => [
            'name' => clean_string($contact['name'] ?? ''),
            'phone' => clean_string($contact['phone'] ?? ''),
            'email' => clean_string($contact['email'] ?? ''),
            'line_icon_url' => clean_string($contact['line_icon_url'] ?? ''),
            'line_id' => clean_string($contact['line_id'] ?? ''),
        ],
        'items' => $items,
        'summary' => [
            'taxable_amount' => clean_string($summary['taxable_amount'] ?? ''),
            'vat_amount' => clean_string($summary['vat_amount'] ?? ''),
            'total_words' => clean_string($summary['total_words'] ?? ''),
            'total_amount' => clean_string($summary['total_amount'] ?? ''),
            'withholding' => clean_string($summary['withholding'] ?? ''),
            'payable' => clean_string($summary['payable'] ?? ''),
        ],
        'banks' => $banks,
        'language' => (($input['language'] ?? 'th') === 'en') ? 'en' : 'th',
        'currency' => clean_string($input['currency'] ?? ''),
        'withholding_rate' => clean_string($input['withholding_rate'] ?? '0'),
        'note' => clean_string($input['note'] ?? ''),
    ];

    $invoice['document'] = normalize_document_dates($invoice['document']);
    $invoice = compute_invoice_totals($invoice);

    return $invoice;
}

// Merge stored data over the canonical skeleton so every section/field exists,
// apply date rules, seed a fresh number for the blank template, attach labels.
function prepare_invoice_for_view($invoice, $key, $storageDir)
{
    $invoice = is_array($invoice) ? $invoice : [];
    $defaults = invoice_defaults();

    foreach (['document', 'seller', 'customer', 'contact', 'summary'] as $section) {
        $stored = is_array($invoice[$section] ?? null) ? $invoice[$section] : [];
        $invoice[$section] = array_merge($defaults[$section], $stored);
    }
    $invoice['items'] = array_values(array_filter((array) ($invoice['items'] ?? []), 'is_array'));
    $invoice['banks'] = array_values(array_filter((array) ($invoice['banks'] ?? []), 'is_array'));
    $invoice['language'] = (($invoice['language'] ?? 'th') === 'en') ? 'en' : 'th';
    $invoice['currency'] = (string) ($invoice['currency'] ?? $defaults['currency']);
    $invoice['withholding_rate'] = (string) ($invoice['withholding_rate'] ?? $defaults['withholding_rate']);
    $invoice['note'] = (string) ($invoice['note'] ?? '');

    $invoice['document'] = normalize_document_dates($invoice['document']);

    if ($key === 'invoice_data.json') {
        $invoice['document']['issue_date'] = format_invoice_date(new DateTimeImmutable('today'));
        $invoice['document']['due_date'] = calculate_due_date($invoice['document']['issue_date'], $invoice['document']['credit']);
        $invoice['document']['number'] = next_document_number($storageDir, $invoice['document']['issue_date']);
    }

    // Numbers are always derived from the raw item inputs, so hand-edited or
    // legacy files display correct, consistent totals.
    $invoice = compute_invoice_totals($invoice);

    $translate = is_array($invoice['translate'] ?? null) ? $invoice['translate'] : [];
    $invoice['translate'] = array_merge(default_translations(), $translate);

    return $invoice;
}

// Run $callback while holding an exclusive lock over the storage directory.
// Serializes number allocation so two concurrent "create" saves can't collide
// on the same document number. Falls back to running unlocked if the lock file
// cannot be opened (best effort — a missing lock never blocks a save).
function with_storage_lock($storageDir, callable $callback)
{
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }

    $handle = @fopen($storageDir . DIRECTORY_SEPARATOR . '.write.lock', 'c');
    if ($handle === false) {
        return $callback();
    }

    try {
        flock($handle, LOCK_EX);
        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

// Encode + atomically write an invoice array to $path.
function write_invoice_json($path, array $invoice)
{
    $json = json_encode($invoice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Could not encode invoice data.');
    }

    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException('Could not write the invoice JSON. Please check folder permissions.');
    }
}

// Persist an incoming invoice payload. Returns metadata + the stored view.
// Throws RuntimeException on failure.
function save_invoice($rawInvoice, $sourceKey)
{
    $paths = invoice_paths();
    $selected = normalize_data_key($sourceKey, $paths['default'], $paths['storage']);
    $invoice = sanitize_invoice($rawInvoice);

    // Updating an existing file writes to a fixed path — no numbering race.
    if ($selected['key'] !== 'invoice_data.json' && is_file($selected['path'])) {
        write_invoice_json($selected['path'], $invoice);

        return [
            'key' => $selected['key'],
            'action' => 'updated',
            'invoice' => prepare_invoice_for_view($invoice, $selected['key'], $paths['storage']),
        ];
    }

    // Creating a new file: allocate the next number and write under one lock so
    // parallel creates are serialized and never reuse a document number.
    $saved = with_storage_lock($paths['storage'], function () use ($paths, $invoice) {
        $identity = next_invoice_identity($paths['storage'], $invoice['document']['issue_date']);
        $invoice['document']['number'] = $identity['number'];
        write_invoice_json($identity['path'], $invoice);

        return ['key' => $identity['key'], 'invoice' => $invoice];
    });

    return [
        'key' => $saved['key'],
        'action' => 'created',
        'invoice' => prepare_invoice_for_view($saved['invoice'], $saved['key'], $paths['storage']),
    ];
}

// Delete a saved invoice file. The blank template can never be deleted.
// Throws RuntimeException if the target is missing or cannot be removed.
function delete_invoice($sourceKey)
{
    $paths = invoice_paths();
    $selected = normalize_data_key($sourceKey, $paths['default'], $paths['storage']);

    if ($selected['key'] === 'invoice_data.json') {
        throw new RuntimeException('The blank template cannot be deleted.');
    }
    if (!is_file($selected['path'])) {
        throw new RuntimeException('Invoice not found.');
    }
    if (!unlink($selected['path'])) {
        throw new RuntimeException('Could not delete the invoice file.');
    }

    return ['key' => $selected['key'], 'action' => 'deleted'];
}

// Copy an existing invoice into a brand-new one (fresh number, same content).
// Returns the same shape as save_invoice with action 'created'.
function duplicate_invoice($sourceKey)
{
    $paths = invoice_paths();
    $selected = normalize_data_key($sourceKey, $paths['default'], $paths['storage']);

    if (!is_file($selected['path'])) {
        throw new RuntimeException('Invoice not found.');
    }

    $invoice = sanitize_invoice(load_invoice_data($selected['path']));
    $invoice['document']['number'] = '';

    // Force the "create" path so a new file + number is allocated.
    return save_invoice($invoice, 'invoice_data.json');
}
