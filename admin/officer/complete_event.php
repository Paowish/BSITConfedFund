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
$currentPic = getCurrentUserProfilePic($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $eventId = $_POST['event_id'];
    $stmt = $pdo->prepare("UPDATE events SET status = 'event_completed' WHERE id = ? AND status = 'funds_released'");
    $stmt->execute([$eventId]);
    $message = "Event marked as completed.";
}

$events = $pdo->query("SELECT * FROM events WHERE status = 'funds_released' ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Complete Event - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .success { color:#27ae60; }
    </style>
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Complete Event';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>

        <div class="card">
            <h3>Events with Funds Released</h3>
            <?php if (count($events) == 0): ?>
                <p>No events waiting to be completed.</p>
            <?php else: ?>
                <table>
                    <tr><th>Event</th><th>School Year</th><th>Venue</th><th>Action</th></tr>
                    <?php foreach ($events as $ev): ?>
                    <tr>
                        <td><?= htmlspecialchars($ev['name']) ?></td>
                        <td><?= htmlspecialchars($ev['school_year']) ?></td>
                        <td><?= htmlspecialchars($ev['venue'] ?? '—') ?></td>
                        <td>
                            <form method="POST" onsubmit="return openConfirmModal(this, 'Mark this event as completed? This will move it to the liquidation stage.')">
                                <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                <button type="submit" class="btn-approve"><i class="fas fa-check"></i> Mark as Completed</button>
                            </form>
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