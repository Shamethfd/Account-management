<?php
session_start();
require_once 'auth.php';
require_once 'db_config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get parameters from request
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get transactions for export
$stmt = $conn->prepare("SELECT 
    DATE_FORMAT(transaction_date, '%Y-%m-%d') AS 'Date',
    description AS 'Description',
    category AS 'Category',
    subcategory AS 'Subcategory',
    amount AS 'Amount',
    transaction_type AS 'Type',
    IFNULL(payment_method, 'Cash') AS 'Payment Method',
    IFNULL(donor_id, '') AS 'Donor ID'
FROM transactions 
WHERE transaction_date BETWEEN ? AND ? 
ORDER BY transaction_date DESC");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

// Create a temporary file
$filename = tempnam(sys_get_temp_dir(), 'excel');
$handle = fopen($filename, 'w');

// Add UTF-8 BOM
fputs($handle, "\xEF\xBB\xBF");

// Write headers
if ($result->num_rows > 0) {
    $firstRow = $result->fetch_assoc();
    fputcsv($handle, array_keys($firstRow));
    fputcsv($handle, $firstRow);
    
    // Write remaining rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($handle, $row);
    }
} else {
    // Write empty file with headers
    $headers = [
        'Date', 'Description', 'Category', 'Subcategory', 
        'Amount', 'Type', 'Payment Method', 'Donor ID'
    ];
    fputcsv($handle, $headers);
}

fclose($handle);

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="church_transactions_'.date('Y-m-d').'.csv"');
header('Cache-Control: max-age=0');

// Send the file
readfile($filename);

// Clean up
unlink($filename);
exit();
?>