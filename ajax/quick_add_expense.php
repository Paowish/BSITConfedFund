<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireOfficer();

header('Content-Type: application/json');

if ($_SESSION['officer_position'] !== 'Treasurer') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$amount = $_POST['amount'] ?? 0;
$category_id = $_POST['category_id'] ?? 0;
$payee = trim($_POST['payee'] ?? '');
$description = trim($_POST['description'] ?? '');
$expense_date = $_POST['expense_date'] ?? '';
$school_year = $_POST['school_year'] ?? null;
$event_id = $_POST['event_id'] ?? null;
$fund_source = $_POST['fund_source'] ?? null;
$budget_id = $_POST['budget_id'] ?? null;
$officer_id = $_SESSION['officer_id'];

if (!is_numeric($amount) || $amount <= 0) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid amount']);
    exit;
}
if (empty($category_id) || empty($payee) || empty($expense_date)) {
    echo json_encode(['success' => false, 'error' => 'Category, payee, and date are required']);
    exit;
}
if (empty($_FILES['receipts']['name'][0])) {
    echo json_encode(['success' => false, 'error' => 'Please upload at least one source document']);
    exit;
}

// Process file uploads
$receipt_paths = [];
$uploadDir = realpath(__DIR__ . '/../') . '/uploads/receipts/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

foreach ($_FILES['receipts']['name'] as $i => $name) {
    $fileError = $_FILES['receipts']['error'][$i];
    if ($fileError === UPLOAD_ERR_OK) {
        $fileTmp = $_FILES['receipts']['tmp_name'][$i];
        $fileName = time() . '_' . $i . '_' . basename($name);
        $targetPath = $uploadDir . $fileName;

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($fileTmp);

        if (in_array($mime, $allowedTypes)) {
            if (move_uploaded_file($fileTmp, $targetPath)) {
                $receipt_paths[] = 'uploads/receipts/' . $fileName;
            }
        }
    }
}

if (empty($receipt_paths)) {
    echo json_encode(['success' => false, 'error' => 'No valid files were uploaded']);
    exit;
}

$receipt_path = implode(',', $receipt_paths);

// Insert expense
$stmt = $pdo->prepare(
    "INSERT INTO expenses (amount, category_id, payee, description, expense_date, school_year, event, event_id, fund_source, budget_id, receipt_path, officer_id, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_audit')"
);

// Get event name
$event_name = '';
if ($event_id) {
    $evStmt = $pdo->prepare("SELECT name FROM events WHERE id = ?");
    $evStmt->execute([$event_id]);
    $event_name = $evStmt->fetchColumn() ?: '';
}

$stmt->execute([
    $amount, $category_id, $payee, $description, $expense_date, $school_year,
    $event_name, $event_id ?: null, $fund_source, $budget_id ?: null, $receipt_path, $officer_id
]);

$newId = $pdo->lastInsertId();

// Get category name for response
$catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
$catStmt->execute([$category_id]);
$category_name = $catStmt->fetchColumn() ?: 'Uncategorized';

echo json_encode([
    'success' => true,
    'expense' => [
        'id' => $newId,
        'date' => $expense_date,
        'category' => $category_name,
        'payee' => $payee,
        'amount' => (float)$amount,
        'status' => 'pending_audit'
    ]
]);