<?php
// includes/report_functions.php
// All PDF report generation functions

require_once __DIR__ . '/lib/fpdf.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ============================================================
// COMMON HEADER — with LOGO and DYNAMIC COLOR
// ============================================================
function printReportHeader($pdf, $title, $subtitle = '') {
    global $pdo;
    $sysSettings = getSettings($pdo);
    
    // Get dynamic color
    $themeColor = $sysSettings['primary_color'] ?? '#1a3c5e';
    list($r, $g, $b) = hexToRgb($themeColor);
    
    // Add logo if exists
    $logoPath = __DIR__ . '/../' . $sysSettings['logo_path'];
    if (file_exists($logoPath)) {
        try {
            $pdf->Image($logoPath, 15, 10, 22, 0);
        } catch (Exception $e) { }
    }

    // Apply dynamic color to Header text
    $pdf->SetTextColor($r, $g, $b);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 8, strtoupper($sysSettings['org_name']) . ' TRANSPARENCY FUND SYSTEM', 0, 1, 'C');
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, $title, 0, 1, 'C');
    
    // Reset text color to black
    $pdf->SetTextColor(0, 0, 0);
    
    if (!empty($subtitle)) {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $subtitle, 0, 1, 'C');
    }
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, 'Generated: ' . date('F d, Y h:i A'), 0, 1, 'C');
    $pdf->Ln(4);
    
    // Apply dynamic color to the horizontal line
    $pdf->SetDrawColor($r, $g, $b);
    $pdf->Cell(0, 0, '', 'T', 1);
    $pdf->Ln(3);
}

// ============================================================
// COMMON TABLE HEADER — with DYNAMIC COLOR
// ============================================================
function printTableHeader($pdf, $columns) {
    global $pdo;
    $sysSettings = getSettings($pdo);
    $themeColor = $sysSettings['primary_color'] ?? '#1a3c5e';
    list($r, $g, $b) = hexToRgb($themeColor);

    $pdf->SetFont('Arial', 'B', 9);
    
    // Apply dynamic color to table header background
    $pdf->SetFillColor($r, $g, $b);
    $pdf->SetTextColor(255, 255, 255); // White text for contrast
    
    foreach ($columns as $col) {
        $pdf->Cell($col['width'], 7, $col['label'], 1, 0, 'L', true);
    }
    $pdf->Ln();
    
    // Reset for the table body
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 9);
}

