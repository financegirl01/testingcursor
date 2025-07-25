<?php
require_once 'config/database.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, username, password, full_name, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-xl shadow-2xl p-8">
        <div class="text-center mb-8">
            <div class="mx-auto h-16 w-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                <i class="fas fa-boxes text-blue-600 text-2xl"></i>
            </div>
            <h2 class="text-3xl font-bold text-gray-900">Welcome Back</h2>
            <p class="text-gray-600 mt-2">Sign in to your inventory account</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-user mr-1"></i>Username
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                    placeholder="Enter your username"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-lock mr-1"></i>Password
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-200"
                    placeholder="Enter your password"
                >
            </div>

            <button 
                type="submit" 
                class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition duration-200 font-medium flex items-center justify-center"
            >
                <i class="fas fa-sign-in-alt mr-2"></i>
                Sign In
            </button>
        </form>

        <div class="mt-8 border-t pt-6">
            <div class="text-sm text-gray-600">
                <h3 class="font-medium mb-2">Demo Accounts:</h3>
                <div class="space-y-1 text-xs">
                    <p><strong>Admin:</strong> admin / password123</p>
                    <p><strong>Manager:</strong> manager1 / password123</p>
                    <p><strong>Staff:</strong> staff1 / password123</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus on username field
        document.getElementById('username').focus();
        
        // Add loading state to form
        document.querySelector('form').addEventListener('submit', function(e) {
            const button = e.target.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Signing In...';
            button.disabled = true;
        });
    </script>
</body>
</html>