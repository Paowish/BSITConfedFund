<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/report_functions.php';
require_once '../includes/functions.php';

// Allow Adviser or Officer only
if (!isset($_SESSION['adviser_id']) && !isset($_SESSION['officer_id'])) {
    header('Location: ../login.php');
    exit;
}

// Determine user info
if (isset($_SESSION['adviser_id'])) {
    $userName = $_SESSION['adviser_name'];
    $cssFile = 'adviser.css';
} else {
    $userName = $_SESSION['officer_name'];
    $position = $_SESSION['officer_position'] ?? '';
    if ($position === 'Auditor') {
        $cssFile = 'auditor.css';
    } else {
        $cssFile = 'officer.css';
    }
}

// Handle direct GET link (from Budget Approvals "Generate PDF")
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['report']) && $_GET['report'] === 'budget_plan' && isset($_GET['id'])) {
    $budgetId = (int)$_GET['id'];
    if ($budgetId > 0) {
        generateBudgetPlanReport($budgetId);
    }
}

$message = '';
$error = '';

// Handle report generation (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportType = $_POST['report_type'] ?? '';
    $startDate = $_POST['start_date'] ?? null;
    $endDate = $_POST['end_date'] ?? null;
    $budgetId = (int)($_POST['budget_id'] ?? 0);

    if (empty($startDate)) $startDate = null;
    if (empty($endDate)) $endDate = null;

    if (empty($reportType)) {
        $error = "Please select a report type.";
    } else {
        switch ($reportType) {
            case 'summary':
                generateSummaryReport($startDate, $endDate);
                break;
            case 'detailed':
                generateDetailedReport($startDate, $endDate);
                break;
            case 'budget_vs_actual':
                generateBudgetVsActualReport();
                break;
            case 'liquidation':
                generateLiquidationReport();
                break;
            case 'custom_range':
                if (empty($startDate) || empty($endDate)) {
                    $error = "Custom Date Range report requires both Start Date and End Date.";
                } else {
                    generateCustomDateReport($startDate, $endDate);
                }
                break;
            case 'budget_plan':
                if ($budgetId <= 0) {
                    $error = "Please select a budget plan.";
                } else {
                    generateBudgetPlanReport($budgetId);
                }
                break;
            default:
                $error = "Invalid report type.";
        }
    }
}

// Fetch approved budgets for dropdown
$approvedBudgets = $pdo->query(
    "SELECT eb.id, eb.budget_name, eb.allocated_amount, e.name AS event_name
     FROM event_budgets eb
     LEFT JOIN events e ON eb.event_id = e.id
     WHERE eb.status = 'approved'
     ORDER BY eb.adviser_reviewed_at DESC, eb.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Download Reports - BSIT Fund System</title>
    <link rel="stylesheet" href="../assets/css/<?= $cssFile ?>">
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
        .card h3 {
            margin-top: 0;
            color: #1a3c5e;
            font-size: 18px;
            margin-bottom: 20px;
        }
        form label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            font-size: 14px;
            color: #333;
        }
        form select,
        form input[type="date"] {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
            background: #fafafa;
            box-sizing: border-box;
        }
        form select:focus,
        form input[type="date"]:focus {
            outline: none;
            border-color: #1a3c5e;
            box-shadow: 0 0 0 2px rgba(26,60,94,0.15);
            background: #fff;
        }
        .btn-generate {
            background: #1a3c5e;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-generate:hover { background: #2c5282; }
        .success { color: #27ae60; }
        .error { color: #e74c3c; }
        .info-box {
            background: #e8f0f8;
            border-left: 4px solid #2980b9;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #2c3e50;
        }
        .info-box strong { color: #1a3c5e; }
        .date-range-row {
            display: flex;
            gap: 15px;
        }
        .date-range-row > div { flex: 1; }
    </style>
</head>
<body>

<!-- ========== SHARED SIDEBAR ========== -->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/sidebar.php'; ?>

<!-- ========== MAIN AREA ========== -->
<div class="main-area">
    <?php 
    $pageTitle = 'Reports';
    $pageSubtitle = 'Welcome, <strong>' . htmlspecialchars($userName) . '</strong>';
    $basePath = '';
    include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/topbar.php'; 
    ?>

    <div class="page-content">
        <?php if ($message): ?><p class="success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

        <div class="info-box">
            <strong><i class="fas fa-file-pdf"></i> PDF Reports</strong><br>
            Select a report type and filters, then click <strong>Generate PDF</strong>. 
            The PDF will download automatically.
        </div>

        <div class="card">
            <h3><i class="fas fa-file-pdf"></i> Generate Report</h3>
            <form method="POST">
                <label>Report Type <span style="color:red;">*</span></label>
                <select name="report_type" id="reportType" required>
                    <option value="">-- Select Report Type --</option>
                    <option value="summary">Summary Report</option>
                    <option value="detailed">Detailed Transactions Report</option>
                    <option value="budget_vs_actual">Budget vs Actual Report</option>
                    <option value="budget_plan">Budget Plan Report (Approved)</option>
                    <option value="liquidation">Liquidation Report</option>
                    <option value="custom_range">Custom Date Range Report</option>
                </select>

                <div id="dateRangeSection" style="display:none;">
                    <div class="date-range-row">
                        <div>
                            <label>Start Date</label>
                            <input type="date" name="start_date" id="startDate">
                        </div>
                        <div>
                            <label>End Date</label>
                            <input type="date" name="end_date" id="endDate">
                        </div>
                    </div>
                    <p style="font-size:13px;color:#666;margin-top:-10px;">
                        <em>Custom Date Range report requires both Start Date and End Date.</em><br>
                        <em>Summary and Detailed reports — dates are optional.</em>
                    </p>
                </div>

                <div id="budgetPlanSection" style="display:none;">
                    <label>Select Approved Budget Plan <span style="color:red;">*</span></label>
                    <select name="budget_id" id="budgetId">
                        <option value="">-- Select Budget Plan --</option>
                        <?php foreach ($approvedBudgets as $b): ?>
                            <option value="<?= $b['id'] ?>">
                                #<?= $b['id'] ?> — <?= htmlspecialchars($b['event_name'] ?? $b['budget_name']) ?> 
                                (₱<?= number_format($b['allocated_amount'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (count($approvedBudgets) == 0): ?>
                        <p style="font-size:13px; color:#e74c3c; margin-top:-10px;">
                            <i class="fas fa-info-circle"></i> 
                            No approved budget plans yet. Approve a budget in Budget Approvals first.
                        </p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-generate">
                    <i class="fas fa-download"></i> Generate PDF
                </button>
            </form>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/bsit-fund-system-v2/includes/shared_ui.php'; ?>

<script>
    const reportType = document.getElementById('reportType');
    const dateRangeSection = document.getElementById('dateRangeSection');
    const budgetPlanSection = document.getElementById('budgetPlanSection');
    const startDate = document.getElementById('startDate');
    const endDate = document.getElementById('endDate');
    const budgetId = document.getElementById('budgetId');

    reportType.addEventListener('change', function() {
        const val = this.value;

        // Reset visibility
        dateRangeSection.style.display = 'none';
        budgetPlanSection.style.display = 'none';
        startDate.required = false;
        endDate.required = false;
        if (budgetId) budgetId.required = false;

        if (val === 'summary' || val === 'detailed' || val === 'custom_range') {
            dateRangeSection.style.display = 'block';
            if (val === 'custom_range') {
                startDate.required = true;
                endDate.required = true;
            }
        } else if (val === 'budget_plan') {
            budgetPlanSection.style.display = 'block';
            if (budgetId) budgetId.required = true;
        }
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