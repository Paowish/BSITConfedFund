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
$currentPic = getCurrentUserProfilePic($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $budgetId = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    $eventId = 0;
    if ($budgetId > 0) {
        $lookup = $pdo->prepare("SELECT event_id FROM event_budgets WHERE id = ?");
        $lookup->execute([$budgetId]);
        $eventId = (int)$lookup->fetchColumn();
    }

    if ($budgetId <= 0 || $eventId <= 0) {
        $error = "Invalid request. Budget or event not found.";
    } elseif ($action === 'return' && empty($comment)) {
        $error = "A remark is required when returning for revision.";
    } else {
        if ($action === 'approve') {
            $newBudgetStatus = 'pending_adviser_review';
            $newEventStatus = 'pending_adviser_review';
            $message = "Budget plan #$budgetId forwarded to Adviser for final approval.";
        } elseif ($action === 'return') {
            $newBudgetStatus = 'returned_for_revision';
            $newEventStatus = 'returned_for_revision';
            $message = "Budget plan #$budgetId returned for revision.";
        } else {
            $error = "Invalid action.";
        }

        if (empty($error)) {
            $stmt = $pdo->prepare("UPDATE event_budgets SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_comment = ? WHERE id = ?");
            $stmt->execute([$newBudgetStatus, $_SESSION['officer_id'], $comment, $budgetId]);

            $stmtEvent = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
            $stmtEvent->execute([$newEventStatus, $eventId]);
        }
    }
}

$pendingBudgets = $pdo->query(
    "SELECT eb.*, e.name AS event_name, e.id AS event_id, e.school_year AS event_school_year, e.venue, e.event_datetime,
            o.name AS creator_name
     FROM event_budgets eb
     JOIN events e ON eb.event_id = e.id
     JOIN officers o ON eb.created_by = o.id
     WHERE eb.status = 'pending_treasurer_review'
     ORDER BY eb.created_at ASC"
)->fetchAll();

