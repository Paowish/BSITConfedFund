<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
requireAdviser();

$message = '';
$error = '';

// Handle final approval
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $liquidationId = $_POST['liquidation_id'] ?? 0;
    $approvalNotes = trim($_POST['approval_notes'] ?? '');

    if (empty($liquidationId)) {
        $error = "Invalid request.";
    } else {
        // Get event_id for status update
        $stmt = $pdo->prepare("SELECT event_id FROM liquidations WHERE id = ?");
        $stmt->execute([$liquidationId]);
        $eventId = $stmt->fetchColumn();

        if ($eventId) {
            // Update liquidation
            $stmt = $pdo->prepare(
                "UPDATE liquidations 
                 SET status = 'closed', approved_by = ?, approved_at = NOW(), approval_notes = ?
                 WHERE id = ?"
            );
            $stmt->execute([$_SESSION['adviser_id'], $approvalNotes, $liquidationId]);

            // Update event status to closed
            $pdo->prepare("UPDATE events SET status = 'closed' WHERE id = ?")->execute([$eventId]);

            $message = "Liquidation approved. Event is now CLOSED.";
        } else {
            $error = "Event not found.";
        }
    }
}

// Fetch liquidations awaiting final approval
$liquidations = $pdo->query(
    "SELECT l.*, e.name AS event_name, e.school_year, e.venue,
            o.name AS submitted_by_name,
            a.name AS audited_by_name
     FROM liquidations l
     JOIN events e ON l.event_id = e.id
     JOIN officers o ON l.submitted_by = o.id
     LEFT JOIN officers a ON l.audited_by = a.id
     WHERE l.status IN ('audited_compliant', 'audited_with_findings')
     ORDER BY l.audited_at ASC"
)->fetchAll();

