<?php
require_once 'config/database.php';
require_once 'includes/auth.php';



$db = new Database();
$auth = new Auth($db->getConnection());

// Check auth status before any output
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'])) {
        die('Invalid request');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add_product':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $image_path = 'images/placeholder.jpg'; // Default image path

            if (strlen($name) > 50) {
                $errors[] = 'Product name must be 50 characters or less';
            }
            if (strlen($description) > 150) {
                $errors[] = 'Description must be 150 characters or less';
            }
            if ($price <= 0) {
                $errors[] = 'Price must be greater than 0';
            }
            if ($stock < 0) {
                $errors[] = 'Stock cannot be negative';
            }

            // Handle image upload
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
                $max_size = 2 * 1024 * 1024; // 2MB

                if (!in_array($_FILES['product_image']['type'], $allowed_types)) {
                    $errors[] = 'Invalid image type. Please upload JPEG, PNG, or WebP images.';
                } elseif ($_FILES['product_image']['size'] > $max_size) {
                    $errors[] = 'Image size must be less than 2MB.';
                } else {
                    $file_extension = pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = strtolower(str_replace(' ', '_', $name)) . '.' . $file_extension;
                    $upload_path = 'images/' . $new_filename;

                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $upload_path)) {
                        $image_path = $upload_path;
                    } else {
                        $errors[] = 'Failed to upload image.';
                    }
                }
            }

            if (empty($errors)) {
                $stmt = $db->getConnection()->prepare('
                    INSERT INTO products (name, description, price, stock, image_path)
                    VALUES (:name, :description, :price, :stock, :image_path)
                ');

                $stmt->bindValue(':name', $name, SQLITE3_TEXT);
                $stmt->bindValue(':description', $description, SQLITE3_TEXT);
                $stmt->bindValue(':price', $price, SQLITE3_FLOAT);
                $stmt->bindValue(':stock', $stock, SQLITE3_INTEGER);
                $stmt->bindValue(':image_path', $image_path, SQLITE3_TEXT);
                $stmt->execute();

                $success = 'Product added successfully';
            }
            break;

        case 'update_stock':
            $productId = (int)($_POST['product_id'] ?? 0);
            $newStock = (int)($_POST['new_stock'] ?? 0);

            if ($newStock < 0) {
                $errors[] = 'Stock cannot be negative';
            } else {
                $stmt = $db->getConnection()->prepare('
                    UPDATE products 
                    SET stock = :stock 
                    WHERE id = :id
                ');
                $stmt->bindValue(':stock', $newStock, SQLITE3_INTEGER);
                $stmt->bindValue(':id', $productId, SQLITE3_INTEGER);
                $stmt->execute();

                $success = 'Stock updated successfully';
            }
            break;
    }
}

// Get products
$products = [];
$result = $db->getConnection()->query('SELECT * FROM products ORDER BY name');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $products[] = $row;
}

// Get orders
$orders = [];
$result = $db->getConnection()->query('
    SELECT o.*, u.username 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC
');
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $orders[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Demo - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <span class="text-xl font-semibold">Admin</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="catalog.php" class="text-gray-600 hover:text-gray-900">View Store</a>
                    <?php if ($auth->isLoggedIn()): ?>
                    <a href="logout.php" class="text-gray-600 hover:text-gray-900">Logout</a>
                    <?php else: ?>
                    <a href="index.php" class="text-gray-600 hover:text-gray-900">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Inventory Management -->
            <div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">Inventory Management</h2>
                    
                    <form method="POST" class="mb-8" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="add_product">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Product Name</label>
                                <input type="text" id="name" name="name" required maxlength="50"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea id="description" name="description" required maxlength="150" rows="3"
                                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                            </div>
                            
                            <div>
                                <label for="price" class="block text-sm font-medium text-gray-700">Price</label>
                                <input type="number" id="price" name="price" required min="0.01" step="0.01"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="stock" class="block text-sm font-medium text-gray-700">Initial Stock</label>
                                <input type="number" id="stock" name="stock" required min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="product_image" class="block text-sm font-medium text-gray-700">Product Image</label>
                                <input type="file" id="product_image" name="product_image" accept="image/jpeg,image/png,image/webp"
                                       class="mt-1 block w-full text-sm text-gray-500
                                              file:mr-4 file:py-2 file:px-4
                                              file:rounded-md file:border-0
                                              file:text-sm file:font-semibold
                                              file:bg-indigo-50 file:text-indigo-700
                                              hover:file:bg-indigo-100">
                                <p class="mt-1 text-sm text-gray-500">Upload JPEG, PNG, or WebP image (max 2MB)</p>
                            </div>

                            <button type="submit"
                                    class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                Add Product
                            </button>
                        </div>
                    </form>

                    <div class="space-y-4">
                        <?php foreach ($products as $product): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($product['description']); ?></p>
                                        <p class="text-sm text-gray-600">Price: $<?php echo number_format($product['price'], 2); ?></p>
                                    </div>
                                    <form method="POST" class="flex items-center space-x-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="update_stock">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="number" name="new_stock" value="<?php echo $product['stock']; ?>" min="0"
                                               class="w-20 px-2 py-1 border rounded-md">
                                        <button type="submit" class="text-indigo-600 hover:text-indigo-900">Update</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Order History -->
            <div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-2xl font-semibold text-gray-900 mb-6">Order History</h2>
                    
                    <div class="space-y-4">
                        <?php foreach ($orders as $order): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Order #<?php echo $order['id']; ?></h3>
                                        <p class="text-sm text-gray-600">Customer: <?php echo htmlspecialchars($order['username']); ?></p>
                                        <p class="text-sm text-gray-600">Date: <?php echo date('Y-m-d H:i:s', strtotime($order['created_at'])); ?></p>
                                        <p class="text-sm text-gray-600">Total: $<?php echo number_format($order['total_amount'], 2); ?></p>
                                        <p class="text-sm text-gray-600">Status: <?php echo htmlspecialchars($order['status']); ?></p>
                                    </div>
                                    <button onclick="showOrderDetails(<?php echo $order['id']; ?>)"
                                            class="text-indigo-600 hover:text-indigo-900">
                                        View Details
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showOrderDetails(orderId) {
            // Implement order details modal or expandable section
            alert('Order details for #' + orderId + ' would be shown here');
        }
    </script>
</body>
</html> 