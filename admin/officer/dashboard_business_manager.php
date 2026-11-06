<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

// Only Business Manager can access
if ($_SESSION['officer_position'] !== 'Business Manager') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$summary = getFinancialSummary($pdo);
$currentPic = getCurrentUserProfilePic($pdo);

// Status counts for Budget Plans
$draftCount = $pdo->prepare("SELECT COUNT(*) FROM event_budgets WHERE created_by = ? AND status = 'draft'");
$draftCount->execute([$_SESSION['officer_id']]);
$drafts = $draftCount->fetchColumn();

$pendingTreasurer = $pdo->prepare("SELECT COUNT(*) FROM event_budgets WHERE created_by = ? AND status = 'pending_treasurer_review'");
$pendingTreasurer->execute([$_SESSION['officer_id']]);
$pendingT = $pendingTreasurer->fetchColumn();

$pendingAdviser = $pdo->prepare("SELECT COUNT(*) FROM event_budgets WHERE created_by = ? AND status = 'pending_adviser_review'");
$pendingAdviser->execute([$_SESSION['officer_id']]);
$pendingA = $pendingAdviser->fetchColumn();

$approvedCount = $pdo->prepare("SELECT COUNT(*) FROM event_budgets WHERE created_by = ? AND status = 'approved'");
$approvedCount->execute([$_SESSION['officer_id']]);
$approved = $approvedCount->fetchColumn();

// Events needing budget plan (LIMIT 5 for Dashboard)
$events = $pdo->query(
    "SELECT e.*
     FROM events e
     LEFT JOIN event_budgets eb ON eb.event_id = e.id
     WHERE e.status = 'pending_budget_planning'
       AND eb.id IS NULL
     ORDER BY e.created_at DESC LIMIT 5"
)->fetchAll();

// My budget plans (LIMIT 5 for Dashboard)
$stmt = $pdo->prepare(
    "SELECT eb.*, e.name AS event_name, e.status AS event_status
     FROM event_budgets eb
     JOIN events e ON eb.event_id = e.id
     WHERE eb.created_by = ?
     ORDER BY eb.created_at DESC LIMIT 5"
);
$stmt->execute([$_SESSION['officer_id']]);
$myPlans = $stmt->fetchAll();

