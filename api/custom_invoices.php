<?php
//custom_invoices.php
// Simple API endpoint to handle custom invoices storage in a separate JSON file

$customInvoicesFile = __DIR__ . '/data/custom_invoices.json';

if (!file_exists($customInvoicesFile)) {
    file_put_contents($customInvoicesFile, json_encode([]));
}

function loadCustomInvoices() {
    global $customInvoicesFile;
    return json_decode(file_get_contents($customInvoicesFile), true);
}

function saveCustomInvoices($invoices) {
    global $customInvoicesFile;
    file_put_contents($customInvoicesFile, json_encode($invoices, JSON_PRETTY_PRINT));
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($uri === '/api/custom-invoices' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid input']);
        exit();
    }
    $invoices = loadCustomInvoices();
    $invoices[] = $input;
    saveCustomInvoices($invoices);
    echo json_encode(['success' => true, 'invoice' => $input]);
    exit();
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);
exit();
?>
