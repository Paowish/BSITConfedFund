<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

if ($_SESSION['officer_position'] !== 'Mayor') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$currentPic = getCurrentUserProfilePic($pdo);

$schoolYears = $pdo->query("SELECT year_label FROM school_years ORDER BY year_label DESC")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $school_year = $_POST['school_year'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $event_datetime = $_POST['event_datetime'] ?? null;
    $venue = trim($_POST['venue'] ?? '');
    $expected_participants = intval($_POST['expected_participants'] ?? 0);
    $proposed_activities = trim($_POST['proposed_activities'] ?? '');
    $initial_requirements = trim($_POST['initial_requirements'] ?? '');

    if (empty($name) || empty($school_year)) {
        $error = "Event name and school year are required.";
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO events (name, school_year, description, purpose, event_datetime, venue, expected_participants, proposed_activities, initial_requirements, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_budget_planning', ?)"
        );
        $stmt->execute([
            $name, $school_year, $description, $purpose, $event_datetime ?: null, $venue,
            $expected_participants ?: null, $proposed_activities, $initial_requirements, $_SESSION['officer_id']
        ]);
        $message = "Event submitted. Status: Pending Budget Planning.";
    }
}

// Active events (not rejected/closed)
$events = $pdo->query(
    "SELECT e.*, o.name AS created_by_name
     FROM events e
     LEFT JOIN officers o ON e.created_by = o.id
     WHERE e.status NOT IN ('adviser_rejected', 'closed')
     ORDER BY e.created_at DESC"
)->fetchAll();

