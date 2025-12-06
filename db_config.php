<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pmccm";


// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Disable foreign key checks temporarily to avoid issues with table creation order
$conn->query("SET FOREIGN_KEY_CHECKS=0");

// Create tables in proper order to satisfy foreign key dependencies
$tables = [
    // Users table must come first as it's referenced by others
    "CREATE TABLE IF NOT EXISTS users (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(30) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    
    // Donors table needs to come before transactions as it's referenced by transactions
    "CREATE TABLE IF NOT EXISTS donors (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact VARCHAR(100),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB",
    
    // Categories table
    "CREATE TABLE IF NOT EXISTS categories (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        color VARCHAR(7) DEFAULT '#4a6cf7',
        is_active BOOLEAN DEFAULT TRUE
    ) ENGINE=InnoDB",
    
    // Transactions table with all columns and foreign keys
    "CREATE TABLE IF NOT EXISTS transactions (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        subcategory VARCHAR(50) NOT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        transaction_type ENUM('income', 'expense') NOT NULL,
        transaction_date DATE NOT NULL,
        donor_id INT(6) UNSIGNED,
        payment_method VARCHAR(20) DEFAULT 'Cash',
        receipt_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT(6) UNSIGNED,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",
    
    // Scheduled transactions
    "CREATE TABLE IF NOT EXISTS scheduled_transactions (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        subcategory VARCHAR(50) NOT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        transaction_type ENUM('income', 'expense') NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        transaction_date DATE NOT NULL,
        recurrence ENUM('none', 'daily', 'weekly', 'monthly') DEFAULT 'none',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT(6) UNSIGNED,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB",
    
    // Budgets table
    "CREATE TABLE IF NOT EXISTS budgets (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        year INT(4) NOT NULL,
        created_by INT(6) UNSIGNED,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB"
];

// Execute the table creation queries
foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS=1");

// Insert default categories if empty
$result = $conn->query("SELECT COUNT(*) as count FROM categories");
if ($result) {
    $row = $result->fetch_assoc();
    if ($row['count'] == 0) {
        $defaultCategories = [
            "('Blue Angel', '#3b82f6')",
            "('Nursery', '#10b981')",
            "('Guest Rooms', '#f59e0b')",
            "('Boys Hostel', '#ef4444')",
            "('Girls Hostel', '#8b5cf6')",
            "('CCM / Saturday Kids group', '#ec4899')",
            "('CCM / Bloom Teens', '#14b8a6')",
            "('Hand in Hand Women''s group', '#f97316')",
            "('Eden Home for Pastoral ministry', '#64748b')"
        ];
        
        $insertSql = "INSERT INTO categories (name, color) VALUES " . implode(", ", $defaultCategories);
        if (!$conn->query($insertSql)) {
            die("Error inserting default categories: " . $conn->error);
        }
    }
} else {
    die("Error checking categories table: " . $conn->error);
}
?>
