<?php
session_start();
require_once 'auth.php';
require_once 'db_config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get date filter values
$start_date = $_GET['start_date'] ?? date('2025-01-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Handle Excel export
if (isset($_GET['export_excel'])) {
    // Get transactions for export
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE transaction_date BETWEEN ? AND ? ORDER BY transaction_date DESC");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = $result->fetch_all(MYSQLI_ASSOC);
    
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="church_transactions_'.date('Y-m-d').'.xls"');
    
    // Start Excel content
    echo "<table border='1'>";
    echo "<tr>
            <th>Date</th>
            <th>Description</th>
            <th>Category</th>
            <th>Subcategory</th>
            <th>Amount</th>
            <th>Type</th>
            <th>Payment Method</th>
            <th>Donor ID</th>
          </tr>";
    
    foreach ($transactions as $transaction) {
        echo "<tr>";
        echo "<td>".htmlspecialchars($transaction['transaction_date'])."</td>";
        echo "<td>".htmlspecialchars($transaction['description'])."</td>";
        echo "<td>".htmlspecialchars($transaction['category'])."</td>";
        echo "<td>".htmlspecialchars($transaction['subcategory'])."</td>";
        echo "<td>".htmlspecialchars($transaction['amount'])."</td>";
        echo "<td>".htmlspecialchars($transaction['transaction_type'])."</td>";
        echo "<td>".htmlspecialchars($transaction['payment_method'] ?? 'Cash')."</td>";
        echo "<td>".htmlspecialchars($transaction['donor_id'] ?? '')."</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    exit();
}

// Handle file upload
$receiptPath = null;
if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/receipts/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $fileExt = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '.' . $fileExt;
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['receipt']['tmp_name'], $filePath)) {
        $receiptPath = $filePath;
    }
}

// Get all transactions grouped by category (filtered by date range)
$transactions_by_category = [];
$stmt = $conn->prepare("SELECT * FROM transactions WHERE transaction_date BETWEEN ? AND ? ORDER BY category, transaction_date DESC");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $transactions_by_category[$row['category']][] = $row;
    }
}

// Calculate totals for each category (filtered by date range)
$category_totals = [];
foreach ($transactions_by_category as $category => $transactions) {
    $income = 0;
    $expense = 0;
    
    foreach ($transactions as $transaction) {
        if ($transaction['transaction_type'] === 'income') {
            $income += $transaction['amount'];
        } else {
            $expense += $transaction['amount'];
        }
    }
    
    $category_totals[$category] = [
        'income' => $income,
        'expense' => $expense,
        'profit' => $income - $expense
    ];
}

