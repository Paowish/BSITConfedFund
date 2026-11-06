<?php
// adviser/cong_approvals.php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
requireAdviser();

$message = '';
$error = '';

// ============ HANDLE POST ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = $_POST['type'] ?? '';
    $id      = (int)($_POST['id'] ?? 0);
    $action  = $_POST['action'] ?? '';
    $comment = trim($_POST['comment'] ?? '');

    if (!in_array($action, ['approve', 'deny'], true)) {
        $error = "Invalid action.";
    } elseif (!in_array($type, ['category', 'event'], true)) {
        $error = "Invalid type. Budget approvals are now handled in the Budget Approvals page.";
    } elseif ($action === 'deny' && $comment === '') {
        $error = "A reason/comment is required when denying.";
    } else {
        $table  = ($type === 'category') ? 'categories' : 'events';
        $status = ($action === 'approve') ? 'approved' : 'denied';

        // Don't approve a category if an approved one with the same name already exists
        if ($type === 'category' && $action === 'approve') {
            $nameStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ? AND status = 'pending'");
            $nameStmt->execute([$id]);
            $catName = $nameStmt->fetchColumn();

            if ($catName !== false) {
                $dup = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE LOWER(name) = LOWER(?) AND status = 'approved' AND id <> ?");
                $dup->execute([$catName, $id]);
                if ($dup->fetchColumn() > 0) {
                    $error = "An approved category named \"$catName\" already exists. Deny this one instead.";
                }
            }
        }

        if (!$error) {
            // Only pending items can be reviewed (can't overwrite an earlier decision)
            $stmt = $pdo->prepare(
                "UPDATE $table
                 SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_comment = ?
                 WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$status, $_SESSION['adviser_id'], $comment, $id]);

            if ($stmt->rowCount() === 0) {
                $error = "That item was already reviewed, cancelled, or no longer exists.";
            } else {
                $message = ucfirst($type) . " #$id has been $status.";
            }
        }
    }

    // PRG: redirect so refresh doesn't resubmit
    header('Location: config_approvals.php?' . http_build_query($error ? ['error' => $error] : ['msg' => $message]));
    exit;
}

if (isset($_GET['msg']))   $message = $_GET['msg'];
if (isset($_GET['error'])) $error   = $_GET['error'];

// Fetch pending categories
$pendingCategories = $pdo->query(
    "SELECT c.*, o.name AS created_by_name
     FROM categories c
     LEFT JOIN officers o ON c.created_by = o.id
     WHERE c.status = 'pending'
     ORDER BY c.created_at ASC"
)->fetchAll();