// Helper to build documents array
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
    <title>Final Approval - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/adviser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card { background:#fff; border-radius:10px; border:1px solid #e2e8f0; box-shadow:0 2px 8px rgba(0,0,0,0.03); padding:25px 30px; margin-bottom:25px; }
        .card h3 { margin-top:0; color:#1a3c5e; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px; text-align:left; border-bottom:1px solid #eee; }
        th { background:#f8f9fa; text-transform:uppercase; font-size:13px; color:#555; }
        .success { color:#27ae60; } .error { color:#e74c3c; }
        .variance-over { color:#e74c3c; font-weight:600; }
        .variance-under { color:#27ae60; font-weight:600; }
        .badge-compliant { background:#27ae60; color:white; padding:3px 10px; border-radius:50px; font-size:12px; font-weight:600; }
        .badge-findings { background:#e74c3c; color:white; padding:3px 10px; border-radius:50px; font-size:12px; font-weight:600; }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
$pageTitle = 'PAGE TITLE';
$pageSubtitle = ' <strong>' . htmlspecialchars($_SESSION['officer_name'] ?? $_SESSION['adviser_name'] ?? 'User') . '</strong>';
$basePath = '../';
include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
?>
    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <h3>Liquidations Awaiting Final Approval</h3>
            <?php if (count($liquidations) == 0): ?>
                <p>No liquidations waiting for final approval.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Event</th>
                        <th>School Year</th>
                        <th>Total Budget</th>
                        <th>Actual Expenses</th>
                        <th>Variance</th>
                        <th>Audit Result</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($liquidations as $l):
                        $variance = $l['variance'];
                        $varianceClass = ($variance >= 0) ? 'variance-under' : 'variance-over';
                        $varianceText = ($variance >= 0)
                            ? '₱' . number_format($variance, 2) . ' Under'
                            : '₱' . number_format(abs($variance), 2) . ' Over';
                        $badgeClass = ($l['audit_result'] === 'compliant') ? 'badge-compliant' : 'badge-findings';
                        $badgeText = ($l['audit_result'] === 'compliant') ? 'COMPLIANT' : 'WITH FINDINGS';

                        $docs = buildDocs($l['attachments']);

                        $viewData = [
                            'type' => 'Liquidation Report',
                            'status' => 'Audited — Awaiting Final Approval',
                            'submitted_by' => $l['submitted_by_name'],
                            'event' => $l['event_name'],
                            'school_year' => $l['school_year'] ?? '—',
                            'amount' => number_format($l['total_expenses'], 2),
                            'date' => $l['created_at'] ? date('Y-m-d', strtotime($l['created_at'])) : '—',
                            'description' => $l['notes'] ?? '—',
                            'documents' => $docs,
                            'reviewer' => $l['audited_by_name'] ?? '—',
                            'reviewed_at' => $l['audited_at'] ? date('Y-m-d H:i', strtotime($l['audited_at'])) : '—',
                            'comment' => $l['audit_notes'] ?? '—'
                        ];

                        $extraRows = [
                            'Event Venue' => $l['venue'] ?? '—',
                            'Total Budget' => '₱' . number_format($l['total_budget'], 2),
                            'Actual Expenses' => '₱' . number_format($l['total_expenses'], 2),
                            'Variance' => $varianceText,
                            'Audit Result' => $badgeText
                        ];
                    ?>
                    <tr>
                        <td><?= $l['id'] ?></td>
                        <td><?= htmlspecialchars($l['event_name']) ?></td>
                        <td><?= htmlspecialchars($l['school_year']) ?></td>
                        <td>₱<?= number_format($l['total_budget'], 2) ?></td>
                        <td>₱<?= number_format($l['total_expenses'], 2) ?></td>
                        <td class="<?= $varianceClass ?>"><?= $varianceText ?></td>
                        <td><span class="<?= $badgeClass ?>"><?= $badgeText ?></span></td>
                        <td>
                            <button type="button" class="btn-view" onclick='openFinalViewModal(<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($extraRows), ENT_QUOTES) ?>)'><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn-approve" onclick='openFinalApproveModal(<?= $l["id"] ?>, "Liquidation #<?= $l["id"] ?> - <?= htmlspecialchars($l["event_name"], ENT_QUOTES) ?>")'><i class="fas fa-check"></i> Approve & Close</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Custom Final Approve Modal (page-specific) -->
<div id="finalApproveModal" class="vmodal-overlay">
    <div class="vmodal-box">
        <button class="vmodal-close" onclick="closeFinalApproveModal()">&times;</button>
        <h3 class="success"><i class="fas fa-check-circle"></i> Final Approval</h3>
        <p>You are about to give final approval and <strong>close</strong> <strong id="finalApproveName"></strong>. This action cannot be undone.</p>
        <form id="finalApproveForm" method="POST" onsubmit="return submitFinalApprove(event)">
            <input type="hidden" name="liquidation_id" id="finalApproveId">

            <label>Approval Notes (optional)</label>
            <textarea name="approval_notes" id="finalApprovalNotes" placeholder="Any final remarks before closing..."></textarea>

            <div class="vmodal-actions">
                <button type="button" class="vmodal-btn-cancel" onclick="closeFinalApproveModal()">Cancel</button>
                <button type="submit" class="vmodal-btn-approve"><i class="fas fa-check"></i> Confirm & Close Event</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    // ============ FINAL APPROVE MODAL ============
    function openFinalApproveModal(liquidationId, name) {
        document.getElementById('finalApproveId').value = liquidationId;
        document.getElementById('finalApproveName').textContent = name;
        document.getElementById('finalApprovalNotes').value = '';
        document.getElementById('finalApproveModal').classList.add('active');
        setTimeout(function() { document.getElementById('finalApprovalNotes').focus(); }, 100);
    }

    function closeFinalApproveModal() {
        document.getElementById('finalApproveModal').classList.remove('active');
    }

    function submitFinalApprove(e) {
        e.preventDefault();
        document.getElementById('finalApproveForm').submit();
        return false;
    }

    // ============ FINAL VIEW MODAL (with liquidation extra fields) ============
    function openFinalViewModal(data, extraRows) {
        var html = '';

        // Standard fields
        if (data.type) html += '<div class="vdetail-row"><div class="vdetail-label">Type</div><div class="vdetail-value">' + data.type + '</div></div>';
        if (data.status) html += '<div class="vdetail-row"><div class="vdetail-label">Status</div><div class="vdetail-value">' + data.status + '</div></div>';
        if (data.submitted_by) html += '<div class="vdetail-row"><div class="vdetail-label">Submitted By</div><div class="vdetail-value">' + data.submitted_by + '</div></div>';
        if (data.event) html += '<div class="vdetail-row"><div class="vdetail-label">Event</div><div class="vdetail-value">' + data.event + '</div></div>';
        if (data.school_year) html += '<div class="vdetail-row"><div class="vdetail-label">School Year</div><div class="vdetail-value">' + data.school_year + '</div></div>';

        // Extra liquidation rows
        if (extraRows) {
            for (var key in extraRows) {
                if (extraRows.hasOwnProperty(key)) {
                    html += '<div class="vdetail-row"><div class="vdetail-label">' + key + '</div><div class="vdetail-value">' + extraRows[key] + '</div></div>';
                }
            }
        }

        if (data.date) html += '<div class="vdetail-row"><div class="vdetail-label">Date Submitted</div><div class="vdetail-value">' + data.date + '</div></div>';
        if (data.description) html += '<div class="vdetail-row"><div class="vdetail-label">Notes</div><div class="vdetail-value">' + data.description + '</div></div>';

        // Auditor info
        if (data.reviewer && data.reviewer !== '—') {
            html += '<div class="vsection-title">Auditor Information</div>';
            html += '<div class="vdetail-row"><div class="vdetail-label">Audited By</div><div class="vdetail-value">' + data.reviewer + '</div></div>';
            if (data.reviewed_at && data.reviewed_at !== '—') html += '<div class="vdetail-row"><div class="vdetail-label">Audited At</div><div class="vdetail-value">' + data.reviewed_at + '</div></div>';
            if (data.comment && data.comment !== '—') html += '<div class="vdetail-row"><div class="vdetail-label">Audit Notes</div><div class="vdetail-value">' + data.comment + '</div></div>';
        }

        // Source documents
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

    // Close modal when clicking outside
    document.getElementById('finalApproveModal').addEventListener('click', function(e) {
        if (e.target === this) closeFinalApproveModal();
    });

    document.getElementById('userMenu').addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('show');});
    window.addEventListener('click',function(){document.getElementById('userMenu').classList.remove('show');});
</script>
</body>
</html>