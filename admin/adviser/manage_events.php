<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
requireAdviser();

$message = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $name = trim($_POST['name'] ?? '');
    $school_year = $_POST['school_year'] ?? '';
    if (empty($name) || empty($school_year)) {
        $error = "Event name and school year required.";
    } else {
        $pdo->prepare("INSERT INTO events (name, school_year) VALUES (?,?)")->execute([$name,$school_year]);
        $message = "Event added.";
    }
}
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM events WHERE id=?")->execute([$_GET['delete']]);
    $message = "Event deleted.";
}
$events = $pdo->query("SELECT * FROM events ORDER BY school_year DESC, name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Events - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/adviser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<script>document.getElementById('userMenu').addEventListener('click',function(e){e.stopPropagation();this.classList.toggle('show');});window.addEventListener('click',function(){document.getElementById('userMenu').classList.remove('show');});</script>
</body>
</html>