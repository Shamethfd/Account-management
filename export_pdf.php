<?php
session_start();
require_once 'auth.php';
require_once 'db_config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Include the TCPDF library
require_once('tcpdf/tcpdf.php');

// Get date range from request
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get all transactions within date range
$transactions = [];
$stmt = $conn->prepare("SELECT * FROM transactions WHERE transaction_date BETWEEN ? AND ? ORDER BY transaction_date DESC");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $transactions = $result->fetch_all(MYSQLI_ASSOC);
}

// Calculate summary statistics
$summary = [
    'total_income' => 0,
    'total_expense' => 0,
    'net_balance' => 0,
    'by_category' => [],
    'by_payment_method' => [],
    'by_donor' => []
];

foreach ($transactions as $transaction) {
    $amount = $transaction['amount'];
    $category = $transaction['category'];
    $type = $transaction['transaction_type'];
    $payment_method = $transaction['payment_method'] ?? 'Cash';
    $donor_id = $transaction['donor_id'] ?? null;
    
    if ($type === 'income') {
        $summary['total_income'] += $amount;
    } else {
        $summary['total_expense'] += $amount;
    }
    
    // Group by category
    if (!isset($summary['by_category'][$category])) {
        $summary['by_category'][$category] = ['income' => 0, 'expense' => 0];
    }
    $summary['by_category'][$category][$type] += $amount;
    
    // Group by payment method
    if (!isset($summary['by_payment_method'][$payment_method])) {
        $summary['by_payment_method'][$payment_method] = ['income' => 0, 'expense' => 0];
    }
    $summary['by_payment_method'][$payment_method][$type] += $amount;
    
    // Group by donor if exists
    if ($donor_id) {
        if (!isset($summary['by_donor'][$donor_id])) {
            $summary['by_donor'][$donor_id] = ['name' => 'Unknown', 'amount' => 0];
        }
        $summary['by_donor'][$donor_id]['amount'] += $amount;
    }
}

// Get donor names
if (!empty($summary['by_donor'])) {
    $donor_ids = array_keys($summary['by_donor']);
    $placeholders = implode(',', array_fill(0, count($donor_ids), '?'));
    $stmt = $conn->prepare("SELECT id, name FROM donors WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($donor_ids)), ...$donor_ids);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $summary['by_donor'][$row['id']]['name'] = $row['name'];
    }
}

$summary['net_balance'] = $summary['total_income'] - $summary['total_expense'];

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Church Accounting System');
$pdf->SetAuthor('Church Admin');
$pdf->SetTitle('Financial Report - ' . date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date)));
$pdf->SetSubject('Financial Report');
$pdf->SetKeywords('Church, Finance, Report');

// Set default header data
$pdf->SetHeaderData('', 0, 'Church Accounting System', 'Financial Report: ' . date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date)));

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 15, 'Financial Summary', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);

// Summary cards
$pdf->SetFillColor(240, 240, 240);
$pdf->SetTextColor(0);

// Create summary table
$summary_table = '<table border="1" cellpadding="4">
    <tr>
        <th width="25%" style="background-color:#e6e6e6;font-weight:bold;">Total Income</th>
        <th width="25%" style="background-color:#e6e6e6;font-weight:bold;">Total Expenses</th>
        <th width="25%" style="background-color:#e6e6e6;font-weight:bold;">Net Balance</th>
        <th width="25%" style="background-color:#e6e6e6;font-weight:bold;">Date Range</th>
    </tr>
    <tr>
        <td>Rs. ' . number_format($summary['total_income'], 2) . '</td>
        <td>Rs. ' . number_format($summary['total_expense'], 2) . '</td>
        <td>Rs. ' . number_format($summary['net_balance'], 2) . '</td>
        <td>' . date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date)) . '</td>
    </tr>
</table>';

$pdf->writeHTML($summary_table, true, false, false, false, '');

// Add space
$pdf->Ln(10);

// Income vs Expenses
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Income vs Expenses', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$income_expense_table = '<table border="1" cellpadding="4">
    <tr>
        <th width="50%" style="background-color:#e6e6e6;font-weight:bold;">Type</th>
        <th width="50%" style="background-color:#e6e6e6;font-weight:bold;">Amount (Rs.)</th>
    </tr>
    <tr>
        <td>Income</td>
        <td>Rs. ' . number_format($summary['total_income'], 2) . '</td>
    </tr>
    <tr>
        <td>Expenses</td>
        <td>Rs. ' . number_format($summary['total_expense'], 2) . '</td>
    </tr>
</table>';

$pdf->writeHTML($income_expense_table, true, false, false, false, '');

// Add space
$pdf->Ln(10);

