<?php
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

require_once 'db_config.php';

// Handle user creation
if (isset($_POST['create_user'])) {
    $username = trim($_POST['username']);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, phone, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $full_name, $email, $phone, $hashed_password);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['message'] = "User created successfully";
    header("Location: admin_dashboard.php");
    exit();
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $user_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND is_admin = FALSE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    $_SESSION['message'] = "User deleted successfully";
    header("Location: admin_dashboard.php");
    exit();
}

// Get all users
$users = $conn->query("SELECT id, username, full_name, email, phone FROM users WHERE is_admin = FALSE ORDER BY username");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --success-color: #1cc88a;
        }
        
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--light-color);
            color: var(--dark-color);
        }
        
        .sidebar {
            background-color: var(--secondary-color);
            min-height: 100vh;
            color: white;
            padding: 0;
            transition: all 0.3s;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            background-color: rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu a {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            display: block;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 3px solid var(--primary-color);
        }
        
        .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 3px solid var(--primary-color);
        }
        
        .sidebar-menu i {
            margin-right: 0.5rem;
            width: 1.2rem;
            text-align: center;
        }
        
        .main-content {
            padding: 2rem;
        }
        
        .card {
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
            padding: 1rem 1.35rem;
            border-radius: 0.35rem 0.35rem 0 0 !important;
        }
        
        .table th {
            background-color: #f8f9fc;
            font-weight: 600;
            color: var(--dark-color);
            border-top: 1px solid #e3e6f0;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
        
        .btn-danger {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 0.5rem 1rem;
        }
        
        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .alert {
            border-radius: 0.35rem;
            border-left: 0.25rem solid;
        }
        
        .alert-success {
            border-left-color: var(--success-color);
        }
        
        .breadcrumb {
            background-color: transparent;
            padding: 0;
            margin-bottom: 1.5rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .badge-success {
            background-color: var(--success-color);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .page-link {
            color: var(--primary-color);
        }
        
        .stat-card {
            border-left: 0.25rem solid var(--primary-color);
        }
        
        .stat-card.success {
            border-left-color: var(--success-color);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                z-index: 1000;
                width: 250px;
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar" id="sidebar">
                <div class="sidebar-header">
                    <h4><i class="bi bi-shield-lock"></i> Admin Panel</h4>
                </div>
                <div class="sidebar-menu">
                    <a href="admin_dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
                    <a href="#"><i class="bi bi-people-fill"></i> User Management</a>
                    <a href="#"><i class="bi bi-gear"></i> System Settings</a>
                    <a href="#"><i class="bi bi-graph-up"></i> Analytics</a>
                    <a href="#"><i class="bi bi-file-earmark-text"></i> Reports</a>
                    <a href="#"><i class="bi bi-archive"></i> Audit Logs</a>
                    <a href="logout.php"><i class="bi bi-box-arrow-left"></i> Logout</a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 ms-sm-auto px-0">
                <!-- Top Navigation -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
                    <div class="container-fluid">
                        <button class="btn btn-link d-md-none" type="button" id="sidebarToggle">
                            <i class="bi bi-list"></i>
                        </button>
                        
                        <div class="d-flex align-items-center">
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="#"><i class="bi bi-house-door"></i></a></li>
                                    <li class="breadcrumb-item">Administration</li>
                                    <li class="breadcrumb-item active">User Management</li>
                                </ol>
                            </nav>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <div class="dropdown me-3">
                                <a href="#" class="text-dark dropdown-toggle" id="notificationsDropdown" 
                                   role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-bell fs-5"></i>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        3
                                    </span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationsDropdown">
                                    <li><h6 class="dropdown-header">Notifications</h6></li>
                                    <li><a class="dropdown-item" href="#">New user registered</a></li>
                                    <li><a class="dropdown-item" href="#">System update available</a></li>
                                    <li><a class="dropdown-item" href="#">New message received</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-center" href="#">View all</a></li>
                                </ul>
                            </div>
                            
                            <div class="dropdown">
                                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" 
                                   id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="user-avatar me-2">A</div>
                                    <span class="d-none d-sm-inline">Administrator</span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> Profile</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Settings</a></li>
                                    <li><a class="dropdown-item" href="#"><i class="bi bi-list-check me-2"></i> Activity Log</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-left me-2"></i> Logout</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <div class="main-content">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php echo $_SESSION['message']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                        <?php unset($_SESSION['message']); ?>
                    <?php endif; ?>
                    
                    <div class="row mb-4">
                        <div class="col-xl-6">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-person-plus me-2"></i>Create New User</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="username" class="form-label">Username *</label>
                                                <input type="text" class="form-control" id="username" name="username" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="full_name" class="form-label">Full Name *</label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="email" class="form-label">Email Address *</label>
                                                <input type="email" class="form-control" id="email" name="email" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="phone" class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" id="phone" name="phone">
                                            </div>
                                            <div class="col-md-12">
                                                <label for="password" class="form-label">Password *</label>
                                                <input type="password" class="form-control" id="password" name="password" required>
                                            </div>
                                            <div class="col-md-12">
                                                <button type="submit" name="create_user" class="btn btn-primary">
                                                    <i class="bi bi-plus-circle me-2"></i> Create User
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-xl-6">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="card border-left-primary shadow h-100 py-2 stat-card">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col me-2">
                                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                        Total Users</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $users->num_rows; ?></div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="bi bi-people-fill fs-2 text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card border-left-success shadow h-100 py-2 stat-card success">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col me-2">
                                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                        Active Today</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800">2</div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="bi bi-activity fs-2 text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card border-left-info shadow h-100 py-2 stat-card">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col me-2">
                                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                        New This Week</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800">5</div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="bi bi-person-plus fs-2 text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="card border-left-warning shadow h-100 py-2 stat-card">
                                        <div class="card-body">
                                            <div class="row no-gutters align-items-center">
                                                <div class="col me-2">
                                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                        Pending Requests</div>
                                                    <div class="h5 mb-0 font-weight-bold text-gray-800">0</div>
                                                </div>
                                                <div class="col-auto">
                                                    <i class="bi bi-clock-history fs-2 text-gray-300"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card shadow">
                        <div class="card-header d-flex justify-content-between align-items-center py-3">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-people-fill me-2"></i>User List</h6>
                            <div class="d-flex">
                                <input type="text" class="form-control form-control-sm me-2" placeholder="Search users..." id="searchInput">
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-funnel"></i> Filter
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($user = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                            <td><span class="badge bg-success">Active</span></td>
                                            <td>
                                                <div class="d-flex">
                                                    <a href="#" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="tooltip" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="admin_dashboard.php?delete=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Are you sure you want to delete this user?')"
                                                       data-bs-toggle="tooltip" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-sm btn-outline-secondary ms-1" data-bs-toggle="tooltip" title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="text-muted">
                                    Showing <span class="fw-bold">1</span> to <span class="fw-bold">10</span> of <span class="fw-bold"><?php echo $users->num_rows; ?></span> entries
                                </div>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination pagination-sm">
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                                        </li>
                                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                                        <li class="page-item">
                                            <a class="page-link" href="#">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Enable tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Toggle sidebar on mobile
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('show');
            });
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    new bootstrap.Alert(alert).close();
                });
            }, 5000);
            
            // Initialize DataTable
            $('#dataTable').DataTable({
                paging: false,
                searching: true,
                info: false
            });
            
            // Simple search functionality
            document.getElementById('searchInput').addEventListener('keyup', function() {
                const value = this.value.toLowerCase();
                document.querySelectorAll('#dataTable tbody tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(value) ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html>