// ============================================================
// REPORT 1 — SUMMARY REPORT
// ============================================================
function generateSummaryReport($startDate = null, $endDate = null) {
    global $pdo;

    $fundWhere = "f.status = 'approved'";
    $expWhere = "e.status = 'approved'";
    $fundParams = [];
    $expParams = [];

    if ($startDate) {
        $fundWhere .= " AND f.fund_date >= ?";
        $expWhere .= " AND e.expense_date >= ?";
        $fundParams[] = $startDate;
        $expParams[] = $startDate;
    }
    if ($endDate) {
        $fundWhere .= " AND f.fund_date <= ?";
        $expWhere .= " AND e.expense_date <= ?";
        $fundParams[] = $endDate;
        $expParams[] = $endDate;
    }

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(f.amount), 0) FROM funds f WHERE $fundWhere");
    $stmt->execute($fundParams);
    $totalFunds = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE $expWhere");
    $stmt->execute($expParams);
    $totalExpenses = $stmt->fetchColumn();

    $balance = $totalFunds - $totalExpenses;

    $stmt = $pdo->prepare(
        "SELECT c.name AS category, COALESCE(SUM(e.amount), 0) AS total
         FROM expenses e
         JOIN categories c ON e.category_id = c.id
         WHERE $expWhere
         GROUP BY c.name
         ORDER BY total DESC"
    );
    $stmt->execute($expParams);
    $expensesByCategory = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT f.source, COALESCE(SUM(f.amount), 0) AS total
         FROM funds f
         WHERE $fundWhere
         GROUP BY f.source
         ORDER BY total DESC"
    );
    $stmt->execute($fundParams);
    $fundsBySource = $stmt->fetchAll();

    $pdf = new FPDF();
    $pdf->AddPage();

    $subtitle = '';
    if ($startDate || $endDate) {
        $subtitle = 'Period: ' . ($startDate ?: 'Start') . ' to ' . ($endDate ?: 'End');
    }

    printReportHeader($pdf, 'SUMMARY REPORT', $subtitle);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'FINANCIAL SUMMARY', 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(60, 7, 'Total Funds:', 0, 0);
    $pdf->Cell(0, 7, 'PHP ' . number_format($totalFunds, 2), 0, 1);
    $pdf->Cell(60, 7, 'Total Expenses:', 0, 0);
    $pdf->Cell(0, 7, 'PHP ' . number_format($totalExpenses, 2), 0, 1);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(60, 7, 'Remaining Balance:', 0, 0);
    $pdf->Cell(0, 7, 'PHP ' . number_format($balance, 2), 0, 1);
    $pdf->Ln(5);

    if (count($fundsBySource) > 0) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, 'FUNDS BY SOURCE', 0, 1);
        printTableHeader($pdf, [
            ['width' => 130, 'label' => 'Source'],
            ['width' => 60, 'label' => 'Amount'],
        ]);
        foreach ($fundsBySource as $f) {
            $pdf->Cell(130, 6, $f['source'], 1);
            $pdf->Cell(60, 6, 'PHP ' . number_format($f['total'], 2), 1, 0, 'R');
            $pdf->Ln();
        }
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(130, 6, 'TOTAL', 1, 0, 'R');
        $pdf->Cell(60, 6, 'PHP ' . number_format($totalFunds, 2), 1, 0, 'R');
        $pdf->Ln();
        $pdf->Ln(5);
    }

    if (count($expensesByCategory) > 0) {
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, 'EXPENSES BY CATEGORY', 0, 1);
        printTableHeader($pdf, [
            ['width' => 130, 'label' => 'Category'],
            ['width' => 60, 'label' => 'Amount'],
        ]);
        foreach ($expensesByCategory as $e) {
            $pdf->Cell(130, 6, $e['category'], 1);
            $pdf->Cell(60, 6, 'PHP ' . number_format($e['total'], 2), 1, 0, 'R');
            $pdf->Ln();
        }
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(130, 6, 'TOTAL', 1, 0, 'R');
        $pdf->Cell(60, 6, 'PHP ' . number_format($totalExpenses, 2), 1, 0, 'R');
        $pdf->Ln();
    }

    $pdf->Output('D', 'Summary_Report_' . date('Ymd') . '.pdf');
    exit;
}

