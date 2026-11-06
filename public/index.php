<?php
// public/index.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Dynamic Branding
$sysSettings = getSettings($pdo);
$sysLogoUrl = '../' . ltrim($sysSettings['logo_path'], '/');
$sysLogoPath = realpath(__DIR__ . '/../') . '/' . $sysSettings['logo_path'];
$themeColor = $sysSettings['primary_color'] ?? '#1a3c5e';

// Get filters
$selectedSchoolYear = $_GET['school_year'] ?? '';
$selectedFundSource = $_GET['fund_source'] ?? '';
$fundFilterActive = ($selectedFundSource !== '');

// Build WHERE clauses
$fundWhere = "WHERE f.status = 'approved'";
$expWhere = "WHERE e.status = 'approved'";
$fundParams = [];
$expParams = [];

if ($selectedSchoolYear !== '') {
    $fundWhere .= " AND f.school_year = ?";
    $expWhere .= " AND e.school_year = ?";
    $fundParams[] = $selectedSchoolYear;
    $expParams[] = $selectedSchoolYear;
}
if ($fundFilterActive) {
    $expWhere .= " AND e.fund_source = ?";
    $expParams[] = $selectedFundSource;
}

// Fetch approved funds
$fundStmt = $pdo->prepare("SELECT f.* FROM funds f $fundWhere ORDER BY f.fund_date DESC");
$fundStmt->execute($fundParams);
$approvedFunds = $fundStmt->fetchAll();

// Fetch approved expenses (LEFT JOIN so expenses without a category are still counted)
$expStmt = $pdo->prepare(
    "SELECT e.*, c.name AS category_name
     FROM expenses e
     LEFT JOIN categories c ON e.category_id = c.id
     $expWhere
     ORDER BY e.expense_date DESC"
);
$expStmt->execute($expParams);
$approvedExpenses = $expStmt->fetchAll();

// Totals
$totalFunds = array_sum(array_column($approvedFunds, 'amount'));
$totalExpenses = array_sum(array_column($approvedExpenses, 'amount'));

// Pie chart data
$catTotals = [];
foreach ($approvedExpenses as $e) {
    $cat = $e['category_name'] ?? 'Uncategorized';
    $catTotals[$cat] = ($catTotals[$cat] ?? 0) + $e['amount'];
}
$pieLabels = array_keys($catTotals);
$pieData = array_values($catTotals);

// Dropdowns
$fundSources = $pdo->query("SELECT DISTINCT fund_source FROM expenses WHERE fund_source IS NOT NULL ORDER BY fund_source")->fetchAll(PDO::FETCH_COLUMN);
$schoolYears = $pdo->query("SELECT year_label FROM school_years ORDER BY year_label DESC")->fetchAll(PDO::FETCH_COLUMN);

// Approved budgets (filtered by school year)
$budgetSQL = "SELECT eb.*, e.name AS event_name
              FROM event_budgets eb
              LEFT JOIN events e ON eb.event_id = e.id
              WHERE eb.status = 'approved'";
$budgetParams = [];
if ($selectedSchoolYear !== '') {
    $budgetSQL .= " AND eb.school_year = ?";
    $budgetParams[] = $selectedSchoolYear;
}
$budgetSQL .= " ORDER BY eb.created_at DESC";
$budgetStmt = $pdo->prepare($budgetSQL);
$budgetStmt->execute($budgetParams);
$budgets = $budgetStmt->fetchAll();

$budgetActuals = [];
$beStmt = $pdo->query("SELECT budget_id, SUM(amount) AS total FROM expenses WHERE status='approved' AND budget_id IS NOT NULL GROUP BY budget_id");
foreach ($beStmt as $row) {
    $budgetActuals[$row['budget_id']] = $row['total'];
}

$budgetItems = [];
$biStmt = $pdo->query("SELECT bpi.* FROM budget_plan_items bpi JOIN event_budgets eb ON bpi.budget_id = eb.id WHERE eb.status='approved'");
foreach ($biStmt as $item) {
    $budgetItems[$item['budget_id']][] = $item;
}

// Events with financial summary (filtered by school year; one liquidation per event = the latest)
$eventSQL = "SELECT e.*, l.total_budget, l.total_expenses, l.variance, l.audit_result
             FROM events e
             LEFT JOIN liquidations l
                    ON l.id = (SELECT MAX(l2.id) FROM liquidations l2 WHERE l2.event_id = e.id)
             WHERE e.status IN ('closed', 'audited_compliant', 'audited_with_findings')";
