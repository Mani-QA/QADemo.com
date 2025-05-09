<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Sample products data
$products = [
    [
        'name' => 'Wireless Headphones',
        'description' => 'High-quality wireless headphones with noise cancellation and 20-hour battery life.',
        'price' => 199.99,
        'stock' => 50,
        'image_path' => 'images/default.jpg'
    ],
    [
        'name' => 'Smart Watch',
        'description' => 'Feature-rich smartwatch with heart rate monitoring, GPS, and water resistance.',
        'price' => 299.99,
        'stock' => 30,
        'image_path' => 'images/default.jpg'
    ],
    [
        'name' => 'Laptop Backpack',
        'description' => 'Durable laptop backpack with multiple compartments and USB charging port.',
        'price' => 49.99,
        'stock' => 100,
        'image_path' => 'images/default.jpg'
    ],
    [
        'name' => 'Coffee Maker',
        'description' => 'Programmable coffee maker with thermal carafe and 12-cup capacity.',
        'price' => 79.99,
        'stock' => 25,
        'image_path' => 'images/default.jpg'
    ],
    [
        'name' => 'Fitness Tracker',
        'description' => 'Water-resistant fitness tracker with sleep monitoring and smartphone notifications.',
        'price' => 89.99,
        'stock' => 75,
        'image_path' => 'images/default.jpg'
    ],
    [
        'name' => 'Bluetooth Speaker',
        'description' => 'Portable Bluetooth speaker with 360-degree sound and 12-hour battery life.',
        'price' => 129.99,
        'stock' => 40,
        'image_path' => 'images/default.jpg'
    ],
    [
        'name' => 'Mechanical Keyboard',
        'description' => 'RGB mechanical gaming keyboard with customizable keys and wrist rest.',
        'price' => 149.99,
        'stock' => 35,
        'image_path' => 'images/default.jpg'
    ],
    [
        'name' => 'Wireless Mouse',
        'description' => 'Ergonomic wireless mouse with precision tracking and long battery life.',
        'price' => 39.99,
        'stock' => 60,
        'image_path' => 'images/default.jpg'
    ],
    [
        'name' => 'Power Bank',
        'description' => '20000mAh power bank with fast charging and multiple USB ports.',
        'price' => 59.99,
        'stock' => 45,
        'image_path' => 'images/default.jpg'
    ],
    [
        'name' => 'Webcam',
        'description' => '1080p HD webcam with built-in microphone and privacy cover.',
        'price' => 69.99,
        'stock' => 55,
        'image_path' => 'images/default.jpg'
    ]
];

// Prepare the insert statement
$stmt = $conn->prepare('
    INSERT INTO products (name, description, price, stock, image_path)
    VALUES (:name, :description, :price, :stock, :image_path)
');

// Insert each product
$success = true;
foreach ($products as $product) {
    $stmt->bindValue(':name', $product['name'], SQLITE3_TEXT);
    $stmt->bindValue(':description', $product['description'], SQLITE3_TEXT);
    $stmt->bindValue(':price', $product['price'], SQLITE3_FLOAT);
    $stmt->bindValue(':stock', $product['stock'], SQLITE3_INTEGER);
    $stmt->bindValue(':image_path', $product['image_path'], SQLITE3_TEXT);
    
    if (!$stmt->execute()) {
        $success = false;
        echo "Error inserting product: " . $product['name'] . "\n";
    }
}

if ($success) {
    echo "Successfully loaded " . count($products) . " sample products into the database.\n";
} else {
    echo "There were some errors while loading the sample products.\n";
}
?> 