// ============================================================
// REPORT 2 — DETAILED TRANSACTIONS REPORT
// ============================================================
function generateDetailedReport($startDate = null, $endDate = null) {
    global $pdo;

    $fundWhere = "f.status = 'approved'";
    $expWhere = "e.status = 'approved'";
    $fundParams = [];
    $expParams = [];

    if ($startDate) {
        $fundWhere .= " AND f.fund_date >= ?";
        $expWhere .= " AND e.expense_date >= ?";
        $fundParams[] = $startDate;
        $expParams[] = $startDate;
    }
    if ($endDate) {
        $fundWhere .= " AND f.fund_date <= ?";
        $expWhere .= " AND e.expense_date <= ?";
        $fundParams[] = $endDate;
        $expParams[] = $endDate;
    }

    $stmt = $pdo->prepare("SELECT f.* FROM funds f WHERE $fundWhere ORDER BY f.fund_date DESC");
    $stmt->execute($fundParams);
    $funds = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT e.*, c.name AS category_name
         FROM expenses e
         JOIN categories c ON e.category_id = c.id
         WHERE $expWhere
         ORDER BY e.expense_date DESC"
    );
    $stmt->execute($expParams);
    $expenses = $stmt->fetchAll();

    $totalFunds = array_sum(array_column($funds, 'amount'));
    $totalExpenses = array_sum(array_column($expenses, 'amount'));

    $pdf = new FPDF();
    $pdf->AddPage();

    $subtitle = '';
    if ($startDate || $endDate) {
        $subtitle = 'Period: ' . ($startDate ?: 'Start') . ' to ' . ($endDate ?: 'End');
    }

    printReportHeader($pdf, 'DETAILED TRANSACTIONS REPORT', $subtitle);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'APPROVED FUNDS (' . count($funds) . ')', 0, 1);
    if (count($funds) == 0) {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 6, 'No approved funds.', 0, 1);
    } else {
        printTableHeader($pdf, [
            ['width' => 25, 'label' => 'Date'],
            ['width' => 75, 'label' => 'Source'],
            ['width' => 40, 'label' => 'School Year'],
            ['width' => 50, 'label' => 'Amount'],
        ]);
        foreach ($funds as $f) {
            $pdf->Cell(25, 6, $f['fund_date'], 1);
            $pdf->Cell(75, 6, substr($f['source'], 0, 40), 1);
            $pdf->Cell(40, 6, $f['school_year'] ?? '-', 1);
            $pdf->Cell(50, 6, 'PHP ' . number_format($f['amount'], 2), 1, 0, 'R');
            $pdf->Ln();
        }
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(140, 6, 'TOTAL FUNDS', 1, 0, 'R');
        $pdf->Cell(50, 6, 'PHP ' . number_format($totalFunds, 2), 1, 0, 'R');
        $pdf->Ln();
    }
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'APPROVED EXPENSES (' . count($expenses) . ')', 0, 1);
    if (count($expenses) == 0) {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 6, 'No approved expenses.', 0, 1);
    } else {
        printTableHeader($pdf, [
            ['width' => 25, 'label' => 'Date'],
            ['width' => 50, 'label' => 'Category'],
            ['width' => 65, 'label' => 'Payee'],
            ['width' => 50, 'label' => 'Amount'],
        ]);
        foreach ($expenses as $e) {
            $pdf->Cell(25, 6, $e['expense_date'], 1);
            $pdf->Cell(50, 6, substr($e['category_name'], 0, 25), 1);
            $pdf->Cell(65, 6, substr($e['payee'], 0, 35), 1);
            $pdf->Cell(50, 6, 'PHP ' . number_format($e['amount'], 2), 1, 0, 'R');
            $pdf->Ln();
        }
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(140, 6, 'TOTAL EXPENSES', 1, 0, 'R');
        $pdf->Cell(50, 6, 'PHP ' . number_format($totalExpenses, 2), 1, 0, 'R');
        $pdf->Ln();
    }

    $pdf->Output('D', 'Detailed_Report_' . date('Ymd') . '.pdf');
    exit;
}