$eventParams = [];
if ($selectedSchoolYear !== '') {
    $eventSQL .= " AND e.school_year = ?";
    $eventParams[] = $selectedSchoolYear;
}
$eventSQL .= " ORDER BY e.created_at DESC";
$eventStmt = $pdo->prepare($eventSQL);
$eventStmt->execute($eventParams);
$events = $eventStmt->fetchAll();

// Unified ledger: approved funds and expenses in one list, newest first
$ledger = [];
foreach ($approvedFunds as $f) {
    $ledger[] = [
        'kind' => 'fund', 'date' => $f['fund_date'], 'desc' => $f['source'],
        'category' => null, 'fund_source' => null, 'event' => null,
        'amount' => $f['amount'], 'receipt' => $f['receipt_path'] ?? ''
    ];
}
foreach ($approvedExpenses as $e) {
    $ledger[] = [
        'kind' => 'expense', 'date' => $e['expense_date'], 'desc' => $e['payee'],
        'category' => $e['category_name'] ?? 'Uncategorized', 'fund_source' => $e['fund_source'] ?? null,
        'event' => $e['event'] ?? null, 'amount' => $e['amount'], 'receipt' => $e['receipt_path'] ?? ''
    ];
}
usort($ledger, function($a, $b) {
    return strtotime((string)$b['date']) <=> strtotime((string)$a['date']);
});

// Helper: icon by file extension
function receiptIcon($path) {
    return strtolower(pathinfo(trim($path), PATHINFO_EXTENSION)) === 'pdf' ? 'fa-file-pdf' : 'fa-file-image';
}

