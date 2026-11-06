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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year_label = trim($_POST['year_label'] ?? '');

    if (empty($year_label)) {
        $error = "School year is required.";
    } else {
        $check = $pdo->prepare("SELECT id FROM school_years WHERE year_label = ?");
        $check->execute([$year_label]);
        if ($check->fetch()) {
            $error = "That school year already exists.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO school_years (year_label) VALUES (?)");
            $stmt->execute([$year_label]);
            $message = "School year added successfully.";
        }
    }
}

if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM school_years WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $message = "School year deleted.";
}

$schoolYears = $pdo->query("SELECT * FROM school_years ORDER BY year_label DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage School Years - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .success { color:#27ae60; } .error { color:#e74c3c; }

        .modal-overlay {
            display:none; position:fixed; top:0; left:0; width:100%; height:100%;
            background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;
        }
        .modal-overlay.active { display:flex; }
        .modal-box {
            background:#fff; border-radius:12px; width:400px; max-width:90%; padding:30px; position:relative;
        }
        .modal-box h3 { margin-top:0; color:#1a3c5e; }
        .modal-close {
            position:absolute; top:15px; right:20px; background:none; border:none; font-size:22px; cursor:pointer;
        }
        .modal-box form label { display:block; font-weight:600; margin-bottom:6px; }
        .modal-box form input[type="text"] {
            width:100%; padding:10px; margin-bottom:15px; border:1px solid #d1d5db; border-radius:6px;
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Manage School Years';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>School Years</h3>
                <button class="btn-add" onclick="openModal()"><i class="fas fa-plus"></i> Add School Year</button>
            </div>

            <table>
                <tr><th>ID</th><th>School Year</th><th>Action</th></tr>
                <?php foreach ($schoolYears as $yr): ?>
                <tr>
                    <td><?= $yr['id'] ?></td>
                    <td><?= htmlspecialchars($yr['year_label']) ?></td>
                    <td>
                        <a href="?delete=<?= $yr['id'] ?>" class="btn-delete"
                           onclick="return openConfirmModal(function(){ window.location.href='?delete=<?= $yr['id'] ?>'; }, 'Delete this school year?')">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>

<!-- Add School Year Modal -->
<div id="schoolYearModal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <h3>Add School Year</h3>
        <form method="POST" onsubmit="return openConfirmModal(this, 'Add this school year?')">
            <label>School Year (e.g., 2028-2029)</label>
            <input type="text" name="year_label" placeholder="2028-2029" required>
            <button type="submit" class="btn-submit" style="width:100%;"><i class="fas fa-plus"></i> Add School Year</button>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    function openModal() {
        document.getElementById('schoolYearModal').classList.add('active');
    }
    function closeModal() {
        document.getElementById('schoolYearModal').classList.remove('active');
    }
    document.getElementById('schoolYearModal').addEventListener('click', function(e) {
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