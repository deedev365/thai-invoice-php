<?php
// api.php — JSON REST API between the backend (storage + business logic) and
// the frontend/admin clients. All logic lives in lib.php.
//
// Endpoints (all responses: application/json; charset=utf-8):
//   GET  api.php?action=list                 -> { ok, files:[{key,label}] }
//   GET  api.php?action=get&data=<key>       -> { ok, key, invoice }
//   GET  api.php?action=next-number&date=... -> { ok, number }
//   POST api.php?action=save                 -> { ok, key, action, invoice }
//        body: { "source_data": "<key>", "invoice": { ... } }
//   POST api.php?action=delete               -> { ok, key, action }
//        body: { "source_data": "<key>" }
//   POST api.php?action=duplicate            -> { ok, key, action, invoice }
//        body: { "source_data": "<key>" }

require __DIR__ . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_respond($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$paths = invoice_paths();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// The public print view only needs `get`; everything that enumerates or mutates
// invoices requires an authenticated admin session.
$publicActions = ['get'];
if (!in_array($action, $publicActions, true)) {
    require_admin_api();
}

try {
    switch ($action) {
        case 'list':
            api_respond([
                'ok' => true,
                'files' => list_invoice_files($paths['default'], $paths['storage']),
            ]);
            break;

        case 'get':
            $selected = normalize_data_key($_GET['data'] ?? '', $paths['default'], $paths['storage']);
            if (!is_file($selected['path'])) {
                $selected = normalize_data_key('', $paths['default'], $paths['storage']);
            }
            $invoice = prepare_invoice_for_view(
                load_invoice_data($selected['path']),
                $selected['key'],
                $paths['storage']
            );
            api_respond(['ok' => true, 'key' => $selected['key'], 'invoice' => $invoice]);
            break;

        case 'next-number':
            $date = $_GET['date'] ?? 'today';
            api_respond(['ok' => true, 'number' => next_document_number($paths['storage'], $date)]);
            break;

        case 'save':
            if ($method !== 'POST') {
                api_respond(['ok' => false, 'error' => 'Use POST to save.'], 405);
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                api_respond(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
            }
            $sourceKey = $payload['source_data'] ?? ($_GET['data'] ?? '');
            $rawInvoice = isset($payload['invoice']) && is_array($payload['invoice'])
                ? $payload['invoice']
                : $payload;
            $result = save_invoice($rawInvoice, $sourceKey);
            api_respond([
                'ok' => true,
                'key' => $result['key'],
                'action' => $result['action'],
                'invoice' => $result['invoice'],
            ]);
            break;

        case 'delete':
            if ($method !== 'POST') {
                api_respond(['ok' => false, 'error' => 'Use POST to delete.'], 405);
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            $sourceKey = (is_array($payload) ? ($payload['source_data'] ?? null) : null) ?? ($_GET['data'] ?? '');
            $result = delete_invoice($sourceKey);
            api_respond(['ok' => true, 'key' => $result['key'], 'action' => $result['action']]);
            break;

        case 'duplicate':
            if ($method !== 'POST') {
                api_respond(['ok' => false, 'error' => 'Use POST to duplicate.'], 405);
            }
            $payload = json_decode((string) file_get_contents('php://input'), true);
            $sourceKey = (is_array($payload) ? ($payload['source_data'] ?? null) : null) ?? ($_GET['data'] ?? '');
            $result = duplicate_invoice($sourceKey);
            api_respond([
                'ok' => true,
                'key' => $result['key'],
                'action' => $result['action'],
                'invoice' => $result['invoice'],
            ]);
            break;

        default:
            api_respond(['ok' => false, 'error' => 'Unknown action: ' . $action], 404);
    }
} catch (Throwable $e) {
    api_respond(['ok' => false, 'error' => $e->getMessage()], 400);
}
