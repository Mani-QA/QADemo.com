<?php

// Start output buffering
ob_start();

try {
    require_once 'config/database.php';
    require_once 'includes/auth.php';

    $db = new Database();
    $auth = new Auth($db->getConnection());

    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        header('Location: index.php');
        exit;
    }

    // Get order ID from URL
    $order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
    if ($order_id <= 0) {
        throw new Exception('Invalid order ID');
    }

    // Debug log
    debug_log("Fetching order details for order ID: " . $order_id);
    debug_log("User ID: " . $_SESSION['user_id']);

    // Get order details
    $stmt = $db->getConnection()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
    if (!$stmt) {
        throw new Exception('Failed to prepare order query: ' . $db->getConnection()->lastErrorMsg());
    }
    
    $stmt->bindValue(1, $order_id, SQLITE3_INTEGER);
    $stmt->bindValue(2, $_SESSION['user_id'], SQLITE3_INTEGER);
    
    $result = $stmt->execute();
    if (!$result) {
        throw new Exception('Failed to execute order query: ' . $db->getConnection()->lastErrorMsg());
    }
    
    $order = $result->fetchArray(SQLITE3_ASSOC);
    if (!$order) {
        throw new Exception('Order not found or does not belong to current user');
    }

    debug_log("Order found: " . print_r($order, true));

    // Get order items
    $stmt = $db->getConnection()->prepare('
        SELECT oi.*, p.name 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ');
    if (!$stmt) {
        throw new Exception('Failed to prepare order items query: ' . $db->getConnection()->lastErrorMsg());
    }
    
    $stmt->bindValue(1, $order_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if (!$result) {
        throw new Exception('Failed to execute order items query: ' . $db->getConnection()->lastErrorMsg());
    }
    
    $order_items = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $order_items[] = $row;
    }

    debug_log("Order items found: " . count($order_items));

    // Decode shipping details
    if (empty($order['shipping_details'])) {
        throw new Exception('Shipping details not found in order');
    }
    
    $shipping_details = json_decode($order['shipping_details'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Failed to decode shipping details: ' . json_last_error_msg());
    }

    debug_log("Shipping details: " . print_r($shipping_details, true));

} catch (Exception $e) {
    debug_log("Error in order_confirmation.php: " . $e->getMessage());
    $error = $e->getMessage(); // Show actual error message for debugging
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Demo - Order Confirmation</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <h1 class="text-xl font-bold">QA Demo</h1>
                    </div>
                </div>
                <div class="flex items-center">
                    <div class="ml-4 flex items-center md:ml-6">
                        <a href="catalog.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Catalog</a>
                        <?php if ($auth->isAdmin()): ?>
                        <a href="admin.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Admin</a>
                        <?php endif; ?>
                        <a href="logout.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
            <div class="text-center mt-4">
                <a href="catalog.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Return to Catalog
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                <div class="px-4 py-5 sm:px-6">
                    <h2 class="text-lg leading-6 font-medium text-gray-900">Order Confirmation</h2>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">Order #<?php echo $order_id; ?></p>
                </div>
                <div class="border-t border-gray-200">
                    <!-- Order Status -->
                    <div class="px-4 py-5 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Order Status</h3>
                                <p class="mt-1 text-sm text-gray-500"><?php echo ucfirst($order['status']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Order Date</p>
                                <p class="text-lg font-medium text-gray-900"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Information -->
                    <div class="px-4 py-5 sm:px-6 border-t border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Shipping Information</h3>
                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <p class="text-sm font-medium text-gray-500">Name</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    <?php echo htmlspecialchars($shipping_details['first_name'] . ' ' . $shipping_details['last_name']); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500">Shipping Address</p>
                                <p class="mt-1 text-sm text-gray-900">
                                    <?php echo nl2br(htmlspecialchars($shipping_details['address'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="px-4 py-5 sm:px-6 border-t border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Order Items</h3>
                        <div class="mt-4">
                            <ul class="divide-y divide-gray-200">
                                <?php foreach ($order_items as $item): ?>
                                    <li class="py-4">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <div class="ml-4">
                                                    <h4 class="text-lg font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </h4>
                                                    <p class="text-sm text-gray-500">
                                                        Quantity: <?php echo $item['quantity']; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="text-lg font-medium text-gray-900">
                                                $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Order Total -->
                    <div class="px-4 py-5 sm:px-6 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-lg font-medium text-gray-900">Total</div>
                            <div class="text-lg font-medium text-gray-900">
                                $<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Continue Shopping Button -->
            <div class="mt-6 text-center">
                <a href="catalog.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                    Continue Shopping
                </a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
<?php
// End output buffering and send the output
ob_end_flush();
?> 