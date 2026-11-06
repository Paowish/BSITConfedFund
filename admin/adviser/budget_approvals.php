<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
requireAdviser();

$message = '';
$error = '';

// ============================================================
// Handle Approve / Reject
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $budgetId = (int)($_POST['budget_id'] ?? 0);
    $eventId = (int)($_POST['event_id'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    $adviserId = $_SESSION['adviser_id'];

    if ($budgetId <= 0 || $eventId <= 0) {
        $error = "Invalid request.";
    } elseif ($action === 'reject' && empty($comment)) {
        $error = "A reason is required when rejecting a budget plan.";
    } elseif ($action !== 'approve' && $action !== 'reject') {
        $error = "Invalid action.";
    } else {
        // Sanity check — dapat pending pa rin
        $check = $pdo->prepare("SELECT id FROM event_budgets WHERE id = ? AND status = 'pending_adviser_review'");
        $check->execute([$budgetId]);

        if (!$check->fetch()) {
            $error = "This budget plan is no longer pending review.";
        } else {
            if ($action === 'approve') {
                $newBudgetStatus = 'approved';
                $newEventStatus = 'budget_approved';
            } else {
                $newBudgetStatus = 'adviser_rejected';
                $newEventStatus = 'adviser_rejected';
            }

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    "UPDATE event_budgets 
                     SET status = ?, adviser_reviewed_by = ?, adviser_reviewed_at = NOW(), adviser_comment = ?
                     WHERE id = ?"
                );
                $stmt->execute([$newBudgetStatus, $adviserId, $comment, $budgetId]);

                $stmt2 = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
                $stmt2->execute([$newEventStatus, $eventId]);

                $pdo->commit();

                $message = ($action === 'approve')
                    ? "Budget plan approved! Ready for fund release."
                    : "Budget plan rejected. Mayor can now create a new event.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to update: " . $e->getMessage();
            }
        }
    }
}

// ============================================================
// Fetch pending budgets (waiting for adviser review)
// ============================================================
$pendingBudgets = $pdo->query(
    "SELECT eb.*, 
            e.id AS event_id, 
            e.name AS event_name, 
            e.school_year AS event_school_year,
            e.venue, 
            e.event_datetime,
            e.description AS event_description,
            e.purpose AS event_purpose,
            o.name AS creator_name, 
            o.position AS creator_position
     FROM event_budgets eb
     JOIN events e ON eb.event_id = e.id
     LEFT JOIN officers o ON eb.created_by = o.id
     WHERE eb.status = 'pending_adviser_review'
     ORDER BY eb.created_at ASC"
)->fetchAll();

