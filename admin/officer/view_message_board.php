<?php
// officer/view_message_board.php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireOfficer();

$message = '';
$error = '';
$currentPic = getCurrentUserProfilePic($pdo);

// Sanitize category (only 'all' or digits)
$selectedCategory = $_GET['category'] ?? 'all';
if ($selectedCategory !== 'all' && !ctype_digit((string)$selectedCategory)) {
    $selectedCategory = 'all';
}

// ============ PAGINATION ============
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 10);
$allowedPerPage = [10, 25, 50, 100];
if (!in_array($perPage, $allowedPerPage)) $perPage = 10;

// ============ HANDLE POST ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    if (($_POST['action'] ?? '') === 'reply') {
        $reply = trim($_POST['reply'] ?? '');

        // Officers can only reply to public inquiries
        $chk = $pdo->prepare("SELECT id FROM inquiries WHERE id = ? AND is_public = 1");
        $chk->execute([$id]);

        if ($reply === '') {
            $error = "Reply cannot be empty.";
        } elseif (!$chk->fetch()) {
            $error = "Inquiry not found.";
        } else {
            // Every reply is its own row, so nobody can overwrite someone else's reply
            $stmt = $pdo->prepare(
                "INSERT INTO inquiry_replies (inquiry_id, reply, replied_by_name, replied_by_role)
                 VALUES (?, ?, ?, 'officer')"
            );
            $stmt->execute([$id, $reply, $_SESSION['officer_name']]);
            $message = "Reply added!";
        }
    }

    // Go back to the same category / page / inquiry
    $back = [
        'category' => $selectedCategory,
        'per_page' => $perPage,
        'page'     => $page,
    ];
    if ($id) $back['selected'] = $id;
    if ($error) $back['error'] = $error; else $back['msg'] = $message;

    header('Location: view_message_board.php?' . http_build_query($back));
    exit;
}

if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['error'])) $error = $_GET['error'];

// Build WHERE (public only)
$whereSQL = "WHERE i.is_public = 1";
$whereParams = [];
if ($selectedCategory !== 'all') {
    $whereSQL .= " AND i.category_id = ?";
    $whereParams[] = (int)$selectedCategory;
}

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM inquiries i $whereSQL");
$countStmt->execute($whereParams);
$totalInquiries = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalInquiries / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$showingFrom = $totalInquiries > 0 ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalInquiries);

// Fetch paginated
$inqSQL = "SELECT i.*, ic.name AS category_name,
                  (SELECT COUNT(*) FROM inquiry_replies r WHERE r.inquiry_id = i.id) AS reply_count
           FROM inquiries i
           JOIN inquiry_categories ic ON i.category_id = ic.id
           $whereSQL
           ORDER BY i.created_at DESC
           LIMIT $perPage OFFSET $offset";
$inqStmt = $pdo->prepare($inqSQL);
$inqStmt->execute($whereParams);
$inquiries = $inqStmt->fetchAll();

// Category tabs data (pending = public inquiries with no replies yet)
$catStmt = $pdo->query(
    "SELECT ic.id, ic.name,
            COUNT(i.id) AS total,
            SUM(CASE WHEN i.id IS NOT NULL AND NOT EXISTS
                (SELECT 1 FROM inquiry_replies r WHERE r.inquiry_id = i.id)
                THEN 1 ELSE 0 END) AS pending
     FROM inquiry_categories ic
     LEFT JOIN inquiries i ON i.category_id = ic.id AND i.is_public = 1
     GROUP BY ic.id, ic.name
     ORDER BY ic.name ASC"
);
$categories = $catStmt->fetchAll();

$totalCount = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE is_public = 1")->fetchColumn();
$totalPending = $pdo->query(
    "SELECT COUNT(*) FROM inquiries i
     WHERE i.is_public = 1
       AND NOT EXISTS (SELECT 1 FROM inquiry_replies r WHERE r.inquiry_id = i.id)"
)->fetchColumn();

