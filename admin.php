<?php

$defaultDataFile = __DIR__ . '/invoice/invoice_data.json';
$storageDir = __DIR__ . '/invoice/invoice';
$message = '';
$error = '';

date_default_timezone_set('Asia/Bangkok');

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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

function normalize_document_dates($document)
{
    $document['issue_date'] = format_invoice_date(parse_invoice_date($document['issue_date'] ?? ''));
    $document['credit'] = (string) credit_days($document['credit'] ?? 0);
    $document['due_date'] = calculate_due_date($document['issue_date'], $document['credit']);

    return $document;
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
        'path' => $defaultDataFile,
        'label' => 'New invoice - invoice_data.json',
    ]];

    $savedFiles = saved_invoice_files($storageDir);

    usort($savedFiles, function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });

    foreach ($savedFiles as $path) {
        $key = 'invoice/' . basename($path);
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
            'key' => $key,
            'path' => $path,
            'label' => $label,
        ];
    }

    return $files;
}

function invoice_path_for_number($storageDir, $documentNumber)
{
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }

    if (preg_match('/^DL-(\d{9})$/', (string) $documentNumber, $matches)) {
        return $storageDir . DIRECTORY_SEPARATOR . 'invoice-' . $matches[1] . '.json';
    }

    return next_invoice_identity($storageDir, 'today')['path'];
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