// ============================================================
// REPORT 3 — BUDGET VS ACTUAL REPORT
// ============================================================
function generateBudgetVsActualReport() {
    global $pdo;

    $budgets = $pdo->query(
        "SELECT eb.*, e.name AS event_name
         FROM event_budgets eb
         LEFT JOIN events e ON eb.event_id = e.id
         WHERE eb.status = 'approved'
         ORDER BY eb.created_at DESC"
    )->fetchAll();

    $actuals = [];
    $stmt = $pdo->query(
        "SELECT budget_id, SUM(amount) AS total
         FROM expenses
         WHERE status = 'approved' AND budget_id IS NOT NULL
         GROUP BY budget_id"
    );
    foreach ($stmt as $row) {
        $actuals[$row['budget_id']] = $row['total'];
    }

    $items = [];
    $stmt = $pdo->query(
        "SELECT bpi.* FROM budget_plan_items bpi
         JOIN event_budgets eb ON bpi.budget_id = eb.id
         WHERE eb.status = 'approved'"
    );
    foreach ($stmt as $item) {
        $items[$item['budget_id']][] = $item;
    }

    $pdf = new FPDF();
    $pdf->AddPage();
    printReportHeader($pdf, 'BUDGET VS ACTUAL REPORT');

    if (count($budgets) == 0) {
        $pdf->SetFont('Arial', 'I', 11);
        $pdf->Cell(0, 8, 'No approved budgets found.', 0, 1);
    } else {
        foreach ($budgets as $b) {
            $allocated = $b['allocated_amount'];
            $actual = $actuals[$b['id']] ?? 0;
            $variance = $allocated - $actual;

            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 8, 'Budget: ' . $b['budget_name'], 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(50, 6, 'Event:', 0, 0);
            $pdf->Cell(0, 6, $b['event_name'] ?? '-', 0, 1);
            $pdf->Cell(50, 6, 'School Year:', 0, 0);
            $pdf->Cell(0, 6, $b['school_year'] ?? '-', 0, 1);
            $pdf->Cell(50, 6, 'Allocated:', 0, 0);
            $pdf->Cell(0, 6, 'PHP ' . number_format($allocated, 2), 0, 1);
            $pdf->Cell(50, 6, 'Actual:', 0, 0);
            $pdf->Cell(0, 6, 'PHP ' . number_format($actual, 2), 0, 1);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(50, 6, 'Variance:', 0, 0);
            $varianceText = ($variance >= 0)
                ? 'PHP ' . number_format($variance, 2) . ' (Under)'
                : 'PHP ' . number_format(abs($variance), 2) . ' (Over)';
            $pdf->Cell(0, 6, $varianceText, 0, 1);
            $pdf->Ln(2);

            if (!empty($items[$b['id']])) {
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->Cell(0, 6, 'Line Items:', 0, 1);
                printTableHeader($pdf, [
                    ['width' => 80, 'label' => 'Item'],
                    ['width' => 25, 'label' => 'Qty'],
                    ['width' => 40, 'label' => 'Unit Cost'],
                    ['width' => 45, 'label' => 'Total'],
                ]);
                foreach ($items[$b['id']] as $it) {
                    $pdf->Cell(80, 6, substr($it['item_name'], 0, 40), 1);
                    $pdf->Cell(25, 6, $it['quantity'], 1, 0, 'C');
                    $pdf->Cell(40, 6, 'PHP ' . number_format($it['unit_cost'], 2), 1, 0, 'R');
                    $pdf->Cell(45, 6, 'PHP ' . number_format($it['estimated_total'], 2), 1, 0, 'R');
                    $pdf->Ln();
                }
            }
            $pdf->Ln(5);
            $pdf->Cell(0, 0, '', 'T', 1);
            $pdf->Ln(3);
        }
    }

    $pdf->Output('D', 'Budget_vs_Actual_Report_' . date('Ymd') . '.pdf');
    exit;
}

