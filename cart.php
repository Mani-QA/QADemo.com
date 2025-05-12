<?php



// Start output buffering

ob_start();



try {

    require_once 'config/database.php';

    require_once 'includes/auth.php';



    $db = new Database();

    $auth = new Auth($db->getConnection());



    // Initialize cart if not exists

    if (!isset($_SESSION['cart'])) {

        $_SESSION['cart'] = [];

    }



    // Handle cart actions

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

        if (!$auth->validateCSRFToken($_POST['csrf_token'])) {

            die('Invalid request');

        }



        $productId = (int)$_POST['product_id'];

        $action = $_POST['action'];



        switch ($action) {

            case 'add':

                if (!isset($_SESSION['cart'][$productId])) {

                    $_SESSION['cart'][$productId] = 1;

                } else {

                    $_SESSION['cart'][$productId]++;

                }

                break;

            case 'remove':

                if (isset($_SESSION['cart'][$productId])) {

                    unset($_SESSION['cart'][$productId]);

                }

                break;

            case 'update':

                if (isset($_POST['quantity']) && isset($_SESSION['cart'][$productId])) {

                    $quantity = (int)$_POST['quantity'];

                    if ($quantity > 0) {

                        $_SESSION['cart'][$productId] = $quantity;

                    } else {

                        unset($_SESSION['cart'][$productId]);

                    }

                }

                break;

        }

        

        header('Location: cart.php');

        exit;

    }



    // Get cart items

    $cart_items = [];

    $cart_total = 0;

    if (!empty($_SESSION['cart'])) {

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

    }



} catch (Exception $e) {

    debug_log("Error in cart.php: " . $e->getMessage());

    $error = 'An error occurred. Please try again later.';

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>QA Demo - Shopping Cart</title>

    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100 min-h-screen">

    <!-- Navigation -->

    <nav class="bg-white shadow-lg">

        <div class="max-w-7xl mx-auto px-4">

            <div class="flex justify-between h-16">

                <div class="flex">

                    <div class="flex-shrink-0 flex items-center">

                        <h1 class="text-xl font-bold"><a href="index.php">QA Demo</a></h1>

                    </div>

                </div>

                <div class="flex items-center">

                    <div class="ml-4 flex items-center md:ml-6">

                        <a href="catalog.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Catalog</a>

                        <?php if ($auth->isAdmin()): ?>

                        <a href="admin.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Admin</a>

                        <?php endif; ?>

                        <?php if ($auth->isLoggedIn()): ?>

                        <a href="logout.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Logout</a>

                        <?php else: ?>

                        <a href="index.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Login</a>

                        <?php endif; ?>

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



        <div class="bg-white shadow overflow-hidden sm:rounded-lg">

            <div class="px-4 py-5 sm:px-6">

                <h2 class="text-lg leading-6 font-medium text-gray-900">Shopping Cart</h2>

            </div>

            

            <?php if (empty($cart_items)): ?>

                <div class="px-4 py-5 sm:p-6">

                    <p class="text-gray-500">Your cart is empty.</p>

                    <a href="catalog.php" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">

                        Continue Shopping

                    </a>

                </div>

            <?php else: ?>

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

                                                $<?php echo number_format($item['price'], 2); ?> each

                                            </p>

                                        </div>

                                    </div>

                                    <div class="flex items-center">

                                        <form method="POST" class="flex items-center">

                                            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">

                                            <input type="hidden" name="action" value="update">

                                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">

                                            <input type="number" name="quantity" value="<?php echo $_SESSION['cart'][$id]; ?>"

                                                   min="1" max="99"

                                                   class="w-16 px-2 py-1 border rounded"

                                                   onchange="this.form.submit()">

                                        </form>

                                        <form method="POST" class="ml-4">

                                            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">

                                            <input type="hidden" name="action" value="remove">

                                            <input type="hidden" name="product_id" value="<?php echo $id; ?>">

                                            <button type="submit" class="text-red-600 hover:text-red-900">

                                                Remove

                                            </button>

                                        </form>

                                    </div>

                                </div>

                            </li>

                        <?php endforeach; ?>

                    </ul>

                </div>

                <div class="px-4 py-5 sm:px-6 border-t border-gray-200">

                    <div class="flex justify-between items-center">

                        <div class="text-lg font-medium text-gray-900">

                            Total: $<?php echo number_format($cart_total, 2); ?>

                        </div>

                        <div class="flex space-x-4">

                            <a href="catalog.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">

                                Continue Shopping

                            </a>

                            <a href="checkout.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">

                                Proceed to Checkout

                            </a>

                        </div>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </main>

</body>

</html>

<?php

// End output buffering and send the output

ob_end_flush();

?> 