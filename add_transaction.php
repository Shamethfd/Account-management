<?php
session_start();
require_once 'auth.php';
require_once 'db_config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Get donors for the dropdown
$donors = [];
$result = $conn->query("SELECT * FROM donors ORDER BY name");
if ($result) {
    $donors = $result->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $subcategory = filter_input(INPUT_POST, 'subcategory', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $transaction_type = filter_input(INPUT_POST, 'transaction_type', FILTER_SANITIZE_STRING);
    $transaction_date = filter_input(INPUT_POST, 'transaction_date', FILTER_SANITIZE_STRING);
    $donor_id = filter_input(INPUT_POST, 'donor_id', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING) ?? 'Cash';
    
    // Handle file upload
    $receiptPath = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/receipts/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Validate file type and size
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        $fileType = $_FILES['receipt']['type'];
        $fileSize = $_FILES['receipt']['size'];
        
        if (in_array($fileType, $allowedTypes) && $fileSize <= $maxSize) {
            $fileExt = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $fileName = uniqid() . '.' . $fileExt;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $filePath)) {
                $receiptPath = $filePath;
            } else {
                $error = "Failed to upload receipt file.";
            }
        } else {
            $error = "Invalid file type or size (max 5MB allowed). Only images and PDFs are accepted.";
        }
    }
    
    if (!$error) {
        $stmt = $conn->prepare("INSERT INTO transactions 
            (category, subcategory, description, amount, transaction_type, 
            transaction_date, created_by, donor_id, payment_method, receipt_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssdssiiss", 
            $category, $subcategory, $description, $amount, $transaction_type, 
            $transaction_date, $_SESSION['user_id'], $donor_id, $payment_method, $receiptPath);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Transaction added successfully!";
            header("Location: index.php");
            exit();
        } else {
            $error = "Error adding transaction: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transaction - Church Accounting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4a6cf7;
            --primary-dark: #3a5bd9;
            --light: #f8f9fa;
            --dark: #343a40;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --radius: 6px;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f7fb;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 15px 0;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .logo {
            font-size: 1.3rem;
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
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .add-transaction-form {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 1rem;
            transition: border 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(74, 108, 247, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .header-content {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">City Mission Church Accounting</div>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="?logout" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <h1>Add New Transaction</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="add-transaction-form">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="transaction_type">Transaction Type</label>
                        <select class="form-control" id="transaction_type" name="transaction_type" required>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category">Category</label>
                        <select class="form-control" id="category" name="category" required>
                            <option value="Blue Angel">Blue Angel</option>
                            <option value="Nursery">Nursery</option>
                            <option value="Guest Rooms">Guest Rooms</option>
                            <option value="Boys Hostel">Boys Hostel</option>
                            <option value="Girls Hostel">Girls Hostel</option>
                            <option value="CCM / Saturday Kids group">CCM / Saturday Kids group</option>
                            <option value="CCM / Bloom Teens">CCM / Bloom Teens</option>
                            <option value="Hand in Hand Women's group">Hand in Hand Women's group</option>
                            <option value="Eden Home for Pastoral ministry">Eden Home for Pastoral ministry</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="subcategory">Subcategory/Activity</label>
                        <input type="text" class="form-control" id="subcategory" name="subcategory" required>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount (Rs.)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="description">Description</label>
                        <input type="text" class="form-control" id="description" name="description" required>
                    </div>
                    <div class="form-group">
                        <label for="transaction_date">Date</label>
                        <input type="date" class="form-control" id="transaction_date" name="transaction_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="donor_id">Donor (Optional)</label>
                        <select class="form-control" id="donor_id" name="donor_id">
                            <option value="">Select Donor</option>
                            <?php foreach ($donors as $donor): ?>
                            <option value="<?php echo htmlspecialchars($donor['id']); ?>">
                                <?php echo htmlspecialchars($donor['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select class="form-control" id="payment_method" name="payment_method">
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Digital Payment">Digital Payment</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="receipt">Upload Receipt (Image/PDF, max 5MB)</label>
                    <input type="file" id="receipt" name="receipt" class="form-control" accept="image/*,.pdf">
                    <small class="text-muted">Accepted formats: JPG, PNG, GIF, PDF</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_transaction" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i>
                        Add Transaction
                    </button>
                    <a href="index.php" class="btn btn-danger">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
if (isset($_GET['logout'])) {
    logout();
    header("Location: login.php");
    exit();
}
?>