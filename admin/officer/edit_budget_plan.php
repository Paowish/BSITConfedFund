<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

if ($_SESSION['officer_position'] !== 'Business Manager') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$warnings = [];

$budgetId = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM event_budgets WHERE id = ? AND created_by = ?");
$stmt->execute([$budgetId, $_SESSION['officer_id']]);
$budget = $stmt->fetch();

if (!$budget) {
    die("Budget plan not found or not accessible.");
}

$itemsStmt = $pdo->prepare("SELECT * FROM budget_plan_items WHERE budget_id = ?");
$itemsStmt->execute([$budgetId]);
$existingItems = $itemsStmt->fetchAll();

$eventStmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$eventStmt->execute([$budget['event_id']]);
$event = $eventStmt->fetch();
$currentPic = getCurrentUserProfilePic($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'draft';
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    $validItems = [];
    $totalEstimated = 0;
    foreach ($items as $item) {
        $name = trim($item['item_name'] ?? '');
        $qty = intval($item['quantity'] ?? 0);
        $unitCost = floatval($item['unit_cost'] ?? 0);
        if ($name !== '' && $qty > 0 && $unitCost >= 0) {
            $estimatedTotal = $qty * $unitCost;
            $totalEstimated += $estimatedTotal;
            $validItems[] = [
                'name' => $name,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'estimated_total' => $estimatedTotal
            ];
        }
    }

    if (count($validItems) == 0) {
        $error = "Please add at least one valid line item.";
    } else {
        $attachmentPaths = [];
        if (!empty($_FILES['attachments']['name'][0])) {
            $uploadDir = realpath(__DIR__ . '/../../') . '/uploads/budget_attachments/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            foreach ($_FILES['attachments']['name'] as $i => $fileName) {
                $fileError = $_FILES['attachments']['error'][$i];
                if ($fileError === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES['attachments']['tmp_name'][$i];
                    $uniqueName = time() . '_' . $i . '_' . basename($fileName);
                    $targetPath = $uploadDir . $uniqueName;
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($fileTmp);
                    if (in_array($mime, $allowedTypes)) {
                        if (move_uploaded_file($fileTmp, $targetPath)) {
                            $attachmentPaths[] = 'uploads/budget_attachments/' . $uniqueName;
                        }
                    }
                }
            }
        }
        $attachmentsStr = !empty($attachmentPaths) ? implode(',', $attachmentPaths) : $budget['attachments'];

        $status = ($action === 'submit') ? 'pending_treasurer_review' : 'draft';

        $stmt = $pdo->prepare("UPDATE event_budgets SET notes = ?, attachments = ?, allocated_amount = ?, status = ?, reviewed_by = NULL, reviewed_at = NULL, review_comment = NULL WHERE id = ?");
        $stmt->execute([$notes, $attachmentsStr, $totalEstimated, $status, $budgetId]);

        $pdo->prepare("DELETE FROM budget_plan_items WHERE budget_id = ?")->execute([$budgetId]);

        $insertItem = $pdo->prepare("INSERT INTO budget_plan_items (budget_id, item_name, quantity, unit_cost, estimated_total) VALUES (?, ?, ?, ?, ?)");
        foreach ($validItems as $item) {
            $insertItem->execute([$budgetId, $item['name'], $item['qty'], $item['unit_cost'], $item['estimated_total']]);
        }

        if ($action === 'submit') {
            $pdo->prepare("UPDATE events SET status = 'pending_treasurer_review' WHERE id = ?")->execute([$budget['event_id']]);
        }

        $message = ($action === 'submit') ? "Budget plan submitted for treasurer review." : "Budget plan saved as draft.";
        header("Location: dashboard.php?msg=" . urlencode($message));
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Budget Plan - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .ebp-wrapper {
            background: #fff;
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: calc(100vh - 140px);
        }
        .ebp-banner {
            background: linear-gradient(135deg, #2980b9 0%, #1a3c5e 100%);
            color: #fff;
            padding: 20px 28px;
            display: flex;
            gap: 16px;
            align-items: flex-start;
            flex-shrink: 0;
        }
        .ebp-banner-icon {
            width: 52px; height: 52px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }
        .ebp-banner h2 {
            margin: 0 0 4px 0;
            font-size: 19px;
            color: #fff;
            font-weight: 700;
        }
        .ebp-banner .ebp-meta {
            font-size: 12.5px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .ebp-body {
            flex: 1;
            overflow-y: auto;
            padding: 22px 28px;
        }
        .ebp-body::-webkit-scrollbar { width: 8px; }
        .ebp-body::-webkit-scrollbar-track { background: #f1f5f9; }
        .ebp-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .ebp-section {
            margin-bottom: 22px;
            padding-bottom: 22px;
            border-bottom: 1px solid #f1f5f9;
        }
        .ebp-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .ebp-section-title {
            margin: 0 0 14px 0;
            color: #1a3c5e;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .ebp-section-title i { color: #2980b9; font-size: 15px; }
        .ebp-body label {
            display: block;
            font-weight: 600;
            font-size: 13.5px;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .ebp-body input[type="text"],
        .ebp-body input[type="number"],
        .ebp-body textarea {
            width: 100%;
            padding: 10px 13px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 13.5px;
            font-family: inherit;
            box-sizing: border-box;
            background: #fafbfd;
            transition: border 0.2s, box-shadow 0.2s;
        }
        .ebp-body input:focus,
        .ebp-body textarea:focus {
            outline: none;
            border-color: #1a3c5e;
            box-shadow: 0 0 0 3px rgba(26, 60, 94, 0.1);
            background: #fff;
        }
        .ebp-body textarea { resize: vertical; min-height: 70px; }
        .ebp-items-header {
            display: grid;
            grid-template-columns: 2.2fr 0.8fr 1.2fr 40px;
            gap: 10px;
            padding: 0 4px 8px 4px;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .ebp-items-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .ebp-item-row {
            display: grid;
            grid-template-columns: 2.2fr 0.8fr 1.2fr 40px;
            gap: 10px;
            align-items: center;
            background: #f8fafc;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            transition: background 0.15s, border 0.15s;
        }
        .ebp-item-row:hover { background: #f1f5f9; border-color: #cbd5e1; }
        .ebp-item-row input {
            width: 100% !important;
            padding: 9px 12px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 6px !important;
            font-size: 13.5px !important;
            font-family: inherit !important;
            background: #fff !important;
            box-sizing: border-box !important;
            margin: 0 !important;
        }
        .ebp-item-row input:focus {
            outline: none !important;
            border-color: #1a3c5e !important;
            box-shadow: 0 0 0 3px rgba(26, 60, 94, 0.1) !important;
        }
        .ebp-remove-btn {
            background: #fee2e2 !important;
            color: #e74c3c !important;
            border: none !important;
            width: 36px !important;
            height: 36px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 13px !important;
            padding: 0 !important;
            margin: 0 !important;
            transition: background 0.2s, color 0.2s !important;
        }
        .ebp-remove-btn:hover {
            background: #e74c3c !important;
            color: #fff !important;
        }
        .ebp-add-btn {
            background: #e8f0f8 !important;
            color: #1a3c5e !important;
            border: 1px dashed #2980b9 !important;
            padding: 10px 18px !important;
            border-radius: 8px !important;
            cursor: pointer !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            margin-top: 12px !important;
            transition: background 0.2s !important;
        }
        .ebp-add-btn:hover { background: #d8e6f2 !important; }
        .ebp-total {
            margin-top: 16px;
            padding: 14px 20px;
            background: linear-gradient(135deg, #e8f0f8, #f0f9ff);
            border-radius: 10px;
            border-left: 4px solid #1a3c5e;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ebp-total span {
            font-size: 13px;
            color: #1a3c5e;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ebp-total strong {
            font-size: 22px;
            color: #1a3c5e;
            font-weight: 800;
        }
        .ebp-attach-list {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 14px;
            border-left: 3px solid #27ae60;
        }
        .ebp-attach-list p {
            margin: 0 0 6px 0;
            font-size: 12px;
            font-weight: 700;
            color: #1a3c5e;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .ebp-attach-list a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #2980b9;
            font-size: 13px;
            text-decoration: none;
            margin-right: 12px;
            font-weight: 600;
        }
        .ebp-attach-list a:hover { text-decoration: underline; }
        .ebp-file-input {
            width: 100% !important;
            padding: 10px 13px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 8px !important;
            font-size: 13px !important;
            background: #fafbfd !important;
            box-sizing: border-box !important;
            cursor: pointer !important;
        }
        .ebp-footer {
            padding: 16px 28px;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            flex-shrink: 0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .ebp-btn {
            border: none;
            padding: 11px 22px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: inherit;
            transition: background 0.2s, transform 0.1s;
        }
        .ebp-btn:active { transform: scale(0.98); }
        .ebp-btn-draft {
            background: #f39c12;
            color: #fff;
        }
        .ebp-btn-draft:hover { background: #d68910; }
        .ebp-btn-submit {
            background: #1a3c5e;
            color: #fff;
        }
        .ebp-btn-submit:hover { background: #2c5282; }
        .ebp-alert {
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 600;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ebp-alert-success { background: #d4edda; color: #155724; }
        .ebp-alert-error { background: #f8d7da; color: #721c24; }
        @media (max-width: 768px) {
            .ebp-wrapper { max-height: none; }
            .ebp-items-header { display: none; }
            .ebp-item-row { grid-template-columns: 1fr; gap: 8px; }
            .ebp-remove-btn { width: 100% !important; height: 34px !important; }
            .ebp-footer { flex-direction: column-reverse; }
            .ebp-btn { width: 100%; justify-content: center; }
            .ebp-banner { flex-direction: column; }
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Edit Budget Plan';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?><div class="ebp-alert ebp-alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="ebp-alert ebp-alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="budgetForm" onsubmit="return confirmBudgetAction(event)">
            <input type="hidden" name="action" id="budgetAction" value="draft">

            <div class="ebp-wrapper">

                <div class="ebp-banner">
                    <div class="ebp-banner-icon"><i class="fas fa-edit"></i></div>
                    <div style="flex:1;">
                        <h2><?= htmlspecialchars($event['name']) ?></h2>
                        <div class="ebp-meta">
                            <i class="fas fa-graduation-cap"></i>
                            School Year: <?= htmlspecialchars($event['school_year'] ?? '—') ?>
                        </div>
                    </div>
                </div>

                <div class="ebp-body">

                    <div class="ebp-section">
                        <h3 class="ebp-section-title"><i class="fas fa-sticky-note"></i> Notes (optional)</h3>
                        <textarea name="notes" rows="3" placeholder="Any additional notes..."><?= htmlspecialchars($budget['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="ebp-section">
                        <h3 class="ebp-section-title"><i class="fas fa-list-ul"></i> Line Items</h3>

                        <div class="ebp-items-header">
                            <div>Item Name</div>
                            <div>Quantity</div>
                            <div>Unit Cost (₱)</div>
                            <div></div>
                        </div>

                        <div class="ebp-items-list" id="lineItemsContainer"></div>

                        <button type="button" class="ebp-add-btn" onclick="addLineItem()">
                            <i class="fas fa-plus"></i> Add Item
                        </button>

                        <div class="ebp-total">
                            <span><i class="fas fa-calculator"></i> Total Estimated Cost</span>
                            <strong>₱<span id="totalCost"><?= number_format($budget['allocated_amount'], 2) ?></span></strong>
                        </div>
                    </div>

                    <div class="ebp-section">
                        <h3 class="ebp-section-title"><i class="fas fa-paperclip"></i> Source Documents</h3>

                        <?php if (!empty($budget['attachments'])): ?>
                            <div class="ebp-attach-list">
                                <p><i class="fas fa-check-circle" style="color:#27ae60;"></i> Current documents (leave blank to keep)</p>
                                <?php foreach (explode(',', $budget['attachments']) as $att): ?>
                                    <a href="../../<?= htmlspecialchars(trim($att)) ?>" target="_blank">
                                        <i class="fas fa-file-alt"></i> View file
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <label>Upload new documents (optional)</label>
                        <input type="file" name="attachments[]" class="ebp-file-input" multiple accept="image/jpeg,image/png,image/gif,application/pdf">
                        <p style="font-size:11.5px; color:#64748b; margin:6px 0 0 0;">
                            <i class="fas fa-info-circle"></i> Accepted: JPG, PNG, GIF, PDF
                        </p>
                    </div>

                </div>

                <div class="ebp-footer">
                    <button type="button" class="ebp-btn ebp-btn-draft" onclick="submitBudget('draft')">
                        <i class="fas fa-save"></i> Save as Draft
                    </button>
                    <button type="button" class="ebp-btn ebp-btn-submit" onclick="submitBudget('submit')">
                        <i class="fas fa-paper-plane"></i> Submit for Review
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    let lineItemCounter = 0;

    const existingItems = <?= json_encode(array_map(function($item) {
        return [
            'name' => $item['item_name'],
            'qty' => $item['quantity'],
            'unit_cost' => $item['unit_cost']
        ];
    }, $existingItems)) ?>;

    window.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('lineItemsContainer');

        if (existingItems.length > 0) {
            existingItems.forEach(item => {
                addLineItem(item.name, item.qty, item.unit_cost);
            });
        } else {
            addLineItem();
        }

        updateTotal();
    });

    function addLineItem(name = '', qty = '', unitCost = '') {
        const container = document.getElementById('lineItemsContainer');
        const idx = lineItemCounter++;
        const row = document.createElement('div');
        row.className = 'ebp-item-row';
        row.innerHTML = `
            <input type="text" name="items[${idx}][item_name]" value="${escapeHtml(name)}" placeholder="e.g. Tarpaulin" required>
            <input type="number" min="1" name="items[${idx}][quantity]" value="${escapeHtml(qty)}" placeholder="Qty" required oninput="updateTotal()">
            <input type="number" step="0.01" min="0" name="items[${idx}][unit_cost]" value="${escapeHtml(unitCost)}" placeholder="0.00" required oninput="updateTotal()">
            <button type="button" class="ebp-remove-btn" onclick="removeLineItem(this)" title="Remove">
                <i class="fas fa-trash"></i>
            </button>
        `;
        container.appendChild(row);
        updateTotal();
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function removeLineItem(btn) {
        btn.closest('.ebp-item-row').remove();
        updateTotal();
    }

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.ebp-item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('input[name*="[quantity]"]')?.value || 0);
            const unit = parseFloat(row.querySelector('input[name*="[unit_cost]"]')?.value || 0);
            total += qty * unit;
        });
        document.getElementById('totalCost').textContent = total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function submitBudget(action) {
        var form = document.getElementById('budgetForm');
        var message = (action === 'submit')
            ? 'Are you sure you want to submit this budget plan for review?'
            : 'Are you sure you want to save this budget plan as draft?';

        document.getElementById('budgetAction').value = action;

        _pendingForm = form;
        _pendingCallback = function() {
            form.submit();
        };
        document.getElementById('confirmMessage').textContent = message;
        document.getElementById('confirmModal').classList.add('active');
    }

    function confirmBudgetAction(e) {
        e.preventDefault();
        return false;
    }

    document.getElementById('userMenu').addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('show');
    });
    window.addEventListener('click', function() {
        document.getElementById('userMenu').classList.remove('show');
    });
</script>
</body>
</html>