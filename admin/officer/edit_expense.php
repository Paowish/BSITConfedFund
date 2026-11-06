<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

if ($_SESSION['officer_position'] !== 'Treasurer') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$warnings = [];

$id = $_GET['id'] ?? 0;
$officer_id = $_SESSION['officer_id'];

$stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND officer_id = ? AND status = 'denied'");
$stmt->execute([$id, $officer_id]);
$expense = $stmt->fetch();

if (!$expense) {
    die("Denied expense entry not found.");
}

$categories = $pdo->query("SELECT id, name FROM categories WHERE status='approved' ORDER BY name")->fetchAll();
$eventList = $pdo->query("SELECT id, name, school_year FROM events WHERE status='approved' ORDER BY school_year DESC, name ASC")->fetchAll();
$schoolYears = $pdo->query("SELECT year_label FROM school_years ORDER BY year_label DESC")->fetchAll(PDO::FETCH_COLUMN);
$officers = $pdo->query("SELECT id, name, position FROM officers ORDER BY name")->fetchAll();
$budgets = $pdo->query("SELECT id, budget_name FROM event_budgets WHERE status='approved' ORDER BY budget_name")->fetchAll();
$currentPic = getCurrentUserProfilePic($pdo);

$fundSources = [
    'Sponsorship Income','Donation Income','Fundraising Income','Merchandise Sales Income',
    'University Financial Assistance','Officer Contribution','Cash Advance from Organization Fund',
    'Previous Event Excess Funds','Savings/Reserve Fund','School Reimbursement Expense/Reimbursement',
    'Income Generating Projects (IGPs)'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $category_id = $_POST['category_id'];
    $payee = trim($_POST['payee']);
    $description = trim($_POST['description']);
    $expense_date = $_POST['expense_date'];
    $school_year = $_POST['school_year'] ?? null;
    $event = $_POST['event'] ?? null;
    $fund_source = $_POST['fund_source'] ?? null;
    $budget_id = $_POST['budget_id'] ?? null;

    if (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif (empty($category_id) || empty($payee) || empty($expense_date)) {
        $error = "Category, who paid, and date are required.";
    } else {
        $receipt_path = $expense['receipt_path'];

        if (!empty($_FILES['receipts']['name'][0])) {
            $uploadDir = realpath(__DIR__ . '/../../') . '/uploads/receipts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $receipt_paths = [];

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
                        } else {
                            $warnings[] = "Failed to move file: " . htmlspecialchars($name);
                        }
                    } else {
                        $warnings[] = "Skipped file (invalid type): " . htmlspecialchars($name);
                    }
                } elseif ($fileError !== UPLOAD_ERR_NO_FILE) {
                    $warnings[] = "File '" . htmlspecialchars($name) . "' upload error code: $fileError";
                }
            }
            if (!empty($receipt_paths)) {
                $receipt_path = implode(',', $receipt_paths);
            }
        }

        $stmt = $pdo->prepare(
            "UPDATE expenses 
             SET amount = ?, category_id = ?, payee = ?, description = ?, expense_date = ?, school_year = ?, event = ?, fund_source = ?, budget_id = ?, receipt_path = ?,
                 status = 'pending_audit', reviewed_by = NULL, reviewed_at = NULL, review_comment = NULL,
                 audited_by = NULL, audited_at = NULL, audit_comment = NULL
             WHERE id = ? AND officer_id = ?"
        );
        $stmt->execute([$amount, $category_id, $payee, $description, $expense_date, $school_year, $event, $fund_source, $budget_id ?: null, $receipt_path, $id, $officer_id]);

        $message = "Expense updated and resubmitted for audit.";
        if (!empty($warnings)) $message .= " Note: " . implode('; ', $warnings);

        header("Location: dashboard.php?msg=" . urlencode($message));
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Expense - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card { background:#fff; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.03); padding:25px 30px; margin-bottom:25px; max-width:700px; }
        .card h3 { margin-top:0; color:#1a3c5e; }
        form label { display:block; font-weight:600; margin-bottom:6px; }
        form input, form select, form textarea { width:100%; padding:10px; margin-bottom:15px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box; }
        .success { color:#27ae60; } .error { color:#e74c3c; }
        .file-preview { font-size:14px; color:#666; margin-top:5px; }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Edit Expense';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <div class="card">
            <h3>Edit Denied Expense Entry</h3>
            <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <form method="POST" enctype="multipart/form-data" onsubmit="return openConfirmModal(this, 'Are you sure you want to resubmit this expense entry?')">
                <label>Amount (₱):</label>
                <input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($expense['amount']) ?>" required>

                <label>Category:</label>
                <select name="category_id" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $expense['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Fund Source:</label>
                <select name="fund_source" required>
                    <option value="">-- Select Fund Source --</option>
                    <?php foreach ($fundSources as $fs): ?>
                        <option value="<?= htmlspecialchars($fs) ?>" <?= $expense['fund_source'] === $fs ? 'selected' : '' ?>><?= htmlspecialchars($fs) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>School Year:</label>
                <select name="school_year">
                    <option value="">-- Select School Year --</option>
                    <?php foreach ($schoolYears as $yr): ?>
                        <option value="<?= htmlspecialchars($yr) ?>" <?= $expense['school_year'] === $yr ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Event:</label>
                <select name="event">
                    <option value="">-- Select Event --</option>
                    <?php foreach ($eventList as $ev): ?>
                        <option value="<?= htmlspecialchars($ev['name']) ?>" <?= $expense['event'] === $ev['name'] ? 'selected' : '' ?>><?= htmlspecialchars($ev['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Budget (optional):</label>
                <select name="budget_id">
                    <option value="">-- Select Budget --</option>
                    <?php foreach ($budgets as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $expense['budget_id'] == $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['budget_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Who paid (officer):</label>
                <select name="payee" required>
                    <option value="">-- Select Officer --</option>
                    <?php foreach ($officers as $off): ?>
                        <option value="<?= htmlspecialchars($off['name']) ?>" <?= $expense['payee'] === $off['name'] ? 'selected' : '' ?>><?= htmlspecialchars($off['name']) ?> (<?= $off['position'] ?>)</option>
                    <?php endforeach; ?>
                </select>

                <label>Description (optional):</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($expense['description']) ?></textarea>

                <label>Date of Expense:</label>
                <input type="date" name="expense_date" value="<?= htmlspecialchars($expense['expense_date']) ?>" required>

                <?php if (!empty($expense['receipt_path'])): ?>
                    <p style="font-size:13px;color:#555;">
                        <strong>Current Source Documents:</strong>
                        <?php foreach (explode(',', $expense['receipt_path']) as $rec): ?>
                            <a href="../../<?= htmlspecialchars(trim($rec)) ?>" target="_blank"><i class="fas fa-file-alt"></i> View</a>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>

                <label>Replace Source Documents (leave blank to keep current)</label>
                <input type="file" name="receipts[]" id="receiptInput" multiple accept="image/jpeg,image/png,image/gif,application/pdf">
                <p id="fileList" class="file-preview"></p>

                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> Resubmit for Audit</button>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    document.getElementById('receiptInput').addEventListener('change', function(e) {
        const files = e.target.files;
        const list = document.getElementById('fileList');
        if (files.length > 0) {
            list.textContent = 'Selected files (' + files.length + '): ' + Array.from(files).map(f => f.name).join(', ');
        } else {
            list.textContent = '';
        }
    });

    document.getElementById('userMenu').addEventListener('click', function(e){ e.stopPropagation(); this.classList.toggle('show'); });
    window.addEventListener('click', function(){ document.getElementById('userMenu').classList.remove('show'); });
</script>
</body>
</html>