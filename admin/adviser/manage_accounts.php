<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireAdviser();

$message = '';
$error = '';
$currentPic = getCurrentUserProfilePic($pdo);

// Handle add / edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = trim($_POST['name'] ?? '');
    $position = $_POST['position'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $school_year = $_POST['school_year'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($position) || empty($username) || empty($email) || empty($school_year)) {
        $error = "Name, position, username, email, and school year are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($action === 'add' && empty($password)) {
        $error = "Password is required for new officers.";
    } else {
        $id = $_POST['id'] ?? 0;
        $checkStmt = $pdo->prepare("SELECT id FROM officers WHERE username = ? AND id != ?");
        $checkStmt->execute([$username, $id]);
        if ($checkStmt->fetch()) {
            $error = "Username already exists.";
        } else {
            $checkEmail = $pdo->prepare("SELECT id FROM officers WHERE email = ? AND id != ?");
            $checkEmail->execute([$email, $id]);
            if ($checkEmail->fetch()) {
                $error = "Email already exists.";
            } else {
                if ($action === 'add') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO officers (name, position, username, email, school_year, is_active, password_hash) VALUES (?, ?, ?, ?, ?, 1, ?)");
                    $stmt->execute([$name, $position, $username, $email, $school_year, $hash]);
                    $message = "Officer added successfully.";
                } elseif ($action === 'edit') {
                    if (!empty($password)) {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE officers SET name=?, position=?, username=?, email=?, school_year=?, password_hash=? WHERE id=?");
                        $stmt->execute([$name, $position, $username, $email, $school_year, $hash, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE officers SET name=?, position=?, username=?, email=?, school_year=? WHERE id=?");
                        $stmt->execute([$name, $position, $username, $email, $school_year, $id]);
                    }
                    $message = "Officer updated.";
                }
            }
        }
    }
}

// Handle toggle active/inactive
if (isset($_GET['toggle_active'])) {
    $id = $_GET['toggle_active'];
    $stmt = $pdo->prepare("UPDATE officers SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$id]);
    $message = "Officer status updated.";
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $check = $pdo->prepare("SELECT id FROM funds WHERE officer_id = ? UNION SELECT id FROM expenses WHERE officer_id = ? LIMIT 1");
    $check->execute([$id, $id]);
    if ($check->fetch()) {
        $error = "Cannot delete officer with existing transactions. Deactivate instead.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM officers WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Officer deleted.";
    }
}

// Fetch all officers
$officers = $pdo->query("SELECT * FROM officers ORDER BY school_year DESC, position, name")->fetchAll();

// Fetch school years
$schoolYears = $pdo->query("SELECT year_label FROM school_years ORDER BY year_label DESC")->fetchAll(PDO::FETCH_COLUMN);

// Determine if editing
$editMode = false;
$editOfficer = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM officers WHERE id = ?");
    $stmt->execute([$editId]);
    $editOfficer = $stmt->fetch();
    if ($editOfficer) {
        $editMode = true;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Accounts - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/adviser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .card { padding: 0; overflow: hidden; }
        .card-header { padding: 20px 25px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fff; }
        .card-header h3 { margin: 0; }
        
        table { width: 100%; border-collapse: collapse; }
        th { padding: 15px 20px; background: #f8fafc; font-weight: 600; font-size: 12px; color: #64748b; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; }
        td { padding: 15px 20px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 14px; }
        tr:last-child td { border-bottom: none; }

                /* ACTION BUTTONS ALIGNMENT FIX */
        .actions-cell {
            display: grid;
            grid-template-columns: repeat(3, 1fr); /* 3 equal columns */
            gap: 6px;
            width: 100%;
            max-width: 280px; /* Adjust this if needed */
        }
        .actions-cell .btn-edit,
        .actions-cell .btn-toggle,
        .actions-cell .btn-delete {
            width: 100%; /* Force fill the grid cell */
            padding: 6px 8px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: all 0.2s;
            height: 32px;
            box-sizing: border-box;
        }
        .actions-cell .btn-edit { background: #f39c12; color: white; }
        .actions-cell .btn-edit:hover { background: #d68910; }
        .actions-cell .btn-toggle { background: #2980b9; color: white; }
        .actions-cell .btn-toggle:hover { background: #1c6ea4; }
        .actions-cell .btn-delete { background: #e74c3c; color: white; }
        .actions-cell .btn-delete:hover { background: #c0392b; }

        /* STATUS BADGES */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }

        .success { color: #27ae60; font-weight: 500; }
        .error { color: #e74c3c; font-weight: 500; }

        /* MODAL */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #fff; border-radius: 12px; width: 600px; max-width: 95%; max-height: 90vh; overflow-y: auto; padding: 30px; position: relative; }
        .modal-box h3 { margin-top: 0; color: #1a3c5e; margin-bottom: 20px; }
        .modal-close { position: absolute; top: 15px; right: 20px; background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #334155; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #1a3c5e; box-shadow: 0 0 0 3px rgba(26,60,94,0.1); }
        .password-note { font-size: 12px; color: #64748b; margin-top: -10px; margin-bottom: 15px; }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Manage Accounts';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['adviser_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Current Officers</h3>
                <button class="btn-add" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Officer</button>
            </div>

            <?php if (count($officers) === 0): ?>
                <p style="padding: 20px;">No officers found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>School Year</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($officers as $off): ?>
                            <tr>
                                <td style="font-weight: 600; color: #1e293b;"><?= htmlspecialchars($off['name']) ?></td>
                                <td><?= htmlspecialchars($off['position']) ?></td>
                                <td><?= htmlspecialchars($off['username']) ?></td>
                                <td><?= htmlspecialchars($off['email'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($off['school_year'] ?? '—') ?></td>
                                <td>
                                    <span class="status-badge <?= $off['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $off['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions-cell">
                                        <button type="button" class="btn-edit" onclick='openEditModal(<?= htmlspecialchars(json_encode($off), ENT_QUOTES) ?>)'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        
                                        <a href="?toggle_active=<?= $off['id'] ?>" class="btn-toggle"
                                           onclick="return openConfirmModal(function(){ window.location.href='?toggle_active=<?= $off['id'] ?>'; }, 'Toggle active status for this officer?')">
                                            <?= $off['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </a>
                                        
                                        <a href="?delete=<?= $off['id'] ?>" class="btn-delete"
                                           onclick="return openConfirmModal(function(){ window.location.href='?delete=<?= $off['id'] ?>'; }, 'Delete this officer? Make sure they have no transactions.')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Officer Modal -->
<div id="officerModal" class="modal-overlay">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">&times;</button>
        <h3 id="modalTitle">Add New Officer</h3>
        <form method="POST" id="officerForm" onsubmit="return openConfirmModal(this, document.getElementById('formAction').value === 'add' ? 'Add this officer?' : 'Update this officer?')">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" id="formName" required>
            </div>

            <div class="form-group">
                <label>Position</label>
                <select name="position" id="formPosition" required>
                    <option value="">-- Select --</option>
                    <option value="Mayor">Mayor</option>
                    <option value="Vice Mayor">Vice Mayor</option>
                    <option value="Treasurer">Treasurer</option>
                    <option value="Auditor">Auditor</option>
                    <option value="Business Manager">Business Manager</option>
                </select>
            </div>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" id="formUsername" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" id="formEmail" required>
            </div>

            <div class="form-group">
                <label>School Year</label>
                <select name="school_year" id="formSchoolYear" required>
                    <option value="">-- Select School Year --</option>
                    <?php foreach ($schoolYears as $yr): ?>
                        <option value="<?= htmlspecialchars($yr) ?>"><?= htmlspecialchars($yr) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label id="passwordLabel">Password</label>
                <input type="password" name="password" id="formPassword">
                <p class="password-note" id="passwordNote" style="display:none;">Only fill if you want to change password.</p>
            </div>

            <button type="submit" class="btn-submit" style="width:100%;"><i class="fas fa-save"></i> <span id="submitBtnText">Add Officer</span></button>
        </form>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add New Officer';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';
        document.getElementById('officerForm').reset();
        document.getElementById('formPassword').required = true;
        document.getElementById('passwordLabel').textContent = 'Password';
        document.getElementById('passwordNote').style.display = 'none';
        document.getElementById('submitBtnText').textContent = 'Add Officer';
        document.getElementById('officerModal').classList.add('active');
    }

    function openEditModal(officer) {
        document.getElementById('modalTitle').textContent = 'Edit Officer';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('formId').value = officer.id;
        document.getElementById('formName').value = officer.name;
        document.getElementById('formPosition').value = officer.position;
        document.getElementById('formUsername').value = officer.username;
        document.getElementById('formEmail').value = officer.email || '';
        document.getElementById('formSchoolYear').value = officer.school_year || '';
        document.getElementById('formPassword').value = '';
        document.getElementById('formPassword').required = false;
        document.getElementById('passwordLabel').textContent = 'New Password (leave blank to keep current)';
        document.getElementById('passwordNote').style.display = 'block';
        document.getElementById('submitBtnText').textContent = 'Update Officer';
        document.getElementById('officerModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('officerModal').classList.remove('active');
    }

    document.getElementById('officerModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.getElementById('userMenu').addEventListener('click', function (e) {
        e.stopPropagation();
        this.classList.toggle('show');
    });
    window.addEventListener('click', function () {
        document.getElementById('userMenu').classList.remove('show');
    });
</script>
</body>
</html>