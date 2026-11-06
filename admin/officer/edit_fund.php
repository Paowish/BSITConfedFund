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

$stmt = $pdo->prepare("SELECT * FROM funds WHERE id = ? AND officer_id = ? AND status = 'denied'");
$stmt->execute([$id, $officer_id]);
$fund = $stmt->fetch();

if (!$fund) {
    die("Denied fund entry not found.");
}

$schoolYears = $pdo->query("SELECT year_label FROM school_years ORDER BY year_label DESC")->fetchAll(PDO::FETCH_COLUMN);
$currentPic = getCurrentUserProfilePic($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $source = trim($_POST['source']);
    $description = trim($_POST['description']);
    $fund_date = $_POST['fund_date'];
    $school_year = $_POST['school_year'] ?? null;

    if (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif (empty($source) || empty($fund_date)) {
        $error = "Source and date are required.";
    } else {
        $receipt_path = $fund['receipt_path'];

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
            "UPDATE funds 
             SET amount = ?, source = ?, description = ?, fund_date = ?, school_year = ?, receipt_path = ?,
                 status = 'pending_audit', reviewed_by = NULL, reviewed_at = NULL, review_comment = NULL,
                 audited_by = NULL, audited_at = NULL, audit_comment = NULL
             WHERE id = ? AND officer_id = ?"
        );
        $stmt->execute([$amount, $source, $description, $fund_date, $school_year, $receipt_path, $id, $officer_id]);

        $message = "Fund updated and resubmitted for audit.";
        if (!empty($warnings)) $message .= " Note: " . implode('; ', $warnings);

        header("Location: dashboard.php?msg=" . urlencode($message));
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Fund - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card { background:#fff; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.03); padding:25px 30px; margin-bottom:25px; max-width:600px; }
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
    $pageTitle = 'Edit Fund';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <div class="card">
            <h3>Edit Denied Fund Entry</h3>
            <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            <form method="POST" enctype="multipart/form-data" onsubmit="return openConfirmModal(this, 'Are you sure you want to resubmit this fund entry?')">
                <label>Amount (₱):</label>
                <input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($fund['amount']) ?>" required>

                <label>Source:</label>
                <select name="source" required>
                    <option value="">-- Select Source --</option>
                    <?php
                    $sources = ['Sponsorship Income','Donation Income','Fundraising Income','Merchandise Sales Income','University Financial Assistance','Officer Contribution','Cash Advance from Organization Fund','Previous Event Excess Funds','Savings/Reserve Fund','School Reimbursement Expense/Reimbursement','Income Generating Projects (IGPs)'];
                    foreach ($sources as $src):
                    ?>
                        <option value="<?= htmlspecialchars($src) ?>" <?= $fund['source'] === $src ? 'selected' : '' ?>><?= htmlspecialchars($src) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>School Year:</label>
                <select name="school_year">
                    <option value="">-- Select School Year --</option>
                    <?php foreach ($schoolYears as $yr): ?>
                        <option value="<?= htmlspecialchars($yr) ?>" <?= $fund['school_year'] === $yr ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Description (optional):</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($fund['description']) ?></textarea>

                <label>Date Received:</label>
                <input type="date" name="fund_date" value="<?= htmlspecialchars($fund['fund_date']) ?>" required>

                <?php if (!empty($fund['receipt_path'])): ?>
                    <p style="font-size:13px;color:#555;">
                        <strong>Current Source Documents:</strong>
                        <?php foreach (explode(',', $fund['receipt_path']) as $rec): ?>
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
    const receiptInput = document.getElementById('receiptInput');
    receiptInput.addEventListener('change', function(e) {
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