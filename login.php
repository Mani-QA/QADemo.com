<?php
// Start output buffering
ob_start();

require_once 'config/database.php';
require_once 'includes/auth.php';

$db = new Database();
$auth = new Auth($db->getConnection());

$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : 'catalog.php');

if ($auth->isLoggedIn()) {
    header('Location: ' . htmlspecialchars($redirect));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$auth->validateCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request';
        error_log(date('Y-m-d H:i:s') . " CSRF validation failed in login.php\n", 3, __DIR__ . '/error_log');
    } else {
        $result = $auth->login($_POST['username'], $_POST['password']);
        if ($result['success']) {
            header('Location: ' . htmlspecialchars($redirect));
            exit;
        } else {
            $error = $result['message'];
            error_log(date('Y-m-d H:i:s') . " Login failed: $error\n", 3, __DIR__ . '/error_log');
        }
    }
}

$csrf_token = $auth->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QA Demo - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-lg">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                QA Demo
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Please sign in to continue
            </p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-blue-50 p-4 rounded-lg mb-6">
            <h3 class="text-lg font-semibold text-blue-800 mb-2">Test Credentials:</h3>
            <ul class="space-y-2 text-sm text-blue-700">
                <li>Standard User: standard_user / standard123</li>
                <li>Locked User: locked_user / locked123</li>
            </ul>
        </div>

        <form class="mt-8 space-y-6" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="username" class="sr-only">Username</label>
                    <input id="username" name="username" type="text" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                           placeholder="Username">
                </div>
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" 
                           placeholder="Password">
                </div>
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Sign in
                </button>
            </div>
        </form>
    </div>
</body>
</html>
<?php ob_end_flush(); ?> 