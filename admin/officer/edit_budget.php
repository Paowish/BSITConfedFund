<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

// Restrict to Business Manager only (sila ang gumagawa ng budget plans)
if ($_SESSION['officer_position'] !== 'Business Manager') {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$currentPic = getCurrentUserProfilePic($pdo);

$id = $_GET['id'] ?? 0;
$officer_id = $_SESSION['officer_id'];

// Fetch the denied budget entry
$stmt = $pdo->prepare("SELECT * FROM event_budgets WHERE id = ? AND created_by = ? AND status = 'denied'");
$stmt->execute([$id, $officer_id]);
$budget = $stmt->fetch();

if (!$budget) {
    die("Denied budget entry not found.");
}

// Fetch approved events
$events = $pdo->query("SELECT id, name, school_year FROM events WHERE status='approved' ORDER BY school_year DESC, name ASC")->fetchAll();
// Fetch approved categories
$categories = $pdo->query("SELECT id, name FROM categories WHERE status='approved' ORDER BY name")->fetchAll();
// Fetch school years
$schoolYears = $pdo->query("SELECT year_label FROM school_years ORDER BY year_label DESC")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = $_POST['event_id'] ?? null;
    $school_year = $_POST['school_year'] ?? '';
    $budget_name = trim($_POST['budget_name'] ?? '');
    $allocated_amount = $_POST['allocated_amount'] ?? 0;
    $category_id = $_POST['category_id'] ?? null;

    if (empty($budget_name) || !is_numeric($allocated_amount) || $allocated_amount <= 0) {
        $error = "Budget name and valid allocated amount are required.";
    } else {
        // FIX: Palitan ang 'pending' ng 'pending_treasurer_review'
        $stmt = $pdo->prepare(
            "UPDATE event_budgets 
             SET event_id = ?, school_year = ?, budget_name = ?, allocated_amount = ?, category_id = ?,
                 status = 'pending_treasurer_review', reviewed_by = NULL, reviewed_at = NULL, review_comment = NULL
             WHERE id = ? AND created_by = ?"
        );
        $stmt->execute([$event_id ?: null, $school_year ?: null, $budget_name, $allocated_amount, $category_id ?: null, $id, $officer_id]);

        $message = "Budget updated and resubmitted for review.";
        
        // FIX: Palitan ang 'manage_budgets.php' ng 'dashboard.php'
        header("Location: dashboard.php?msg=" . urlencode($message));
        exit;
    }
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Budget - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card {
            background: #ffffff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            padding: 25px 30px;
            margin-bottom: 25px;
            max-width: 700px;
        }
        .card h3 { margin-top: 0; color: #1a3c5e; }
        form label { display: block; font-weight: 600; color: #2c3e50; margin-bottom: 6px; font-size: 14px; }
        form input[type="text"],
        form input[type="number"],
        form select,
        form textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            color: #1f2937;
            background: #fafafa;
            margin-bottom: 18px;
            box-sizing: border-box;
        }
        form input:focus, form select:focus, form textarea:focus {
            outline: none;
            border-color: #1a3c5e;
            box-shadow: 0 0 0 2px rgba(26, 60, 94, 0.15);
            background: #fff;
        }
        button[type="submit"] {
            background: #1a3c5e;
            color: white;
            border: none;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
        }
        button[type="submit"]:hover { background: #2c5282; }
        .success { color: #27ae60; font-weight: 500; }
        .error { color: #e74c3c; font-weight: 500; }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Edit Budget';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <div class="card">
            <h3>Edit Denied Budget Plan</h3>
            <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
            
            <form method="POST" onsubmit="return openConfirmModal(this, 'Resubmit this budget?')">
                <label>Event (optional)</label>
                <select name="event_id">
                    <option value="">-- Select Event --</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?= $ev['id'] ?>" <?= $budget['event_id'] == $ev['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ev['name']) ?> (<?= htmlspecialchars($ev['school_year']) ?>)</option>
                    <?php endforeach; ?>
                </select>

                <label>School Year</label>
                <select name="school_year">
                    <option value="">-- Select School Year --</option>
                    <?php foreach ($schoolYears as $yr): ?>
                        <option value="<?= htmlspecialchars($yr) ?>" <?= $budget['school_year'] === $yr ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Budget Name</label>
                <input type="text" name="budget_name" value="<?= htmlspecialchars($budget['budget_name']) ?>" required>

                <label>Allocated Amount (₱)</label>
                <input type="number" step="0.01" name="allocated_amount" value="<?= htmlspecialchars($budget['allocated_amount']) ?>" required>

                <label>Category (optional)</label>
                <select name="category_id">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $budget['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit"><i class="fas fa-paper-plane"></i> Resubmit Budget</button>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    document.getElementById('userMenu').addEventListener('click', function(e){ e.stopPropagation(); this.classList.toggle('show'); });
    window.addEventListener('click', function(){ document.getElementById('userMenu').classList.remove('show'); });
</script>
</body>
</html>