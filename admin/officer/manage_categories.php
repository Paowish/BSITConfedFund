<?php
// officer/manage_categories.php (Mayor only)
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

if (strcasecmp($_SESSION['officer_position'] ?? '', 'Mayor') !== 0) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
$error = '';
$currentPic = getCurrentUserProfilePic($pdo);

// Keep what the mayor typed if there's an error (so the modal reopens prefilled)
$formName = '';
$formDesc = '';
$reopenModal = false;

// ============ HANDLE POST ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $error = "Category name is required.";
        } elseif (mb_strlen($name) > 100) {
            $error = "Category name is too long (max 100 characters).";
        } else {
            // Block duplicates of pending/approved categories (denied ones can be resubmitted)
            $dup = $pdo->prepare(
                "SELECT COUNT(*) FROM categories
                 WHERE LOWER(name) = LOWER(?) AND status IN ('pending','approved')"
            );
            $dup->execute([$name]);

            if ($dup->fetchColumn() > 0) {
                $error = "A category with that name already exists or is pending approval.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, status, created_by) VALUES (?, ?, 'pending', ?)");
                $stmt->execute([$name, $description, $_SESSION['officer_id']]);

                // PRG: redirect so refresh doesn't resubmit
                header('Location: manage_categories.php?msg=' . urlencode('Category submitted for approval.'));
                exit;
            }
        }

        // Error: keep input and reopen the modal
        $formName = $name;
        $formDesc = $description;
        $reopenModal = true;

    } elseif ($action === 'cancel') {
        // Mayor can cancel only his own category, and only while it's still pending
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ? AND created_by = ? AND status = 'pending'");
        $stmt->execute([$id, $_SESSION['officer_id']]);

        if ($stmt->rowCount() === 0) {
            $error = "Can't cancel: it may have already been reviewed.";
            header('Location: manage_categories.php?error=' . urlencode($error));
        } else {
            header('Location: manage_categories.php?msg=' . urlencode('Pending category cancelled.'));
        }
        exit;
    }
}

if (isset($_GET['msg']))   $message = $_GET['msg'];
if (isset($_GET['error'])) $error   = $_GET['error'];

// Categories + who added + who reviewed (reviewer is an adviser account)
$categories = $pdo->query(
    "SELECT c.*, o.name AS created_by_name, adv.name AS reviewed_by_name
     FROM categories c
     LEFT JOIN officers o ON c.created_by = o.id
     LEFT JOIN adviser_account adv ON c.reviewed_by = adv.id
     ORDER BY c.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Categories - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .badge-pending { color:#e67e22; font-weight:600; }
        .badge-approved { color:#27ae60; font-weight:600; }
        .badge-denied { color:#e74c3c; font-weight:600; }

        .alert {
            padding: 10px 18px; border-radius: 8px; margin-bottom: 18px;
            font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px;
        }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #27ae60; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #e74c3c; }

        .review-note { font-size: 12px; color: #666; line-height: 1.4; }
        .review-note .reason { color: #c0392b; font-style: italic; display: block; margin-top: 3px; }

        .modal-overlay {
            display:none; position:fixed; top:0; left:0; width:100%; height:100%;
            background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;
        }
        .modal-overlay.active { display:flex; }
        .modal-box {
            background:#fff; border-radius:12px; width:500px; max-width:90%; padding:30px; position:relative;
        }
        .modal-box h3 { margin-top:0; color:#1a3c5e; }
        .modal-close {
            position:absolute; top:15px; right:20px; background:none; border:none; font-size:22px; cursor:pointer;
        }
        .modal-box form label { display:block; font-weight:600; margin-bottom:6px; }
        .modal-box form input[type="text"],
        .modal-box form textarea {
            width:100%; padding:10px; margin-bottom:15px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box;
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Manage Categories';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h3>Categories</h3>
                <button class="btn-add" onclick="openModal()"><i class="fas fa-plus"></i> Add Category</button>
            </div>

            <?php if (count($categories) == 0): ?>
                <p>No categories yet.</p>
            <?php else: ?>
                <table>
                    <tr><th>Name</th><th>Description</th><th>Status</th><th>Added By</th><th>Review</th><th>Action</th></tr>
                    <?php foreach ($categories as $cat):
                        $isMine = ((int)$cat['created_by'] === (int)$_SESSION['officer_id']);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($cat['name']) ?></td>
                        <td><?= htmlspecialchars($cat['description'] ?? '') ?></td>
                        <td class="badge-<?= htmlspecialchars($cat['status']) ?>"><?= htmlspecialchars(ucfirst($cat['status'])) ?></td>
                        <td><?= htmlspecialchars($cat['created_by_name'] ?? '—') ?></td>
                        <td class="review-note">
                            <?php if ($cat['status'] === 'pending'): ?>
                                Waiting for adviser
                            <?php else: ?>
                                <?= htmlspecialchars($cat['reviewed_by_name'] ?? '—') ?>
                                <?php if (!empty($cat['reviewed_at'])): ?>
                                    · <?= date('M d, Y', strtotime($cat['reviewed_at'])) ?>
                                <?php endif; ?>
                                <?php if ($cat['status'] === 'denied' && !empty($cat['review_comment'])): ?>
                                    <span class="reason">Reason: <?= nl2br(htmlspecialchars($cat['review_comment'])) ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($cat['status'] === 'pending' && $isMine): ?>
                                <form method="POST" style="display:inline;" onsubmit="return openConfirmModal(this, 'Cancel this pending category?');">
                                    <input type="hidden" name="action" value="cancel">
                                    <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
                                    <button type="submit" class="btn-deny"><i class="fas fa-ban"></i> Cancel</button>
                                </form>
                            <?php elseif ($cat['status'] === 'denied'): ?>
                                <button type="button" class="btn-edit"
                                        data-name="<?= htmlspecialchars($cat['name']) ?>"
                                        data-desc="<?= htmlspecialchars($cat['description'] ?? '') ?>"
                                        onclick="resubmitCategory(this)">
                                    <i class="fas fa-redo"></i> Resubmit
                                </button>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div id="categoryModal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <h3 id="categoryModalTitle">Add New Category</h3>
        <form method="POST" onsubmit="return openConfirmModal(this, 'Submit this category for approval?')">
            <input type="hidden" name="action" value="add">
            <label>Category Name</label>
            <input type="text" name="name" id="catName" maxlength="100" required>
            <label>Description (optional)</label>
            <textarea name="description" id="catDesc" rows="3"></textarea>
            <button type="submit" class="btn-submit" style="width:100%;"><i class="fas fa-plus"></i> Submit for Approval</button>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    function openModal(name, desc, title) {
        document.getElementById('catName').value = name || '';
        document.getElementById('catDesc').value = desc || '';
        document.getElementById('categoryModalTitle').textContent = title || 'Add New Category';
        document.getElementById('categoryModal').classList.add('active');
    }
    function closeModal() {
        document.getElementById('categoryModal').classList.remove('active');
    }
    // Resubmit a denied category: opens the modal prefilled, creates a NEW pending entry
    function resubmitCategory(btn) {
        openModal(btn.dataset.name, btn.dataset.desc, 'Resubmit Category');
    }
    document.getElementById('categoryModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.getElementById('userMenu').addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('show');
    });
    window.addEventListener('click', function() {
        document.getElementById('userMenu').classList.remove('show');
    });

    <?php if ($reopenModal): ?>
        openModal(<?= json_encode($formName) ?>, <?= json_encode($formDesc) ?>);
    <?php endif; ?>
</script>
</body>
</html>