// Transactions by Category
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Transactions by Category', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$category_table = '<table border="1" cellpadding="4">
    <tr>
        <th width="30%" style="background-color:#e6e6e6;font-weight:bold;">Category</th>
        <th width="20%" style="background-color:#e6e6e6;font-weight:bold;">Income</th>
        <th width="20%" style="background-color:#e6e6e6;font-weight:bold;">Expenses</th>
        <th width="30%" style="background-color:#e6e6e6;font-weight:bold;">Balance</th>
    </tr>';

foreach ($summary['by_category'] as $category => $data) {
    $balance = $data['income'] - $data['expense'];
    $category_table .= '
    <tr>
        <td>' . htmlspecialchars($category) . '</td>
        <td>Rs. ' . number_format($data['income'], 2) . '</td>
        <td>Rs. ' . number_format($data['expense'], 2) . '</td>
        <td>Rs. ' . number_format($balance, 2) . '</td>
    </tr>';
}

$category_table .= '</table>';
$pdf->writeHTML($category_table, true, false, false, false, '');

// Add space
$pdf->Ln(10);

// Payment Methods
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Payment Methods', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$payment_table = '<table border="1" cellpadding="4">
    <tr>
        <th width="50%" style="background-color:#e6e6e6;font-weight:bold;">Method</th>
        <th width="50%" style="background-color:#e6e6e6;font-weight:bold;">Total Amount (Rs.)</th>
    </tr>';

foreach ($summary['by_payment_method'] as $method => $data) {
    $total = $data['income'] + $data['expense'];
    $payment_table .= '
    <tr>
        <td>' . htmlspecialchars($method) . '</td>
        <td>Rs. ' . number_format($total, 2) . '</td>
    </tr>';
}

$payment_table .= '</table>';
$pdf->writeHTML($payment_table, true, false, false, false, '');

// Add space
$pdf->Ln(10);

// Top Donors
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Top Donors', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$donor_table = '<table border="1" cellpadding="4">
    <tr>
        <th width="50%" style="background-color:#e6e6e6;font-weight:bold;">Donor Name</th>
        <th width="50%" style="background-color:#e6e6e6;font-weight:bold;">Total Donated (Rs.)</th>
    </tr>';

// Sort donors by amount
$donor_amounts = [];
foreach ($summary['by_donor'] as $donor_id => $donor_info) {
    $donor_amounts[$donor_info['name']] = $donor_info['amount'];
}
arsort($donor_amounts);

$count = 0;
foreach ($donor_amounts as $name => $amount) {
    if ($count++ >= 5) break;
    $donor_table .= '
    <tr>
        <td>' . htmlspecialchars($name) . '</td>
        <td>Rs. ' . number_format($amount, 2) . '</td>
    </tr>';
}

$donor_table .= '</table>';
$pdf->writeHTML($donor_table, true, false, false, false, '');

// Add space
$pdf->Ln(10);

// Transaction Details
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Transaction Details', 0, 1);
$pdf->SetFont('helvetica', '', 10);

$transactions_table = '<table border="1" cellpadding="4">
    <tr>
        <th width="15%" style="background-color:#e6e6e6;font-weight:bold;">Date</th>
        <th width="20%" style="background-color:#e6e6e6;font-weight:bold;">Description</th>
        <th width="15%" style="background-color:#e6e6e6;font-weight:bold;">Category</th>
        <th width="15%" style="background-color:#e6e6e6;font-weight:bold;">Subcategory</th>
        <th width="15%" style="background-color:#e6e6e6;font-weight:bold;">Amount (Rs.)</th>
        <th width="10%" style="background-color:#e6e6e6;font-weight:bold;">Type</th>
        <th width="10%" style="background-color:#e6e6e6;font-weight:bold;">Payment</th>
    </tr>';

foreach ($transactions as $transaction) {
    $transactions_table .= '
    <tr>
        <td>' . date('M j, Y', strtotime($transaction['transaction_date'])) . '</td>
        <td>' . htmlspecialchars($transaction['description']) . '</td>
        <td>' . htmlspecialchars($transaction['category']) . '</td>
        <td>' . htmlspecialchars($transaction['subcategory']) . '</td>
        <td>Rs. ' . number_format($transaction['amount'], 2) . '</td>
        <td>' . ucfirst($transaction['transaction_type']) . '</td>
        <td>' . htmlspecialchars($transaction['payment_method'] ?? 'Cash') . '</td>
    </tr>';
}

$transactions_table .= '</table>';
$pdf->writeHTML($transactions_table, true, false, false, false, '');

// Close and output PDF document
$pdf->Output('financial_report_' . date('Y-m-d') . '.pdf', 'D');
?>