// ============================================================
// REPORT 4 — LIQUIDATION REPORT
// ============================================================
function generateLiquidationReport() {
    global $pdo;

    $liquidations = $pdo->query(
        "SELECT l.*, e.name AS event_name, e.school_year, e.venue,
                o.name AS submitted_by_name,
                a.name AS audited_by_name
         FROM liquidations l
         JOIN events e ON l.event_id = e.id
         JOIN officers o ON l.submitted_by = o.id
         LEFT JOIN officers a ON l.audited_by = a.id
         WHERE l.status IN ('audited_compliant', 'audited_with_findings', 'closed')
         ORDER BY l.created_at DESC"
    )->fetchAll();

    $pdf = new FPDF();
    $pdf->AddPage();
    printReportHeader($pdf, 'LIQUIDATION REPORT');

    if (count($liquidations) == 0) {
        $pdf->SetFont('Arial', 'I', 11);
        $pdf->Cell(0, 8, 'No liquidations found.', 0, 1);
    } else {
        foreach ($liquidations as $l) {
            $variance = $l['variance'];
            $varianceText = ($variance >= 0)
                ? 'PHP ' . number_format($variance, 2) . ' (Under)'
                : 'PHP ' . number_format(abs($variance), 2) . ' (Over)';

            $auditResult = '-';
            if ($l['audit_result'] === 'compliant') $auditResult = 'COMPLIANT';
            elseif ($l['audit_result'] === 'with_findings') $auditResult = 'WITH FINDINGS';

            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 8, 'Event: ' . $l['event_name'], 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(50, 6, 'School Year:', 0, 0);
            $pdf->Cell(0, 6, $l['school_year'] ?? '-', 0, 1);
            $pdf->Cell(50, 6, 'Venue:', 0, 0);
            $pdf->Cell(0, 6, $l['venue'] ?? '-', 0, 1);
            $pdf->Cell(50, 6, 'Submitted By:', 0, 0);
            $pdf->Cell(0, 6, $l['submitted_by_name'], 0, 1);
            $pdf->Cell(50, 6, 'Total Budget:', 0, 0);
            $pdf->Cell(0, 6, 'PHP ' . number_format($l['total_budget'], 2), 0, 1);
            $pdf->Cell(50, 6, 'Actual Expenses:', 0, 0);
            $pdf->Cell(0, 6, 'PHP ' . number_format($l['total_expenses'], 2), 0, 1);
            $pdf->Cell(50, 6, 'Variance:', 0, 0);
            $pdf->Cell(0, 6, $varianceText, 0, 1);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(50, 6, 'Audit Result:', 0, 0);
            $pdf->Cell(0, 6, $auditResult, 0, 1);
            $pdf->SetFont('Arial', '', 10);

            if (!empty($l['audit_notes'])) {
                $pdf->Cell(50, 6, 'Audit Notes:', 0, 0);
                $pdf->MultiCell(0, 6, $l['audit_notes']);
            }
            if (!empty($l['audited_by_name'])) {
                $pdf->Cell(50, 6, 'Audited By:', 0, 0);
                $pdf->Cell(0, 6, $l['audited_by_name'], 0, 1);
            }
            if (!empty($l['notes'])) {
                $pdf->Cell(50, 6, 'Treasurer Notes:', 0, 0);
                $pdf->MultiCell(0, 6, $l['notes']);
            }

            $pdf->Ln(3);
            $pdf->Cell(0, 0, '', 'T', 1);
            $pdf->Ln(3);
        }
    }

    $pdf->Output('D', 'Liquidation_Report_' . date('Ymd') . '.pdf');
    exit;
}

// ============================================================
// REPORT 5 — CUSTOM DATE RANGE REPORT
// ============================================================
function generateCustomDateReport($startDate, $endDate) {
    global $pdo;

    if (empty($startDate) || empty($endDate)) {
        die("Start date and end date are required for Custom Date Range report.");
    }

    $stmt = $pdo->prepare(
        "SELECT f.* FROM funds f
         WHERE f.status = 'approved' AND f.fund_date >= ? AND f.fund_date <= ?
         ORDER BY f.fund_date ASC"
    );
    $stmt->execute([$startDate, $endDate]);
    $funds = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT e.*, c.name AS category_name
         FROM expenses e
         JOIN categories c ON e.category_id = c.id
         WHERE e.status = 'approved' AND e.expense_date >= ? AND e.expense_date <= ?
         ORDER BY e.expense_date ASC"
    );
    $stmt->execute([$startDate, $endDate]);
    $expenses = $stmt->fetchAll();

    $totalFunds = array_sum(array_column($funds, 'amount'));
    $totalExpenses = array_sum(array_column($expenses, 'amount'));

    $pdf = new FPDF();
    $pdf->AddPage();
    printReportHeader($pdf, 'CUSTOM DATE RANGE REPORT', 'Period: ' . $startDate . ' to ' . $endDate);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'SUMMARY', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(60, 6, 'Total Funds:', 0, 0);
    $pdf->Cell(0, 6, 'PHP ' . number_format($totalFunds, 2), 0, 1);
    $pdf->Cell(60, 6, 'Total Expenses:', 0, 0);
    $pdf->Cell(0, 6, 'PHP ' . number_format($totalExpenses, 2), 0, 1);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(60, 6, 'Balance:', 0, 0);
    $pdf->Cell(0, 6, 'PHP ' . number_format($totalFunds - $totalExpenses, 2), 0, 1);
    $pdf->Ln(4);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'FUNDS (' . count($funds) . ')', 0, 1);
    if (count($funds) == 0) {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 6, 'No funds in this range.', 0, 1);
    } else {
        printTableHeader($pdf, [
            ['width' => 30, 'label' => 'Date'],
            ['width' => 100, 'label' => 'Source'],
            ['width' => 60, 'label' => 'Amount'],
        ]);
        foreach ($funds as $f) {
            $pdf->Cell(30, 6, $f['fund_date'], 1);
            $pdf->Cell(100, 6, substr($f['source'], 0, 50), 1);
            $pdf->Cell(60, 6, 'PHP ' . number_format($f['amount'], 2), 1, 0, 'R');
            $pdf->Ln();
        }
    }
    $pdf->Ln(4);

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'EXPENSES (' . count($expenses) . ')', 0, 1);
    if (count($expenses) == 0) {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 6, 'No expenses in this range.', 0, 1);
    } else {
        printTableHeader($pdf, [
            ['width' => 30, 'label' => 'Date'],
            ['width' => 60, 'label' => 'Category'],
            ['width' => 60, 'label' => 'Payee'],
            ['width' => 40, 'label' => 'Amount'],
        ]);
        foreach ($expenses as $e) {
            $pdf->Cell(30, 6, $e['expense_date'], 1);
            $pdf->Cell(60, 6, substr($e['category_name'], 0, 30), 1);
            $pdf->Cell(60, 6, substr($e['payee'], 0, 30), 1);
            $pdf->Cell(40, 6, 'PHP ' . number_format($e['amount'], 2), 1, 0, 'R');
            $pdf->Ln();
        }
    }

    $pdf->Output('D', 'Custom_Range_Report_' . $startDate . '_to_' . $endDate . '.pdf');
    exit;
}