// Fetch pending events
$pendingEvents = $pdo->query(
    "SELECT e.*, o.name AS created_by_name
     FROM events e
     LEFT JOIN officers o ON e.created_by = o.id
     WHERE e.status = 'pending'
     ORDER BY e.created_at ASC"
)->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Configuration Approvals - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/adviser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        form.inline { display:inline; }
        .success { color:#27ae60; } .error { color:#e74c3c; }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
$pageTitle = 'CONFIGURATION APPROVALS';
$pageSubtitle = ' <strong>' . htmlspecialchars($_SESSION['officer_name'] ?? $_SESSION['adviser_name'] ?? 'User') . '</strong>';
$basePath = '../';
include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <!-- Pending Categories -->
        <div class="card">
            <h3><i class="fas fa-tags"></i> Pending Categories</h3>
            <?php if (count($pendingCategories) == 0): ?>
                <p>No pending categories.</p>
            <?php else: ?>
                <table>
                    <tr><th>ID</th><th>Name</th><th>Description</th><th>Added By</th><th>Action</th></tr>
                    <?php foreach ($pendingCategories as $cat):
                        $viewData = [
                            'type' => 'Category',
                            'status' => 'Pending Approval',
                            'submitted_by' => $cat['created_by_name'] ?? 'Unknown',
                            'source' => $cat['name'],
                            'description' => $cat['description'] ?? '—',
                            'date' => $cat['created_at'] ? date('Y-m-d', strtotime($cat['created_at'])) : '—',
                            'documents' => []
                        ];
                        $entryLabel = 'Category: ' . $cat['name'];
                    ?>
                    <tr>
                        <td><?= (int)$cat['id'] ?></td>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td><?= htmlspecialchars($cat['description'] ?? '') ?></td>
                        <td><?= htmlspecialchars($cat['created_by_name'] ?? 'Unknown') ?></td>
                        <td>
                            <button type="button" class="btn-view" onclick='openViewModal(<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES) ?>)'><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn-approve" onclick='openApproveModal("category", <?= (int)$cat['id'] ?>, <?= htmlspecialchars(json_encode($entryLabel), ENT_QUOTES) ?>)'><i class="fas fa-check"></i> Approve</button>
                            <button type="button" class="btn-deny" onclick='openDenyModal("category", <?= (int)$cat['id'] ?>, <?= htmlspecialchars(json_encode($entryLabel), ENT_QUOTES) ?>)'><i class="fas fa-times"></i> Deny</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pending Events -->
        <div class="card">
            <h3><i class="fas fa-calendar-alt"></i> Pending Events</h3>
            <?php if (count($pendingEvents) == 0): ?>
                <p>No pending events.</p>
            <?php else: ?>
                <table>
                    <tr><th>ID</th><th>Event Name</th><th>School Year</th><th>Date</th><th>Venue</th><th>Added By</th><th>Action</th></tr>
                    <?php foreach ($pendingEvents as $ev):
                        $viewData = [
                            'type' => 'Event',
                            'status' => 'Pending Approval',
                            'submitted_by' => $ev['created_by_name'] ?? 'Unknown',
                            'event' => $ev['name'],
                            'school_year' => $ev['school_year'] ?? '—',
                            'date' => $ev['event_datetime'] ? date('Y-m-d H:i', strtotime($ev['event_datetime'])) : '—',
                            'description' => $ev['description'] ?? '—',
                            'documents' => []
                        ];
                        $extraRows = [
                            'Purpose' => $ev['purpose'] ?? '—',
                            'Venue' => $ev['venue'] ?? '—',
                            'Expected Participants' => $ev['expected_participants'] ?? '—',
                            'Proposed Activities' => $ev['proposed_activities'] ?? '—',
                            'Initial Requirements' => $ev['initial_requirements'] ?? '—'
                        ];
                        $entryLabel = 'Event: ' . $ev['name'];
                    ?>
                    <tr>
                        <td><?= (int)$ev['id'] ?></td>
                        <td><?= htmlspecialchars($ev['name']) ?></td>
                        <td><?= htmlspecialchars($ev['school_year'] ?? '—') ?></td>
                        <td><?= $ev['event_datetime'] ? date('Y-m-d H:i', strtotime($ev['event_datetime'])) : '—' ?></td>
                        <td><?= htmlspecialchars($ev['venue'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($ev['created_by_name'] ?? 'Unknown') ?></td>
                        <td>
                            <button type="button" class="btn-view" onclick='openEventViewModal(<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($extraRows), ENT_QUOTES) ?>)'><i class="fas fa-eye"></i> View</button>
                            <button type="button" class="btn-approve" onclick='openApproveModal("event", <?= (int)$ev['id'] ?>, <?= htmlspecialchars(json_encode($entryLabel), ENT_QUOTES) ?>)'><i class="fas fa-check"></i> Approve</button>
                            <button type="button" class="btn-deny" onclick='openDenyModal("event", <?= (int)$ev['id'] ?>, <?= htmlspecialchars(json_encode($entryLabel), ENT_QUOTES) ?>)'><i class="fas fa-times"></i> Deny</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    // ============ EVENT VIEW MODAL (with extra fields) ============
    // vEsc / vEscBr are defined in shared_ui.php — every value is escaped before going into innerHTML
    function openEventViewModal(data, extraRows) {
        var html = '';

        function row(label, value) {
            return '<div class="vdetail-row"><div class="vdetail-label">' + vEsc(label) + '</div><div class="vdetail-value">' + vEscBr(value) + '</div></div>';
        }

        if (data.type) html += row('Type', data.type);
        if (data.status) html += row('Status', data.status);
        if (data.submitted_by) html += row('Submitted By', data.submitted_by);
        if (data.event) html += row('Event Name', data.event);
        if (data.school_year) html += row('School Year', data.school_year);

        if (extraRows) {
            for (var key in extraRows) {
                if (Object.prototype.hasOwnProperty.call(extraRows, key)) {
                    html += row(key, extraRows[key]);
                }
            }
        }

        if (data.date) html += row('Date', data.date);s
        if (data.description) html += row('Description', data.description);

        html += '<div class="vsection-title">Source Documents</div>';
        html += '<p style="color:#999;font-size:13px;">No documents attached</p>';

        document.getElementById('viewContent').innerHTML = html;
        document.getElementById('viewModal').classList.add('active');
    }

    document.getElementById('userMenu').addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('show');});
    window.addEventListener('click',function(){document.getElementById('userMenu').classList.remove('show');});
</script>
</body>
</html>