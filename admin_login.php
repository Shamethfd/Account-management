<?php
session_start();

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

if (isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_dashboard.php");
    exit();
}

// Environment-based credentials
$admin_credentials = [
    'username' => getenv('ADMIN_USER') ?: 'ccmsf',
    'password' => getenv('ADMIN_PASS') ?: password_hash('Jesusisgood1234', PASSWORD_BCRYPT),
    'id' => 1
];

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Basic brute force protection
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
    }
    
    if ($_SESSION['login_attempts'] >= 5) {
        $error = "Too many login attempts. Please try again later.";
    } else {
        if ($username === $admin_credentials['username'] && 
            password_verify($password, $admin_credentials['password'])) {
            
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin_credentials['id'];
            $_SESSION['last_activity'] = time();
            unset($_SESSION['login_attempts']);
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $_SESSION['login_attempts']++;
            $error = "Invalid username or password";
            sleep(1);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal - City Mission Church</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --primary-dark: #1a252f;
            --secondary: #3498db;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --white: #ffffff;
            --white-transparent: rgba(255, 255, 255, 0.92);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('images/church-bg.jpg') no-repeat center center fixed;
            background-size: cover;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--primary);
        }
        
        .login-container {
            background: var(--white-transparent);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 400px;
            text-align: center;
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo-container {
            margin-bottom: 1.5rem;
        }
        
        .logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 50%;
            border: 3px solid var(--white);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background-color: var(--white);
            padding: 5px;
        }
        
        .login-container h2 {
            margin-bottom: 1rem;
            color: var(--primary);
            font-size: 1.8rem;
            position: relative;
            padding-bottom: 0.8rem;
        }
        
        .login-container h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--secondary);
            border-radius: 2px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            font-weight: 500;
            color: var(--primary);
            font-size: 0.95rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            outline: none;
        }
        
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .error {
            color: var(--danger);
            margin-bottom: 1.5rem;
            background: rgba(231, 76, 60, 0.1);
            padding: 0.9rem;
            border-radius: 8px;
            border-left: 4px solid var(--danger);
            font-size: 0.9rem;
            text-align: center;
        }
        
        .login-link {
            margin-top: 1.8rem;
            font-size: 0.9rem;
            color: var(--primary);
        }
        
        .login-link a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
            padding: 0.3rem 0;
            border-bottom: 1px dashed var(--secondary);
        }
        
        .login-link a:hover {
            color: var(--primary-dark);
            border-bottom-color: var(--primary-dark);
        }
        
        .security-notice {
            font-size: 0.75rem;
            color: #7f8c8d;
            margin-top: 1.5rem;
            text-align: center;
        }
        
        .attempts-warning {
            color: var(--danger);
            font-size: 0.8rem;
            margin-top: 0.5rem;
            display: <?php echo isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 3 ? 'block' : 'none'; ?>;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="data:image/jpeg;base64,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" alt="Church Logo" class="logo">
        </div>
        
        <h2>Admin Portal</h2>
        <p style="color: #7f8c8d; margin-bottom: 1.5rem;">City Mission Church Accounting System</p>
        
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 3): ?>
                <div class="attempts-warning">
                    Attempts remaining: <?php echo 5 - $_SESSION['login_attempts']; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Admin Username</label>
                <input type="text" id="username" name="username" required autocomplete="username" placeholder="Enter admin username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
            </div>
            <button type="submit" class="btn">Access Dashboard</button>
        </form>
        
        <div class="login-link">
            <a href="login.php"><i class="fas fa-arrow-left"></i> Return to User Login</a>
        </div>
        
        <div class="security-notice">
            <i class="fas fa-lock"></i> Secure admin portal. Unauthorized access prohibited.
        </div>
    </div>
</body>
</html>