<?php
require_once '../../includes/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireAuditor();

$summary = getFinancialSummary($pdo);
$currentPic = getCurrentUserProfilePic($pdo);

// Pending audit counts
$pendingAuditFunds = $pdo->query("SELECT COUNT(*) FROM funds WHERE status='pending_audit'")->fetchColumn();
$pendingAuditExp = $pdo->query("SELECT COUNT(*) FROM expenses WHERE status='pending_audit'")->fetchColumn();
$pendingAudit = $pendingAuditFunds + $pendingAuditExp;

// Recent pending audit funds
$pendingFunds = $pdo->query("SELECT f.*, o.name AS officer_name, o.position FROM funds f JOIN officers o ON f.officer_id=o.id WHERE f.status='pending_audit' ORDER BY f.created_at DESC LIMIT 5")->fetchAll();

// Recent pending audit expenses
$pendingExpenses = $pdo->query("SELECT e.*, c.name AS category_name, o.name AS officer_name, o.position FROM expenses e JOIN categories c ON e.category_id=c.id JOIN officers o ON e.officer_id=o.id WHERE e.status='pending_audit' ORDER BY e.created_at DESC LIMIT 5")->fetchAll();

// ============ TRANSACTIONS TAB QUERY ============
$stmtTx = $pdo->prepare(
    "SELECT 'Fund' AS type, id, source AS details, amount, fund_date AS tx_date, status, created_at
     FROM funds WHERE status = 'pending_audit' OR audited_by = ?
     UNION ALL
     SELECT 'Expense' AS type, id, payee AS details, amount, expense_date AS tx_date, status, created_at
     FROM expenses WHERE status = 'pending_audit' OR audited_by = ?
     ORDER BY created_at DESC"
);
$stmtTx->execute([$_SESSION['officer_id'], $_SESSION['officer_id']]);
$allTransactions = $stmtTx->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auditor Dashboard - BSIT Fund System</title>
    <link rel="stylesheet" href="../../assets/css/officer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #1a3c5e;
            --navy-hover: #2c5282;
            --blue: #2980b9;
            --red: #ff6b6b;
            --red-deep: #ee5a24;
            --green: #00b894;
            --green-deep: #009432;
            --orange: #e67e22;
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
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        .main-area { margin-left: 240px; }
        .content { padding: 28px 32px 48px; max-width: 1400px; }

        :focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }

        /* ---------- Summary cards ---------- */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .summary-card {
            position: relative;
            overflow: hidden;
            border-radius: var(--radius);
            padding: 22px 24px;
            color: #fff;
            display: flex;
            flex-direction: column;
            gap: 18px;
            box-shadow: var(--shadow);
        }
        .summary-card::after {
            content: "";
            position: absolute;
            right: -40px; top: -40px;
            width: 160px; height: 160px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
            pointer-events: none;
        }
        .summary-card .card-top {
            display: flex; align-items: center; justify-content: space-between;
        }
        .summary-card .label {
            font-size: 13px; font-weight: 500; color: rgba(255, 255, 255, .88);
        }
        .summary-card .icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: grid; place-items: center;
            font-size: 17px;
            background: rgba(255, 255, 255, .18);
        }
        .summary-card .value {
            font-size: 30px; font-weight: 700; letter-spacing: -.5px;
            font-variant-numeric: tabular-nums; line-height: 1.1;
        }
        .summary-card .foot {
            font-size: 12.5px; color: rgba(255, 255, 255, .8);
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, .18);
        }
        .summary-card.funds    { background: linear-gradient(135deg, var(--blue), var(--navy)); }
        .summary-card.expenses { background: linear-gradient(135deg, var(--red), var(--red-deep)); }
        .summary-card.balance  { background: linear-gradient(135deg, var(--green), var(--green-deep)); }

        /* ---------- Toolbar: primary action ---------- */
        .toolbar {
            display: flex; align-items: center; justify-content: flex-end;
            gap: 16px; flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .btn-primary {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--navy); color: #fff;
            padding: 10px 18px;
            border-radius: 10px; text-decoration: none; font-weight: 600;
            box-shadow: 0 1px 2px rgba(26, 60, 94, .2);
            transition: background .15s, transform .15s;
        }
        .btn-primary:hover { background: var(--navy-hover); transform: translateY(-1px); }

        .dash-section { display: block; margin-bottom: 24px; }

        /* ---------- Cards & tables ---------- */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .card-header {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap;
            padding: 18px 22px;
            border-bottom: 1px solid var(--line-soft);
        }
        .card-title {
            display: flex; align-items: center; gap: 10px;
            font-size: 15px; font-weight: 600; color: var(--navy);
        }
        .card-title i {
            width: 30px; height: 30px; border-radius: 8px;
            display: grid; place-items: center; font-size: 13px;
            background: #eaf2f9; color: var(--blue);
        }
        .card-title.expense i { background: #fff0ee; color: var(--red-deep); }
        .card-title.neutral i { background: #eaf2f9; color: var(--navy); }
        .card-sub { color: var(--muted); font-size: 13px; padding: 14px 22px 0; }
        .badge-count {
            font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 999px;
            background: #fdf0e2; color: var(--orange);
        }

        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #f8fafc; color: var(--muted);
            text-align: left; padding: 11px 22px;
            font-size: 12px; font-weight: 600; letter-spacing: .3px;
            border-bottom: 1px solid var(--line);
            white-space: nowrap;
        }
        td {
            padding: 14px 22px;
            border-bottom: 1px solid var(--line-soft);
            vertical-align: middle;
        }
        tbody tr:last-child td, table tr:last-child td { border-bottom: 0; }
        tbody tr, table tr:not(:first-child) { transition: background .12s; }
        tbody tr:hover td { background: #f8fafc; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        th.num { text-align: right; }
        .id-cell { color: var(--muted); font-variant-numeric: tabular-nums; }
        .cell-main { font-weight: 500; }

        .type-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 3px 10px; border-radius: 999px;
            font-size: 12px; font-weight: 600;
        }
        .type-pill.fund { background: #eaf2f9; color: var(--blue); }
        .type-pill.expense { background: #fff0ee; color: var(--red-deep); }

        /* ---------- Empty state ---------- */
        .empty {
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            padding: 44px 20px; text-align: center; color: var(--muted);
        }
        .empty i {
            width: 48px; height: 48px; border-radius: 50%;
            display: grid; place-items: center; font-size: 20px;
            background: var(--line-soft); color: #94a3b8;
        }
        .empty strong { color: var(--text); font-weight: 600; }

        /* ---------- Transactions filters ---------- */
        .filters { display: flex; gap: 10px; flex-wrap: wrap; }
        .search, .select {
            height: 38px; border: 1px solid var(--line); border-radius: 10px;
            background: #fff; font: inherit; color: var(--text);
            transition: border-color .15s, box-shadow .15s;
        }
        .search { padding: 0 12px 0 36px; width: 240px; }
        .select { padding: 0 12px; }
        .search:focus, .select:focus {
            outline: none; border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(41, 128, 185, .15);
        }
        .search-wrap { position: relative; }
        .search-wrap i {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 13px; pointer-events: none;
        }
        #noMatch { display: none; }

        /* ---------- Responsive ---------- */
        @media (max-width: 1100px) {
            .summary-cards { grid-template-columns: 1fr; }
        }
        @media (max-width: 900px) {
            .two-col { grid-template-columns: 1fr; }
            .content { padding: 20px 16px 40px; }
        }
        @media (max-width: 600px) {
            .search { width: 100%; }
            .search-wrap, .filters { width: 100%; }
            th, td { padding-left: 16px; padding-right: 16px; }
        }
        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<div class="main-area">
    <?php 
    $pageTitle = 'Auditor Dashboard';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($_SESSION['officer_name']) . '</strong>';
    $basePath = '../';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="content">
        <!-- Summary -->
        <div class="summary-cards">
            <div class="summary-card funds">
                <div class="card-top">
                    <span class="label">Total funds</span>
                    <span class="icon"><i class="fas fa-coins"></i></span>
                </div>
                <div class="value">₱<?= number_format($summary['total_funds'], 2) ?></div>
                <div class="foot"><?= (int)$pendingAuditFunds ?> pending audit</div>
            </div>
            <div class="summary-card expenses">
                <div class="card-top">
                    <span class="label">Total expenses</span>
                    <span class="icon"><i class="fas fa-receipt"></i></span>
                </div>
                <div class="value">₱<?= number_format($summary['total_expenses'], 2) ?></div>
                <div class="foot"><?= (int)$pendingAuditExp ?> pending audit</div>
            </div>
            <div class="summary-card balance">
                <div class="card-top">
                    <span class="label">Balance</span>
                    <span class="icon"><i class="fas fa-balance-scale"></i></span>
                </div>
                <div class="value">₱<?= number_format($summary['balance'], 2) ?></div>
                <div class="foot">Funds minus expenses</div>
            </div>
        </div>

        <!-- Primary action -->
        <div class="toolbar">
            <a class="btn-primary" href="review.php"><i class="fas fa-clipboard-check"></i> Go to audit queue</a>
        </div>

        <!-- OVERVIEW -->
        <div id="overview" class="dash-section">
            <div class="two-col">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-coins"></i> Recent pending funds</div>
                        <span class="badge-count"><?= (int)$pendingAuditFunds ?> pending</span>
                    </div>
                    <?php if (count($pendingFunds) === 0): ?>
                        <div class="empty">
                            <i class="fas fa-check"></i>
                            <strong>All caught up</strong>
                            <span>No funds are waiting for audit.</span>
                        </div>
                    <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Source</th><th class="num">Amount</th></tr></thead>
                            <tbody>
                            <?php foreach ($pendingFunds as $f): ?>
                                <tr>
                                    <td class="id-cell">#<?= $f['id'] ?></td>
                                    <td class="cell-main"><?= htmlspecialchars($f['source']) ?></td>
                                    <td class="num">₱<?= number_format($f['amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title expense"><i class="fas fa-receipt"></i> Recent pending expenses</div>
                        <span class="badge-count"><?= (int)$pendingAuditExp ?> pending</span>
                    </div>
                    <?php if (count($pendingExpenses) === 0): ?>
                        <div class="empty">
                            <i class="fas fa-check"></i>
                            <strong>All caught up</strong>
                            <span>No expenses are waiting for audit.</span>
                        </div>
                    <?php else: ?>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Payee</th><th class="num">Amount</th></tr></thead>
                            <tbody>
                            <?php foreach ($pendingExpenses as $e): ?>
                                <tr>
                                    <td class="id-cell">#<?= $e['id'] ?></td>
                                    <td class="cell-main"><?= htmlspecialchars($e['payee']) ?></td>
                                    <td class="num">₱<?= number_format($e['amount'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TRANSACTIONS -->
        <div id="transactions" class="dash-section">
            <div class="card">
                <div class="card-header">
                    <div class="card-title neutral"><i class="fas fa-exchange-alt"></i> Transaction tracking</div>
                    <?php if (count($allTransactions) > 0): ?>
                    <div class="filters">
                        <div class="search-wrap">
                            <i class="fas fa-search"></i>
                            <input type="search" id="txSearch" class="search" placeholder="Search details or ID" aria-label="Search transactions">
                        </div>
                        <select id="txType" class="select" aria-label="Filter by type">
                            <option value="">All types</option>
                            <option value="fund">Funds</option>
                            <option value="expense">Expenses</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <p class="card-sub">Funds and expenses waiting for your audit, plus the ones you have already audited.</p>

                <?php if (count($allTransactions) === 0): ?>
                    <div class="empty">
                        <i class="fas fa-inbox"></i>
                        <strong>No transactions yet</strong>
                        <span>Items to audit will show up here.</span>
                    </div>
                <?php else: ?>
                    <div class="table-wrap" style="margin-top:14px;">
                        <table id="txTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>ID</th>
                                    <th>Details</th>
                                    <th class="num">Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allTransactions as $tx): ?>
                                <tr data-type="<?= strtolower($tx['type']) ?>">
                                    <td class="id-cell"><?= date('M d, Y', strtotime($tx['tx_date'])) ?></td>
                                    <td>
                                        <span class="type-pill <?= strtolower($tx['type']) ?>">
                                            <i class="fas <?= $tx['type'] === 'Fund' ? 'fa-coins' : 'fa-receipt' ?>"></i>
                                            <?= $tx['type'] ?>
                                        </span>
                                    </td>
                                    <td class="id-cell">#<?= $tx['id'] ?></td>
                                    <td class="cell-main"><?= htmlspecialchars($tx['details']) ?></td>
                                    <td class="num">₱<?= number_format($tx['amount'], 2) ?></td>
                                    <td><?= getStatusBadge($tx['status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="empty" id="noMatch">
                        <i class="fas fa-search"></i>
                        <strong>No matching transactions</strong>
                        <span>Try a different search or clear the type filter.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/shared_ui.php'; ?>

<script>
    // Transactions filter
    const txSearch = document.getElementById('txSearch');
    const txType = document.getElementById('txType');
    const txTable = document.getElementById('txTable');
    const noMatch = document.getElementById('noMatch');

    function filterTx() {
        if (!txTable) return;
        const q = txSearch.value.trim().toLowerCase();
        const type = txType.value;
        let visible = 0;
        txTable.querySelectorAll('tbody tr').forEach(row => {
            const matches = (!type || row.dataset.type === type) &&
                            (!q || row.textContent.toLowerCase().includes(q));
            row.style.display = matches ? '' : 'none';
            if (matches) visible++;
        });
        txTable.parentElement.style.display = visible ? '' : 'none';
        noMatch.style.display = visible ? 'none' : 'flex';
    }
    if (txSearch) {
        txSearch.addEventListener('input', filterTx);
        txType.addEventListener('change', filterTx);
    }

    // User menu (from shared UI)
    const userMenu = document.getElementById('userMenu');
    if (userMenu) {
        userMenu.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('show');
        });
        window.addEventListener('click', () => userMenu.classList.remove('show'));
    }
</script>
</body>
</html>