<?php

// Start output buffering
ob_start();

try {
    require_once 'config/database.php';
    require_once 'includes/auth.php';

    $db = new Database();
    $auth = new Auth($db->getConnection());

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
    <title>QA Demo - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8 p-8 bg-white rounded-lg shadow-lg">
        <!-- Debug information (remove in production) -->
        <?php if (isset($debug_info) && $debug_info): ?>
        <div class="bg-yellow-50 p-4 rounded-lg mb-6">
            <h3 class="text-lg font-semibold text-yellow-800 mb-2">Debug Information:</h3>
            <div class="text-sm text-yellow-700">
                <?php echo $debug_info; ?>
            </div>
        </div>
        <?php endif; ?>

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
<?php
// End output buffering and send the output
ob_end_flush();
?> 