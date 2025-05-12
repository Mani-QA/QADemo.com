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

    // Handle cart actions via AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!$auth->validateCSRFToken($_POST['csrf_token'])) {
            die(json_encode(['success' => false, 'message' => 'Invalid request']));
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
        }
        
        // Return JSON response for AJAX
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'cartCount' => array_sum($_SESSION['cart']),
            'action' => $action
        ]);
        exit;
    }

    // Get all products
    $stmt = $db->getConnection()->prepare('SELECT * FROM products ORDER BY name');
    $result = $stmt->execute();
    $products = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $products[] = $row;
    }

    // Calculate total items in cart
    $cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

    if ($auth->isLoggedIn()) {
        header('Location: catalog.php');
        exit;
    }

    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        debug_log("Login form submitted");
        debug_log("POST data received: " . print_r($_POST, true));
        debug_log("Session data before validation: " . print_r($_SESSION, true));
        
        if (!isset($_POST['csrf_token']) || !$auth->validateCSRFToken($_POST['csrf_token'])) {
            $error = 'Invalid request';
            debug_log("CSRF validation failed in index.php");
        } else {
            $result = $auth->login($_POST['username'], $_POST['password']);
            if ($result['success']) {
                header('Location: catalog.php');
                exit;
            } else {
                $error = $result['message'];
                debug_log("Login failed: " . $error);
            }
        }
    }

    // Generate a new CSRF token for the form
    $csrf_token = $auth->generateCSRFToken();

    // Get the current script path
    $script_path = dirname($_SERVER['SCRIPT_NAME']);
    if ($script_path === '/') {
        $script_path = '';
    }

    
} catch (Exception $e) {
    debug_log("Error in index.php: " . $e->getMessage());
    $error = 'An error occurred. Please try again later.';
    $debug_info = "Error: " . $e->getMessage() . "<br>";
    $debug_info .= "File: " . $e->getFile() . "<br>";
    $debug_info .= "Line: " . $e->getLine() . "<br>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Demo Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold"><a href="index.php">QA Demo</a></h1>
                </div>
                <div class="flex items-center">
                    <a href="cart.php" class="relative ml-4 p-2 text-gray-600 hover:text-gray-900 cart-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <?php if ($cartCount > 0): ?>
                        <span class="cart-count absolute -top-1 -right-1 bg-indigo-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo $cartCount; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php if ($auth->isAdmin()): ?>
                    <a href="admin.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Admin</a>
                    <?php endif; ?>
                    <?php if ($auth->isLoggedIn()): ?>
                    <a href="logout.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Logout</a>
                    <?php else: ?>
                    <a href="login.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
    

        <!-- Welcome Section -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:p-6">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Welcome to QA Demo Store</h1>
            </div>
        </div>

        <!-- Product Catalog -->
        <div class="bg-white shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h2 class="text-lg leading-6 font-medium text-gray-900">Product Catalog</h2>
            </div>
            
            <div class="border-t border-gray-200">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 p-4">
                    <?php foreach ($products as $product): ?>
                        <div class="bg-white overflow-hidden shadow rounded-lg">
                            <a href="product_details.php?id=<?php echo $product['id']; ?>">
                                <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'images/placeholder.jpg'); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     class="w-full h-48 object-cover">
                            </a>
                            <div class="p-4">
                                <h3 class="text-lg font-medium text-gray-900">
                                    <a href="product_details.php?id=<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </a>
                                </h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    <?php echo htmlspecialchars($product['description']); ?>
                                </p>
                                <div class="mt-4 flex items-center justify-between">
                                    <span class="text-lg font-medium text-gray-900">
                                        $<?php echo number_format($product['price'], 2); ?>
                                    </span>
                                    <button type="button" 
                                            class="cart-button inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700"
                                            data-product-id="<?php echo $product['id']; ?>"
                                            data-action="<?php echo isset($_SESSION['cart'][$product['id']]) ? 'remove' : 'add'; ?>">
                                        <?php echo isset($_SESSION['cart'][$product['id']]) ? 'Remove' : 'Add to Cart'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
    $(document).ready(function() {
        $('.cart-button').click(function() {
            const button = $(this);
            const productId = button.data('product-id');
            const action = button.data('action');
            const csrfToken = '<?php echo $auth->generateCSRFToken(); ?>';

            $.ajax({
                url: 'index.php',
                method: 'POST',
                data: {
                    action: action,
                    product_id: productId,
                    csrf_token: csrfToken
                },
                success: function(response) {
                    if (response.success) {
                        // Toggle button text and action
                        if (action === 'add') {
                            button.text('Remove').data('action', 'remove');
                        } else {
                            button.text('Add to Cart').data('action', 'add');
                        }
                        
                        // Update cart count
                        const cartCount = response.cartCount;
                        const cartIcon = $('.cart-icon');
                        
                        if (cartCount > 0) {
                            if ($('.cart-count').length === 0) {
                                cartIcon.append('<span class="cart-count absolute -top-1 -right-1 bg-indigo-600 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">' + cartCount + '</span>');
                            } else {
                                $('.cart-count').text(cartCount);
                            }
                        } else {
                            $('.cart-count').remove();
                        }
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });
    });
    </script>
</body>
</html>
<?php
// End output buffering and send the output
ob_end_flush();
?> 