$selectedId = isset($_GET['selected']) ? (int)$_GET['selected'] : null;
if (!$selectedId && count($inquiries) > 0) {
    $selectedId = (int)$inquiries[0]['id'];
}

$selectedInquiry = null;
foreach ($inquiries as $inq) {
    if ($inq['id'] == $selectedId) {
        $selectedInquiry = $inq;
        break;
    }
}

// If selected not in current page, fetch separately
if (!$selectedInquiry && $selectedId) {
    $selStmt = $pdo->prepare(
        "SELECT i.*, ic.name AS category_name
         FROM inquiries i
         JOIN inquiry_categories ic ON i.category_id = ic.id
         WHERE i.id = ? AND i.is_public = 1"
    );
    $selStmt->execute([$selectedId]);
    $selectedInquiry = $selStmt->fetch();
}

// Replies of the selected inquiry (oldest first = thread order)
$replies = [];
if ($selectedInquiry) {
    $rs = $pdo->prepare("SELECT * FROM inquiry_replies WHERE inquiry_id = ? ORDER BY created_at ASC, id ASC");
    $rs->execute([$selectedInquiry['id']]);
    $replies = $rs->fetchAll();
}

function buildOficerMBUrl($pageNum, $category, $perPage, $selectedId = null) {
    $params = ['category' => $category, 'per_page' => $perPage, 'page' => $pageNum];
    if ($selectedId) $params['selected'] = $selectedId;
    return 'view_message_board.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Message Board - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .category-tabs {
            display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; padding: 5px 0;
        }
        .cat-tab {
            background: white; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 10px 18px; font-weight: 600; font-size: 13px; cursor: pointer;
            color: #555; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s;
        }
        .cat-tab:hover { border-color: #1a3c5e; color: #1a3c5e; transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .cat-tab.active { background: #1a3c5e; color: white; border-color: #1a3c5e; }
        .cat-tab .badge { background: rgba(0,0,0,0.08); color: inherit; padding: 2px 8px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .cat-tab.active .badge { background: rgba(255,255,255,0.25); }
        .cat-tab .pending-dot { width: 8px; height: 8px; border-radius: 50%; background: #f39c12; display: inline-block; }
        .cat-tab.active .pending-dot { background: #ffcc66; }

        .inquiry-layout {
            display: grid; grid-template-columns: 380px 1fr; gap: 20px;
            min-height: 500px;
        }
        .inquiry-list {
            background: white; border-radius: 12px; border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03); overflow: hidden;
            display: flex; flex-direction: column;
        }
        .list-header { padding: 15px 20px; border-bottom: 1px solid #f0f0f0; background: #f8f9fa; }
        .list-header h3 { margin: 0; color: #1a3c5e; font-size: 14px; display: flex; justify-content: space-between; align-items: center; }
        .list-header .count { background: #1a3c5e; color: white; padding: 2px 10px; border-radius: 50px; font-size: 11px; font-weight: 700; }

        /* ===== PAGINATION BAR ===== */
        .pag-bar {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f0f0;
            background: #fafbfc;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: #64748b;
            flex-wrap: wrap;
        }
        .pag-bar-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        .pag-bar select {
            padding: 6px 28px 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 600;
            color: #1a3c5e;
            background-color: white;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 6px center;
            background-size: 13px;
            transition: all 0.15s;
            min-width: 60px;
        }
        .pag-bar select:hover { border-color: #2980b9; }
        .pag-bar select:focus {
            outline: none;
            border-color: #1a3c5e;
            box-shadow: 0 0 0 3px rgba(26, 60, 94, 0.1);
        }
        .pag-info { font-size: 11.5px; color: #64748b; font-weight: 500; }
        .pag-info strong { color: #1a3c5e; font-weight: 700; }

        /* ===== PAGINATION CONTROLS ===== */
        .pagination {
            padding: 12px 16px;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 4px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            background: #fafbfc;
        }
        .pag-btn {
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            color: #475569;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }
        .pag-btn:hover { background: #e8f0f8; border-color: #2980b9; color: #1a3c5e; }
        .pag-btn.active { background: #1a3c5e; color: white; border-color: #1a3c5e; cursor: default; }
        .pag-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
        .pag-dots { padding: 0 4px; color: #94a3b8; font-weight: 700; font-size: 12px; }

        .list-scroll { max-height: 550px; overflow-y: auto; }
        .list-item {
            padding: 12px 20px; border-bottom: 1px solid #f5f5f5; cursor: pointer;
            text-decoration: none; display: block; color: inherit; transition: background 0.15s;
        }
        .list-item:hover { background: #f9fafb; }
        .list-item.active { background: #e8f0f8; border-left: 4px solid #1a3c5e; padding-left: 16px; }
        .list-item-header { display: flex; align-items: center; gap: 8px; margin-bottom: 5px; }
        .list-item-header .avatar {
            width: 30px; height: 30px; border-radius: 50%; background: #e8f0f8;
            color: #1a3c5e; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 11px; flex-shrink: 0;
        }
        .list-item-header .name {
            font-weight: 700; color: #1a3c5e; font-size: 13px; flex: 1;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .list-item-header .status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .status-pending { background: #f39c12; }
        .status-replied { background: #27ae60; }
        .list-item-preview {
            font-size: 12px; color: #666; overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap; margin-bottom: 4px;
        }
        .list-item-footer { display: flex; justify-content: space-between; align-items: center; font-size: 10px; color: #999; }
        .list-item-footer .cat { color: #2980b9; font-weight: 600; }

        .inquiry-detail {
            background: white; border-radius: 12px; border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03); overflow: hidden;
            display: flex; flex-direction: column;
        }
        .detail-header {
            padding: 20px 25px; border-bottom: 1px solid #f0f0f0;
            background: linear-gradient(135deg, #2980b9, #1a3c5e); color: white;
        }
        .detail-header-top { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
        .detail-header .avatar-lg {
            width: 44px; height: 44px; border-radius: 50%; background: rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 16px; flex-shrink: 0;
        }
        .detail-header .student-info { flex: 1; }
        .detail-header .student-info .name { font-size: 16px; font-weight: 700; }
        .detail-header .student-info .email { font-size: 12px; opacity: 0.85; margin-top: 2px; }
        .detail-header-tags { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 5px; }
        .detail-header .tag {
            background: rgba(255,255,255,0.2); color: white; padding: 3px 10px;
            border-radius: 50px; font-size: 11px; font-weight: 600;
        }
        .detail-body { flex: 1; padding: 25px; overflow-y: auto; }
        .detail-section { margin-bottom: 25px; }
        .detail-section-title {
            font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;
            color: #888; font-weight: 700; margin-bottom: 10px;
            display: flex; align-items: center; gap: 6px;
        }
        .message-box {
            background: #f8f9fa; border-left: 3px solid #2980b9; padding: 15px 18px;
            border-radius: 6px; font-size: 14px; line-height: 1.6; color: #333;
        }
        .reply-box {
            background: #f0f9f4; border-left: 3px solid #27ae60; padding: 15px 18px;
            border-radius: 6px; font-size: 14px; line-height: 1.6; color: #333;
        }
        .reply-box + .reply-box { margin-top: 10px; }
        .reply-box .who { font-size: 12px; font-weight: 700; color: #1a3c5e; margin-bottom: 5px; display: block; }
        .reply-box .when { font-size: 11px; color: #888; margin-top: 6px; display: block; }

        .detail-footer { padding: 20px 25px; border-top: 1px solid #f0f0f0; background: #fafbfc; }
        .reply-form-row { display: flex; gap: 8px; align-items: flex-end; }
        .reply-form-row textarea {
            flex: 1; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px;
            font-size: 13px; font-family: inherit; resize: vertical; min-height: 40px;
            max-height: 120px; box-sizing: border-box; background: white;
        }
        .reply-form-row textarea:focus { outline: none; border-color: #1a3c5e; box-shadow: 0 0 0 2px rgba(26,60,94,0.1); }
        .btn-action {
            width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 14px;
            transition: all 0.2s;
        }
        .btn-primary-action { background: #1a3c5e; color: white; }
        .btn-primary-action:hover { background: #2c5282; }

        .empty-detail {
            flex: 1; display: flex; flex-direction: column; align-items: center;
            justify-content: center; color: #999; padding: 60px 20px;
        }
        .empty-detail i { font-size: 60px; color: #ddd; margin-bottom: 15px; }

        .alert {
            padding: 10px 18px; border-radius: 8px; margin-bottom: 18px;
            font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px;
        }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #27ae60; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #e74c3c; }

        @media (max-width: 900px) {
            .inquiry-layout { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Message Board';
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

        <div class="category-tabs">
            <a href="?category=all&per_page=<?= $perPage ?>" class="cat-tab <?= $selectedCategory === 'all' ? 'active' : '' ?>">
                <i class="fas fa-inbox"></i> All
                <span class="badge"><?= $totalCount ?></span>
                <?php if ($totalPending > 0): ?><span class="pending-dot"></span><?php endif; ?>
            </a>
            <?php foreach ($categories as $cat):
                $isActive = ($selectedCategory == $cat['id']);
            ?>
                <a href="?category=<?= $cat['id'] ?>&per_page=<?= $perPage ?>" class="cat-tab <?= $isActive ? 'active' : '' ?>">
                    <i class="fas fa-tag"></i> <?= htmlspecialchars($cat['name']) ?>
                    <span class="badge"><?= $cat['total'] ?></span>
                    <?php if ($cat['pending'] > 0): ?><span class="pending-dot"></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (count($inquiries) === 0 && !$selectedInquiry): ?>
            <div class="inquiry-detail">
                <div class="empty-detail">
                    <i class="fas fa-inbox"></i>
                    <h3>No inquiries in this category</h3>
                    <p>Student questions will appear here.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="inquiry-layout">

                <div class="inquiry-list">
                    <div class="list-header">
                        <h3>
                            <span><i class="fas fa-inbox"></i> Inquiries</span>
                            <span class="count"><?= $totalInquiries ?></span>
                        </h3>
                    </div>

                    <div class="pag-bar">
                        <div class="pag-bar-left">
                            <label style="font-weight:600;">Show</label>
                            <select onchange="changePerPage(this.value)">
                                <?php foreach ($allowedPerPage as $pp): ?>
                                    <option value="<?= $pp ?>" <?= $pp === $perPage ? 'selected' : '' ?>><?= $pp ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span>entries</span>
                        </div>
                        <div class="pag-info">
                            Showing <strong><?= $showingFrom ?>–<?= $showingTo ?></strong> of <strong><?= $totalInquiries ?></strong>
                        </div>
                    </div>

                    <div class="list-scroll">
                        <?php foreach ($inquiries as $inq):
                            $name = $inq['student_name'] ?: 'Anonymous';
                            $initials = 'AN';
                            if ($inq['student_name']) {
                                $parts = explode(' ', $inq['student_name']);
                                $initials = strtoupper(mb_substr($parts[0], 0, 1));
                                if (count($parts) > 1) $initials .= strtoupper(mb_substr(end($parts), 0, 1));
                            }
                            $isActive = ($inq['id'] == $selectedId);
                            $statusClass = ((int)$inq['reply_count'] > 0) ? 'status-replied' : 'status-pending';
                        ?>
                            <a href="?category=<?= htmlspecialchars($selectedCategory) ?>&per_page=<?= $perPage ?>&page=<?= $page ?>&selected=<?= (int)$inq['id'] ?>" 
                               class="list-item <?= $isActive ? 'active' : '' ?>">
                                <div class="list-item-header">
                                    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
                                    <div class="name"><?= htmlspecialchars($name) ?></div>
                                    <span class="status-dot <?= $statusClass ?>"></span>
                                </div>
                                <div class="list-item-preview">
                                    <?= htmlspecialchars(mb_substr($inq['message'], 0, 60)) ?><?= mb_strlen($inq['message']) > 60 ? '...' : '' ?>
                                </div>
                                <div class="list-item-footer">
                                    <span class="cat"><?= htmlspecialchars($inq['category_name']) ?></span>
                                    <span><?= date('M d', strtotime($inq['created_at'])) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <a href="<?= $page > 1 ? buildOficerMBUrl($page - 1, $selectedCategory, $perPage) : '#' ?>" 
                           class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);

                        if ($start > 1) {
                            echo '<a href="' . buildOficerMBUrl(1, $selectedCategory, $perPage) . '" class="pag-btn">1</a>';
                            if ($start > 2) echo '<span class="pag-dots">…</span>';
                        }

                        for ($i = $start; $i <= $end; $i++) {
                            $cls = ($i === $page) ? 'pag-btn active' : 'pag-btn';
                            echo '<a href="' . buildOficerMBUrl($i, $selectedCategory, $perPage) . '" class="' . $cls . '">' . $i . '</a>';
                        }

                        if ($end < $totalPages) {
                            if ($end < $totalPages - 1) echo '<span class="pag-dots">…</span>';
                            echo '<a href="' . buildOficerMBUrl($totalPages, $selectedCategory, $perPage) . '" class="pag-btn">' . $totalPages . '</a>';
                        }
                        ?>

                        <a href="<?= $page < $totalPages ? buildOficerMBUrl($page + 1, $selectedCategory, $perPage) : '#' ?>" 
                           class="pag-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($selectedInquiry):
                    $name = $selectedInquiry['student_name'] ?: 'Anonymous';
                    $initials = 'AN';
                    if ($selectedInquiry['student_name']) {
                        $parts = explode(' ', $selectedInquiry['student_name']);
                        $initials = strtoupper(mb_substr($parts[0], 0, 1));
                        if (count($parts) > 1) $initials .= strtoupper(mb_substr(end($parts), 0, 1));
                    }
                ?>
                <div class="inquiry-detail">
                    <div class="detail-header">
                        <div class="detail-header-top">
                            <div class="avatar-lg"><?= htmlspecialchars($initials) ?></div>
                            <div class="student-info">
                                <div class="name"><?= htmlspecialchars($name) ?></div>
                                <?php if ($selectedInquiry['student_email']): ?>
                                    <div class="email"><i class="fas fa-envelope"></i> <?= htmlspecialchars($selectedInquiry['student_email']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-header-tags">
                            <span class="tag">#<?= (int)$selectedInquiry['id'] ?></span>
                            <span class="tag"><?= htmlspecialchars($selectedInquiry['category_name']) ?></span>
                            <span class="tag"><i class="fas fa-clock"></i> <?= date('M d, Y h:i A', strtotime($selectedInquiry['created_at'])) ?></span>
                        </div>
                    </div>

                    <div class="detail-body">
                        <div class="detail-section">
                            <div class="detail-section-title"><i class="fas fa-comment"></i> Student Message</div>
                            <div class="message-box"><?= nl2br(htmlspecialchars($selectedInquiry['message'])) ?></div>
                        </div>

                        <?php if ($replies): ?>
                            <div class="detail-section">
                                <div class="detail-section-title"><i class="fas fa-reply"></i> Replies (<?= count($replies) ?>)</div>
                                <?php foreach ($replies as $r): ?>
                                    <div class="reply-box">
                                        <span class="who">
                                            <?= htmlspecialchars($r['replied_by_name']) ?>
                                            (<?= htmlspecialchars(ucfirst($r['replied_by_role'])) ?>)
                                        </span>
                                        <?= nl2br(htmlspecialchars($r['reply'])) ?>
                                        <span class="when"><?= date('M d, Y h:i A', strtotime($r['created_at'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="detail-footer">
                        <form method="POST">
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="id" value="<?= (int)$selectedInquiry['id'] ?>">
                            <div class="reply-form-row">
                                <textarea name="reply" placeholder="Add a reply as officer..." required></textarea>
                                <button type="submit" class="btn-action btn-primary-action" title="Send Reply">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    function changePerPage(val) {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', val);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

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