<?php
// login.php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize error message
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token.';
    } else {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        // Validate inputs
        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            // Fetch user
            $stmt = $pdo->prepare("SELECT user_id, password_hash, full_name, referral_code, account_status FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['account_status'] === 'suspended') {
                    $error = 'Your account is suspended. Contact support.';
                } elseif ($user['account_status'] === 'pending') {
                    $error = 'Your account is pending verification.';
                } else {
                    // Set session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['referral_code'] = $user['referral_code'];

                    // Log login activity
                    $stmt = $pdo->prepare("INSERT INTO login_activity (user_id, device_type, browser, ip_address, location) VALUES (?, ?, ?, ?, ?)");
                    $device = strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false ? 'Mobile' : 'Desktop';
                    $browser = $_SERVER['HTTP_USER_AGENT'];
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $location = 'Unknown'; // Implement geolocation API if needed
                    $stmt->execute([$user['user_id'], $device, substr($browser, 0, 50), $ip, $location]);

                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CryptoMiner ERP</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#3b82f6',secondary:'#10b981'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include 'header.php'; ?>

    <main class="flex-grow container mx-auto px-4 py-12">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-sm p-8">
            <h1 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Sign In</h1>
            
            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 text-red-600 text-sm rounded-button flex items-center">
                    <i class="ri-error-warning-line mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="ri-mail-line text-gray-400"></i>
                        </div>
                        <input type="email" name="email" required class="block w-full pl-10 py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" placeholder="you@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="ri-lock-line text-gray-400"></i>
                        </div>
                        <input type="password" name="password" required class="block w-full pl-10 py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" placeholder="••••••••">
                    </div>
                    <div class="mt-2 text-right">
                        <a href="forgot-password.php" class="text-sm text-primary hover:text-blue-600">Forgot Password?</a>
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-primary text-white py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">Sign In</button>
            </form>
            
            <p class="mt-6 text-center text-sm text-gray-600">
                Don't have an account? <a href="register.php" class="text-primary font-medium hover:text-blue-600">Sign Up</a>
            </p>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>