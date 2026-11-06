<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

$schoolYears = $pdo->query("SELECT year_label FROM school_years ORDER BY year_label DESC")->fetchAll(PDO::FETCH_COLUMN);
$currentPic = getCurrentUserProfilePic($pdo);

if ($_SESSION['officer_position'] !== 'Treasurer') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$warnings = [];

$approvedFunds = $pdo->query(
    "SELECT f.*, o.name AS officer_name, o.position
     FROM funds f
     JOIN officers o ON f.officer_id = o.id
     WHERE f.status = 'approved'
     ORDER BY f.created_at DESC"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $source = trim($_POST['source']);
    $description = trim($_POST['description']);
    $fund_date = $_POST['fund_date'];
    $school_year = $_POST['school_year'] ?? null;
    $officer_id = $_SESSION['officer_id'];

    if (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif (empty($source) || empty($fund_date)) {
        $error = "Source and date are required.";
    } elseif (empty($_FILES['receipts']['name'][0])) {
        $error = "Please upload at least one source document.";
    } else {
        $receipt_paths = [];

        if (!empty($_FILES['receipts']['name'][0])) {
            $uploadDir = realpath(__DIR__ . '/../../') . '/uploads/receipts/';
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
        }

        $receipt_path = !empty($receipt_paths) ? implode(',', $receipt_paths) : null;

        $stmt = $pdo->prepare(
            "INSERT INTO funds (amount, source, description, receipt_path, fund_date, school_year, officer_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_audit')"
        );
        $stmt->execute([$amount, $source, $description, $receipt_path, $fund_date, $school_year, $officer_id]);

        $message = "Fund submitted for audit!";
        if (!empty($warnings)) {
            $message .= " Note: " . implode('; ', $warnings);
        }

        header("Location: submit_fund.php?msg=" . urlencode($message));
        exit;
    }
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Submit Fund - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header-row h2 { margin: 0; }
        .card { background: #fff; border-radius: 10px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.03); padding: 25px 30px; margin-bottom: 25px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; font-size: 14px; }
        th { background: #f8f9fa; color: #555; text-transform: uppercase; font-size: 13px; }
        .file-preview { font-size: 13px; color: #666; margin-top: -8px; margin-bottom: 16px; }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; }

        .modal-box {
            background: #fff;
            border-radius: 14px;
            width: 680px;
            max-width: 95vw;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
            animation: modalSlideUp 0.25s ease-out;
            padding: 0;
            position: relative;
        }

        @keyframes modalSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .modal-header {
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            background: #fff;
            border-radius: 14px 14px 0 0;
        }
        .modal-header h3 {
            margin: 0;
            color: #1a3c5e;
            font-size: 19px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-header h3 i { color: #2980b9; }

        .modal-close {
            background: #f1f5f9;
            border: none;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            cursor: pointer;
            color: #64748b;
            font-size: 20px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s, color 0.2s;
        }
        .modal-close:hover { background: #e2e8f0; color: #1a3c5e; }

        .modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 22px 30px;
        }
        .modal-body::-webkit-scrollbar { width: 8px; }
        .modal-body::-webkit-scrollbar-track { background: #f1f5f9; }
        .modal-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        .modal-body label {
            display: block;
            font-weight: 600;
            font-size: 13.5px;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .modal-body input,
        .modal-body select,
        .modal-body textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            margin-bottom: 16px;
            box-sizing: border-box;
            background: #fafbfd;
            transition: border 0.2s, box-shadow 0.2s;
        }
        .modal-body input:focus,
        .modal-body select:focus,
        .modal-body textarea:focus {
            outline: none;
            border-color: #1a3c5e;
            box-shadow: 0 0 0 3px rgba(26, 60, 94, 0.1);
            background: #fff;
        }
        .modal-body textarea { resize: vertical; min-height: 70px; }

        .modal-footer {
            padding: 16px 30px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            flex-shrink: 0;
            border-radius: 0 0 14px 14px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-submit {
            background: #1a3c5e;
            color: #fff;
            border: none;
            padding: 12px 26px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-submit:hover { background: #2c5282; }
        .btn-submit:active { transform: scale(0.98); }

        .btn-cancel {
            background: #fff;
            color: #64748b;
            border: 1px solid #d1d5db;
            padding: 12px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-cancel:hover { background: #f1f5f9; }

        @media (max-width: 600px) {
            .modal-box { width: 100%; max-height: 95vh; }
            .modal-header, .modal-body, .modal-footer { padding-left: 20px; padding-right: 20px; }
            .modal-footer { flex-direction: column-reverse; }
            .btn-submit, .btn-cancel { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Submit Fund';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <div class="header-row">
                <h2><i class="fas fa-coins"></i> Approved Funds</h2>
                <button class="btn-add" onclick="openModal()"><i class="fas fa-plus"></i> Add Fund</button>
            </div>
            <?php if(count($approvedFunds)==0): ?><p>No approved funds yet.</p><?php else: ?>
            <table><tr><th>Date</th><th>Source</th><th>Amount</th><th>School Year</th><th>Receipt</th></tr>
            <?php foreach($approvedFunds as $f): ?>
            <tr><td><?= date('Y-m-d', strtotime($f['created_at'])) ?></td><td><?= htmlspecialchars($f['source']) ?></td><td>₱<?= number_format($f['amount'],2) ?></td><td><?= htmlspecialchars($f['school_year'] ?? '—') ?></td><td><?php if(!empty($f['receipt_path'])): foreach(explode(',',$f['receipt_path']) as $rec): ?><a href="../../<?=trim($rec)?>" target="_blank"><i class="fas fa-file-alt"></i></a><?php endforeach; else: ?>—<?php endif; ?></td></tr>
            <?php endforeach; ?>
            </table><?php endif; ?>
        </div>
    </div>
</div>

<div id="fundModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-coins"></i> New Fund Entry</h3>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="fundForm"
              onsubmit="return openConfirmModal(this, 'Are you sure you want to submit this fund entry?')"
              style="display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0;">

            <div class="modal-body">
                <label>Amount (₱):</label>
                <input type="number" step="0.01" name="amount" required>

                <label>Source:</label>
                <select name="source" required>
                    <option value="">-- Select Source --</option>
                    <option value="Sponsorship Income">Sponsorship Income</option>
                    <option value="Donation Income">Donation Income</option>
                    <option value="Fundraising Income">Fundraising Income</option>
                    <option value="Merchandise Sales Income">Merchandise Sales Income</option>
                    <option value="University Financial Assistance">University Financial Assistance</option>
                    <option value="Officer Contribution">Officer Contribution</option>
                    <option value="Cash Advance from Organization Fund">Cash Advance from Organization Fund</option>
                    <option value="Previous Event Excess Funds">Previous Event Excess Funds</option>
                    <option value="Savings/Reserve Fund">Savings/Reserve Fund</option>
                    <option value="School Reimbursement Expense/Reimbursement">School Reimbursement Expense/Reimbursement</option>
                    <option value="Income Generating Projects (IGPs)">Income Generating Projects (IGPs)</option>
                </select>

                <label>School Year:</label>
                <select name="school_year">
                    <option value="">-- Select School Year --</option>
                    <?php foreach ($schoolYears as $yr): ?>
                        <option value="<?= htmlspecialchars($yr) ?>"><?= htmlspecialchars($yr) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Description (optional):</label>
                <textarea name="description" rows="3"></textarea>

                <label>Date Received:</label>
                <input type="date" name="fund_date" required>

                <label>Source Documents (receipts/attachments) <span style="color:red;">*</span></label>
                <input type="file" name="receipts[]" id="receiptInput" multiple required accept="image/jpeg,image/png,image/gif,application/pdf">
                <p id="fileList" class="file-preview"></p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit for Audit
                </button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    function openModal(){ document.getElementById('fundModal').classList.add('active'); document.body.style.overflow='hidden'; }
    function closeModal(){ document.getElementById('fundModal').classList.remove('active'); document.body.style.overflow=''; }
    document.getElementById('fundModal').addEventListener('click', function(e){ if(e.target===this) closeModal(); });
    document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeModal(); });

    var receiptInput = document.getElementById('receiptInput');
    if(receiptInput){
        receiptInput.addEventListener('change', function(e){
            var files = e.target.files;
            var list = document.getElementById('fileList');
            if(files.length > 0){
                var names = [];
                for(var i=0; i<files.length; i++) names.push(files[i].name);
                list.textContent = 'Selected files (' + files.length + '): ' + names.join(', ');
            } else {
                list.textContent = '';
            }
        });
    }
    document.getElementById('userMenu').addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('show');});
    window.addEventListener('click',function(){document.getElementById('userMenu').classList.remove('show');});
</script>
</body>
</html>