// Get budgets
$budgets = [];
$result = $conn->query("SELECT * FROM budgets");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $budgets[$row['category']] = $row['amount'];
    }
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Church Accounting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --primary-light: #34495e;
            --secondary: #2980b9;
            --accent: #16a085;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gray: #95a5a6;
            --light-gray: #bdc3c7;
            --white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --radius: 6px;
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f9f9f9;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: var(--white);
            padding: 15px 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo i {
            font-size: 1.8rem;
            color: var(--accent);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background-color: var(--accent);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: #1abc9c;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--success);
            color: var(--white);
        }
        
        .btn-success:hover {
            background-color: #2ecc71;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-top: 4px solid var(--accent);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            font-size: 1rem;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .stat-card .value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .stat-card .trend {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .trend.up {
            color: var(--success);
        }
        
        .trend.down {
            color: var(--danger);
        }
        
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .budget-summary {
            background: var(--white);
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            border-left: 4px solid var(--accent);
        }
        
        .budget-summary h2 {
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }
        
        .budget-item {
            margin-bottom: 20px;
        }
        
        .budget-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .budget-title {
            font-weight: 500;
            color: var(--dark);
        }
        
        .budget-amount {
            font-weight: 600;
            color: var(--dark);
        }
        
        .progress-bar {
            height: 10px;
            background: var(--light-gray);
            border-radius: 5px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--secondary));
            transition: width 0.5s ease;
        }
        
        .category-section {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
            transition: var(--transition);
            border-left: 4px solid var(--accent);
        }
        
        .category-section:hover {
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .category-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            padding: 15px 20px;
            color: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }
        
        .category-title {
            font-size: 1.1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .category-summary {
            display: flex;
            gap: 25px;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-label {
            font-size: 0.8rem;
            opacity: 0.8;
            color: rgba(255,255,255,0.8);
        }
        
        .summary-value {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--white);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--gray);
        }
        
        tr:hover {
            background-color: rgba(22, 160, 133, 0.05);
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: rgba(39, 174, 96, 0.2);
            color: var(--success);
        }
        
        .badge-danger {
            background-color: rgba(231, 76, 60, 0.2);
            color: var(--danger);
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .action-btn.edit {
            background-color: rgba(41, 128, 185, 0.1);
            color: var(--secondary);
        }
        
        .action-btn.delete {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--danger);
        }
        
        .action-btn.view {
            background-color: rgba(22, 160, 133, 0.1);
            color: var(--accent);
        }
        
        .action-btn:hover {
            transform: scale(1.1);
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: rgba(39, 174, 96, 0.2);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--light-gray);
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }
        
        .modal-content {
            display: block;
            margin: auto;
            max-width: 80%;
            max-height: 80vh;
        }
        
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: white;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .text-muted {
            color: var(--gray);
            font-size: 0.85rem;
        }
        
        /* Dashboard header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .dashboard-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark);
        }
        
        .dashboard-subtitle {
            font-size: 1rem;
            color: var(--gray);
            margin-top: 5px;
        }
        
        /* Date filter */
        .date-filter {
            display: flex;
            gap: 10px;
            align-items: center;
            background: var(--white);
            padding: 10px 15px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .date-filter label {
            font-weight: 500;
            color: var(--gray);
        }
        
        .date-filter input {
            padding: 8px 12px;
            border: 1px solid var(--light-gray);
            border-radius: var(--radius);
            font-family: 'Poppins', sans-serif;
        }
        
        .date-filter button {
            padding: 8px 15px;
            background-color: var(--accent);
            color: white;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .date-filter button:hover {
            background-color: #1abc9c;
        }
        
        @media (max-width: 768px) {
            .quick-stats {
                grid-template-columns: 1fr;
            }
            
            .category-summary {
                flex-direction: column;
                gap: 10px;
                align-items: flex-end;
            }
            
            .summary-item {
                text-align: right;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .user-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .date-filter {
                width: 100%;
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="logo">
            <i class="fas fa-church"></i>
            <span>City Mission Church Accounting</span>
        </div>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
            <a href="?logout" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
</header>

<div class="container">
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-title">Financial Dashboard</h1>
            <p class="dashboard-subtitle">Overview of church finances and transactions</p>
        </div>
        <form method="GET" class="date-filter">
            <div>
                <label for="start_date">From:</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div>
                <label for="end_date">To:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <button type="submit">Filter</button>
            <a href="?export_excel&start_date=<?php echo htmlspecialchars($start_date); ?>&end_date=<?php echo htmlspecialchars($end_date); ?>" class="btn btn-success">
                <i class="fas fa-file-excel"></i>
                Export
            </a>
        </form>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <div class="quick-stats">
        <div class="stat-card">
            <h3>Total Income</h3>
            <div class="value">Rs. <?php 
                $total_income = array_sum(array_column($category_totals, 'income'));
                echo number_format($total_income, 2); 
            ?></div>
            <div class="trend up">
                <i class="fas fa-arrow-up"></i>
                <span>12% from last month</span>
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Total Expenses</h3>
            <div class="value">Rs. <?php 
                $total_expense = array_sum(array_column($category_totals, 'expense'));
                echo number_format($total_expense, 2); 
            ?></div>
            <div class="trend down">
                <i class="fas fa-arrow-down"></i>
                <span>8% from last month</span>
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Net Balance</h3>
            <div class="value">Rs. <?php 
                $net_balance = $total_income - $total_expense;
                echo number_format($net_balance, 2); 
            ?></div>
            <div class="trend <?php echo $net_balance >= 0 ? 'up' : 'down'; ?>">
                <i class="fas fa-<?php echo $net_balance >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                <span><?php echo $net_balance >= 0 ? 'Positive' : 'Negative'; ?> balance</span>
            </div>
        </div>
        
        <div class="stat-card">
            <h3>Active Categories</h3>
            <div class="value"><?php echo count($transactions_by_category); ?></div>
            <div class="trend up">
                <i class="fas fa-layer-group"></i>
                <span>Manage categories</span>
            </div>
        </div>
    </div>
    
    <div class="quick-links">
        <a href="add_transaction.php" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            <span>Add Transaction</span>
        </a>
        <a href="donors.php" class="btn btn-outline">
            <i class="fas fa-users"></i>
            <span>Donor Reports</span>
        </a>
        <a href="budgets.php" class="btn btn-outline">
            <i class="fas fa-wallet"></i>
            <span>Manage Budgets</span>
        </a>
        <a href="reports.php" class="btn btn-outline">
            <i class="fas fa-chart-pie"></i>
            <span>Financial Reports</span>
        </a>
    </div>
    
    <?php if (!empty($budgets)): ?>
    <div class="budget-summary">
        <h2><i class="fas fa-wallet"></i> Budget Utilization</h2>
        <?php foreach ($category_totals as $category => $totals): ?>
            <?php if (isset($budgets[$category])): ?>
            <div class="budget-item">
                <div class="budget-header">
                    <span class="budget-title"><?php echo htmlspecialchars($category); ?></span>
                    <span class="budget-amount">Rs. <?php echo number_format($totals['expense'], 2); ?> of Rs. <?php echo number_format($budgets[$category], 2); ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, ($totals['expense']/$budgets[$category])*100); ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($transactions_by_category)): ?>
        <?php foreach ($transactions_by_category as $category => $transactions): ?>
        <div class="category-section">
            <div class="category-header">
                <div class="category-title">
                    <i class="fas fa-folder"></i>
                    <span><?php echo htmlspecialchars($category); ?></span>
                </div>
                <div class="category-summary">
                    <div class="summary-item">
                        <div class="summary-label">Income</div>
                        <div class="summary-value">Rs. <?php echo number_format($category_totals[$category]['income'], 2); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Expenses</div>
                        <div class="summary-value">Rs. <?php echo number_format($category_totals[$category]['expense'], 2); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Balance</div>
                        <div class="summary-value">Rs. <?php echo number_format($category_totals[$category]['profit'], 2); ?></div>
                    </div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Subcategory</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Payment</th>
                        <th>Receipt</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td>
                            <div><?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?></div>
                            <div class="text-muted"><?php echo date('h:i A', strtotime($transaction['created_at'])); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['subcategory']); ?></td>
                        <td>Rs. <?php echo number_format($transaction['amount'], 2); ?></td>
                        <td>
                            <span class="badge <?php echo $transaction['transaction_type'] === 'income' ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo ucfirst($transaction['transaction_type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($transaction['payment_method'] ?? 'Cash'); ?></td>
                        <td>
                            <?php if (!empty($transaction['receipt_path'])): ?>
                                <a href="<?php echo htmlspecialchars($transaction['receipt_path']); ?>" 
                                   target="_blank" 
                                   class="action-btn view"
                                   title="View Receipt">
                                    <i class="fas fa-receipt"></i>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" class="action-btn edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="id" value="<?php echo $transaction['id']; ?>">
                                    <input type="hidden" name="delete_transaction" value="1">
                                    <button type="submit" class="action-btn delete" onclick="return confirm('Are you sure you want to delete this transaction?');">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice-dollar"></i>
            <h3>No Transactions Found</h3>
            <p>Get started by adding your first transaction</p>
            <a href="add_transaction.php" class="btn btn-primary">Add Transaction</a>
        </div>
    <?php endif; ?>
</div>

<div id="receiptModal" class="modal">
    <span class="close">&times;</span>
    <img class="modal-content" id="receiptImage">
    <iframe id="receiptPdf" style="display:none; width:100%; height:80vh;" frameborder="0"></iframe>
</div>

<script>
// Get the modal
const modal = document.getElementById('receiptModal');
const modalImg = document.getElementById('receiptImage');
const pdfFrame = document.getElementById('receiptPdf');
const closeBtn = document.querySelector('.close');

document.querySelectorAll('.action-btn.view').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const receiptPath = this.getAttribute('href');
        
        if (receiptPath.endsWith('.pdf')) {
            modalImg.style.display = 'none';
            pdfFrame.style.display = 'block';
            pdfFrame.src = receiptPath;
        } else {
            pdfFrame.style.display = 'none';
            modalImg.style.display = 'block';
            modalImg.src = receiptPath;
        }
        
        modal.style.display = 'block';
    });
});

closeBtn.addEventListener('click', function() {
    modal.style.display = 'none';
    pdfFrame.src = '';
});

// Close modal when clicking outside the content
window.addEventListener('click', function(event) {
    if (event.target === modal) {
        modal.style.display = 'none';
        pdfFrame.src = '';
    }
});
</script>
</body>
</html>
<?php
if (isset($_GET['logout'])) {
    logout();
    header("Location: login.php");
    exit();
}
?>