<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
requireAuditor();

$message = '';
$error = '';

// Handle audit submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $liquidationId = $_POST['liquidation_id'] ?? 0;
    $result = $_POST['audit_result'] ?? '';
    $auditNotes = trim($_POST['audit_notes'] ?? '');

    if (!in_array($result, ['compliant', 'with_findings'])) {
        $error = "Please select a valid audit result.";
    } else {
        $newStatus = ($result === 'compliant') ? 'audited_compliant' : 'audited_with_findings';

        // Update liquidation
        $stmt = $pdo->prepare(
            "UPDATE liquidations 
             SET status = ?, audited_by = ?, audited_at = NOW(), audit_result = ?, audit_notes = ?
             WHERE id = ?"
        );
        $stmt->execute([$newStatus, $_SESSION['officer_id'], $result, $auditNotes, $liquidationId]);

        // Update event status
        $eventStatus = ($result === 'compliant') ? 'audited_compliant' : 'audited_with_findings';
        $stmtEvent = $pdo->prepare(
            "UPDATE events SET status = ? WHERE id = (SELECT event_id FROM liquidations WHERE id = ?)"
        );
        $stmtEvent->execute([$eventStatus, $liquidationId]);

        $message = "Audit submitted. Result: " . ucwords(str_replace('_', ' ', $result)) . ".";
    }
}

