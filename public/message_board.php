<?php
// public/message_board.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$sysSettings = getSettings($pdo);
$sysLogoUrl = '../' . ltrim($sysSettings['logo_path'], '/');
$sysLogoPath = realpath(__DIR__ . '/../') . '/' . $sysSettings['logo_path'];
$themeColor = $sysSettings['primary_color'] ?? '#1a3c5e';

$message = '';
$error = '';

// Handle form submission (students can only ask; they cannot reply)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_name = trim($_POST['student_name'] ?? '');
    $student_email = trim($_POST['student_email'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $question = trim($_POST['message'] ?? '');

    if ($question === '') {
        $error = "Please enter your question.";
    } elseif ($category_id <= 0) {
        $error = "Please select a category.";
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO inquiries (student_name, student_email, message, category_id, is_public)
             VALUES (?, ?, ?, ?, 1)"
        );
        $stmt->execute([$student_name, $student_email, $question, $category_id]);

        // PRG pattern — redirect to prevent refresh-resubmit
        header('Location: message_board.php?msg=' . urlencode('Your question has been submitted!'));
        exit;
    }
}

if (isset($_GET['msg'])) $message = $_GET['msg'];

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

// Build WHERE
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
$inqSQL = "SELECT i.*, ic.name AS category_name
           FROM inquiries i
           JOIN inquiry_categories ic ON i.category_id = ic.id
           $whereSQL
           ORDER BY i.created_at DESC
           LIMIT $perPage OFFSET $offset";
$inqStmt = $pdo->prepare($inqSQL);
$inqStmt->execute($whereParams);
$inquiries = $inqStmt->fetchAll();

