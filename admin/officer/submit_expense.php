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
$currentPic = getCurrentUserProfilePic($pdo);

$prefillEventId = (int)($_GET['event_id'] ?? 0);
$prefillEvent = null;
$prefillBudgetId = null;

if ($prefillEventId > 0) {
    $stmt = $pdo->prepare("SELECT id, name, school_year FROM events WHERE id = ?");
    $stmt->execute([$prefillEventId]);
    $prefillEvent = $stmt->fetch();

    if ($prefillEvent) {
        $stmt = $pdo->prepare(
            "SELECT id FROM event_budgets 
             WHERE event_id = ? AND status = 'approved' 
             ORDER BY adviser_reviewed_at DESC LIMIT 1"
        );
        $stmt->execute([$prefillEventId]);
        $prefillBudgetId = $stmt->fetchColumn() ?: null;
    }
}

$categories = $pdo->query("SELECT id, name FROM categories WHERE status='approved' ORDER BY name")->fetchAll();
$eventList = $pdo->query("SELECT id, name, school_year FROM events WHERE status='approved' ORDER BY school_year DESC, name ASC")->fetchAll();
$releasedEvents = $pdo->query("SELECT id, name, school_year FROM events WHERE status='funds_released' ORDER BY school_year DESC, name ASC")->fetchAll();
$schoolYears = $pdo->query("SELECT year_label FROM school_years ORDER BY year_label DESC")->fetchAll(PDO::FETCH_COLUMN);
$officers = $pdo->query("SELECT id, name, position FROM officers WHERE is_active = 1 ORDER BY name")->fetchAll();
$budgets = $pdo->query("SELECT id, budget_name, school_year, event_id FROM event_budgets WHERE status='approved' ORDER BY budget_name")->fetchAll();

$fundSources = [
    'Sponsorship Income','Donation Income','Fundraising Income','Merchandise Sales Income',
    'University Financial Assistance','Officer Contribution','Cash Advance from Organization Fund',
    'Previous Event Excess Funds','Savings/Reserve Fund','School Reimbursement Expense/Reimbursement',
    'Income Generating Projects (IGPs)'
];

$approvedExpenses = $pdo->query(
    "SELECT e.*, c.name AS category_name, o.name AS officer_name, o.position
     FROM expenses e
     JOIN categories c ON e.category_id = c.id
     JOIN officers o ON e.officer_id = o.id
     WHERE e.status = 'approved'
     ORDER BY e.created_at DESC"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $category_id = $_POST['category_id'];
    $payee_select = trim($_POST['payee_select'] ?? '');
    $payee_other = trim($_POST['payee_other'] ?? '');
    $description = trim($_POST['description']);
    $expense_date = $_POST['expense_date'];
    $school_year = $_POST['school_year'] ?? null;
    $event_name = trim($_POST['event'] ?? '');
    $event_id = $_POST['event_id'] ?? null;
    $fund_source = $_POST['fund_source'] ?? null;
    $budget_id = $_POST['budget_id'] ?? null;
    $officer_id = $_SESSION['officer_id'];

    $payee = ($payee_select === '__others__') ? $payee_other : $payee_select;

    if (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount.";
    } elseif (empty($category_id) || empty($payee) || empty($expense_date)) {
        $error = "Category, payee, and date are required.";
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
            "INSERT INTO expenses (amount, category_id, payee, description, expense_date, school_year, event, event_id, fund_source, budget_id, receipt_path, officer_id, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_audit')"
        );
        $stmt->execute([$amount, $category_id, $payee, $description, $expense_date, $school_year, $event_name, $event_id, $fund_source, $budget_id ?: null, $receipt_path, $officer_id]);

        $message = "Expense submitted for audit!";
        if (!empty($warnings)) $message .= " Note: " . implode('; ', $warnings);

        header("Location: submit_expense.php?msg=" . urlencode($message));
        exit;
    }
}