// Fetch pending audit liquidations
$liquidations = $pdo->query(
    "SELECT l.*, e.name AS event_name, e.school_year, e.venue,
            o.name AS submitted_by_name
     FROM liquidations l
     JOIN events e ON l.event_id = e.id
     JOIN officers o ON l.submitted_by = o.id
     WHERE l.status = 'pending_audit'
     ORDER BY l.created_at ASC"
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
    <title>Liquidation Audit - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/auditor.css">
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
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
$pageTitle = 'LIQUIDATION AUDIT';
$pageSubtitle = ' <strong>' . htmlspecialchars($_SESSION['officer_name'] ?? $_SESSION['adviser_name'] ?? 'User') . '</strong>';
$basePath = '../';
include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
?>
    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <h3>Liquidations Pending Audit</h3>
            <?php if (count($liquidations) == 0): ?>
                <p>No liquidations waiting for audit.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Event</th>
                        <th>School Year</th>
                        <th>Total Budget</th>
                        <th>Actual Expenses</th>
                        <th>Variance</th>
                        <th>Submitted By</th>
                        <th>Action</th>
                    </tr>
                    <?php foreach ($liquidations as $l):
                        $variance = $l['variance'];
                        $varianceClass = ($variance >= 0) ? 'variance-under' : 'variance-over';
                        $varianceText = ($variance >= 0)
                            ? '₱' . number_format($variance, 2) . ' Under'
                            : '₱' . number_format(abs($variance), 2) . ' Over';

                        $docs = buildDocs($l['attachments']);

                        $viewData = [
                            'type' => 'Liquidation Report',
                            'status' => 'Pending Audit',
                            'submitted_by' => $l['submitted_by_name'],
                            'event' => $l['event_name'],
                            'school_year' => $l['school_year'] ?? '—',
                            'amount' => number_format($l['total_expenses'], 2),
                            'date' => $l['created_at'] ? date('Y-m-d', strtotime($l['created_at'])) : '—',
                            'description' => $l['notes'] ?? '—',
                            'documents' => $docs
                        ];

                        $extraRows = [
                            'Event Venue' => $l['venue'] ?? '—',
                            'Total Budget' => '₱' . number_format($l['total_budget'], 2),
                            'Actual Expenses' => '₱' . number_format($l['total_expenses'], 2),
                            'Variance' => $varianceText
                        ];
                    ?>
                    <tr>
                        <td><?= $l['id'] ?></td>
                        <td><?= htmlspecialchars($l['event_name']) ?></td>
                        <td><?= htmlspecialchars($l['school_year']) ?></td>
                        <td>₱<?= number_format($l['total_budget'], 2) ?></td>
                        <td>₱<?= number_format($l['total_expenses'], 2) ?></td>
                        <td class="<?= $varianceClass ?>"><?= $varianceText ?></td>
                        <td><?= htmlspecialchars($l['submitted_by_name']) ?></td>
                        <td>
                            <button type="button" class="btn-view" onclick='openLiquidationViewModal(<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($extraRows), ENT_QUOTES) ?>)'><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn-approve" onclick='openAuditModal(<?= $l["id"] ?>, "Liquidation #<?= $l["id"] ?> - <?= htmlspecialchars($l["event_name"], ENT_QUOTES) ?>", "compliant")'><i class="fas fa-check"></i> Compliant</button>
                            <button type="button" class="btn-deny" onclick='openAuditModal(<?= $l["id"] ?>, "Liquidation #<?= $l["id"] ?> - <?= htmlspecialchars($l["event_name"], ENT_QUOTES) ?>", "with_findings")'><i class="fas fa-exclamation-triangle"></i> With Findings</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Custom Audit Modal (page-specific — Compliant / With Findings) -->
<div id="auditModal" class="vmodal-overlay">
    <div class="vmodal-box">
        <button class="vmodal-close" onclick="closeAuditModal()">&times;</button>
        <h3 id="auditModalTitle"><i class="fas fa-clipboard-check"></i> Audit Result</h3>
        <p>You are about to mark <strong id="auditEntryName"></strong> as <strong id="auditResultLabel"></strong>.</p>
        <form id="auditForm" method="POST" onsubmit="return submitAudit(event)">
            <input type="hidden" name="liquidation_id" id="auditLiquidationId">
            <input type="hidden" name="audit_result" id="auditResultValue">

            <label>Audit Notes / Findings <span id="auditNotesRequired" style="color:red; display:none;">*</span></label>
            <textarea name="audit_notes" id="auditNotes" placeholder="Enter audit notes here..."></textarea>

            <div class="vmodal-actions">
                <button type="button" class="vmodal-btn-cancel" onclick="closeAuditModal()">Cancel</button>
                <button type="submit" id="auditSubmitBtn" class="vmodal-btn-approve"><i class="fas fa-check"></i> Confirm</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    // ============ AUDIT MODAL ============
    function openAuditModal(liquidationId, name, result) {
        document.getElementById('auditLiquidationId').value = liquidationId;
        document.getElementById('auditResultValue').value = result;
        document.getElementById('auditEntryName').textContent = name;

        var title = document.getElementById('auditModalTitle');
        var label = document.getElementById('auditResultLabel');
        var notesRequired = document.getElementById('auditNotesRequired');
        var submitBtn = document.getElementById('auditSubmitBtn');

        if (result === 'compliant') {
            title.className = 'success';
            title.innerHTML = '<i class="fas fa-check-circle"></i> Mark as Compliant';
            label.textContent = 'COMPLIANT';
            notesRequired.style.display = 'none';
            document.getElementById('auditNotes').required = false;
            submitBtn.className = 'vmodal-btn-approve';
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Compliant';
        } else {
            title.className = 'danger';
            title.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Mark With Findings';
            label.textContent = 'WITH FINDINGS';
            notesRequired.style.display = 'inline';
            document.getElementById('auditNotes').required = true;
            submitBtn.className = 'vmodal-btn-deny';
            submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Confirm With Findings';
        }

        document.getElementById('auditNotes').value = '';
        document.getElementById('auditModal').classList.add('active');
        setTimeout(function() { document.getElementById('auditNotes').focus(); }, 100);
    }

    function closeAuditModal() {
        document.getElementById('auditModal').classList.remove('active');
    }

    function submitAudit(e) {
        e.preventDefault();
        var result = document.getElementById('auditResultValue').value;
        var notes = document.getElementById('auditNotes').value.trim();

        if (result === 'with_findings' && notes === '') {
            alert('Please provide audit notes / findings.');
            return false;
        }

        document.getElementById('auditForm').submit();
        return false;
    }

    // ============ LIQUIDATION VIEW MODAL (with extra liquidation fields) ============
    function openLiquidationViewModal(data, extraRows) {
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
    document.getElementById('auditModal').addEventListener('click', function(e) {
        if (e.target === this) closeAuditModal();
    });

    document.getElementById('userMenu').addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('show');});
    window.addEventListener('click',function(){document.getElementById('userMenu').classList.remove('show');});
</script>
</body>
</html>