// Fetch all replies for the inquiries on this page in ONE query
$repliesByInquiry = [];
if ($inquiries) {
    $ids = array_column($inquiries, 'id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rs = $pdo->prepare("SELECT * FROM inquiry_replies WHERE inquiry_id IN ($ph) ORDER BY created_at ASC, id ASC");
    $rs->execute($ids);
    foreach ($rs->fetchAll() as $r) {
        $repliesByInquiry[$r['inquiry_id']][] = $r;
    }
}

// Categories for form + tabs
$formCategories = $pdo->query("SELECT id, name FROM inquiry_categories ORDER BY name")->fetchAll();

$catStmt = $pdo->query(
    "SELECT ic.id, ic.name, COUNT(i.id) AS total
     FROM inquiry_categories ic
     LEFT JOIN inquiries i ON i.category_id = ic.id AND i.is_public = 1
     GROUP BY ic.id, ic.name
     ORDER BY ic.name ASC"
);
$categories = $catStmt->fetchAll();

$totalCount = $pdo->query("SELECT COUNT(*) FROM inquiries WHERE is_public = 1")->fetchColumn();

// Helper: build URL
function buildMBUrl($pageNum, $category, $perPage) {
    $params = ['category' => $category, 'per_page' => $perPage, 'page' => $pageNum];
    return 'message_board.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Message Board - <?= htmlspecialchars($sysSettings['org_short_name']) ?></title>
    <link rel="stylesheet" href="../assets/css/public.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --theme-primary: <?= htmlspecialchars($themeColor) ?>;
        }

        .page-title {
            color: var(--theme-primary);
            margin-bottom: 8px;
            font-size: 26px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 25px;
        }

        /* Category Tabs */
        .category-tabs {
            display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .cat-tab {
            background: white; border: 1px solid #e2e8f0; border-radius: 8px;
            padding: 10px 18px; font-weight: 600; font-size: 13px; cursor: pointer;
            color: #555; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s;
        }
        .cat-tab:hover { border-color: var(--theme-primary); color: var(--theme-primary); transform: translateY(-1px); box-shadow: 0 2px 6px rgba(0,0,0,0.06); }
        .cat-tab.active { background: var(--theme-primary); color: white; border-color: var(--theme-primary); }
        .cat-tab .badge { background: rgba(0,0,0,0.08); color: inherit; padding: 2px 8px; border-radius: 50px; font-size: 11px; font-weight: 700; }
        .cat-tab.active .badge { background: rgba(255,255,255,0.25); }

        /* Pagination bar */
        .mb-pag-bar {
            background: white;
            border-radius: 12px 12px 0 0;
            border: 1px solid #e2e8f0;
            border-bottom: none;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            font-size: 12.5px;
            color: #64748b;
            flex-wrap: wrap;
        }
        .mb-pag-left {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .mb-pag-bar select {
            padding: 6px 28px 6px 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--theme-primary);
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
        .mb-pag-bar select:hover { border-color: var(--theme-primary); }
        .mb-pag-bar select:focus {
            outline: none;
            border-color: var(--theme-primary);
            box-shadow: 0 0 0 3px rgba(26, 60, 94, 0.1);
        }
        .mb-pag-info { font-size: 11.5px; color: #64748b; font-weight: 500; }
        .mb-pag-info strong { color: var(--theme-primary); font-weight: 700; }

        /* Q&A List */
        .qa-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .qa-card {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.03);
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .qa-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .qa-question {
            padding: 18px 22px;
            cursor: pointer;
            display: flex;
            gap: 15px;
            align-items: flex-start;
        }
        .qa-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: #e8f0f8;
            color: var(--theme-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 15px;
            flex-shrink: 0;
        }
        .qa-content { flex: 1; min-width: 0; }
        .qa-meta {
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
            margin-bottom: 6px; font-size: 12px;
        }
        .qa-meta .cat-tag { background: #e8f0f8; color: var(--theme-primary); padding: 2px 10px; border-radius: 50px; font-weight: 600; }
        .qa-meta .date { color: #999; }
        .qa-meta .student { font-weight: 600; color: #555; }
        .qa-message { font-size: 14px; color: #333; line-height: 1.5; word-wrap: break-word; }
        .qa-toggle {
            color: #999; font-size: 14px; margin-left: 10px;
            flex-shrink: 0; transition: transform 0.3s;
        }
        .qa-card.open .qa-toggle { transform: rotate(180deg); }

        .qa-answer {
            display: none;
            padding: 0 22px 20px 79px;
            border-top: 1px solid #f0f0f0;
        }
        .qa-card.open .qa-answer { display: block; }
        .qa-answer-inner {
            background: #f0f9f4;
            border-left: 4px solid #27ae60;
            padding: 15px 18px;
            border-radius: 6px;
            margin-top: 15px;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
        }
        .qa-answer-inner .who {
            font-weight: 700; color: var(--theme-primary); font-size: 13px;
            display: block; margin-bottom: 6px;
        }
        .qa-answer-inner .who i { color: #27ae60; margin-right: 5px; }
        .qa-answer-inner .when { font-size: 11px; color: #888; margin-top: 8px; display: block; }
        .qa-no-answer { color: #999; font-style: italic; font-size: 13px; padding-top: 15px; }

        /* Pagination controls */
        .mb-pagination {
            padding: 15px 20px;
            background: white;
            border-radius: 0 0 12px 12px;
            border: 1px solid #e2e8f0;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 4px;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            margin-top: -15px;
        }
        .pag-btn {
            min-width: 34px;
            height: 34px;
            padding: 0 8px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            color: #475569;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s;
        }
        .pag-btn:hover { background: #e8f0f8; border-color: var(--theme-primary); color: var(--theme-primary); }
        .pag-btn.active { background: var(--theme-primary); color: white; border-color: var(--theme-primary); cursor: default; }
        .pag-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
        .pag-dots { padding: 0 4px; color: #94a3b8; font-weight: 700; font-size: 12px; }

        /* Floating Ask Button */
        .ask-float-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--theme-primary);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            transition: transform 0.2s, box-shadow 0.2s;
            z-index: 999;
        }
        .ask-float-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.35);
        }
        .ask-float-btn i { font-size: 18px; }

        /* Modal */
        .ask-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .ask-modal-overlay.active { display: flex; }
        .ask-modal {
            background: white;
            border-radius: 14px;
            width: 500px;
            max-width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.25);
            animation: modalSlideUp 0.25s ease-out;
        }
        @keyframes modalSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .ask-modal-header {
            background: var(--theme-primary);
            color: white;
            padding: 22px 28px;
            border-radius: 14px 14px 0 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ask-modal-header h3 { margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px; }
        .ask-modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .ask-modal-close:hover { background: rgba(255,255,255,0.35); }

        .ask-modal-body { padding: 25px 28px; }
        .ask-modal-body label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #333;
            margin-bottom: 6px;
        }
        .ask-modal-body input,
        .ask-modal-body select,
        .ask-modal-body textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            margin-bottom: 16px;
            box-sizing: border-box;
            background: #fafbfd;
            transition: border 0.2s, box-shadow 0.2s;
        }
        .ask-modal-body input:focus,
        .ask-modal-body select:focus,
        .ask-modal-body textarea:focus {
            outline: none;
            border-color: var(--theme-primary);
            box-shadow: 0 0 0 3px rgba(26,60,94,0.1);
            background: white;
        }
        .ask-modal-body textarea { resize: vertical; min-height: 110px; }
        .btn-ask-submit {
            background: var(--theme-primary);
            color: white;
            border: none;
            padding: 13px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .btn-ask-submit:hover { opacity: 0.9; }

        /* Alerts */
        .alert {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d4edda; color: #155724; border-left: 4px solid #27ae60; }
        .alert-error { background: #f8d7da; color: #721c24; border-left: 4px solid #e74c3c; }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }
        .empty-state i { font-size: 60px; color: #ddd; margin-bottom: 15px; }

        @media (max-width: 600px) {
            .ask-float-btn { bottom: 20px; right: 20px; padding: 14px 20px; font-size: 14px; }
            .qa-answer { padding-left: 22px; }
        }
    </style>
</head>
<body>

<header class="public-header" style="background-color: var(--theme-primary);">
    <div class="logo-area">
        <?php if (file_exists($sysLogoPath)): ?>
            <img src="<?= $sysLogoUrl ?>" alt="Logo">
        <?php else: ?>
            <img src="../assets/images/logo.png" alt="Logo">
        <?php endif; ?>
        <span><?= htmlspecialchars($sysSettings['org_short_name']) ?></span>
    </div>
    <ul class="nav-links">
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="message_board.php"><i class="fas fa-comments"></i> Message Board</a></li>
        <?php if (isset($_SESSION['adviser_id']) || isset($_SESSION['officer_id'])): ?>
            <li><a href="../admin/<?= isset($_SESSION['adviser_id']) ? 'adviser' : 'officer' ?>/dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li><a href="../admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        <?php else: ?>
            <li><a href="../login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
        <?php endif; ?>
    </ul>
</header>

<div class="public-content">

    <h1 class="page-title"><i class="fas fa-comments"></i> Student Message Board</h1>
    <p class="page-subtitle">Browse questions from fellow students. Click a question to see the official answers.</p>

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Category Tabs -->
    <div class="category-tabs">
        <a href="?category=all&per_page=<?= $perPage ?>" class="cat-tab <?= $selectedCategory === 'all' ? 'active' : '' ?>">
            <i class="fas fa-inbox"></i> All
            <span class="badge"><?= $totalCount ?></span>
        </a>
        <?php foreach ($categories as $cat):
            $isActive = ($selectedCategory == $cat['id']);
        ?>
            <a href="?category=<?= $cat['id'] ?>&per_page=<?= $perPage ?>" class="cat-tab <?= $isActive ? 'active' : '' ?>">
                <i class="fas fa-tag"></i> <?= htmlspecialchars($cat['name']) ?>
                <span class="badge"><?= $cat['total'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($totalInquiries > 0): ?>
    <!-- Pagination Bar (top) -->
    <div class="mb-pag-bar">
        <div class="mb-pag-left">
            <label style="font-weight:600;">Show</label>
            <select onchange="changePerPage(this.value)">
                <?php foreach ($allowedPerPage as $pp): ?>
                    <option value="<?= $pp ?>" <?= $pp === $perPage ? 'selected' : '' ?>><?= $pp ?></option>
                <?php endforeach; ?>
            </select>
            <span>entries</span>
        </div>
        <div class="mb-pag-info">
            Showing <strong><?= $showingFrom ?>–<?= $showingTo ?></strong> of <strong><?= $totalInquiries ?></strong>
        </div>
    </div>
    <?php endif; ?>

    <!-- Q&A List -->
    <div class="qa-list">
        <?php if (count($inquiries) === 0): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No questions yet</h3>
                <p>Be the first to ask!</p>
            </div>
        <?php else: ?>
            <?php foreach ($inquiries as $inq):
                $name = $inq['student_name'] ?: 'Anonymous';
                $initials = 'AN';
                if ($inq['student_name']) {
                    $parts = explode(' ', $inq['student_name']);
                    $initials = strtoupper(mb_substr($parts[0], 0, 1));
                    if (count($parts) > 1) $initials .= strtoupper(mb_substr(end($parts), 0, 1));
                }
            ?>
                <div class="qa-card" id="qa-<?= (int)$inq['id'] ?>">
                    <div class="qa-question" onclick="toggleAnswer(<?= (int)$inq['id'] ?>)">
                        <div class="qa-avatar"><?= htmlspecialchars($initials) ?></div>
                        <div class="qa-content">
                            <div class="qa-meta">
                                <span class="student"><?= htmlspecialchars($name) ?></span>
                                <span class="cat-tag"><?= htmlspecialchars($inq['category_name']) ?></span>
                                <span class="date">
                                    <i class="fas fa-clock"></i> <?= date('M d, Y', strtotime($inq['created_at'])) ?>
                                </span>
                            </div>
                            <div class="qa-message"><?= nl2br(htmlspecialchars($inq['message'])) ?></div>
                        </div>
                        <i class="fas fa-chevron-down qa-toggle"></i>
                    </div>

                    <div class="qa-answer">
                        <?php if (!empty($repliesByInquiry[$inq['id']])): ?>
                            <?php foreach ($repliesByInquiry[$inq['id']] as $r): ?>
                                <div class="qa-answer-inner">
                                    <span class="who">
                                        <i class="fas fa-reply"></i>
                                        <?= htmlspecialchars($r['replied_by_name']) ?>
                                        (<?= htmlspecialchars(ucfirst($r['replied_by_role'])) ?>)
                                    </span>
                                    <?= nl2br(htmlspecialchars($r['reply'])) ?>
                                    <span class="when">
                                        <i class="fas fa-clock"></i> <?= date('M d, Y h:i A', strtotime($r['created_at'])) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="qa-no-answer">
                                <i class="fas fa-hourglass-half"></i> No official reply yet.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination Controls (bottom) -->
    <?php if ($totalPages > 1): ?>
    <div class="mb-pagination">
        <a href="<?= $page > 1 ? buildMBUrl($page - 1, $selectedCategory, $perPage) : '#' ?>"
           class="pag-btn <?= $page <= 1 ? 'disabled' : '' ?>">
            <i class="fas fa-chevron-left"></i>
        </a>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);

        if ($start > 1) {
            echo '<a href="' . buildMBUrl(1, $selectedCategory, $perPage) . '" class="pag-btn">1</a>';
            if ($start > 2) echo '<span class="pag-dots">…</span>';
        }

        for ($i = $start; $i <= $end; $i++) {
            $cls = ($i === $page) ? 'pag-btn active' : 'pag-btn';
            echo '<a href="' . buildMBUrl($i, $selectedCategory, $perPage) . '" class="' . $cls . '">' . $i . '</a>';
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) echo '<span class="pag-dots">…</span>';
            echo '<a href="' . buildMBUrl($totalPages, $selectedCategory, $perPage) . '" class="pag-btn">' . $totalPages . '</a>';
        }
        ?>

        <a href="<?= $page < $totalPages ? buildMBUrl($page + 1, $selectedCategory, $perPage) : '#' ?>"
           class="pag-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    <?php endif; ?>

</div>

<!-- Floating Ask Button -->
<button class="ask-float-btn" onclick="openAskModal()">
    <i class="fas fa-plus-circle"></i>
    Ask a Question
</button>

<!-- Ask Modal -->
<div class="ask-modal-overlay" id="askModal">
    <div class="ask-modal">
        <div class="ask-modal-header">
            <h3><i class="fas fa-question-circle"></i> Ask a Question</h3>
            <button class="ask-modal-close" onclick="closeAskModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="ask-modal-body">
            <form method="POST">
                <label>Your Name (optional)</label>
                <input type="text" name="student_name" placeholder="Anonymous">

                <label>Your Email (optional)</label>
                <input type="email" name="student_email" placeholder="email@example.com">

                <label>Category <span style="color:red;">*</span></label>
                <select name="category_id" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($formCategories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Your Question <span style="color:red;">*</span></label>
                <textarea name="message" placeholder="Type your question or concern..." required></textarea>

                <button type="submit" class="btn-ask-submit">
                    <i class="fas fa-paper-plane"></i> Submit Question
                </button>
            </form>
        </div>
    </div>
</div>

<footer class="public-footer">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($sysSettings['org_name']) ?>. All rights reserved.
</footer>

<script>
    function toggleAnswer(id) {
        const card = document.getElementById('qa-' + id);
        card.classList.toggle('open');
    }

    function openAskModal() {
        document.getElementById('askModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeAskModal() {
        document.getElementById('askModal').classList.remove('active');
        document.body.style.overflow = '';
    }

    document.getElementById('askModal').addEventListener('click', function(e) {
        if (e.target === this) closeAskModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeAskModal();
    });

    function changePerPage(val) {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', val);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    <?php if ($error): ?>
        openAskModal();
    <?php endif; ?>
</script>
</body>
</html>