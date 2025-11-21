<?php
/**
 * Database Connection Configuration using PDO
 */

// Database credentials for XAMPP MySQL
$host = '10.28.120.199'; //localhost 
$db   = 'it_inventory_assets'; // The database name you created
$user = 'root';              // Default XAMPP user
$pass = '';                  // Default XAMPP password (often empty)
$charset = 'utf8mb4';        // Recommended character set

// Data Source Name (DSN) string
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Connection Options
$options = [
    // Throw an exception for every error
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // Fetch results as associative arrays by default
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // Disable emulation of prepared statements
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Attempt to create a new PDO instance (the actual connection)
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Optional: Confirmation message (remove this in a production environment)
    // echo "Database connection successful!"; 
    
} catch (\PDOException $e) {
    // If connection fails, stop the script and display the error
    // In a production environment, you should log the error instead of displaying it.
    die("Database connection failed: " . $e->getMessage());
}

// $pdo is now the active database connection object used for all queries.
?>