if (isset($_GET['msg'])) $message = $_GET['msg'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Submit Expense - BSIT Fund System</title>
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
        .success { color: #27ae60; } .error { color: #e74c3c; }

        .prefill-banner {
            background: linear-gradient(135deg, #e8f0f8, #f0f9ff);
            border-left: 4px solid #2980b9;
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #1a3c5e;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .prefill-banner i { color: #2980b9; font-size: 20px; }
        .prefill-banner strong { color: #0f2942; }

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
            width: 720px;
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
    $pageTitle = 'Submit Expense';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($prefillEvent): ?>
        <div class="prefill-banner">
            <i class="fas fa-info-circle"></i>
            <div>
                You are adding an expense for: <strong><?= htmlspecialchars($prefillEvent['name']) ?></strong>
                <br>
                <span style="font-size: 13px; opacity: 0.85;">
                    School Year: <?= htmlspecialchars($prefillEvent['school_year']) ?>
                    <?php if ($prefillBudgetId): ?>&middot; Linked budget has been auto-selected<?php endif; ?>
                </span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <div class="header-row">
                <h2><i class="fas fa-receipt"></i> Approved Expenses</h2>
                <button class="btn-add" onclick="openModal()"><i class="fas fa-plus"></i> Add Expense</button>
            </div>
            <?php if (count($approvedExpenses) == 0): ?>
                <p>No approved expenses yet.</p>
            <?php else: ?>
                <table>
                    <tr><th>Date</th><th>Category</th><th>Payee</th><th>Amount</th><th>Event</th><th>Receipt</th></tr>
                    <?php foreach ($approvedExpenses as $e): ?>
                    <tr>
                        <td><?= date('Y-m-d', strtotime($e['created_at'])) ?></td>
                        <td><?= htmlspecialchars($e['category_name']) ?></td>
                        <td><?= htmlspecialchars($e['payee']) ?></td>
                        <td>₱<?= number_format($e['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($e['event'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($e['receipt_path'])): ?>
                                <?php foreach (explode(',', $e['receipt_path']) as $rec): ?>
                                    <a href="../../<?= htmlspecialchars(trim($rec)) ?>" target="_blank"><i class="fas fa-file-alt"></i></a>
                                <?php endforeach; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="expenseModal" class="modal-overlay">
    <div class="modal-box">

        <div class="modal-header">
            <h3><i class="fas fa-receipt"></i> New Expense Entry</h3>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" id="expenseForm"
              onsubmit="return openConfirmModal(this, 'Are you sure you want to submit this expense entry?')"
              style="display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0;">

            <div class="modal-body">
                <label>Amount (₱):</label>
                <input type="number" step="0.01" name="amount" required>

                <label>Category:</label>
                <select name="category_id" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Fund Source:</label>
                <select name="fund_source" required>
                    <option value="">-- Select Fund Source --</option>
                    <?php foreach ($fundSources as $fs): ?>
                        <option value="<?= htmlspecialchars($fs) ?>"><?= htmlspecialchars($fs) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>School Year:</label>
                <select name="school_year" id="schoolYear">
                    <option value="">-- Select School Year --</option>
                    <?php foreach ($schoolYears as $yr): ?>
                        <option value="<?= htmlspecialchars($yr) ?>"><?= htmlspecialchars($yr) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Event (optional):</label>
                <select name="event_id" id="eventSelect">
                    <option value="">-- Select Event (funds released) --</option>
                    <?php foreach ($releasedEvents as $ev): ?>
                        <option value="<?= $ev['id'] ?>" data-name="<?= htmlspecialchars($ev['name']) ?>" data-school-year="<?= htmlspecialchars($ev['school_year']) ?>">
                            <?= htmlspecialchars($ev['name']) ?> (<?= htmlspecialchars($ev['school_year']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="event" id="eventNameHidden">

                <label>Budget (optional):</label>
                <select name="budget_id" id="budgetSelect">
                    <option value="">-- Select Budget --</option>
                    <?php foreach ($budgets as $b): ?>
                        <option value="<?= $b['id'] ?>" data-event-id="<?= $b['event_id'] ?>"><?= htmlspecialchars($b['budget_name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Payee:</label>
                <select name="payee_select" id="payeeSelect" required>
                    <option value="">-- Select Payee --</option>
                    <?php foreach ($officers as $off): ?>
                        <option value="<?= htmlspecialchars($off['name']) ?>">
                            <?= htmlspecialchars($off['name']) ?> (<?= $off['position'] ?>)
                        </option>
                    <?php endforeach; ?>
                    <option value="__others__">Others (specify below)</option>
                </select>

                <div id="payeeOtherWrapper" style="display:none;">
                    <label>Specify Payee Name <span style="color:red;">*</span></label>
                    <input type="text" name="payee_other" id="payeeOther" placeholder="Enter payee name">
                </div>

                <label>Description (optional):</label>
                <textarea name="description" rows="3"></textarea>

                <label>Date of Expense:</label>
                <input type="date" name="expense_date" required>

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
    const prefillEventId = <?= $prefillEventId ?: 'null' ?>;
    const prefillEvent = <?= $prefillEvent ? json_encode($prefillEvent) : 'null' ?>;
    const prefillBudgetId = <?= $prefillBudgetId ?: 'null' ?>;

    document.getElementById('eventSelect').addEventListener('change', function() {
        var selected = this.options[this.selectedIndex];
        var name = selected.getAttribute('data-name') || '';
        document.getElementById('eventNameHidden').value = name;

        var sy = selected.getAttribute('data-school-year') || '';
        if (sy) document.getElementById('schoolYear').value = sy;
    });

    const payeeSelect = document.getElementById('payeeSelect');
    const payeeOtherWrapper = document.getElementById('payeeOtherWrapper');
    const payeeOther = document.getElementById('payeeOther');

    payeeSelect.addEventListener('change', function() {
        if (this.value === '__others__') {
            payeeOtherWrapper.style.display = 'block';
            payeeOther.required = true;
            payeeOther.focus();
        } else {
            payeeOtherWrapper.style.display = 'none';
            payeeOther.required = false;
            payeeOther.value = '';
        }
    });

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

    function openModal() {
        document.getElementById('expenseModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        document.getElementById('expenseModal').classList.remove('active');
        document.body.style.overflow = '';
    }
    document.getElementById('expenseModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    window.addEventListener('DOMContentLoaded', function() {
        if (prefillEvent && prefillEventId) {
            const eventSel = document.getElementById('eventSelect');
            eventSel.value = prefillEventId;
            eventSel.dispatchEvent(new Event('change'));

            if (prefillBudgetId) {
                document.getElementById('budgetSelect').value = prefillBudgetId;
            }

            openModal();
        }
    });

    document.getElementById('userMenu').addEventListener('click', function(e) { e.stopPropagation(); this.classList.toggle('show'); });
    window.addEventListener('click', function() { document.getElementById('userMenu').classList.remove('show'); });
</script>
</body>
</html>