// ============ TRANSACTIONS TAB QUERY ============
$stmtTx = $pdo->prepare(
    "SELECT 'Budget Plan' AS type, id, budget_name AS details, allocated_amount AS amount, created_at AS tx_date, status, created_at
     FROM event_budgets WHERE created_by = ?
     ORDER BY created_at DESC"
);
$stmtTx->execute([$_SESSION['officer_id']]);
$allTransactions = $stmtTx->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Business Manager Dashboard - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background: #f4f6f9; }
        .main-area { margin-left: 240px; }
        .content { padding: 25px; }

        .summary-cards { display: flex; gap: 20px; margin-bottom: 25px; flex-wrap: wrap; }
        .summary-card {
            flex: 1; min-width: 180px; border-radius: 12px; padding: 20px;
            color: white; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex; align-items: center; gap: 15px;
        }
        .summary-card .icon {
            font-size: 30px; background: rgba(255,255,255,0.2);
            width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; border-radius: 50%;
        }
        .summary-card .details { color: #ffffff !important; }
        .summary-card .details h4 {
            font-size: 14px; font-weight: 400; color: #ffffff !important;
            opacity: 0.95; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .summary-card .details .amount {
            font-size: 28px; font-weight: 700; color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.15);
        }
        .funds { background: linear-gradient(135deg, #2980b9, #1a3c5e); }
        .expenses { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
        .balance { background: linear-gradient(135deg, #00b894, #009432); }

        .dash-section { display: block; margin-bottom: 25px; }
        .dash-section > .two-col:last-child { margin-bottom: 0; }

        .stat-badges { display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
        .stat-badge {
            background: #fff; border: 1px solid #e2e8f0; border-radius: 50px;
            padding: 8px 16px; display: flex; align-items: center; gap: 6px;
            font-weight: 600; font-size: 14px; box-shadow: 0 2px 4px rgba(0,0,0,0.03);
        }
        .stat-badge .stat-icon { font-size: 16px; }
        .stat-badge .stat-number { font-size: 16px; font-weight: bold; color: #1a3c5e; }
        .stat-badge .stat-label { text-transform: uppercase; font-size: 12px; color: #777; }
        
        .stat-badge.draft .stat-icon, .stat-badge.draft .stat-number { color: #f39c12; }
        .stat-badge.pending-t .stat-icon, .stat-badge.pending-t .stat-number { color: #2980b9; }
        .stat-badge.pending-a .stat-icon, .stat-badge.pending-a .stat-number { color: #8e44ad; }
        .stat-badge.approved .stat-icon, .stat-badge.approved .stat-number { color: #27ae60; }

        /* 2-COLUMN GRID FOR OVERVIEW TAB */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px; }
        @media (max-width: 900px) { .two-col { grid-template-columns: 1fr; } }

        .card {
            background: white; border-radius: 12px; border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03); padding: 25px;
        }
        .card h3 { color: #1a3c5e; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; font-size: 18px; }

        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8f9fa; color: #555; text-align: left; padding: 12px 15px;
            font-size: 13px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0;
        }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; font-size: 14px; vertical-align: middle; }
        tr:hover { background: #f1f5f9; }
        .amount { text-align: right; }

        .badge { padding:3px 8px; border-radius:4px; font-size:12px; }
        .badge-draft { background:#f39c12; color:white; }
        .badge-pending_treasurer_review { background:#2980b9; color:white; }
        .badge-pending_adviser_review { background:#8e44ad; color:white; }
        .badge-returned_for_revision { background:#e67e22; color:white; }
        .badge-approved { background:#27ae60; color:white; }
        .badge-denied { background:#e74c3c; color:white; }
        .badge-adviser_rejected { background:#c0392b; color:white; }
        .success { color:#27ae60; } .error { color:#e74c3c; }

        .alert-info { background: #e8f0f8; border-left: 4px solid #8e44ad; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #5b2c6f; margin-top: 8px; }
        .alert-info i { color: #8e44ad; margin-right: 6px; }
        .alert-danger { background: #fdecea; border-left: 4px solid #c0392b; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #721c24; margin-top: 8px; }
        .alert-danger i { color: #c0392b; margin-right: 6px; }

        /* MODAL STYLES */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.55); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #fff; border-radius: 14px; width: 720px; max-width: 95vw; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.25); animation: budgetModalSlide 0.25s ease-out; padding: 0; position: relative; }
        @keyframes budgetModalSlide { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .budget-modal-header { padding: 20px 28px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; background: #fff; border-radius: 14px 14px 0 0; }
        .budget-modal-header h3 { margin: 0; color: #1a3c5e; font-size: 19px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .budget-modal-header h3 i { color: #2980b9; }
        .budget-modal-close { background: #f1f5f9; border: none; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; color: #64748b; font-size: 20px; line-height: 1; display: flex; align-items: center; justify-content: center; transition: background 0.2s, color 0.2s; }
        .budget-modal-close:hover { background: #e2e8f0; color: #1a3c5e; }
        .budget-modal-body { flex: 1; overflow-y: auto; padding: 22px 28px; }
        .budget-modal-body::-webkit-scrollbar { width: 8px; }
        .budget-modal-body::-webkit-scrollbar-track { background: #f1f5f9; }
        .budget-modal-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .budget-modal-body label { display: block; font-weight: 600; font-size: 13.5px; color: #1e293b; margin-bottom: 6px; }
        .budget-modal-body input[type="text"], .budget-modal-body input[type="number"], .budget-modal-body textarea { width: 100%; padding: 10px 13px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13.5px; font-family: inherit; box-sizing: border-box; background: #fafbfd; margin-bottom: 14px; transition: border 0.2s, box-shadow 0.2s; }
        .budget-modal-body input:focus, .budget-modal-body textarea:focus { outline: none; border-color: #1a3c5e; box-shadow: 0 0 0 3px rgba(26, 60, 94, 0.1); background: #fff; }
        .budget-modal-body textarea { resize: vertical; min-height: 70px; }
        .budget-event-pill { background: linear-gradient(135deg, #e8f0f8, #f0f9ff); border-left: 4px solid #2980b9; padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13.5px; color: #1a3c5e; }
        .line-items-header { display: grid; grid-template-columns: 2.2fr 0.8fr 1.2fr 40px; gap: 10px; padding: 0 4px 8px 4px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; }
        .line-item-row { display: grid; grid-template-columns: 2.2fr 0.8fr 1.2fr 40px; gap: 10px; align-items: center; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 10px; }
        .line-item-row input { margin: 0 !important; padding: 9px 12px !important; background: #fff !important; }
        .line-remove-btn { background: #fee2e2; color: #e74c3c; border: none; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: background 0.2s, color 0.2s; padding: 0; }
        .line-remove-btn:hover { background: #e74c3c; color: #fff; }
        .budget-add-item-btn { background: #e8f0f8; color: #1a3c5e; border: 1px dashed #2980b9; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 8px; margin-top: 6px; transition: background 0.2s; }
        .budget-add-item-btn:hover { background: #d8e6f2; }
        .budget-total { margin-top: 16px; padding: 14px 20px; background: linear-gradient(135deg, #e8f0f8, #f0f9ff); border-radius: 10px; border-left: 4px solid #1a3c5e; display: flex; justify-content: space-between; align-items: center; }
        .budget-total span { font-size: 13px; color: #1a3c5e; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .budget-total strong { font-size: 22px; color: #1a3c5e; font-weight: 800; }
        .budget-modal-body input[type="file"] { width: 100%; padding: 10px 13px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; background: #fafbfd; box-sizing: border-box; cursor: pointer; }
        .file-preview { font-size: 12px; color: #666; margin: 6px 0 0 0; }
        .budget-modal-footer { padding: 16px 28px; border-top: 1px solid #e2e8f0; background: #f8fafc; flex-shrink: 0; border-radius: 0 0 14px 14px; display: flex; justify-content: flex-end; gap: 10px; }
        .budget-btn { border: none; padding: 11px 22px; border-radius: 8px; cursor: pointer; font-size: 13.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; font-family: inherit; transition: background 0.2s, transform 0.1s; }
        .budget-btn:active { transform: scale(0.98); }
        .budget-btn-draft { background: #f39c12; color: #fff; }
        .budget-btn-draft:hover { background: #d68910; }
        .budget-btn-submit { background: #1a3c5e; color: #fff; }
        .budget-btn-submit:hover { background: #2c5282; }

        @media (max-width: 768px) {
            .modal-box { width: 100%; max-height: 95vh; }
            .line-items-header { display: none; }
            .line-item-row { grid-template-columns: 1fr; gap: 8px; }
            .line-remove-btn { width: 100%; height: 34px; }
            .budget-modal-footer { flex-direction: column-reverse; }
            .budget-btn { width: 100%; justify-content: center; }
            .budget-modal-header, .budget-modal-body, .budget-modal-footer { padding-left: 20px; padding-right: 20px; }
        }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Business Manager Dashboard';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong> (Business Manager)';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="content">
        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card funds">
                <div class="icon"><i class="fas fa-coins"></i></div>
                <div class="details">
                    <h4>Total Funds</h4>
                    <div class="amount">₱<?= number_format($summary['total_funds'], 2) ?></div>
                </div>
            </div>
            <div class="summary-card expenses">
                <div class="icon"><i class="fas fa-receipt"></i></div>
                <div class="details">
                    <h4>Total Expenses</h4>
                    <div class="amount">₱<?= number_format($summary['total_expenses'], 2) ?></div>
                </div>
            </div>
            <div class="summary-card balance">
                <div class="icon"><i class="fas fa-balance-scale"></i></div>
                <div class="details">
                    <h4>Balance</h4>
                    <div class="amount">₱<?= number_format($summary['balance'], 2) ?></div>
                </div>
            </div>
        </div>


        <!-- OVERVIEW -->
        <div id="overview" class="dash-section">
            
            <!-- Stat Badges -->
            <div class="stat-badges">
                <div class="stat-badge draft">
                    <i class="fas fa-file-alt stat-icon"></i>
                    <span class="stat-number"><?= $drafts ?></span>
                    <span class="stat-label">Drafts</span>
                </div>
                <div class="stat-badge pending-t">
                    <i class="fas fa-clock stat-icon"></i>
                    <span class="stat-number"><?= $pendingT ?></span>
                    <span class="stat-label">For Treasurer</span>
                </div>
                <div class="stat-badge pending-a">
                    <i class="fas fa-clock stat-icon"></i>
                    <span class="stat-number"><?= $pendingA ?></span>
                    <span class="stat-label">For Adviser</span>
                </div>
                <div class="stat-badge approved">
                    <i class="fas fa-check-circle stat-icon"></i>
                    <span class="stat-number"><?= $approved ?></span>
                    <span class="stat-label">Approved</span>
                </div>
            </div>

            <!-- 2-COLUMN GRID -->
            <div class="two-col">
                <div class="card">
                    <h3><i class="fas fa-calendar-plus"></i> Events Waiting</h3>
                    <table>
                        <tr><th>Event Name</th><th>Date</th><th>Action</th></tr>
                        <?php if (count($events) == 0): ?>
                            <tr><td colspan="3" style="text-align:center; color:#999;">No events waiting.</td></tr>
                        <?php else: ?>
                            <?php foreach ($events as $ev): ?>
                            <tr>
                                <td><?= htmlspecialchars($ev['name']) ?></td>
                                <td><?= $ev['event_datetime'] ? date('Y-m-d', strtotime($ev['event_datetime'])) : '—' ?></td>
                                <td>
                                    <button type="button" class="btn-add" style="padding: 4px 10px; font-size: 12px;" onclick='openBudgetModal(<?= $ev["id"] ?>, <?= htmlspecialchars(json_encode($ev["name"]), ENT_QUOTES) ?>)'>
                                        <i class="fas fa-plus"></i> Create
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="card">
                    <h3><i class="fas fa-wallet"></i> My Budget Plans</h3>
                    <table>
                        <tr><th>Event</th><th class="amount">Amount</th><th>Status</th><th>Action</th></tr>
                        <?php if (count($myPlans) == 0): ?>
                            <tr><td colspan="4" style="text-align:center; color:#999;">No budget plans yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($myPlans as $plan): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($plan['event_name']) ?>
                                    <?php if ($plan['status'] === 'adviser_rejected'): ?>
                                        <div class="alert-danger" style="padding: 6px 10px; font-size: 11px; margin-top: 4px;"><i class="fas fa-times-circle"></i> <?= !empty($plan['adviser_comment']) ? htmlspecialchars($plan['adviser_comment']) : 'Rejected.' ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="amount">₱<?= number_format($plan['allocated_amount'], 2) ?></td>
                                <td><span class="badge badge-<?= $plan['status'] ?>"><?= ucwords(str_replace('_', ' ', $plan['status'])) ?></span></td>
                                <td>
                                    <?php if ($plan['status'] == 'draft' || $plan['status'] == 'returned_for_revision'): ?>
                                        <a href="edit_budget_plan.php?id=<?= $plan['id'] ?>" class="btn-edit" style="padding: 4px 8px; font-size: 12px;"><i class="fas fa-edit"></i></a>
                                    <?php elseif ($plan['status'] == 'adviser_rejected'): ?>
                                        <span style="color:#c0392b; font-size:11px; font-style:italic;">Locked</span>
                                    <?php else: ?>
                                        <a href="view_budget_plan.php?id=<?= $plan['id'] ?>" class="btn-view" style="padding: 4px 8px; font-size: 12px;"><i class="fas fa-eye"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- TRANSACTIONS -->
        <div id="transactions" class="dash-section">
            <div class="card">
                <h3><i class="fas fa-exchange-alt"></i> Transaction Tracking</h3>
                <p style="color: #64748b; margin-bottom: 20px; font-size: 14px;">Track the real-time status of all your submitted budget plans.</p>
                <?php if (count($allTransactions) == 0): ?>
                    <p>No transactions found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>ID</th>
                                <th>Details</th>
                                <th class="amount">Amount</th>
                                <th>Current Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allTransactions as $tx): ?>
                            <tr>
                                <td><?= date('Y-m-d', strtotime($tx['tx_date'])) ?></td>
                                <td><strong><?= $tx['type'] ?></strong></td>
                                <td>#<?= $tx['id'] ?></td>
                                <td><?= htmlspecialchars($tx['details']) ?></td>
                                <td class="amount">₱<?= number_format($tx['amount'], 2) ?></td>
                                <td><?= getStatusBadge($tx['status']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============ BUDGET PLAN MODAL ============ -->
<div id="budgetModal" class="modal-overlay">
    <div class="modal-box">
        <div class="budget-modal-header">
            <h3><i class="fas fa-clipboard-list"></i> Create Budget Plan</h3>
            <button type="button" class="budget-modal-close" onclick="closeBudgetModal()">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data" action="create_budget_plan.php" id="budgetForm" onsubmit="return confirmBudgetAction(event)" style="display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0;">
            <input type="hidden" name="event_id" id="budgetEventId">
            <input type="hidden" name="action" id="budgetAction" value="draft">

            <div class="budget-modal-body">
                <div class="budget-event-pill">
                    <i class="fas fa-calendar-check"></i>
                    <strong>Event:</strong> <span id="budgetEventName"></span>
                </div>

                <label>Notes (optional)</label>
                <textarea name="notes" rows="3" placeholder="Any additional notes..."></textarea>

                <label style="margin-top: 10px;">Line Items</label>
                <div class="line-items-header">
                    <div>Item Name</div><div>Quantity</div><div>Unit Cost (₱)</div><div></div>
                </div>
                <div id="lineItemsContainer"></div>
                <button type="button" class="budget-add-item-btn" onclick="addLineItem()"><i class="fas fa-plus"></i> Add Item</button>

                <div class="budget-total">
                    <span><i class="fas fa-calculator"></i> Total Estimated Cost</span>
                    <strong>₱<span id="totalCost">0.00</span></strong>
                </div>

                <label style="margin-top: 20px;">Source Documents (receipts/attachments) <span style="color:red;">*</span></label>
                <input type="file" name="attachments[]" id="attachmentInput" multiple required accept="image/jpeg,image/png,image/gif,application/pdf">
                <p id="fileList" class="file-preview"></p>
            </div>

            <div class="budget-modal-footer">
                <button type="button" class="budget-btn budget-btn-draft" onclick="submitBudget('draft')"><i class="fas fa-save"></i> Save as Draft</button>
                <button type="button" class="budget-btn budget-btn-submit" onclick="submitBudget('submit')"><i class="fas fa-paper-plane"></i> Submit for Review</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    let lineItemCounter = 0;

    function openBudgetModal(eventId, eventName) {
        document.getElementById('budgetEventId').value = eventId;
        document.getElementById('budgetEventName').textContent = eventName;
        document.getElementById('lineItemsContainer').innerHTML = '';
        document.getElementById('attachmentInput').value = '';
        document.getElementById('fileList').textContent = '';
        lineItemCounter = 0;
        document.getElementById('budgetAction').value = 'draft';
        addLineItem();
        document.getElementById('budgetModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeBudgetModal() {
        document.getElementById('budgetModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('budgetModal').addEventListener('click', function(e) {
        if (e.target === this) closeBudgetModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeBudgetModal();
    });

    function addLineItem() {
        const container = document.getElementById('lineItemsContainer');
        const idx = lineItemCounter++;
        const row = document.createElement('div');
        row.className = 'line-item-row';
        row.innerHTML = `
            <input type="text" name="items[${idx}][item_name]" placeholder="e.g. Tarpaulin" required>
            <input type="number" min="1" name="items[${idx}][quantity]" placeholder="Qty" required oninput="updateTotal()">
            <input type="number" step="0.01" min="0" name="items[${idx}][unit_cost]" placeholder="0.00" required oninput="updateTotal()">
            <button type="button" class="line-remove-btn" onclick="removeLineItem(this)" title="Remove"><i class="fas fa-trash"></i></button>
        `;
        container.appendChild(row);
        updateTotal();
    }

    function removeLineItem(btn) {
        btn.closest('.line-item-row').remove();
        updateTotal();
    }

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.line-item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('input[name*="[quantity]"]')?.value || 0);
            const unit = parseFloat(row.querySelector('input[name*="[unit_cost]"]')?.value || 0);
            total += qty * unit;
        });
        document.getElementById('totalCost').textContent = total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    document.getElementById('attachmentInput').addEventListener('change', function(e) {
        const files = e.target.files;
        const list = document.getElementById('fileList');
        if (files.length > 0) {
            list.textContent = 'Selected files (' + files.length + '): ' + Array.from(files).map(f => f.name).join(', ');
        } else {
            list.textContent = '';
        }
    });

    function submitBudget(action) {
        var form = document.getElementById('budgetForm');
        var message = (action === 'submit') ? 'Are you sure you want to submit this budget plan for review?' : 'Are you sure you want to save this budget plan as draft?';
        document.getElementById('budgetAction').value = action;
        _pendingForm = form;
        _pendingCallback = function() { form.submit(); };
        document.getElementById('confirmMessage').textContent = message;
        document.getElementById('confirmModal').classList.add('active');
    }

    function confirmBudgetAction(e) {
        e.preventDefault();
        return false;
    }

    document.getElementById('userMenu').addEventListener('click', function(e) {
        e.stopPropagation(); this.classList.toggle('show');
    });
    window.addEventListener('click', function() {
        document.getElementById('userMenu').classList.remove('show');
    });
</script>
</body>
</html>