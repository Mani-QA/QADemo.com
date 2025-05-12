<?php
// Start output buffering
ob_start();

require_once 'config/database.php';
require_once 'includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

$error = '';
$product = null;
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

try {
    // Validate product ID
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($productId <= 0) {
        throw new Exception('Invalid product ID');
    }
    // Fetch product
    $stmt = $db->getConnection()->prepare('SELECT * FROM products WHERE id = :id');
    $stmt->bindValue(':id', $productId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $product = $result->fetchArray(SQLITE3_ASSOC);
    if (!$product) {
        throw new Exception('Product not found');
    }
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    // Handle cart actions via AJAX
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!$auth->validateCSRFToken($_POST['csrf_token'])) {
            error_log(date('Y-m-d H:i:s') . " Invalid CSRF in product_details.php\n", 3, __DIR__ . '/error_log');
            die(json_encode(['success' => false, 'message' => 'Invalid request']));
        }
        $action = $_POST['action'];
        switch ($action) {
            case 'add':
                if (!isset($_SESSION['cart'][$productId])) {
                    $_SESSION['cart'][$productId] = 1;
                }
                break;
            case 'remove':
                if (isset($_SESSION['cart'][$productId])) {
                    unset($_SESSION['cart'][$productId]);
                }
                break;
        }
        $cartCount = array_sum($_SESSION['cart']);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'cartCount' => $cartCount,
            'action' => $action
        ]);
        exit;
    }
    $cartCount = array_sum($_SESSION['cart']);
} catch (Exception $e) {
    error_log(date('Y-m-d H:i:s') . " Error in product_details.php: " . $e->getMessage() . "\n", 3, __DIR__ . '/error_log');
    $error = 'An error occurred. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details - QA Demo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
                        <a href="index.php" class="ml-4 p-2 text-gray-600 hover:text-gray-900">Login</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    <!-- Main Content -->
    <main class="max-w-2xl mx-auto py-8">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php elseif ($product): ?>
            <div class="bg-white shadow overflow-hidden sm:rounded-lg p-6 flex flex-col md:flex-row">
                <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'images/placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full md:w-1/2 h-64 object-cover rounded mb-4 md:mb-0 md:mr-6">
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($product['name']); ?></h2>
                    <p class="text-gray-700 mb-4"><?php echo htmlspecialchars($product['description']); ?></p>
                    <div class="mb-2 text-lg font-semibold text-gray-900">$<?php echo number_format($product['price'], 2); ?></div>
                    <div class="mb-4 text-sm text-gray-600">Available: <?php echo (int)$product['stock']; ?></div>
                    <div>
                        <button type="button"
                            id="cart-action-btn"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white <?php echo isset($_SESSION['cart'][$productId]) ? 'bg-red-600 hover:bg-red-700' : 'bg-indigo-600 hover:bg-indigo-700'; ?>"
                            data-action="<?php echo isset($_SESSION['cart'][$productId]) ? 'remove' : 'add'; ?>"
                            <?php echo (isset($_SESSION['cart'][$productId]) ? '' : ''); ?>>
                            <?php echo isset($_SESSION['cart'][$productId]) ? 'Remove' : 'Add to Cart'; ?>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
    <script>
    $(document).ready(function() {
        var processing = false;
        $('#cart-action-btn').click(function() {
            if (processing) return;
            processing = true;
            var button = $(this);
            var action = button.data('action');
            var csrfToken = '<?php echo $auth->generateCSRFToken(); ?>';
            button.prop('disabled', true);
            $.ajax({
                url: 'product_details.php?id=<?php echo $productId; ?>',
                method: 'POST',
                data: {
                    action: action,
                    csrf_token: csrfToken
                },
                success: function(response) {
                    if (response.success) {
                        if (action === 'add') {
                            button.text('Remove')
                                  .data('action', 'remove')
                                  .removeClass('bg-indigo-600 hover:bg-indigo-700')
                                  .addClass('bg-red-600 hover:bg-red-700');
                        } else {
                            button.text('Add to Cart')
                                  .data('action', 'add')
                                  .removeClass('bg-red-600 hover:bg-red-700')
                                  .addClass('bg-indigo-600 hover:bg-indigo-700');
                        }
                        // Update cart count in nav
                        var cartCount = response.cartCount;
                        var cartIcon = $('.cart-icon');
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
                },
                complete: function() {
                    processing = false;
                    button.prop('disabled', false);
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