// Rejected events
$rejectedEvents = $pdo->query(
    "SELECT e.*, o.name AS created_by_name,
            eb.adviser_comment, eb.adviser_reviewed_at,
            a.name AS adviser_name
     FROM events e
     LEFT JOIN officers o ON e.created_by = o.id
     LEFT JOIN event_budgets eb ON eb.event_id = e.id
     LEFT JOIN adviser_account a ON eb.adviser_reviewed_by = a.id
     WHERE e.status = 'adviser_rejected'
     ORDER BY eb.adviser_reviewed_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Events - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .badge-pending { color:#e67e22; font-weight:600; }
        .badge-approved { color:#27ae60; font-weight:600; }
        .badge-denied { color:#e74c3c; font-weight:600; }
        .badge-pending_budget_planning { color:#2980b9; font-weight:600; }
        .badge-pending_treasurer_review { color:#8e44ad; font-weight:600; }
        .badge-pending_adviser_review { color:#8e44ad; font-weight:600; }
        .badge-budget_approved { color:#27ae60; font-weight:600; }
        .badge-funds_released { color:#16a085; font-weight:600; }
        .badge-event_completed { color:#27ae60; font-weight:600; }
        .badge-pending_audit { color:#e67e22; font-weight:600; }
        .badge-closed { color:#7f8c8d; font-weight:600; }
        .badge-adviser_rejected { color:#c0392b; font-weight:600; }
        .success { color:#27ae60; } .error { color:#e74c3c; }

        .modal-overlay {
            display:none; position:fixed; top:0; left:0; width:100%; height:100%;
            background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;
        }
        .modal-overlay.active { display:flex; }
        .modal-box {
            background:#fff; border-radius:12px; width:700px; max-width:90%; max-height:85vh; overflow-y:auto;
            padding:30px; position:relative;
        }
        .modal-box h3 { margin-top:0; color:#1a3c5e; }
        .modal-close {
            position:absolute; top:15px; right:20px; background:none; border:none; font-size:22px; cursor:pointer;
        }
        .modal-box form label { display:block; font-weight:600; margin-bottom:6px; }
        .modal-box form input,
        .modal-box form select,
        .modal-box form textarea {
            width:100%; padding:10px; margin-bottom:15px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;
        }
        .modal-box form textarea { resize: vertical; }

        .rejected-section {
            border-left: 4px solid #c0392b;
        }
        .rejected-section h3 { color: #c0392b; }
        .rejection-reason {
            background: #fdecea;
            border-left: 3px solid #c0392b;
            padding: 10px 14px;
            border-radius: 6px;
            font-size: 13px;
            color: #721c24;
            margin-top: 6px;
            font-style: italic;
        }
        .btn-new-event {
            background: #27ae60;
            color: white;
            padding: 6px 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-new-event:hover { background: #219a52; }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Manage Events';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Events</h3>
                <button class="btn-add" onclick="openModal()"><i class="fas fa-plus"></i> Create Event</button>
            </div>

            <?php if (count($events) == 0): ?>
                <p>No events yet.</p>
            <?php else: ?>
                <table>
                    <tr><th>Event Name</th><th>School Year</th><th>Date</th><th>Venue</th><th>Status</th></tr>
                    <?php foreach ($events as $ev): ?>
                    <tr>
                        <td><?= htmlspecialchars($ev['name']) ?></td>
                        <td><?= htmlspecialchars($ev['school_year']) ?></td>
                        <td><?= $ev['event_datetime'] ? date('Y-m-d H:i', strtotime($ev['event_datetime'])) : '—' ?></td>
                        <td><?= htmlspecialchars($ev['venue'] ?? '—') ?></td>
                        <td class="badge-<?= $ev['status'] ?>"><?= ucwords(str_replace('_', ' ', $ev['status'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <?php if (count($rejectedEvents) > 0): ?>
        <div class="card rejected-section">
            <h3><i class="fas fa-exclamation-triangle"></i> Rejected by Adviser</h3>
            <p style="color:#666; font-size:13px; margin-bottom:15px;">
                These events were rejected by the Adviser. They cannot be edited. To continue, create a <strong>new event</strong>.
            </p>
            <table>
                <tr>
                    <th>Event Name</th>
                    <th>School Year</th>
                    <th>Budget</th>
                    <th>Rejected By</th>
                    <th>Rejected At</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($rejectedEvents as $ev): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($ev['name']) ?></strong>
                        <?php if (!empty($ev['adviser_comment'])): ?>
                            <div class="rejection-reason">
                                <i class="fas fa-comment-dots"></i>
                                <strong>Reason:</strong> <?= htmlspecialchars($ev['adviser_comment']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($ev['school_year']) ?></td>
                    <td>₱<?= number_format($ev['allocated_amount'] ?? 0, 2) ?></td>
                    <td><?= htmlspecialchars($ev['adviser_name'] ?? 'Adviser') ?></td>
                    <td><?= $ev['adviser_reviewed_at'] ? date('M d, Y h:i A', strtotime($ev['adviser_reviewed_at'])) : '—' ?></td>
                    <td>
                        <button type="button" class="btn-new-event" onclick="openModal()">
                            <i class="fas fa-plus"></i> Create New Event
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create Event Modal -->
<div id="eventModal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <h3>Create New Event</h3>
        <form method="POST" onsubmit="return openConfirmModal(this, 'Submit this event for budget planning?')">
            <label>Event Name</label>
            <input type="text" name="name" required>

            <label>School Year</label>
            <select name="school_year" required>
                <option value="">-- Select School Year --</option>
                <?php foreach ($schoolYears as $yr): ?>
                    <option value="<?= htmlspecialchars($yr) ?>"><?= htmlspecialchars($yr) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Description</label>
            <textarea name="description" rows="2"></textarea>

            <label>Purpose / Objectives</label>
            <textarea name="purpose" rows="2"></textarea>

            <label>Date and Time</label>
            <input type="datetime-local" name="event_datetime">

            <label>Venue</label>
            <input type="text" name="venue">

            <label>Expected Number of Participants</label>
            <input type="number" name="expected_participants" min="0">

            <label>Proposed Activities</label>
            <textarea name="proposed_activities" rows="2"></textarea>

            <label>Initial Requirements</label>
            <textarea name="initial_requirements" rows="2"></textarea>

            <button type="submit" class="btn-submit" style="width:100%;"><i class="fas fa-paper-plane"></i> Submit for Budget Planning</button>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    function openModal() {
        document.getElementById('eventModal').classList.add('active');
    }
    function closeModal() {
        document.getElementById('eventModal').classList.remove('active');
    }
    document.getElementById('eventModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
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