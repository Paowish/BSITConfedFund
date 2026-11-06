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
    $eventId = $_POST['event_id'] ?? 0;
    $budgetId = $_POST['budget_id'] ?? 0;
    $releaseDate = $_POST['release_date'] ?? date('Y-m-d');
    $reference = trim($_POST['reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($eventId) || empty($budgetId)) {
        $error = "Invalid request.";
    } else {
        $stmt = $pdo->prepare("UPDATE events SET status = 'funds_released' WHERE id = ?");
        $stmt->execute([$eventId]);

        $stmt2 = $pdo->prepare("UPDATE event_budgets SET reviewed_by = ?, reviewed_at = NOW(), review_comment = CONCAT(IFNULL(review_comment,''), ' | RELEASED on ', ?, ' Ref: ', ?) WHERE id = ?");
        $stmt2->execute([$_SESSION['officer_id'], $releaseDate, $reference, $budgetId]);

        $message = "Funds released successfully for the event.";
    }
}

$events = $pdo->query(
    "SELECT e.*, eb.id AS budget_id, eb.allocated_amount, eb.budget_name
     FROM events e
     JOIN event_budgets eb ON eb.event_id = e.id
     WHERE e.status = 'budget_approved' AND eb.status = 'approved'
     ORDER BY e.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fund Release - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .success { color:#27ae60; } .error { color:#e74c3c; }
        .modal-overlay { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }
        .modal-overlay.active { display:flex; }
        .modal-box { background:#fff; border-radius:12px; width:500px; max-width:90%; padding:30px; position:relative; }
        .modal-box h3 { margin-top:0; color:#1a3c5e; }
        .modal-close { position:absolute; top:15px; right:20px; background:none; border:none; font-size:22px; cursor:pointer; }
        .modal-box label { display:block; font-weight:600; margin-bottom:6px; }
        .modal-box input, .modal-box textarea { width:100%; padding:10px; margin-bottom:15px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box; }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Fund Release';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <h3>Events with Approved Budget (Ready for Fund Release)</h3>
            <?php if (count($events) == 0): ?>
                <p>No approved budgets waiting for fund release.</p>
            <?php else: ?>
                <table>
                    <tr><th>Event</th><th>Budget Name</th><th>Approved Amount</th><th>Action</th></tr>
                    <?php foreach ($events as $ev): ?>
                    <tr>
                        <td><?= htmlspecialchars($ev['name']) ?></td>
                        <td><?= htmlspecialchars($ev['budget_name']) ?></td>
                        <td>₱<?= number_format($ev['allocated_amount'], 2) ?></td>
                        <td>
                            <button type="button" class="btn-approve" onclick='openReleaseModal(<?= $ev["id"] ?>, <?= $ev["budget_id"] ?>, <?= htmlspecialchars(json_encode($ev["name"]), ENT_QUOTES) ?>)'>
                                <i class="fas fa-hand-holding-usd"></i> Release Funds
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="releaseModal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeReleaseModal()">&times;</button>
        <h3>Release Funds</h3>
        <form method="POST" onsubmit="return openConfirmModal(this, 'Are you sure you want to release funds for this event? This action cannot be undone.')">
            <input type="hidden" name="event_id" id="releaseEventId">
            <input type="hidden" name="budget_id" id="releaseBudgetId">
            <p><strong>Event:</strong> <span id="releaseEventName"></span></p>

            <label>Release Date</label>
            <input type="date" name="release_date" value="<?= date('Y-m-d') ?>" required>

            <label>Reference / Voucher Number (optional)</label>
            <input type="text" name="reference" placeholder="e.g., CV-2025-001">

            <label>Notes (optional)</label>
            <textarea name="notes" rows="3"></textarea>

            <button type="submit" class="btn-submit" style="width:100%;"><i class="fas fa-check"></i> Confirm Release</button>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    function openReleaseModal(eventId, budgetId, eventName) {
        document.getElementById('releaseEventId').value = eventId;
        document.getElementById('releaseBudgetId').value = budgetId;
        document.getElementById('releaseEventName').textContent = eventName;
        document.getElementById('releaseModal').classList.add('active');
    }
    function closeReleaseModal() {
        document.getElementById('releaseModal').classList.remove('active');
    }
    document.getElementById('releaseModal').addEventListener('click', function(e) {
        if (e.target === this) closeReleaseModal();
    });

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