$selectedRequestKey = ($_SERVER['REQUEST_METHOD'] === 'POST') ? ($_POST['source_data'] ?? '') : ($_GET['data'] ?? '');
$selectedData = normalize_data_key($selectedRequestKey, $defaultDataFile, $storageDir);
if (!is_file($selectedData['path'])) {
    $selectedData = normalize_data_key('', $defaultDataFile, $storageDir);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document = $_POST['document'] ?? [];
    $seller = $_POST['seller'] ?? [];
    $customer = $_POST['customer'] ?? [];
    $contact = $_POST['contact'] ?? [];
    $summary = $_POST['summary'] ?? [];
    $postedItems = $_POST['items'] ?? [];
    $postedBanks = $_POST['banks'] ?? [];

    $items = [];
    $itemCount = count($postedItems['name'] ?? []);
    for ($i = 0; $i < $itemCount; $i++) {
        $item = [
            'name' => clean_string($postedItems['name'][$i] ?? ''),
            'detail' => clean_string($postedItems['detail'][$i] ?? ''),
            'qty' => clean_string($postedItems['qty'][$i] ?? ''),
            'price' => clean_string($postedItems['price'][$i] ?? ''),
            'discount' => clean_string($postedItems['discount'][$i] ?? ''),
            'vat' => clean_string($postedItems['vat'][$i] ?? ''),
            'pre_tax' => clean_string($postedItems['pre_tax'][$i] ?? ''),
        ];

        $itemHasContent = $item['name'] . $item['detail'] . $item['qty'] . $item['price'] . $item['discount'] . $item['pre_tax'];
        if ($itemHasContent !== '') {
            $items[] = $item;
        }
    }

    $banks = [];
    $bankCount = count($postedBanks['bank_name'] ?? []);
    for ($i = 0; $i < $bankCount; $i++) {
        $bank = [
            'logo_class' => clean_string($postedBanks['logo_class'][$i] ?? 'other'),
            'logo_text' => clean_string($postedBanks['logo_text'][$i] ?? ''),
            'bank_name' => clean_string($postedBanks['bank_name'][$i] ?? ''),
            'account_number' => clean_string($postedBanks['account_number'][$i] ?? ''),
            'account_name' => clean_string($postedBanks['account_name'][$i] ?? ''),
        ];

        $bankHasContent = $bank['logo_text'] . $bank['bank_name'] . $bank['account_number'] . $bank['account_name'];
        if ($bankHasContent !== '') {
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
        'note' => clean_string($_POST['note'] ?? ''),
    ];
    $invoice['document'] = normalize_document_dates($invoice['document']);

    $json = json_encode($invoice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        $error = 'Could not encode invoice data.';
    } else {
        if ($selectedData['key'] !== 'invoice_data.json' && is_file($selectedData['path'])) {
            $savePath = $selectedData['path'];
            $saveKey = $selectedData['key'];
            $savedAction = 'updated';
        } else {
            $identity = next_invoice_identity($storageDir, $invoice['document']['issue_date']);
            $invoice['document']['number'] = $identity['number'];
            $json = json_encode($invoice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($json === false) {
                $error = 'Could not encode invoice data.';
                $savePath = '';
                $saveKey = '';
            } else {
                $savePath = $identity['path'];
                $saveKey = $identity['key'];
                $savedAction = 'created';
            }
        }

        if ($savePath === '' || file_put_contents($savePath, $json . PHP_EOL, LOCK_EX) === false) {
            $error = 'Could not write the invoice JSON. Please check folder permissions.';
        } else {
            header('Location: admin.php?data=' . rawurlencode($saveKey) . '&saved=' . rawurlencode($savedAction));
            exit;
        }
    }
}

$invoice = load_invoice_data($selectedData['path']);
$invoice['document'] = normalize_document_dates($invoice['document'] ?? []);
if ($selectedData['key'] === 'invoice_data.json') {
    $invoice['document']['issue_date'] = format_invoice_date(new DateTimeImmutable('today'));
    $invoice['document']['due_date'] = calculate_due_date($invoice['document']['issue_date'], $invoice['document']['credit'] ?? 0);
    $invoice['document']['number'] = next_document_number($storageDir, $invoice['document']['issue_date']);
}
$invoiceFiles = list_invoice_files($defaultDataFile, $storageDir);
$newInvoiceNumbers = [];
for ($offset = -370; $offset <= 370; $offset++) {
    $date = (new DateTimeImmutable('today'))->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
    $issueDate = format_invoice_date($date);
    $newInvoiceNumbers[$issueDate] = next_document_number($storageDir, $issueDate);
}
if (isset($_GET['saved'])) {
    $message = ($_GET['saved'] === 'updated' ? 'Invoice updated: ' : 'Invoice created: ') . $selectedData['key'];
}

if (empty($invoice['items'])) {
    $invoice['items'] = [[
        'name' => '',
        'detail' => '',
        'qty' => '',
        'price' => '',
        'discount' => '',
        'vat' => '',
        'pre_tax' => '',
    ]];
}

if (empty($invoice['banks'])) {
    $invoice['banks'] = [[
        'logo_class' => 'other',
        'logo_text' => '',
        'bank_name' => '',
        'account_number' => '',
        'account_name' => '',
    ]];
}
?>
<!doctype html>
<html lang="th">
<head>
<link rel="stylesheet" href="tax-invoice/css/admin.css">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice Admin - <?= h($invoice['document']['number']) ?></title>
</head>
<body>
<div class="shell">
  <div class="topbar">
    <div>
      <h1><a href="admin.php">Invoice Admin</a></h1>
      <div class="doc-number"><?= h($invoice['document']['number']) ?> - Editing <?= h($selectedData['key']) ?></div>
    </div>
    <div class="actions">
      <form class="invoice-picker" method="get" action="admin.php">
        <div>
          <label for="data">Choose invoice</label>
          <select id="data" name="data">
            <?php foreach ($invoiceFiles as $file): ?>
              <option value="<?= h($file['key']) ?>" <?= $file['key'] === $selectedData['key'] ? 'selected' : '' ?>>
                <?= h($file['label']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="secondary" type="submit">Load</button>
      </form>
      <button type="submit" form="invoice-form">Save Invoice</button>
    </div>
  </div>

  <?php if ($message): ?>
    <div class="message"><?= h($message) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>

  <form id="invoice-form" method="post" action="admin.php">
    <input type="hidden" name="source_data" value="<?= h($selectedData['key']) ?>">
    <div class="grid">
      <section class="panel">
        <div class="panel-header"><h2>Document</h2></div>
        <div class="fields">
          <div class="field"><label>Title</label><input name="document[title]" value="<?= h($invoice['document']['title']) ?>"></div>
          <div class="field"><label>Page label</label><input name="document[page_label]" value="<?= h($invoice['document']['page_label']) ?>"></div>
          <div class="field"><label>Document number</label><input id="document-number" name="document[number]" value="<?= h($invoice['document']['number']) ?>" readonly></div>
          <div class="field"><label>Issue date</label><input id="issue-date" name="document[issue_date]" placeholder="DD/MM/YYYY" value="<?= h($invoice['document']['issue_date']) ?>"></div>
          <div class="field"><label>Credit days</label><input id="credit-days" name="document[credit]" inputmode="numeric" value="<?= h($invoice['document']['credit']) ?>"></div>
          <div class="field"><label>Due date</label><input id="due-date" name="document[due_date]" value="<?= h($invoice['document']['due_date']) ?>" readonly></div>
          <div class="field"><label>Reference</label><input name="document[reference]" value="<?= h($invoice['document']['reference']) ?>"></div>
          <div class="field"><label>Project name</label><input name="document[project_name]" value="<?= h($invoice['document']['project_name']) ?>"></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header"><h2>Seller</h2></div>
        <div class="fields">
          <div class="field full"><label>Seller name</label><input name="seller[name]" value="<?= h($invoice['seller']['name']) ?>"></div>
          <div class="field full"><label>Seller address</label><textarea name="seller[address]"><?= h($invoice['seller']['address']) ?></textarea></div>
          <div class="field full"><label>Seller tax ID</label><input name="seller[tax_id]" value="<?= h($invoice['seller']['tax_id']) ?>"></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header"><h2>Customer</h2></div>
        <div class="fields">
          <div class="field full"><label>Customer name</label><input name="customer[name]" value="<?= h($invoice['customer']['name']) ?>"></div>
          <div class="field full"><label>Customer address</label><textarea name="customer[address]"><?= h($invoice['customer']['address']) ?></textarea></div>
          <div class="field"><label>Tax ID</label><input name="customer[tax_id]" value="<?= h($invoice['customer']['tax_id']) ?>"></div>
          <div class="field"><label>Phone</label><input name="customer[phone]" value="<?= h($invoice['customer']['phone']) ?>"></div>
          <div class="field full"><label>Email</label><input name="customer[email]" value="<?= h($invoice['customer']['email']) ?>"></div>
        </div>
      </section>

      <section class="panel">
        <div class="panel-header"><h2>Contact</h2></div>
        <div class="fields">
          <div class="field"><label>Name</label><input name="contact[name]" value="<?= h($invoice['contact']['name']) ?>"></div>
          <div class="field"><label>Phone</label><input name="contact[phone]" value="<?= h($invoice['contact']['phone']) ?>"></div>
          <div class="field full"><label>Email</label><input name="contact[email]" value="<?= h($invoice['contact']['email']) ?>"></div>
          <div class="field full"><label>Line icon URL</label><input name="contact[line_icon_url]" value="<?= h($invoice['contact']['line_icon_url']) ?>"></div>
          <div class="field full"><label>Line ID</label><input name="contact[line_id]" value="<?= h($invoice['contact']['line_id']) ?>"></div>
        </div>
      </section>
    </div>

    <section class="panel full">
      <div class="panel-header">
        <h2>Items</h2>
        <button class="secondary" type="button" data-add="items">Add item</button>
      </div>
      <div class="repeat-list" data-list="items">
        <?php foreach ($invoice['items'] as $i => $item): ?>
          <div class="repeat-row" data-row="items">
            <div class="row-title">
              <strong>Item <span data-index><?= $i + 1 ?></span></strong>
              <button class="danger" type="button" data-remove>Remove</button>
            </div>
            <div class="item-grid">
              <div><label>Name</label><input name="items[name][]" value="<?= h($item['name']) ?>"></div>
              <div><label>Qty</label><input name="items[qty][]" value="<?= h($item['qty']) ?>"></div>
			  <div><label>Cost</label><input name="items[cost][]" value="<?= h($item['cost']) ?>"></div>
              <div><label>Selling Price</label><input name="items[price][]" value="<?= h($item['price']) ?>"></div>
              <div><label>Discount</label><input name="items[discount][]" value="<?= h($item['discount']) ?>"></div>
              <div><label>VAT</label><input name="items[vat][]" value="<?= h($item['vat']) ?>"></div>
              <div><label>Pre-tax</label><input name="items[pre_tax][]" value="<?= h($item['pre_tax']) ?>"></div>
              <div class="item-detail-field"><label>Detail</label><textarea name="items[detail][]"><?= h($item['detail']) ?></textarea></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="panel full">
      <div class="panel-header"><h2>Summary</h2></div>
      <div class="fields">
        <div class="field"><label>Taxable amount</label><input name="summary[taxable_amount]" value="<?= h($invoice['summary']['taxable_amount']) ?>"></div>
        <div class="field"><label>VAT (7%) amount</label><input name="summary[vat_amount]" value="<?= h($invoice['summary']['vat_amount']) ?>"></div>
        <div class="field full"><label>Total words</label><input name="summary[total_words]" value="<?= h($invoice['summary']['total_words']) ?>"></div>
        <div class="field"><label>Total amount</label><input name="summary[total_amount]" value="<?= h($invoice['summary']['total_amount']) ?>"></div>
        <div class="field"><label>Withholding (3%)</label><input name="summary[withholding]" value="<?= h($invoice['summary']['withholding']) ?>"></div>
        <div class="field"><label>Payable</label><input name="summary[payable]" value="<?= h($invoice['summary']['payable']) ?>"></div>
        <div class="field full"><label>Note</label><input name="note" value="<?= h($invoice['note']) ?>"></div>
      </div>
    </section>

    <section class="panel full">
      <div class="panel-header">
        <h2>Payment Banks</h2>
        <button class="secondary" type="button" data-add="banks">Add bank</button>
      </div>
      <div class="repeat-list" data-list="banks">
        <?php foreach ($invoice['banks'] as $i => $bank): ?>
          <div class="repeat-row" data-row="banks">
            <div class="row-title">
              <strong>Bank <span data-index><?= $i + 1 ?></span></strong>
              <button class="danger" type="button" data-remove>Remove</button>
            </div>
            <div class="bank-grid">
              <div>
                <label>Logo class</label>
                <select name="banks[logo_class][]">
                  <?php foreach (['kbank', 'scb', 'other'] as $class): ?>
                    <option value="<?= h($class) ?>" <?= ($bank['logo_class'] === $class) ? 'selected' : '' ?>><?= h($class) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div><label>Logo text</label><input name="banks[logo_text][]" value="<?= h($bank['logo_text']) ?>"></div>
              <div><label>Bank name</label><input name="banks[bank_name][]" value="<?= h($bank['bank_name']) ?>"></div>
              <div><label>Account number</label><input name="banks[account_number][]" value="<?= h($bank['account_number']) ?>"></div>
              <div><label>Account name</label><input name="banks[account_name][]" value="<?= h($bank['account_name']) ?>"></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <div class="sticky-save">
      <a class="button secondary" href="invoice.php?data=<?= rawurlencode($selectedData['key']) ?>" target="_blank">Open invoice</a>
      <button type="submit">Save Invoice</button>
    </div>
  </form>
</div>

<template id="items-template">
  <div class="repeat-row" data-row="items">
    <div class="row-title">
      <strong>Item <span data-index></span></strong>
      <button class="danger" type="button" data-remove>Remove</button>
    </div>
    <div class="item-grid">
      <div><label>Name</label><input name="items[name][]"></div>
      <div><label>Qty</label><input name="items[qty][]"></div>
      <div><label>Price</label><input name="items[price][]"></div>
      <div><label>Discount</label><input name="items[discount][]"></div>
      <div><label>VAT</label><input name="items[vat][]" value="7%"></div>
      <div><label>Pre-tax</label><input name="items[pre_tax][]"></div>
      <div class="item-detail-field"><label>Detail</label><textarea name="items[detail][]"></textarea></div>
    </div>
  </div>
</template>

<template id="banks-template">
  <div class="repeat-row" data-row="banks">
    <div class="row-title">
      <strong>Bank <span data-index></span></strong>
      <button class="danger" type="button" data-remove>Remove</button>
    </div>
    <div class="bank-grid">
      <div>
        <label>Logo class</label>
        <select name="banks[logo_class][]">
          <option value="kbank">kbank</option>
          <option value="scb">scb</option>
          <option value="other" selected>other</option>
        </select>
      </div>
      <div><label>Logo text</label><input name="banks[logo_text][]"></div>
      <div><label>Bank name</label><input name="banks[bank_name][]"></div>
      <div><label>Account number</label><input name="banks[account_number][]"></div>
      <div><label>Account name</label><input name="banks[account_name][]"></div>
    </div>
  </div>
</template>

<script>
  const isNewInvoice = <?= json_encode($selectedData['key'] === 'invoice_data.json') ?>;
  const newInvoiceNumbers = <?= json_encode($newInvoiceNumbers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  function renumberRows(type) {
    document.querySelectorAll(`[data-row="${type}"] [data-index]`).forEach((node, index) => {
      node.textContent = index + 1;
    });
  }

  function setupRepeater(type) {
    const list = document.querySelector(`[data-list="${type}"]`);
    const template = document.getElementById(`${type}-template`);
    const addButton = document.querySelector(`[data-add="${type}"]`);

    addButton.addEventListener('click', () => {
      list.appendChild(template.content.cloneNode(true));
      renumberRows(type);
    });
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove]');
    if (!button) return;

    const row = button.closest('[data-row]');
    const type = row.dataset.row;
    row.remove();
    renumberRows(type);
  });

  function parseInvoiceDate(value) {
    const match = String(value).trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (!match) return null;

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);
    const date = new Date(year, month - 1, day);

    if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
      return null;
    }

    return date;
  }

  function formatInvoiceDate(date) {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    return `${day}/${month}/${date.getFullYear()}`;
  }

  function documentNumberForDate(value) {
    const issueDate = parseInvoiceDate(value);
    if (!issueDate) return '';

    const formatted = formatInvoiceDate(issueDate);
    if (newInvoiceNumbers[formatted]) {
      return newInvoiceNumbers[formatted];
    }

    const year = String(issueDate.getFullYear()).slice(-2);
    const month = String(issueDate.getMonth() + 1).padStart(2, '0');
    const day = String(issueDate.getDate()).padStart(2, '0');

    return `DL-${year}${month}${day}001`;
  }

  function updateDueDate() {
    const issueInput = document.getElementById('issue-date');
    const creditInput = document.getElementById('credit-days');
    const dueInput = document.getElementById('due-date');
    const documentNumberInput = document.getElementById('document-number');
    const issueDate = parseInvoiceDate(issueInput.value);
    const creditDays = Math.max(0, parseInt(creditInput.value, 10) || 0);

    if (!issueDate) return;

    if (isNewInvoice) {
      documentNumberInput.value = documentNumberForDate(issueInput.value);
    }

    issueDate.setDate(issueDate.getDate() + creditDays);
    dueInput.value = formatInvoiceDate(issueDate);
  }

  document.getElementById('issue-date')?.addEventListener('input', updateDueDate);
  document.getElementById('credit-days')?.addEventListener('input', updateDueDate);
  updateDueDate();

  setupRepeater('items');
  setupRepeater('banks');
</script>
</body>
</html>