$budgetItems = [];
$itemsStmt = $pdo->query(
    "SELECT bpi.* FROM budget_plan_items bpi
     JOIN event_budgets eb ON bpi.budget_id = eb.id
     WHERE eb.status = 'pending_treasurer_review'"
);
foreach ($itemsStmt as $item) {
    $budgetItems[$item['budget_id']][] = $item;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Budget Review - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        form.inline { display:inline; }
        .success { color:#27ae60; } .error { color:#e74c3c; }

        .budget-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .budget-items-table th,
        .budget-items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
            text-align: left;
        }
        .budget-items-table th {
            background: #f8f9fa;
            text-transform: uppercase;
            font-size: 11px;
            color: #555;
        }
        .budget-items-table td.amount {
            text-align: right;
        }
        .budget-total-row td {
            font-weight: 700;
            color: #1a3c5e;
            border-top: 2px solid #1a3c5e;
        }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Budget Review';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <h3>Budget Plans Pending Your Review</h3>
            <?php if (count($pendingBudgets) == 0): ?>
                <p>No budget plans waiting for review.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Budget ID</th>
                        <th>Event</th>
                        <th>School Year</th>
                        <th>Total Estimated Cost</th>
                        <th>Submitted By</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($pendingBudgets as $b):
                        $docs = [];
                        if (!empty($b['attachments'])) {
                            foreach (explode(',', $b['attachments']) as $att) {
                                $att = trim($att);
                                if ($att === '') continue;
                                $ext = strtolower(pathinfo($att, PATHINFO_EXTENSION));
                                $isImage = in_array($ext, ['jpg','jpeg','png','gif']);
                                $docs[] = [
                                    'name' => basename($att),
                                    'type' => $isImage ? 'image' : 'pdf',
                                    'url'  => '../../' . $att
                                ];
                            }
                        }

                        $items = $budgetItems[$b['id']] ?? [];
                        $total = 0;
                        $itemsHtml = '';
                        if (count($items) > 0) {
                            $itemsHtml = '<table class="budget-items-table">';
                            $itemsHtml .= '<tr><th>Item</th><th>Qty</th><th>Unit Cost</th><th>Total</th></tr>';
                            foreach ($items as $it) {
                                $total += $it['estimated_total'];
                                $itemsHtml .= '<tr>';
                                $itemsHtml .= '<td>' . htmlspecialchars($it['item_name']) . '</td>';
                                $itemsHtml .= '<td>' . $it['quantity'] . '</td>';
                                $itemsHtml .= '<td>₱' . number_format($it['unit_cost'], 2) . '</td>';
                                $itemsHtml .= '<td class="amount">₱' . number_format($it['estimated_total'], 2) . '</td>';
                                $itemsHtml .= '</tr>';
                            }
                            $itemsHtml .= '<tr class="budget-total-row"><td colspan="3">TOTAL</td><td class="amount">₱' . number_format($total, 2) . '</td></tr>';
                            $itemsHtml .= '</table>';
                        } else {
                            $itemsHtml = '<p style="color:#999;font-size:13px;">No line items.</p>';
                        }

                        $viewData = [
                            'type' => 'Budget Plan',
                            'status' => 'Pending Treasurer Review',
                            'submitted_by' => $b['creator_name'],
                            'event' => $b['event_name'],
                            'school_year' => $b['event_school_year'] ?? $b['school_year'] ?? '—',
                            'amount' => number_format($b['allocated_amount'], 2),
                            'date' => $b['created_at'] ? date('Y-m-d', strtotime($b['created_at'])) : '—',
                            'description' => $b['notes'] ?? '—',
                            'documents' => $docs
                        ];
                    ?>
                    <tr>
                        <td><?= $b['id'] ?></td>
                        <td><?= htmlspecialchars($b['event_name']) ?></td>
                        <td><?= htmlspecialchars($b['school_year'] ?? '—') ?></td>
                        <td>₱<?= number_format($b['allocated_amount'], 2) ?></td>
                        <td><?= htmlspecialchars($b['creator_name']) ?></td>
                        <td>
                            <button type="button" class="btn-view" onclick='openBudgetViewModal(<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($itemsHtml), ENT_QUOTES) ?>)'><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn-approve" onclick='openApproveModal("budget", <?= $b["id"] ?>, "Budget #<?= $b["id"] ?> - <?= htmlspecialchars($b["event_name"], ENT_QUOTES) ?>")'><i class="fas fa-check"></i> Approve</button>
                            <button type="button" class="btn-return" onclick='openReturnModal(<?= $b["id"] ?>, <?= $b["event_id"] ?>, "Budget #<?= $b["id"] ?> - <?= htmlspecialchars($b["event_name"], ENT_QUOTES) ?>")'><i class="fas fa-undo"></i> Return</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="returnModal" class="vmodal-overlay">
    <div class="vmodal-box">
        <button class="vmodal-close" onclick="closeReturnModal()">&times;</button>
        <h3 class="warning"><i class="fas fa-undo"></i> Return Budget Plan</h3>
        <p>Are you sure you want to return <strong id="returnBudgetName"></strong> for revision? Please provide a reason.</p>
        <form id="returnForm" method="POST" onsubmit="return submitReturn(event)">
            <input type="hidden" name="id" id="returnBudgetId">
            <input type="hidden" name="event_id" id="returnEventId">
            <input type="hidden" name="action" value="return">
            <label>Reason for Return <span style="color:red;">*</span></label>
            <textarea name="comment" id="returnReason" placeholder="Enter reason here..." required></textarea>
            <div class="vmodal-actions">
                <button type="button" class="vmodal-btn-cancel" onclick="closeReturnModal()">Cancel</button>
                <button type="submit" class="vmodal-btn-deny"><i class="fas fa-undo"></i> Confirm Return</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    function openReturnModal(budgetId, eventId, name) {
        document.getElementById('returnBudgetId').value = budgetId;
        document.getElementById('returnEventId').value = eventId;
        document.getElementById('returnBudgetName').textContent = name;
        document.getElementById('returnReason').value = '';
        document.getElementById('returnModal').classList.add('active');
        setTimeout(function() { document.getElementById('returnReason').focus(); }, 100);
    }

    function closeReturnModal() {
        document.getElementById('returnModal').classList.remove('active');
    }

    function submitReturn(e) {
        e.preventDefault();
        var reason = document.getElementById('returnReason').value.trim();
        if (reason === '') {
            alert('Please provide a reason for return.');
            return false;
        }
        document.getElementById('returnForm').submit();
        return false;
    }

    function openBudgetViewModal(data, itemsHtml) {
        var html = '';

        if (data.type) html += '<div class="vdetail-row"><div class="vdetail-label">Type</div><div class="vdetail-value">' + data.type + '</div></div>';
        if (data.status) html += '<div class="vdetail-row"><div class="vdetail-label">Status</div><div class="vdetail-value">' + data.status + '</div></div>';
        if (data.submitted_by) html += '<div class="vdetail-row"><div class="vdetail-label">Submitted By</div><div class="vdetail-value">' + data.submitted_by + '</div></div>';
        if (data.event) html += '<div class="vdetail-row"><div class="vdetail-label">Event</div><div class="vdetail-value">' + data.event + '</div></div>';
        if (data.school_year) html += '<div class="vdetail-row"><div class="vdetail-label">School Year</div><div class="vdetail-value">' + data.school_year + '</div></div>';
        if (data.amount) html += '<div class="vdetail-row"><div class="vdetail-label">Total Estimated</div><div class="vdetail-value">₱' + data.amount + '</div></div>';
        if (data.date) html += '<div class="vdetail-row"><div class="vdetail-label">Date Submitted</div><div class="vdetail-value">' + data.date + '</div></div>';
        if (data.description) html += '<div class="vdetail-row"><div class="vdetail-label">Notes</div><div class="vdetail-value">' + data.description + '</div></div>';

        html += '<div class="vsection-title">Line Items</div>';
        html += itemsHtml;

        html += '<div class="vsection-title">Source Documents</div>';
        if (data.documents && data.documents.length > 0) {
            html += '<div class="vdocs-grid">';
            data.documents.forEach(function(doc) {
                if (doc.type === 'image') {
                    html += '<div class="vdoc-item" onclick="openLightbox(\'' + doc.url + '\')">' +
                                '<img src="' + doc.url + '" alt="">' +
                                '<div class="vdoc-label">' + doc.name + '</div>' +
                            '</div>';
                } else {
                    html += '<div class="vdoc-item pdf" onclick="window.open(\'' + doc.url + '\', \'_blank\')">' +
                                '<div class="vdoc-icon"><i class="fas fa-file-pdf"></i></div>' +
                                '<div class="vdoc-label">' + doc.name + '</div>' +
                            '</div>';
                }
            });
            html += '</div>';
        } else {
            html += '<p style="color:#999;font-size:13px;">No documents attached</p>';
        }

        document.getElementById('viewContent').innerHTML = html;
        document.getElementById('viewModal').classList.add('active');
    }

    document.getElementById('returnModal').addEventListener('click', function(e) {
        if (e.target === this) closeReturnModal();
    });

    document.getElementById('userMenu').addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('show');});
    window.addEventListener('click',function(){document.getElementById('userMenu').classList.remove('show');});
</script>
</body>
</html>