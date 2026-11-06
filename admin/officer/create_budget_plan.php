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

$event_id = $_GET['event_id'] ?? $_POST['event_id'] ?? 0;
$currentPic = getCurrentUserProfilePic($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $items = $_POST['items'] ?? [];

    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'pending_budget_planning'");
    $stmt->execute([$event_id]);
    $eventData = $stmt->fetch();

    if (!$eventData) {
        die("Event not found or not eligible for budget planning. Event ID: $event_id");
    }

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
    } elseif (empty($_FILES['attachments']['name'][0])) {
        $error = "Please upload at least one source document.";
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
        $attachmentsStr = !empty($attachmentPaths) ? implode(',', $attachmentPaths) : null;

        $status = ($action === 'submit') ? 'pending_treasurer_review' : 'draft';

        $stmt = $pdo->prepare(
            "INSERT INTO event_budgets (event_id, school_year, budget_name, allocated_amount, notes, attachments, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $budgetName = $eventData['name'] . ' - Budget Plan';
        $stmt->execute([
            $event_id,
            $eventData['school_year'] ?? null,
            $budgetName,
            $totalEstimated,
            $notes,
            $attachmentsStr,
            $status,
            $_SESSION['officer_id']
        ]);

        $budgetId = $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            "INSERT INTO budget_plan_items (budget_id, item_name, quantity, unit_cost, estimated_total)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($validItems as $item) {
            $itemStmt->execute([$budgetId, $item['name'], $item['qty'], $item['unit_cost'], $item['estimated_total']]);
        }

        if ($action === 'submit') {
            $updateEvent = $pdo->prepare("UPDATE events SET status = 'pending_treasurer_review' WHERE id = ?");
            $updateEvent->execute([$event_id]);
        }

        $message = ($action === 'submit') ? "Budget plan submitted for treasurer review." : "Budget plan saved as draft.";
        header("Location: dashboard.php?msg=" . urlencode($message));
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'pending_budget_planning'");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Event not found or not eligible for budget planning.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Budget Plan - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card { background:#fff; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.03); padding:25px 30px; margin-bottom:25px; }
        .card h3 { margin-top:0; color:#1a3c5e; }
        form label { display:block; font-weight:600; margin-bottom:6px; }
        form input, form textarea { width:100%; padding:10px; margin-bottom:15px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box; }
        .line-item-row { display:flex; gap:10px; margin-bottom:10px; align-items:end; }
        .line-item-row > div { flex:1; }
        .line-item-row button { background:#e74c3c; color:white; border:none; padding:8px 12px; border-radius:6px; cursor:pointer; }
        .btn-add-item { background:#27ae60; color:white; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; margin-bottom:20px; }
        .success { color:#27ae60; } .error { color:#e74c3c; }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Create Budget Plan';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <?php if (!empty($warnings)): ?>
            <div style="background:#fff3cd;padding:10px;border-radius:6px;margin-bottom:15px;">
                <?php foreach ($warnings as $warn): ?><p style="margin:5px 0;color:#856404;">⚠️ <?= htmlspecialchars($warn) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Event: <?= htmlspecialchars($event['name'] ?? '') ?></h3>
            <p><strong>School Year:</strong> <?= htmlspecialchars($event['school_year'] ?? '—') ?></p>
            <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($event['description'] ?? '—')) ?></p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="budgetForm" onsubmit="return confirmBudgetAction(event)">
            <input type="hidden" name="event_id" value="<?= $event_id ?>">
            <input type="hidden" name="action" id="budgetAction" value="draft">

            <div class="card">
                <h3>Line Items</h3>
                <div id="lineItemsContainer"></div>
                <button type="button" class="btn-add-item" onclick="addLineItem()"><i class="fas fa-plus"></i> Add Item</button>
                <p><strong>Total Estimated Cost:</strong> ₱<span id="totalCost">0.00</span></p>
            </div>

            <div class="card">
                <h3>Notes (optional)</h3>
                <textarea name="notes" rows="3" placeholder="Any additional notes"></textarea>
            </div>

            <div class="card">
                <h3>Source Documents (receipts/attachments) <span style="color:red;">*</span></h3>
                <input type="file" name="attachments[]" multiple required accept="image/jpeg,image/png,image/gif,application/pdf">
            </div>

            <div style="display:flex; gap:10px;">
                <button type="button" class="btn-draft" onclick="submitBudget('draft')"><i class="fas fa-save"></i> Save as Draft</button>
                <button type="button" class="btn-submit" onclick="submitBudget('submit')"><i class="fas fa-paper-plane"></i> Submit for Review</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    function addLineItem() {
        const container = document.getElementById('lineItemsContainer');
        const row = document.createElement('div');
        row.className = 'line-item-row';
        row.innerHTML = `
            <div>
                <label>Item Name</label>
                <input type="text" name="items[][item_name]" required>
            </div>
            <div>
                <label>Quantity</label>
                <input type="number" min="1" name="items[][quantity]" required onchange="updateTotal()">
            </div>
            <div>
                <label>Unit Cost (₱)</label>
                <input type="number" step="0.01" min="0" name="items[][unit_cost]" required onchange="updateTotal()">
            </div>
            <div>
                <label>&nbsp;</label>
                <button type="button" onclick="removeLineItem(this)"><i class="fas fa-trash"></i></button>
            </div>
        `;
        container.appendChild(row);
    }

    function removeLineItem(btn) {
        btn.closest('.line-item-row').remove();
        updateTotal();
    }

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.line-item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('input[name="items[][quantity]"]')?.value || 0);
            const unit = parseFloat(row.querySelector('input[name="items[][unit_cost]"]')?.value || 0);
            total += qty * unit;
        });
        document.getElementById('totalCost').textContent = total.toFixed(2);
    }

    document.addEventListener('input', updateTotal);

    window.addEventListener('DOMContentLoaded', () => {
        addLineItem();
    });

    function submitBudget(action) {
        var form = document.getElementById('budgetForm');
        var message = (action === 'submit')
            ? 'Are you sure you want to submit this budget plan for review?'
            : 'Are you sure you want to save this budget plan as draft?';

        _pendingForm = form;
        _pendingCallback = function() {
            document.getElementById('budgetAction').value = action;
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