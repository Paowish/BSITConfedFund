<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
requireAdviser();

$message = '';
$error = '';

// Add category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description'] ?? '');
    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
        $message = "Category added.";
    }
}

// Delete category
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Category deleted.";
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Categories - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/adviser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
$pageTitle = 'PAGE TITLE';
$pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name'] ?? $_SESSION['adviser_name'] ?? 'User') . '</strong>';
$basePath = '../';
include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
?>
    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <div class="card">
            <h3>Add Category</h3>
            <form method="POST"><input type="hidden" name="action" value="add">
                <label>Name:</label><input type="text" name="name" required>
                <label>Description:</label><input type="text" name="description">
                <button type="submit" class="btn-primary">Add</button>
            </form>
        </div>
        <div class="card"><h3>Existing Categories</h3><table><tr><th>ID</th><th>Name</th><th>Description</th><th>Action</th></tr>
        <?php foreach ($categories as $c): ?>
        <tr><td><?= $c['id'] ?></td><td><?= htmlspecialchars($c['name']) ?></td><td><?= htmlspecialchars($c['description']) ?></td><td><a href="?delete=<?= $c['id'] ?>" onclick="return confirm('Delete?')">Delete</a></td></tr>
        <?php endforeach; ?>
        </table></div>
    </div>
</div>
<script>document.getElementById('userMenu').addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('show');});window.addEventListener('click',function(){document.getElementById('userMenu').classList.remove('show');});</script>
</body>
</html>