// ============================================================
// REPORT 6 — BUDGET PLAN REPORT (per approved budget)
// ============================================================
function generateBudgetPlanReport($budgetId) {
    global $pdo;
    $sysSettings = getSettings($pdo); // FIX: load settings so $sysSettings is defined for the footer

    $stmt = $pdo->prepare(
    "SELECT eb.*, 
            e.name AS event_name, 
            e.school_year AS event_school_year,
            e.venue, 
            e.event_datetime,
            e.description AS event_description,
            e.purpose AS event_purpose,
            
            bm.name AS bm_name,
            tr.name AS treasurer_name,
            adv.name AS adviser_name
     FROM event_budgets eb
     LEFT JOIN events e ON eb.event_id = e.id
     
     LEFT JOIN officers bm ON eb.created_by = bm.id
     LEFT JOIN officers tr ON eb.reviewed_by = tr.id
     LEFT JOIN adviser_account adv ON eb.adviser_reviewed_by = adv.id
     WHERE eb.id = ?"
);
    $stmt->execute([$budgetId]);
    $budget = $stmt->fetch();

    if (!$budget) {
        die("Budget plan not found.");
    }

    if ($budget['status'] !== 'approved') {
        die("This budget plan is not yet approved. Only approved budget plans can be exported.");
    }

    $itemsStmt = $pdo->prepare("SELECT * FROM budget_plan_items WHERE budget_id = ? ORDER BY id ASC");
    $itemsStmt->execute([$budgetId]);
    $items = $itemsStmt->fetchAll();

    $pdf = new FPDF();
    $pdf->AddPage();

    printReportHeader($pdf, 'BUDGET PLAN REPORT', 'Budget #' . $budgetId);

    // EVENT INFORMATION
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'EVENT INFORMATION', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Event Name:', 0, 0);
    $pdf->Cell(0, 6, $budget['event_name'] ?? '-', 0, 1);
    $pdf->Cell(50, 6, 'School Year:', 0, 0);
    $pdf->Cell(0, 6, $budget['event_school_year'] ?? '-', 0, 1);
    $pdf->Cell(50, 6, 'Venue:', 0, 0);
    $pdf->Cell(0, 6, $budget['venue'] ?? '-', 0, 1);
    if (!empty($budget['event_datetime'])) {
        $pdf->Cell(50, 6, 'Event Date:', 0, 0);
        $pdf->Cell(0, 6, date('F d, Y h:i A', strtotime($budget['event_datetime'])), 0, 1);
    }
    $pdf->Ln(4);

    // BUDGET DETAILS
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'BUDGET DETAILS', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 6, 'Budget Name:', 0, 0);
    $pdf->Cell(0, 6, $budget['budget_name'], 0, 1);
    $pdf->Cell(50, 6, 'Date Submitted:', 0, 0);
    $pdf->Cell(0, 6, date('F d, Y', strtotime($budget['created_at'])), 0, 1);
    if (!empty($budget['notes'])) {
        $pdf->Cell(50, 6, 'Notes:', 0, 0);
        $pdf->MultiCell(0, 6, $budget['notes']);
    }
    $pdf->Ln(4);

    // LINE ITEMS
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'LINE ITEMS', 0, 1);
    printTableHeader($pdf, [
        ['width' => 15, 'label' => '#'],
        ['width' => 90, 'label' => 'Item Name'],
        ['width' => 20, 'label' => 'Qty'],
        ['width' => 30, 'label' => 'Unit Cost'],
        ['width' => 35, 'label' => 'Total'],
    ]);

    $grandTotal = 0;
    $i = 1;
    foreach ($items as $item) {
        $pdf->Cell(15, 6, $i, 1, 0, 'C');
        $pdf->Cell(90, 6, substr($item['item_name'], 0, 50), 1);
        $pdf->Cell(20, 6, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(30, 6, 'PHP ' . number_format($item['unit_cost'], 2), 1, 0, 'R');
        $pdf->Cell(35, 6, 'PHP ' . number_format($item['estimated_total'], 2), 1, 0, 'R');
        $pdf->Ln();
        $grandTotal += $item['estimated_total'];
        $i++;
    }

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(155, 7, 'GRAND TOTAL', 1, 0, 'R');
    $pdf->Cell(35, 7, 'PHP ' . number_format($grandTotal, 2), 1, 0, 'R');
    $pdf->Ln();
    $pdf->Ln(8);

    // APPROVAL SIGNATURES
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 8, 'APPROVAL SIGNATURES', 0, 1);
    $pdf->Ln(2);

   // Created by Mayor (FIRST)
    // $pdf->SetFont('Arial', '', 10);
    // $pdf->Cell(40, 6, 'Created by:', 0, 0);
    // $pdf->SetFont('Arial', 'B', 10);
    // $pdf->Cell(0, 6, $budget['mayor_name'] ?? '-', 0, 1);
    // $pdf->SetFont('Arial', 'I', 9);
    // $pdf->Cell(40, 5, '', 0, 0);
    // $mayorDate = !empty($budget['mayor_reviewed_at']) ? date('F d, Y', strtotime($budget['mayor_reviewed_at'])) : '-';
    // $pdf->Cell(0, 5, 'Mayor | ' . $mayorDate, 0, 1);
    // $pdf->Ln(3);

    // Submitted by
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 6, 'Submitted by:', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, $budget['bm_name'] ?? '-', 0, 1);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(40, 5, '', 0, 0);
    $pdf->Cell(0, 5, 'Business Manager | ' . date('F d, Y', strtotime($budget['created_at'])), 0, 1);
    $pdf->Ln(3);

    // Reviewed by Treasurer
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 6, 'Reviewed by:', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, $budget['treasurer_name'] ?? '-', 0, 1);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(40, 5, '', 0, 0);
    $reviewedDate = !empty($budget['reviewed_at']) ? date('F d, Y', strtotime($budget['reviewed_at'])) : '-';
    $pdf->Cell(0, 5, 'Treasurer | ' . $reviewedDate, 0, 1);
    $pdf->Ln(3);

    // Approved by Adviser
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 6, 'Approved by:', 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, $budget['adviser_name'] ?? '-', 0, 1);
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(40, 5, '', 0, 0);
    $approvedDate = !empty($budget['adviser_reviewed_at']) ? date('F d, Y', strtotime($budget['adviser_reviewed_at'])) : '-';
    $pdf->Cell(0, 5, 'Adviser | ' . $approvedDate, 0, 1);

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 5, 'This is a system-generated report from ' . $sysSettings['org_name'] . '.', 0, 1, 'C');

    $pdf->Output('D', 'Budget_Plan_' . $budgetId . '_' . date('Ymd') . '.pdf');
    exit;
}
?>