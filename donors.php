<?php
session_start();
require_once 'auth.php';
require_once 'db_config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Handle donor operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_donor'])) {
        $name = $_POST['name'];
        $contact = $_POST['contact'];
        $address = $_POST['address'];
        
        $stmt = $conn->prepare("INSERT INTO donors (name, contact, address) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $contact, $address);
        $stmt->execute();
    } elseif (isset($_POST['delete_donor'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM donors WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}

// Get all donors
$donors = [];
$result = $conn->query("SELECT * FROM donors ORDER BY name");
if ($result) {
    $donors = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Management - Church Accounting System</title>
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
            background-color: #f5f7ff;
            color: var(--dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
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
            padding: 8px 15px;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 600;
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
        
        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background-color: #d1145a;
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
        
        .card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .card h2 {
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--light-gray);
            border-radius: var(--radius);
            font-family: inherit;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
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
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }
        
        .action-btn.delete {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
        }
        
        .action-btn:hover {
            transform: scale(1.1);
        }
        
        h1 {
            margin-bottom: 20px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            th, td {
                padding: 8px 10px;
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
            <a href="donors.php" class="btn btn-primary">
                <i class="fas fa-users"></i>
                <span>Donor Management</span>
            </a>
            <a href="budgets.php" class="btn btn-outline">
                <i class="fas fa-wallet"></i>
                <span>Manage Budgets</span>
            </a>
        </div>
        
        <h1><i class="fas fa-users"></i> Donor Management</h1>
        
        <div class="card">
            <h2><i class="fas fa-user-plus"></i> Add New Donor</h2>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Donor Name</label>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="contact">Contact Info</label>
                        <input type="text" id="contact" name="contact" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control"></textarea>
                </div>
                <button type="submit" name="add_donor" class="btn btn-primary">
                    <i class="fas fa-save"></i>
                    <span>Add Donor</span>
                </button>
            </form>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-list"></i> Donor List</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Total Donations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donors as $donor): 
                        // Get donation total
                        $result = $conn->query("SELECT SUM(amount) as total FROM transactions 
                                              WHERE donor_id = {$donor['id']} AND transaction_type = 'income'");
                        $total = $result->fetch_assoc()['total'] ?? 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($donor['name']); ?></td>
                        <td><?php echo htmlspecialchars($donor['contact']); ?></td>
                        <td>Rs. <?php echo number_format($total, 2); ?></td>
                        <td>
                        <td>
                         <div class="actions">
                         <a href="edit_donor.php?id=<?php echo $donor['id']; ?>" class="action-btn edit">
                          <i class="fas fa-edit"></i>
                           </a>
                           <button type="button" class="action-btn delete" disabled style="opacity: 0.5; cursor: not-allowed;">
                         <i class="fas fa-trash"></i>
                         </button>
                          </div>
</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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