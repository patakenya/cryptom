<?php
// admin/login.php
require_once '../config.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Initialize message
$message = '';
$type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("SELECT admin_id, email, username, password_hash, account_status FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            if ($admin['account_status'] !== 'active') {
                $message = 'Please verify your email before logging in.';
            } else {
                $_SESSION['admin_id'] = $admin['admin_id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_username'] = $admin['username'];

                // Log login activity
                $stmt = $pdo->prepare("INSERT INTO login_activity (user_id, device_type, browser, ip_address, location, status) VALUES (?, ?, ?, ?, ?, 'Active')");
                $device = strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false ? 'Mobile' : 'Desktop';
                $browser = substr($_SERVER['HTTP_USER_AGENT'], 0, 100);
                $ip = $_SERVER['REMOTE_ADDR'];
                $location = 'Unknown';
                $stmt->execute([$admin['admin_id'], $device, $browser, $ip, $location]);

                header('Location: dashboard.php');
                exit;
            }
        } else {
            $message = 'Invalid email or password.';
        }
    } catch (PDOException $e) {
        $message = 'Login failed: ' . htmlspecialchars($e->getMessage());
        error_log('Admin Login Error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CryptoMiner ERP</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#3b82f6',secondary:'#10b981'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include '../header.php'; ?>

    <main class="flex-grow container mx-auto px-4 py-12">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-sm p-8">
            <h1 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Admin Login</h1>
            
            <?php if ($message): ?>
                <div class="mb-4 p-3 bg-<?php echo $type === 'success' ? 'green' : 'red'; ?>-50 text-<?php echo $type === 'success' ? 'green' : 'red'; ?>-600 text-sm rounded-button flex items-center">
                    <i class="ri-<?php echo $type === 'success' ? 'check-line' : 'error-warning-line'; ?> mr-2"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" id="email" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" id="password" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                </div>
                <button type="submit" class="w-full bg-primary text-white py-2 rounded-button font-medium hover:bg-blue-600 transition-colors">Sign In</button>
            </form>
            <p class="mt-4 text-center text-sm text-gray-600">
                Don’t have an account? <a href="register.php" class="text-primary hover:text-blue-600">Register as Admin</a>.
            </p>
        </div>
    </main>

    <?php include '../footer.php'; ?>
</body>
</html>