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



    // Initialize cart if not exists

    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {

        header('Location: cart.php');

        exit;

    }



    // Get cart items

    $cart_items = [];

    $cart_total = 0;

    

    $placeholders = str_repeat('?,', count($_SESSION['cart']) - 1) . '?';

    $stmt = $db->getConnection()->prepare("SELECT * FROM products WHERE id IN ($placeholders)");

    

    // Bind parameters

    $i = 1;

    foreach (array_keys($_SESSION['cart']) as $productId) {

        $stmt->bindValue($i++, $productId, SQLITE3_INTEGER);

    }

    

    $result = $stmt->execute();

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {

        $cart_items[$row['id']] = $row;

        $cart_total += $row['price'] * $_SESSION['cart'][$row['id']];

    }



    // Handle form submission

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        if (!$auth->validateCSRFToken($_POST['csrf_token'])) {

            die('Invalid request');

        }



        // Validate shipping information

        $firstName = trim($_POST['first_name'] ?? '');

        $lastName = trim($_POST['last_name'] ?? '');

        $address = trim($_POST['address'] ?? '');



        if (empty($firstName) || empty($lastName) || empty($address)) {

            throw new Exception('Please fill in all shipping details');

        }



        debug_log("Creating order for user: " . $_SESSION['user_id']);

        debug_log("Cart total: " . $cart_total);

        debug_log("Shipping details: " . json_encode([

            'first_name' => $firstName,

            'last_name' => $lastName,

            'address' => $address

        ]));



        // Start transaction

        $db->getConnection()->exec('BEGIN TRANSACTION');



        try {

            // Create order with shipping details

            $stmt = $db->getConnection()->prepare('INSERT INTO orders (user_id, total_amount, status, shipping_details, created_at) VALUES (?, ?, ?, ?, datetime("now"))');

            if (!$stmt) {

                throw new Exception('Failed to prepare order insert: ' . $db->getConnection()->lastErrorMsg());

            }



            $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);

            $stmt->bindValue(2, $cart_total, SQLITE3_FLOAT);

            $stmt->bindValue(3, 'pending', SQLITE3_TEXT);

            

            // Store shipping details as JSON

            $shippingDetails = json_encode([

                'first_name' => $firstName,

                'last_name' => $lastName,

                'address' => $address

            ]);

            $stmt->bindValue(4, $shippingDetails, SQLITE3_TEXT);

            

            if (!$stmt->execute()) {

                throw new Exception('Failed to insert order: ' . $db->getConnection()->lastErrorMsg());

            }

            

            $order_id = $db->getConnection()->lastInsertRowID();

            debug_log("Order created with ID: " . $order_id);



            // Create order items

            $stmt = $db->getConnection()->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)');

            if (!$stmt) {

                throw new Exception('Failed to prepare order items insert: ' . $db->getConnection()->lastErrorMsg());

            }



            foreach ($cart_items as $id => $item) {

                $stmt->bindValue(1, $order_id, SQLITE3_INTEGER);

                $stmt->bindValue(2, $id, SQLITE3_INTEGER);

                $stmt->bindValue(3, $_SESSION['cart'][$id], SQLITE3_INTEGER);

                $stmt->bindValue(4, $item['price'], SQLITE3_FLOAT);

                

                if (!$stmt->execute()) {

                    throw new Exception('Failed to insert order item: ' . $db->getConnection()->lastErrorMsg());

                }

            }



            // Commit transaction

            $db->getConnection()->exec('COMMIT');

            debug_log("Transaction committed successfully");



            // Clear cart

            $_SESSION['cart'] = [];



            // Redirect to order confirmation

            header('Location: order_confirmation.php?order_id=' . $order_id);

            exit;



        } catch (Exception $e) {

            // Rollback transaction on error

            $db->getConnection()->exec('ROLLBACK');

            debug_log("Transaction rolled back due to error: " . $e->getMessage());

            throw $e;

        }

    }



} catch (Exception $e) {

    debug_log("Error in checkout.php: " . $e->getMessage());

    $error = $e->getMessage();

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>QA Demo - Checkout</title>

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

        <?php endif; ?>



        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">

            <!-- Order Summary -->

            <div class="bg-white shadow overflow-hidden sm:rounded-lg">

                <div class="px-4 py-5 sm:px-6">

                    <h2 class="text-lg leading-6 font-medium text-gray-900">Order Summary</h2>

                </div>

                <div class="border-t border-gray-200">

                    <ul class="divide-y divide-gray-200">

                        <?php foreach ($cart_items as $id => $item): ?>

                            <li class="px-4 py-4 sm:px-6">

                                <div class="flex items-center justify-between">

                                    <div class="flex items-center">

                                        <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'images/placeholder.jpg'); ?>" 

                                             alt="<?php echo htmlspecialchars($item['name']); ?>"

                                             class="h-16 w-16 object-cover rounded">

                                        <div class="ml-4">

                                            <h3 class="text-lg font-medium text-gray-900">

                                                <?php echo htmlspecialchars($item['name']); ?>

                                            </h3>

                                            <p class="text-sm text-gray-500">

                                                Quantity: <?php echo $_SESSION['cart'][$id]; ?>

                                            </p>

                                        </div>

                                    </div>

                                    <div class="text-lg font-medium text-gray-900">

                                        $<?php echo number_format($item['price'] * $_SESSION['cart'][$id], 2); ?>

                                    </div>

                                </div>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                    <div class="px-4 py-5 sm:px-6 border-t border-gray-200">

                        <div class="flex justify-between items-center">

                            <div class="text-lg font-medium text-gray-900">Total</div>

                            <div class="text-lg font-medium text-gray-900">

                                $<?php echo number_format($cart_total, 2); ?>

                            </div>

                        </div>

                    </div>

                </div>

            </div>



            <!-- Checkout Form -->

            <div class="space-y-8">

                <!-- Shipping Information -->

                <div class="bg-white shadow overflow-hidden sm:rounded-lg">

                    <div class="px-4 py-5 sm:px-6">

                        <h2 class="text-lg leading-6 font-medium text-gray-900">Shipping Information</h2>

                    </div>

                    <div class="border-t border-gray-200 px-4 py-5 sm:px-6">

                        <form method="POST" class="space-y-6">

                            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">

                            

                            <div class="grid grid-cols-2 gap-4">

                                <div>

                                    <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>

                                    <input type="text" name="first_name" id="first_name" required

                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">

                                </div>

                                <div>

                                    <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>

                                    <input type="text" name="last_name" id="last_name" required

                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">

                                </div>

                            </div>



                            <div>

                                <label for="address" class="block text-sm font-medium text-gray-700">Shipping Address</label>

                                <textarea name="address" id="address" rows="3" required

                                          class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>

                            </div>



                            <!-- Payment Information -->

                            <div class="pt-6 border-t border-gray-200">

                                <h3 class="text-lg font-medium text-gray-900 mb-4">Payment Information</h3>

                                

                                <div>

                                    <label for="card_number" class="block text-sm font-medium text-gray-700">Card Number</label>

                                    <input type="text" name="card_number" id="card_number" required

                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">

                                </div>



                                <div class="grid grid-cols-2 gap-4 mt-4">

                                    <div>

                                        <label for="expiry" class="block text-sm font-medium text-gray-700">Expiry Date</label>

                                        <input type="text" name="expiry" id="expiry" placeholder="MM/YY" required

                                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">

                                    </div>

                                    <div>

                                        <label for="cvv" class="block text-sm font-medium text-gray-700">CVV</label>

                                        <input type="text" name="cvv" id="cvv" required

                                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">

                                    </div>

                                </div>



                                <div class="mt-4">

                                    <label for="name" class="block text-sm font-medium text-gray-700">Name on Card</label>

                                    <input type="text" name="name" id="name" required

                                           class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">

                                </div>

                            </div>



                            <div class="flex justify-end space-x-4 pt-6">

                                <a href="cart.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">

                                    Back to Cart

                                </a>

                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">

                                    Place Order

                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </main>

</body>

</html>

<?php

// End output buffering and send the output

ob_end_flush();

?> 