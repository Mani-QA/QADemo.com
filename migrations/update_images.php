<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Sample product images
    $product_images = [
        1 => 'images/products/bluetooth-speaker.jpg',
        2 => 'images/products/wireless-headphones.jpg',
        3 => 'images/products/smart-watch.jpg',
        4 => 'images/products/laptop.jpg',
        5 => 'images/products/tablet.jpg',
        6 => 'images/products/smartphone.jpg',
        7 => 'images/products/camera.jpg',
        8 => 'images/products/gaming-console.jpg',
        9 => 'images/products/fitness-tracker.jpg',
        10 => 'images/products/smart-home-hub.jpg'
    ];

    // Update each product's image URL
    foreach ($product_images as $id => $image_url) {
        $stmt = $conn->prepare('UPDATE products SET image_url = :image_url WHERE id = :id');
        $stmt->bindValue(':image_url', $image_url, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
    }

    echo "Product images updated successfully!";
} catch (Exception $e) {
    echo "Error updating images: " . $e->getMessage();
}
?>