// Helper: pretty date (falls back to the raw value)
function prettyDate($d) {
    $t = strtotime((string)$d);
    return $t ? date('M d, Y', $t) : htmlspecialchars((string)$d);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($sysSettings['org_name']) ?> - Student Portal</title>
    <link rel="stylesheet" href="../assets/css/public.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --theme-primary: <?= htmlspecialchars($themeColor) ?>;
            --blue: #2980b9;
            --red: #ff6b6b;
            --red-deep: #ee5a24;
            --green: #00b894;
            --green-deep: #009432;
            --bg: #f4f6f9;
            --surface: #ffffff;
            --line: #e2e8f0;
            --line-soft: #f0f2f5;
            --text: #1e293b;
            --muted: #64748b;
            --radius: 14px;
            --shadow: 0 1px 2px rgba(26, 60, 94, .04), 0 4px 16px rgba(26, 60, 94, .06);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: var(--bg); color: var(--text);
            font-size: 14px; line-height: 1.5;
            min-height: 100vh; display: flex; flex-direction: column;
            -webkit-font-smoothing: antialiased;
        }
        :focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }

        /* ---------- Header ---------- */
        .public-header {
            background: var(--theme-primary); color: #fff;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px; height: 64px; flex-shrink: 0;
            position: sticky; top: 0; z-index: 50;
            box-shadow: 0 1px 0 rgba(255,255,255,.08) inset, 0 2px 8px rgba(0,0,0,.15);
        }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-area img {
            height: 38px; width: 38px; background: #fff; border-radius: 50%;
            padding: 3px; object-fit: contain;
        }
        .logo-area span { font-size: 17px; font-weight: 700; letter-spacing: -.2px; }
        .nav-links { display: flex; gap: 6px; list-style: none; }
        .nav-links a {
            color: rgba(255,255,255,.85); text-decoration: none; font-weight: 500;
            display: flex; align-items: center; gap: 8px; font-size: 14px;
            padding: 8px 14px; border-radius: 8px;
            transition: background .15s, color .15s;
        }
        .nav-links a:hover { background: rgba(255,255,255,.12); color: #fff; }
        .nav-links a.current { background: rgba(255,255,255,.18); color: #fff; }

        /* ---------- Layout ---------- */
        .main-wrapper {
            flex: 1; display: flex; flex-direction: column;
            padding: 24px 32px 40px;
            width: 100%; max-width: 1480px; margin: 0 auto;
        }

        /* ---------- Filters ---------- */
        .filters-bar {
            background: var(--surface); border: 1px solid var(--line);
            border-radius: var(--radius); box-shadow: var(--shadow);
            padding: 14px 18px; display: flex; gap: 14px;
            align-items: flex-end; flex-wrap: wrap;
            margin-bottom: 18px; flex-shrink: 0;
        }
        .filter-group { flex: 1; min-width: 180px; }
        .filters-bar label {
            display: block; font-weight: 600; font-size: 12px;
            color: var(--muted); margin-bottom: 6px;
        }
        .filters-bar select {
            width: 100%; height: 40px; padding: 0 12px;
            border: 1px solid var(--line); border-radius: 10px;
            font: inherit; color: var(--text); background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }
        .filters-bar select:focus {
            outline: none; border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(41,128,185,.15);
        }
        .filter-actions { display: flex; gap: 8px; }
        .filters-bar button, .filters-bar a.btn-clear {
            height: 40px; padding: 0 18px; border-radius: 10px; border: 1px solid transparent;
            font: inherit; font-weight: 600; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px;
            transition: opacity .15s, background .15s;
        }
        .btn-apply { background: var(--theme-primary); color: #fff; }
        .btn-apply:hover { opacity: .9; }
        .btn-clear { background: #fff; color: var(--muted); border-color: var(--line) !important; }
        .btn-clear:hover { background: #f8fafc; color: var(--text); }

        /* ---------- Summary cards ---------- */
        .summary-cards {
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px; margin-bottom: 20px; flex-shrink: 0;
        }
        .summary-card {
            position: relative; overflow: hidden;
            border-radius: var(--radius); padding: 18px 22px; color: #fff;
            box-shadow: var(--shadow);
            display: flex; align-items: center; gap: 16px;
        }
        .summary-card::after {
            content: ""; position: absolute; right: -36px; top: -46px;
            width: 140px; height: 140px; border-radius: 50%;
            background: rgba(255,255,255,.08); pointer-events: none;
        }
        .summary-card .icon {
            flex-shrink: 0; width: 46px; height: 46px; border-radius: 12px;
            display: grid; place-items: center; font-size: 19px;
            background: rgba(255,255,255,.18);
        }
        .summary-card .label { font-size: 13px; font-weight: 500; color: rgba(255,255,255,.88); }
        .summary-card .value {
            font-size: 26px; font-weight: 700; letter-spacing: -.5px; line-height: 1.15;
            font-variant-numeric: tabular-nums; color: #fff;
        }
        .summary-card .note { font-size: 12px; color: rgba(255,255,255,.82); margin-top: 2px; }
        .summary-card.funds    { background: linear-gradient(135deg, var(--blue), #1a3c5e); }
        .summary-card.expenses { background: linear-gradient(135deg, var(--red), var(--red-deep)); }
        .summary-card.balance  { background: linear-gradient(135deg, var(--green), var(--green-deep)); }

        /* ---------- Page sections (single scrolling page) ---------- */
        .page-content { display: flex; flex-direction: column; gap: 20px; }
        .page-content > .card, .page-content > .chart-row { margin-bottom: 0; }
        .card-title .count {
            min-width: 22px; padding: 1px 8px; border-radius: 999px;
            font-size: 12px; font-weight: 600; text-align: center;
            background: var(--line-soft); color: var(--muted);
        }
        .amt-in  { color: var(--green-deep); font-weight: 600; }
        .amt-out { color: #d63031; font-weight: 600; }
        .pg-filters { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }

        /* ---------- Cards ---------- */
        .card {
            background: var(--surface); border: 1px solid var(--line);
            border-radius: var(--radius); box-shadow: var(--shadow);
            margin-bottom: 20px; overflow: hidden;
        }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
            padding: 16px 22px; border-bottom: 1px solid var(--line-soft);
        }
        .card-title {
            display: flex; align-items: center; gap: 10px;
            font-size: 15px; font-weight: 600; color: var(--theme-primary);
        }
        .card-title i {
            width: 30px; height: 30px; border-radius: 8px;
            display: grid; place-items: center; font-size: 13px;
            background: #eaf2f9; color: var(--blue);
        }
        .card-title.expense i { background: #fff0ee; color: var(--red-deep); }
        .card-meta { font-size: 12.5px; color: var(--muted); }
        .card-body { padding: 18px 22px; }
        .table-wrap { overflow-x: auto; }

        /* ---------- Charts ---------- */
        .chart-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .chart-row .card { margin-bottom: 0; }
        .chart-wrapper { position: relative; height: 200px; width: 100%; }

        /* ---------- Tables ---------- */
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8fafc; color: var(--muted); text-align: left;
            padding: 11px 22px; font-size: 12px; font-weight: 600; letter-spacing: .3px;
            border-bottom: 1px solid var(--line); white-space: nowrap;
        }
        td { padding: 13px 22px; border-bottom: 1px solid var(--line-soft); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: 0; }
        tbody tr:hover td { background: #f8fafc; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        th.num { text-align: right; }
        .muted { color: var(--muted); }
        .nowrap { white-space: nowrap; }
        .cell-main { font-weight: 500; }

        .type-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600;
        }
        .type-pill.fund { background: #eaf2f9; color: var(--blue); }
        .type-pill.expense { background: #fff0ee; color: var(--red-deep); }

        .variance { font-weight: 600; font-size: 13px; }
        .variance.under { color: var(--green-deep); }
        .variance.over { color: #d63031; }

        .badge { padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .badge-compliant { background: #d4edda; color: #155724; }
        .badge-findings { background: #f8d7da; color: #721c24; }

        .receipt-link {
            display: inline-grid; place-items: center;
            width: 30px; height: 30px; margin-right: 4px; border-radius: 8px;
            background: #eaf2f9; color: var(--blue); text-decoration: none;
            transition: background .15s, color .15s;
        }
        .receipt-link:hover { background: var(--blue); color: #fff; }

        /* Budget progress */
        .progress { width: 120px; height: 6px; border-radius: 999px; background: var(--line-soft); overflow: hidden; margin-top: 6px; margin-left: auto; }
        .progress > span { display: block; height: 100%; border-radius: 999px; background: var(--green); }
        .progress.over > span { background: #d63031; }

        details summary {
            cursor: pointer; color: var(--blue); font-weight: 600; font-size: 13px;
            list-style: none; display: inline-flex; align-items: center; gap: 6px;
        }
        details summary::-webkit-details-marker { display: none; }
        details summary::before {
            content: "\f054"; font-family: "Font Awesome 6 Free"; font-weight: 900;
            font-size: 10px; transition: transform .15s;
        }
        details[open] summary::before { transform: rotate(90deg); }
        details > .table-wrap, details > table {
            margin-top: 10px; border: 1px solid var(--line); border-radius: 10px; overflow: hidden;
        }
        details th, details td { padding: 8px 14px; }

        /* ---------- Empty state ---------- */
        .empty {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            padding: 44px 20px; text-align: center; color: var(--muted);
        }
        .empty i {
            width: 48px; height: 48px; border-radius: 50%;
            display: grid; place-items: center; font-size: 20px;
            background: var(--line-soft); color: #94a3b8; margin-bottom: 4px;
        }
        .empty strong { color: var(--text); font-weight: 600; }
        .empty.small { padding: 40px 0; }

        /* ---------- Search + pagination ---------- */
        .pg-toolbar, .pg-footer {
            display: flex; justify-content: space-between; align-items: center;
            gap: 10px; flex-wrap: wrap; font-size: 13px; color: var(--muted);
            padding: 14px 22px;
        }
        .pg-toolbar { border-bottom: 1px solid var(--line-soft); }
        .pg-footer { border-top: 1px solid var(--line-soft); }
        .pg-search-wrap { position: relative; }
        .pg-search-wrap i {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 13px; pointer-events: none;
        }
        .pg-toolbar input[type="search"] {
            height: 38px; padding: 0 12px 0 36px; width: 260px;
            border: 1px solid var(--line); border-radius: 10px;
            font: inherit; background: #fff; color: var(--text);
            transition: border-color .15s, box-shadow .15s;
        }
        .pg-toolbar input[type="search"]:focus, .pg-toolbar select:focus {
            outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(41,128,185,.15);
        }
        .pg-toolbar select {
            height: 34px; padding: 0 8px; margin: 0 4px;
            border: 1px solid var(--line); border-radius: 8px;
            font: inherit; font-weight: 600; color: var(--theme-primary); background: #fff;
        }
        .pg-btns { display: flex; gap: 6px; }
        .pg-btns button {
            height: 34px; padding: 0 14px; border: 1px solid var(--line); border-radius: 8px;
            background: #fff; color: #475569; font: inherit; font-weight: 600; font-size: 13px; cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
        }
        .pg-btns button:hover:not(:disabled) { background: #eaf2f9; border-color: var(--theme-primary); color: var(--theme-primary); }
        .pg-btns button:disabled { opacity: .4; cursor: not-allowed; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1100px) { .summary-cards { grid-template-columns: 1fr; } }
        @media (max-width: 900px) {
            .main-wrapper { padding: 16px 16px 32px; }
            .public-header { padding: 0 16px; }
            .chart-row { grid-template-columns: 1fr; }
            .nav-links a span { display: none; }
            .pg-toolbar input[type="search"] { width: 100%; }
            .pg-search-wrap { width: 100%; }
            .filter-actions { width: 100%; }
            .filter-actions > * { flex: 1; justify-content: center; }
            th, td { padding-left: 16px; padding-right: 16px; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>

<!-- Header -->
<header class="public-header">
    <div class="logo-area">
        <?php if (file_exists($sysLogoPath)): ?>
            <img src="<?= $sysLogoUrl ?>" alt="Logo">
        <?php else: ?>
            <img src="../assets/images/logo.png" alt="Logo">
        <?php endif; ?>
        <span><?= htmlspecialchars($sysSettings['org_short_name']) ?></span>
    </div>
    <ul class="nav-links">
        <li><a href="index.php" class="current"><i class="fas fa-home"></i> <span>Home</span></a></li>
        <li><a href="message_board.php"><i class="fas fa-comments"></i> <span>Message Board</span></a></li>
        <?php if (isset($_SESSION['adviser_id']) || isset($_SESSION['officer_id'])): ?>
            <li><a href="../admin/<?= isset($_SESSION['adviser_id']) ? 'adviser' : 'officer' ?>/dashboard.php"><i class="fas fa-th-large"></i> <span>Dashboard</span></a></li>
            <li><a href="../admin/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
        <?php else: ?>
            <li><a href="../login.php"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a></li>
        <?php endif; ?>
    </ul>
</header>

<div class="main-wrapper">
    <!-- Filters -->
    <form method="GET" class="filters-bar">
        <div class="filter-group">
            <label for="fSchoolYear">School year</label>
            <select name="school_year" id="fSchoolYear">
                <option value="">All years</option>
                <?php foreach ($schoolYears as $yr): ?>
                    <option value="<?= htmlspecialchars($yr) ?>" <?= $selectedSchoolYear === $yr ? 'selected' : '' ?>><?= htmlspecialchars($yr) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label for="fFundSource">Fund source</label>
            <select name="fund_source" id="fFundSource">
                <option value="">All sources</option>
                <?php foreach ($fundSources as $fs): ?>
                    <option value="<?= htmlspecialchars($fs) ?>" <?= $selectedFundSource === $fs ? 'selected' : '' ?>><?= htmlspecialchars($fs) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply</button>
            <a href="index.php" class="btn-clear">Clear</a>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card funds">
            <div class="icon"><i class="fas fa-coins"></i></div>
            <div class="details">
                <div class="label">Total funds</div>
                <div class="value">₱<?= number_format($totalFunds, 2) ?></div>
                <?php if ($fundFilterActive): ?><div class="note">All sources (not filtered)</div><?php endif; ?>
            </div>
        </div>
        <div class="summary-card expenses">
            <div class="icon"><i class="fas fa-receipt"></i></div>
            <div class="details">
                <div class="label">Total expenses</div>
                <div class="value">₱<?= number_format($totalExpenses, 2) ?></div>
            </div>
        </div>
        <div class="summary-card balance">
            <div class="icon"><i class="fas fa-balance-scale"></i></div>
            <div class="details">
                <div class="label">Balance</div>
                <?php if ($fundFilterActive): ?>
                    <div class="value">—</div>
                    <div class="note">Not available when filtered by fund source</div>
                <?php else: ?>
                    <div class="value">₱<?= number_format($totalFunds - $totalExpenses, 2) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="page-content">

        <!-- CHARTS -->
        <div class="chart-row">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-pie"></i> Expenses by category</div>
                </div>
                <div class="card-body">
                    <?php if (count($pieData) > 0): ?>
                        <div class="chart-wrapper">
                            <canvas id="pieChart"
                                    data-labels="<?= htmlspecialchars(json_encode($pieLabels), ENT_QUOTES) ?>"
                                    data-values="<?= htmlspecialchars(json_encode($pieData), ENT_QUOTES) ?>"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="empty small">
                            <i class="fas fa-chart-pie"></i>
                            <strong>No expense data yet</strong>
                            <span>Approved expenses will be charted here.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fas fa-chart-bar"></i> <?= $fundFilterActive ? 'Expenses' : 'Funds vs expenses' ?></div>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper">
                        <canvas id="barChart"
                                data-funds="<?= (float)$totalFunds ?>"
                                data-expenses="<?= (float)$totalExpenses ?>"
                                data-filtered="<?= $fundFilterActive ? '1' : '0' ?>"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- TRANSACTIONS (funds + expenses in one ledger) -->
        <div class="card" id="transactions">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-list"></i> Transactions</div>
                <span class="card-meta"><?= count($approvedFunds) ?> funds &middot; <?= count($approvedExpenses) ?> expenses</span>
            </div>
            <?php if (count($ledger) == 0): ?>
                <div class="empty">
                    <i class="fas fa-inbox"></i>
                    <strong>No approved transactions</strong>
                    <span>Nothing matches the selected filters. Try clearing them.</span>
                </div>
            <?php else: ?>
                <div class="pageable" data-per="10">
                    <div class="pg-toolbar">
                        <div class="pg-filters">
                            <div class="pg-search-wrap">
                                <i class="fas fa-search"></i>
                                <input type="search" class="pg-search" placeholder="Search transactions" aria-label="Search transactions">
                            </div>
                            <select class="pg-type" aria-label="Filter by type">
                                <option value="">All types</option>
                                <option value="fund">Funds only</option>
                                <option value="expense">Expenses only</option>
                            </select>
                        </div>
                        <label>Show
                            <select class="pg-size">
                                <option value="10">10</option><option value="25">25</option>
                                <option value="50">50</option><option value="100">100</option>
                            </select> entries
                        </label>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>Category</th><th>Fund source</th><th>Event</th><th class="num">Amount</th><th>Receipt</th></tr></thead>
                            <tbody>
                            <?php foreach ($ledger as $r): $isFund = $r['kind'] === 'fund'; ?>
                                <tr data-type="<?= $r['kind'] ?>">
                                    <td class="muted nowrap"><?= prettyDate($r['date']) ?></td>
                                    <td>
                                        <span class="type-pill <?= $isFund ? 'fund' : 'expense' ?>">
                                            <i class="fas <?= $isFund ? 'fa-coins' : 'fa-receipt' ?>"></i> <?= $isFund ? 'Fund' : 'Expense' ?>
                                        </span>
                                    </td>
                                    <td class="cell-main"><?= htmlspecialchars($r['desc'] ?? '') ?></td>
                                    <td class="muted"><?= htmlspecialchars($r['category'] ?? '—') ?></td>
                                    <td class="muted"><?= htmlspecialchars($r['fund_source'] ?? '—') ?></td>
                                    <td class="muted"><?= htmlspecialchars($r['event'] ?? '—') ?></td>
                                    <td class="num <?= $isFund ? 'amt-in' : 'amt-out' ?>"><?= $isFund ? '+' : '−' ?>₱<?= number_format($r['amount'], 2) ?></td>
                                    <td class="nowrap">
                                        <?php if (!empty($r['receipt'])): ?>
                                            <?php foreach (explode(',', $r['receipt']) as $rec): ?>
                                                <a class="receipt-link" href="../<?= htmlspecialchars(trim($rec)) ?>" target="_blank" rel="noopener" title="View receipt" aria-label="View receipt">
                                                    <i class="fas <?= receiptIcon($rec) ?>"></i>
                                                </a>
                                            <?php endforeach; ?>
                                        <?php else: ?><span class="muted">—</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pg-footer">
                        <span class="pg-info"></span>
                        <div class="pg-btns">
                            <button type="button" class="pg-prev">&laquo; Prev</button>
                            <button type="button" class="pg-next">Next &raquo;</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- BUDGETS -->
        <div class="card" id="budgets">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-wallet"></i> Approved budget plans</div>
                <span class="card-meta"><?= count($budgets) ?> total</span>
            </div>
            <?php if (count($budgets) == 0): ?>
                <div class="empty">
                    <i class="fas fa-wallet"></i>
                    <strong>No approved budgets</strong>
                    <span>Nothing matches the selected school year.</span>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Budget name</th><th>Event</th><th class="num">Allocated</th><th class="num">Actual</th><th class="num">Variance</th><th>Details</th></tr></thead>
                        <tbody>
                        <?php foreach ($budgets as $b):
                            $allocated = $b['allocated_amount'];
                            $actual = $budgetActuals[$b['id']] ?? 0;
                            $variance = $allocated - $actual;
                            $isUnder = $variance >= 0;
                            $varianceText = $isUnder ? '₱' . number_format($variance, 2) . ' under' : '₱' . number_format(abs($variance), 2) . ' over';
                            $pct = $allocated > 0 ? min(100, ($actual / $allocated) * 100) : ($actual > 0 ? 100 : 0);
                        ?>
                            <tr>
                                <td class="cell-main"><?= htmlspecialchars($b['budget_name']) ?></td>
                                <td class="muted"><?= htmlspecialchars($b['event_name'] ?? '—') ?></td>
                                <td class="num">₱<?= number_format($allocated, 2) ?></td>
                                <td class="num">
                                    ₱<?= number_format($actual, 2) ?>
                                    <div class="progress <?= $isUnder ? '' : 'over' ?>" title="<?= round($pct) ?>% of budget used"><span style="width: <?= round($pct, 1) ?>%"></span></div>
                                </td>
                                <td class="num"><span class="variance <?= $isUnder ? 'under' : 'over' ?>"><?= $varianceText ?></span></td>
                                <td>
                                    <?php if (!empty($budgetItems[$b['id']])): ?>
                                        <details>
                                            <summary>View line items</summary>
                                            <div class="table-wrap">
                                                <table>
                                                    <thead><tr><th>Item</th><th>Qty</th><th class="num">Unit cost</th><th class="num">Total</th></tr></thead>
                                                    <tbody>
                                                    <?php foreach ($budgetItems[$b['id']] as $item): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                            <td><?= htmlspecialchars($item['quantity']) ?></td>
                                                            <td class="num">₱<?= number_format($item['unit_cost'], 2) ?></td>
                                                            <td class="num">₱<?= number_format($item['estimated_total'], 2) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </details>
                                    <?php else: ?><span class="muted">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- EVENTS -->
        <div class="card" id="events">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-calendar-alt"></i> Events with financial summary</div>
                <span class="card-meta"><?= count($events) ?> total</span>
            </div>
            <?php if (count($events) == 0): ?>
                <div class="empty">
                    <i class="fas fa-calendar-alt"></i>
                    <strong>No completed events</strong>
                    <span>Nothing matches the selected school year.</span>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Event</th><th>School year</th><th class="num">Budget</th><th class="num">Actual</th><th class="num">Variance</th><th>Audit result</th></tr></thead>
                        <tbody>
                        <?php foreach ($events as $ev):
                            $hasLiq = ($ev['variance'] !== null);
                            $var = (float)($ev['variance'] ?? 0);
                            $isUnder = $var >= 0;
                            $varText = $isUnder ? '₱' . number_format($var, 2) . ' under' : '₱' . number_format(abs($var), 2) . ' over';
                            if ($ev['audit_result'] === 'compliant') $auditBadge = '<span class="badge badge-compliant">Compliant</span>';
                            elseif ($ev['audit_result'] === 'with_findings') $auditBadge = '<span class="badge badge-findings">With findings</span>';
                            else $auditBadge = '<span class="muted">—</span>';
                        ?>
                            <tr>
                                <td class="cell-main"><?= htmlspecialchars($ev['name']) ?></td>
                                <td class="muted"><?= htmlspecialchars($ev['school_year'] ?? '—') ?></td>
                                <td class="num"><?= $hasLiq ? '₱' . number_format($ev['total_budget'] ?? 0, 2) : '<span class="muted">—</span>' ?></td>
                                <td class="num"><?= $hasLiq ? '₱' . number_format($ev['total_expenses'] ?? 0, 2) : '<span class="muted">—</span>' ?></td>
                                <td class="num">
                                    <?php if ($hasLiq): ?>
                                        <span class="variance <?= $isUnder ? 'under' : 'over' ?>"><?= $varText ?></span>
                                    <?php else: ?><span class="muted">—</span><?php endif; ?>
                                </td>
                                <td><?= $auditBadge ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
    // Search + type filter + client-side pagination for the transactions table
    document.querySelectorAll('.pageable').forEach(function(wrap) {
        var table = wrap.querySelector('table');
        var rows = Array.prototype.filter.call(table.querySelectorAll('tr'), function(tr) { return tr.querySelector('td'); });
        var search = wrap.querySelector('.pg-search');
        var sizeSel = wrap.querySelector('.pg-size');
        var typeSel = wrap.querySelector('.pg-type');
        var info = wrap.querySelector('.pg-info');
        var prev = wrap.querySelector('.pg-prev');
        var next = wrap.querySelector('.pg-next');
        var page = 1;

        sizeSel.value = wrap.dataset.per || '10';

        function render() {
            var q = search.value.trim().toLowerCase();
            var size = parseInt(sizeSel.value, 10);
            var type = typeSel ? typeSel.value : '';
            var matched = rows.filter(function(tr) {
                if (type && tr.dataset.type !== type) return false;
                return !q || tr.textContent.toLowerCase().indexOf(q) !== -1;
            });
            var pages = Math.max(1, Math.ceil(matched.length / size));
            if (page > pages) page = pages;
            var start = (page - 1) * size;

            rows.forEach(function(tr) { tr.style.display = 'none'; });
            matched.slice(start, start + size).forEach(function(tr) { tr.style.display = ''; });

            info.textContent = matched.length
                ? 'Showing ' + (start + 1) + '–' + Math.min(start + size, matched.length) + ' of ' + matched.length
                : 'No matching records';
            prev.disabled = page <= 1;
            next.disabled = page >= pages;
        }

        search.addEventListener('input', function() { page = 1; render(); });
        sizeSel.addEventListener('change', function() { page = 1; render(); });
        if (typeSel) typeSel.addEventListener('change', function() { page = 1; render(); });
        prev.addEventListener('click', function() { page--; render(); });
        next.addEventListener('click', function() { page++; render(); });
        render();
    });

    // Shared chart defaults
    Chart.defaults.font.family = "'Inter', 'Segoe UI', Arial, sans-serif";
    Chart.defaults.color = '#64748b';
    var peso = function(v) { return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    // Category chart
    const pieCanvas = document.getElementById('pieChart');
    if (pieCanvas) {
        const labels = JSON.parse(pieCanvas.dataset.labels || '[]');
        const values = JSON.parse(pieCanvas.dataset.values || '[]');
        if (labels.length > 0) {
            new Chart(pieCanvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        borderColor: '#fff',
                        borderWidth: 2,
                        backgroundColor: ['#2980b9','#27ae60','#e67e22','#8e44ad','#c0392b','#16a085','#f39c12','#2c3e50','#d35400','#7f8c8d']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: {
                        legend: { position: 'right', labels: { font: { size: 12 }, boxWidth: 10, boxHeight: 10, usePointStyle: true, pointStyle: 'rectRounded' } },
                        tooltip: { callbacks: { label: function(ctx) { return ' ' + ctx.label + ': ' + peso(ctx.parsed); } } }
                    }
                }
            });
        }
    }

    // Funds vs expenses (only Expenses when a fund source filter is active)
    const barCanvas = document.getElementById('barChart');
    if (barCanvas) {
        const funds = parseFloat(barCanvas.dataset.funds || 0);
        const expenses = parseFloat(barCanvas.dataset.expenses || 0);
        const filtered = barCanvas.dataset.filtered === '1';

        new Chart(barCanvas, {
            type: 'bar',
            data: {
                labels: filtered ? ['Total expenses'] : ['Total funds', 'Total expenses'],
                datasets: [{
                    label: 'Amount',
                    data: filtered ? [expenses] : [funds, expenses],
                    backgroundColor: filtered ? ['#e74c3c'] : ['#2980b9', '#e74c3c'],
                    borderRadius: 8,
                    maxBarThickness: 64
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f0f2f5' },
                        border: { display: false },
                        ticks: { font: { size: 11 }, callback: function(v) { return '₱' + Number(v).toLocaleString('en-PH'); } }
                    },
                    x: { grid: { display: false }, ticks: { font: { size: 12 } } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(ctx) { return ' ' + peso(ctx.parsed.y); } } }
                }
            }
        });
    }
</script>
</body>
</html>