// Fetch line items for pending budgets
$budgetItems = [];
if (count($pendingBudgets) > 0) {
    $ids = array_column($pendingBudgets, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $itemsStmt = $pdo->prepare(
        "SELECT * FROM budget_plan_items WHERE budget_id IN ($placeholders) ORDER BY id ASC"
    );
    $itemsStmt->execute($ids);
    foreach ($itemsStmt as $item) {
        $budgetItems[$item['budget_id']][] = $item;
    }
}

// ============================================================
// Fetch recently reviewed by this adviser (for PDF generation)
// ============================================================
$stmt = $pdo->prepare(
    "SELECT eb.*, e.name AS event_name, e.school_year AS event_school_year
     FROM event_budgets eb
     JOIN events e ON eb.event_id = e.id
     WHERE eb.adviser_reviewed_by = ? 
       AND eb.status = 'approved'
     ORDER BY eb.adviser_reviewed_at DESC
     LIMIT 5"
);
$stmt->execute([$_SESSION['adviser_id']]);
$recentlyApproved = $stmt->fetchAll();

// ============================================================
// Helper — build documents array
// ============================================================
function buildDocs($attachments) {
    $docs = [];
    if (!empty($attachments)) {
        foreach (explode(',', $attachments) as $att) {
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
    return $docs;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Budget Approvals - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/adviser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card { background:#fff; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.03); padding:25px 30px; margin-bottom:25px; }
        .card h3 { margin-top:0; color:#1a3c5e; }
        .success { color:#27ae60; } .error { color:#e74c3c; }

        /* Line items table inside view modal */
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

        /* Rejection reason display */
        .rejection-reason {
            color: #e74c3c;
            font-size: 13px;
            font-style: italic;
        }

        /* PDF button */
        .btn-pdf {
            background: #c0392b;
            color: white;
            padding: 5px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-pdf:hover { background: #a93226; }

        /* Warning box para sa reject */
        .warn-box {
            background: #fff3cd;
            border-left: 4px solid #f39c12;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #856404;
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
$pageTitle = 'BUDGET APPROVALS';
$pageSubtitle = ' <strong>' . htmlspecialchars($_SESSION['officer_name'] ?? $_SESSION['adviser_name'] ?? 'User') . '</strong>';
$basePath = '../';
include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></p><?php endif; ?>

        <!-- PENDING BUDGET PLANS -->
        <div class="card">
            <h3><i class="fas fa-wallet"></i> Budget Plans Awaiting Your Approval</h3>
            <?php if (count($pendingBudgets) == 0): ?>
                <p>No budget plans waiting for your review.</p>
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
                        // Build documents array
                        $docs = buildDocs($b['attachments']);

                        // Build line items HTML
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
                            'status' => 'Pending Adviser Review',
                            'submitted_by' => $b['creator_name'] ?? 'Unknown',
                            'event' => $b['event_name'],
                            'school_year' => $b['event_school_year'] ?? '—',
                            'venue' => $b['venue'] ?? '—',
                            'event_datetime' => $b['event_datetime'] ? date('M d, Y h:i A', strtotime($b['event_datetime'])) : '—',
                            'event_description' => $b['event_description'] ?? '—',
                            'amount' => number_format($b['allocated_amount'], 2),
                            'date' => $b['created_at'] ? date('Y-m-d', strtotime($b['created_at'])) : '—',
                            'description' => $b['notes'] ?? '—',
                            'treasurer_reviewed_at' => $b['reviewed_at'] ? date('M d, Y h:i A', strtotime($b['reviewed_at'])) : '—',
                            'treasurer_comment' => $b['review_comment'] ?? '—',
                            'documents' => $docs
                        ];
                    ?>
                    <tr>
                        <td>#<?= $b['id'] ?></td>
                        <td><?= htmlspecialchars($b['event_name']) ?></td>
                        <td><?= htmlspecialchars($b['event_school_year'] ?? '—') ?></td>
                        <td>₱<?= number_format($b['allocated_amount'], 2) ?></td>
                        <td><?= htmlspecialchars($b['creator_name'] ?? 'Unknown') ?></td>
                        <td>
                            <button type="button" class="btn-view" onclick='openViewBudgetModal(<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($itemsHtml), ENT_QUOTES) ?>)'>
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button type="button" class="btn-approve" onclick='openApproveBudgetModal(<?= $b["id"] ?>, <?= $b["event_id"] ?>, "Budget #<?= $b["id"] ?> — <?= htmlspecialchars($b["event_name"], ENT_QUOTES) ?>")'>
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="button" class="btn-deny" onclick='openRejectBudgetModal(<?= $b["id"] ?>, <?= $b["event_id"] ?>, "Budget #<?= $b["id"] ?> — <?= htmlspecialchars($b["event_name"], ENT_QUOTES) ?>")'>
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <!-- RECENTLY APPROVED (with PDF generation) -->
        <?php if (count($recentlyApproved) > 0): ?>
        <div class="card">
            <h3><i class="fas fa-file-pdf"></i> Recently Approved by You</h3>
            
            <table>
                <tr>
                    <th>Budget ID</th>
                    <th>Event</th>
                    <th>Total</th>
                    <th>Approved At</th>
                    <th>Report</th>
                </tr>
                <?php foreach ($recentlyApproved as $r): ?>
                <tr>
                    <td>#<?= $r['id'] ?></td>
                    <td><?= htmlspecialchars($r['event_name']) ?></td>
                    <td>₱<?= number_format($r['allocated_amount'], 2) ?></td>
                    <td><?= $r['adviser_reviewed_at'] ? date('M d, Y h:i A', strtotime($r['adviser_reviewed_at'])) : '—' ?></td>
                    <td>
                        <a href="../download_report.php?report=budget_plan&id=<?= $r['id'] ?>" 
                           class="btn-pdf" target="_blank">
                            <i class="fas fa-file-pdf"></i> Generate PDF
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ APPROVE MODAL ============ -->
<div id="approveBudgetModal" class="vmodal-overlay">
    <div class="vmodal-box">
        <button class="vmodal-close" onclick="closeApproveBudgetModal()">&times;</button>
        <h3 class="success"><i class="fas fa-check-circle"></i> Approve Budget Plan</h3>
        <p>You are about to approve <strong id="approveBudgetName"></strong>.</p>
        <!-- <p style="font-size:13px; color:#666;">
            <i class="fas fa-info-circle"></i> Matapos ang approval, ang event ay magiging <strong>budget_approved</strong> — 
            ready na para sa fund release ng Treasurer.
        </p> -->
        <form id="approveBudgetForm" method="POST">
            <input type="hidden" name="budget_id" id="approveBudgetId">
            <input type="hidden" name="event_id" id="approveBudgetEventId">
            <input type="hidden" name="action" value="approve">

            <label>Approval Notes (optional)</label>
            <textarea name="comment" id="approveBudgetComment" placeholder="Any final remarks..."></textarea>

            <div class="vmodal-actions">
                <button type="button" class="vmodal-btn-cancel" onclick="closeApproveBudgetModal()">Cancel</button>
                <button type="submit" class="vmodal-btn-approve"><i class="fas fa-check"></i> Confirm Approve</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ REJECT MODAL ============ -->
<div id="rejectBudgetModal" class="vmodal-overlay">
    <div class="vmodal-box">
        <button class="vmodal-close" onclick="closeRejectBudgetModal()">&times;</button>
        <h3 class="danger"><i class="fas fa-exclamation-triangle"></i> Reject Budget Plan</h3>
        <p>You are about to reject <strong id="rejectBudgetName"></strong>.</p>

        <div class="warn-box">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Important:</strong> Rejecting this will reject the entire event.
            The Mayor will have to create a <strong>new event</strong> to start over.
            This event can no longer be edited.
        </div>

        <form id="rejectBudgetForm" method="POST">
            <input type="hidden" name="budget_id" id="rejectBudgetId">
            <input type="hidden" name="event_id" id="rejectBudgetEventId">
            <input type="hidden" name="action" value="reject">

            <label>Reason for Rejection <span style="color:red;">*</span></label>
            <textarea name="comment" id="rejectBudgetComment" placeholder="Explain why this budget is rejected..." required></textarea>

            <div class="vmodal-actions">
                <button type="button" class="vmodal-btn-cancel" onclick="closeRejectBudgetModal()">Cancel</button>
                <button type="submit" class="vmodal-btn-deny"><i class="fas fa-times"></i> Confirm Reject</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    // ============ APPROVE MODAL ============
    function openApproveBudgetModal(budgetId, eventId, name) {
        document.getElementById('approveBudgetId').value = budgetId;
        document.getElementById('approveBudgetEventId').value = eventId;
        document.getElementById('approveBudgetName').textContent = name;
        document.getElementById('approveBudgetComment').value = '';
        document.getElementById('approveBudgetModal').classList.add('active');
    }
    function closeApproveBudgetModal() {
        document.getElementById('approveBudgetModal').classList.remove('active');
    }
    document.getElementById('approveBudgetModal').addEventListener('click', function(e) {
        if (e.target === this) closeApproveBudgetModal();
    });

    // ============ REJECT MODAL ============
    function openRejectBudgetModal(budgetId, eventId, name) {
        document.getElementById('rejectBudgetId').value = budgetId;
        document.getElementById('rejectBudgetEventId').value = eventId;
        document.getElementById('rejectBudgetName').textContent = name;
        document.getElementById('rejectBudgetComment').value = '';
        document.getElementById('rejectBudgetModal').classList.add('active');
        setTimeout(function() { document.getElementById('rejectBudgetComment').focus(); }, 100);
    }
    function closeRejectBudgetModal() {
        document.getElementById('rejectBudgetModal').classList.remove('active');
    }
    document.getElementById('rejectBudgetModal').addEventListener('click', function(e) {
        if (e.target === this) closeRejectBudgetModal();
    });

    // ============ VIEW BUDGET MODAL ============
    function openViewBudgetModal(data, itemsHtml) {
        var html = '';

        if (data.type) html += '<div class="vdetail-row"><div class="vdetail-label">Type</div><div class="vdetail-value">' + data.type + '</div></div>';
        if (data.status) html += '<div class="vdetail-row"><div class="vdetail-label">Status</div><div class="vdetail-value">' + data.status + '</div></div>';
        if (data.submitted_by) html += '<div class="vdetail-row"><div class="vdetail-label">Submitted By</div><div class="vdetail-value">' + data.submitted_by + '</div></div>';
        if (data.event) html += '<div class="vdetail-row"><div class="vdetail-label">Event</div><div class="vdetail-value">' + data.event + '</div></div>';
        if (data.school_year) html += '<div class="vdetail-row"><div class="vdetail-label">School Year</div><div class="vdetail-value">' + data.school_year + '</div></div>';
        if (data.venue) html += '<div class="vdetail-row"><div class="vdetail-label">Venue</div><div class="vdetail-value">' + data.venue + '</div></div>';
        if (data.event_datetime) html += '<div class="vdetail-row"><div class="vdetail-label">Event Date</div><div class="vdetail-value">' + data.event_datetime + '</div></div>';
        if (data.event_description && data.event_description !== '—') html += '<div class="vdetail-row"><div class="vdetail-label">Description</div><div class="vdetail-value">' + data.event_description + '</div></div>';
        if (data.amount) html += '<div class="vdetail-row"><div class="vdetail-label">Total Estimated</div><div class="vdetail-value">₱' + data.amount + '</div></div>';
        if (data.date) html += '<div class="vdetail-row"><div class="vdetail-label">Date Submitted</div><div class="vdetail-value">' + data.date + '</div></div>';
        if (data.description && data.description !== '—') html += '<div class="vdetail-row"><div class="vdetail-label">Notes</div><div class="vdetail-value">' + data.description + '</div></div>';

        html += '<div class="vsection-title">Line Items</div>';
        html += itemsHtml;

        if (data.treasurer_reviewed_at && data.treasurer_reviewed_at !== '—') {
            html += '<div class="vsection-title">Treasurer Review</div>';
            html += '<div class="vdetail-row"><div class="vdetail-label">Reviewed At</div><div class="vdetail-value">' + data.treasurer_reviewed_at + '</div></div>';
            if (data.treasurer_comment && data.treasurer_comment !== '—') {
                html += '<div class="vdetail-row"><div class="vdetail-label">Remarks</div><div class="vdetail-value">' + data.treasurer_comment + '</div></div>';
            }
        }

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