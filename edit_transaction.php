<?php
session_start();
require_once 'auth.php';
require_once 'db_config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $subcategory = $_POST['subcategory'];
    $amount = $_POST['amount'];
    $type = $_POST['type'];
    $payment_method = $_POST['payment_method'];
    
    $stmt = $conn->prepare("UPDATE transactions SET 
                          transaction_date = ?,
                          description = ?,
                          category = ?,
                          subcategory = ?,
                          amount = ?,
                          transaction_type = ?,
                          payment_method = ?
                          WHERE id = ?");
    $stmt->bind_param("ssssdssi", $date, $description, $category, $subcategory, $amount, $type, $payment_method, $id);
    $stmt->execute();
    
    $_SESSION['success_message'] = "Transaction updated successfully!";
    header("Location: index.php");
    exit();
}

// Get transaction data
$id = $_GET['id'] ?? 0;
$transaction = [];
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();
}

if (!$transaction) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaction - Church Accounting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
            background-color: var(--white);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background: var(--white);
            color: var(--dark);
            padding: 20px 0;
            border-bottom: 1px solid var(--light-gray);
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
            font-size: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
        }
        
        .logo i {
            font-size: 1.8rem;
        }
        
        h1 {
            margin: 30px 0;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
        }
        
        .card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid var(--light-gray);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.95rem;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: var(--radius);
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
            background-color: var(--white);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 1rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
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
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 1em;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .container {
                padding: 15px;
            }
            
            .card {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
        </div>
    </header>
    
    <div class="container">
        <h1><i class="fas fa-edit"></i> Edit Transaction</h1>
        
        <div class="card">
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $transaction['id']; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" id="date" name="date" class="form-control" 
                               value="<?php echo $transaction['transaction_date']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="amount">Amount (Rs.)</label>
                        <input type="number" step="0.01" id="amount" name="amount" class="form-control" 
                               value="<?php echo $transaction['amount']; ?>" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="type">Type</label>
                        <select id="type" name="type" class="form-control" required>
                            <option value="income" <?php echo $transaction['transaction_type'] === 'income' ? 'selected' : ''; ?>>Income</option>
                            <option value="expense" <?php echo $transaction['transaction_type'] === 'expense' ? 'selected' : ''; ?>>Expense</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-control" required>
                            <option value="Cash" <?php echo ($transaction['payment_method'] ?? 'Cash') === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Bank Transfer" <?php echo ($transaction['payment_method'] ?? '') === 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="Check" <?php echo ($transaction['payment_method'] ?? '') === 'Check' ? 'selected' : ''; ?>>Check</option>
                            <option value="Online" <?php echo ($transaction['payment_method'] ?? '') === 'Online' ? 'selected' : ''; ?>>Online</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" class="form-control" 
                           value="<?php echo $transaction['category']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="subcategory">Subcategory</label>
                    <input type="text" id="subcategory" name="subcategory" class="form-control" 
                           value="<?php echo $transaction['subcategory']; ?>">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"><?php echo $transaction['description']; ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span>Update Transaction</span>
                    </button>
                    
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-times"></i>
                        <span>Cancel</span>
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>