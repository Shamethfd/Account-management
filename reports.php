<?php
session_start();
require_once 'auth.php';
require_once 'db_config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get date range from request or default to current month
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Church Accounting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --radius: 8px;
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7ff;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
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
            background-color: var(--light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
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
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--success);
            color: var(--white);
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
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
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .report-title {
            font-size: 1.8rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .date-filter {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--white);
            padding: 10px 15px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .date-filter input {
            padding: 8px;
            border: 1px solid var(--light-gray);
            border-radius: var(--radius);
        }
        
        .date-filter button {
            padding: 8px 15px;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: var(--white);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
        }
        
        .summary-card h3 {
            font-size: 1rem;
            color: var(--gray);
            margin-bottom: 10px;
        }
        
        .summary-card .value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .income-value {
            color: var(--success);
        }
        
        .expense-value {
            color: var(--danger);
        }
        
        .balance-value {
            color: var(--primary);
        }
        
        .chart-container {
            background: var(--white);
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .chart-container h2 {
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .chart-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-wrapper {
            height: 350px;
            position: relative;
        }
        
        .data-table {
            background: var(--white);
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--light-gray);
        }
        
        th {
            background-color: var(--light);
            font-weight: 600;
            color: var(--gray);
        }
        
        tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: rgba(76, 201, 240, 0.2);
            color: var(--success);
        }
        
        .badge-danger {
            background-color: rgba(247, 37, 133, 0.2);
            color: var(--danger);
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .export-options {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .chart-row {
                grid-template-columns: 1fr;
            }
            
            .date-filter {
                flex-direction: column;
                align-items: stretch;
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

/* Specific style for back button in header */
header .btn-outline {
    color: var(--white);
    border-color: var(--white);
}

header .btn-outline:hover {
    background-color: rgba(255,255,255,0.2);
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
            <a href="javascript:history.back()" class="btn btn-outline" style="color: white; border-color: white;">
                <i class="fas fa-arrow-left"></i>
                <span>Back</span>
            </a>
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
        <div class="quick-links">
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="add_transaction.php" class="btn btn-outline">
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
        </div>
        
        <div class="report-header">
            <h1 class="report-title">
                <i class="fas fa-chart-line"></i>
                Financial Reports
            </h1>
            <form class="date-filter" method="GET">
                <div>
                    <label for="start_date">From:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div>
                    <label for="end_date">To:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i>
                    Filter
                </button>
                <a href="reports.php" class="btn btn-outline">
                    <i class="fas fa-sync"></i>
                    Reset
                </a>
            </form>
        </div>
        
        <!-- In the export options section -->
        <div class="export-options">
    <button class="btn btn-success" onclick="window.print()">
        <i class="fas fa-print"></i>
        Print Report
    </button>
    <a href="export_excel.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-outline">
        <i class="fas fa-file-excel"></i>
        Export to Excel (EXL)
    </a>
    <a href="export_pdf.php?start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-outline">
        <i class="fas fa-file-pdf"></i>
        Export to PDF
    </a>
</div>
        
        <div class="summary-cards">
            <div class="summary-card">
                <h3>Total Income</h3>
                <div class="value income-value">Rs. <?php echo number_format($summary['total_income'], 2); ?></div>
                <small>From <?php echo count($transactions); ?> transactions</small>
            </div>
            
            <div class="summary-card">
                <h3>Total Expenses</h3>
                <div class="value expense-value">Rs. <?php echo number_format($summary['total_expense'], 2); ?></div>
                <small>From <?php echo count($transactions); ?> transactions</small>
            </div>
            
            <div class="summary-card">
                <h3>Net Balance</h3>
                <div class="value balance-value">Rs. <?php echo number_format($summary['net_balance'], 2); ?></div>
                <small>
                    <?php if ($summary['net_balance'] >= 0): ?>
                        <span style="color: var(--success);">Positive Balance</span>
                    <?php else: ?>
                        <span style="color: var(--danger);">Negative Balance</span>
                    <?php endif; ?>
                </small>
            </div>
            
            <div class="summary-card">
                <h3>Date Range</h3>
                <div class="value"><?php echo date('M j, Y', strtotime($start_date)); ?> to <?php echo date('M j, Y', strtotime($end_date)); ?></div>
                <small><?php echo (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1; ?> days</small>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-container">
                <h2><i class="fas fa-chart-pie"></i> Income vs Expenses</h2>
                <div class="chart-wrapper">
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h2><i class="fas fa-chart-bar"></i> Transactions by Category</h2>
                <div class="chart-wrapper">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-container">
                <h2><i class="fas fa-money-bill-wave"></i> Payment Methods</h2>
                <div class="chart-wrapper">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h2><i class="fas fa-users"></i> Top Donors</h2>
                <div class="chart-wrapper">
                    <canvas id="donorChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="data-table">
            <h2><i class="fas fa-table"></i> Transaction Details</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Subcategory</th>
                        <th class="text-right">Amount (Rs.)</th>
                        <th>Type</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($transaction['transaction_date'])); ?></td>
                            <td><?php echo $transaction['description']; ?></td>
                            <td><?php echo $transaction['category']; ?></td>
                            <td><?php echo $transaction['subcategory']; ?></td>
                            <td class="text-right"><?php echo number_format($transaction['amount'], 2); ?></td>
                            <td>
                                <span class="badge <?php echo $transaction['transaction_type'] === 'income' ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo ucfirst($transaction['transaction_type']); ?>
                                </span>
                            </td>
                            <td><?php echo $transaction['payment_method'] ?? 'Cash'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No transactions found for the selected date range</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4">Total</th>
                        <th class="text-right">Rs. <?php echo number_format($summary['total_income'] + $summary['total_expense'], 2); ?></th>
                        <th colspan="2">
                            <?php echo count($transactions); ?> transactions
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <script>
        // Income vs Expense Pie Chart
        const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
        const incomeExpenseChart = new Chart(incomeExpenseCtx, {
            type: 'pie',
            data: {
                labels: ['Income', 'Expenses'],
                datasets: [{
                    data: [<?php echo $summary['total_income']; ?>, <?php echo $summary['total_expense']; ?>],
                    backgroundColor: ['#4cc9f0', '#f72585'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += 'Rs. ' + context.raw.toLocaleString();
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Category Bar Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo "'" . implode("','", array_keys($summary['by_category'])) . "'"; ?>],
                datasets: [
                    {
                        label: 'Income',
                        data: [<?php echo implode(',', array_column($summary['by_category'], 'income')); ?>],
                        backgroundColor: '#4cc9f0',
                        borderWidth: 1
                    },
                    {
                        label: 'Expenses',
                        data: [<?php echo implode(',', array_column($summary['by_category'], 'expense')); ?>],
                        backgroundColor: '#f72585',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: false,
                    },
                    y: {
                        stacked: false,
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rs. ' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += 'Rs. ' + context.raw.toLocaleString();
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Payment Method Doughnut Chart
        const paymentMethodCtx = document.getElementById('paymentMethodChart').getContext('2d');
        const paymentMethodChart = new Chart(paymentMethodCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo "'" . implode("','", array_keys($summary['by_payment_method'])) . "'"; ?>],
                datasets: [{
                    data: [
                        <?php 
                        $payment_totals = [];
                        foreach ($summary['by_payment_method'] as $method => $types) {
                            $payment_totals[$method] = $types['income'] + $types['expense'];
                        }
                        echo implode(',', $payment_totals);
                        ?>
                    ],
                    backgroundColor: [
                        '#4361ee', '#4895ef', '#3f37c9', '#4cc9f0', '#f72585', '#f8961e'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += 'Rs. ' + context.raw.toLocaleString();
                                return label;
                            }
                        }
                    }
                }
            }
        });
        
        // Donor Horizontal Bar Chart (Top 5)
        const donorCtx = document.getElementById('donorChart').getContext('2d');
        
        // Prepare donor data and sort by amount
        const donorData = [
            <?php 
            $donor_amounts = [];
            foreach ($summary['by_donor'] as $donor_id => $donor_info) {
                $donor_amounts[$donor_info['name']] = $donor_info['amount'];
            }
            arsort($donor_amounts);
            $count = 0;
            foreach ($donor_amounts as $name => $amount) {
                if ($count++ >= 5) break;
                echo "{name: '" . addslashes($name) . "', amount: $amount},";
            }
            ?>
        ];
        
        const donorChart = new Chart(donorCtx, {
            type: 'bar',
            data: {
                labels: donorData.map(d => d.name),
                datasets: [{
                    label: 'Donation Amount',
                    data: donorData.map(d => d.amount),
                    backgroundColor: '#4895ef',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rs. ' + value.toLocaleString();
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rs. ' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
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