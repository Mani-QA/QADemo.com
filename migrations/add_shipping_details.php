<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Add shipping_details column to orders table
    $conn->exec('ALTER TABLE orders ADD COLUMN shipping_details TEXT');

    echo "Successfully added shipping_details column to orders table.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 