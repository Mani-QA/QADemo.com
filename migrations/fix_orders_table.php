<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Start transaction
    $conn->exec('BEGIN TRANSACTION');

    try {
        // Drop the existing orders table
        $conn->exec('DROP TABLE IF EXISTS orders');

        // Create orders table with correct schema
        $conn->exec('
            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                total_amount DECIMAL(10,2) NOT NULL,
                status TEXT NOT NULL,
                shipping_details TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )
        ');

        // Commit transaction
        $conn->exec('COMMIT');
        echo "Successfully fixed orders table schema.\n";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->exec('ROLLBACK');
        throw $e;
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 