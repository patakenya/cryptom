<?php
// verify_email.php
require_once 'config.php';

// Initialize message
$message = '';
$type = 'error'; // error or success

if (!isset($_GET['token']) || empty($_GET['token'])) {
    $message = 'Invalid verification link.';
} else {
    $token = filter_var($_GET['token'], FILTER_SANITIZE_STRING);

    // Fetch user with token
    $stmt = $pdo->prepare("SELECT user_id, email, full_name, verification_token_expires, account_status FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $message = 'Invalid or expired verification link.';
    } elseif ($user['account_status'] === 'active') {
        $message = 'Your account is already verified. You can <a href="login.php" class="text-primary hover:text-blue-600">sign in</a> now.';
        $type = 'success';
    } elseif (strtotime($user['verification_token_expires']) < time()) {
        $message = 'Verification link has expired. Please <a href="resend_verification.php" class="text-primary hover:text-blue-600">request a new link</a>.';
    } else {
        try {
            $pdo->beginTransaction();

            // Update user status and clear token
            $stmt = $pdo->prepare("UPDATE users SET account_status = 'active', verification_token = NULL, verification_token_expires = NULL WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);

            // Log login activity (optional, for tracking activation)
            $stmt = $pdo->prepare("INSERT INTO login_activity (user_id, device_type, browser, ip_address, location, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $device = strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false ? 'Mobile' : 'Desktop';
            $browser = substr($_SERVER['HTTP_USER_AGENT'], 0, 50);
            $ip = $_SERVER['REMOTE_ADDR'];
            $location = 'Unknown'; // Implement geolocation API if needed
            $stmt->execute([$user['user_id'], $device, $browser, $ip, $location]);

            $pdo->commit();

            $message = 'Your account has been verified successfully! You can now <a href="login.php" class="text-primary hover:text-blue-600">sign in</a>.';
            $type = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = 'Verification failed: ' . htmlspecialchars($e->getMessage());
            error_log('Verification Error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - CryptoMiner ERP</title>
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
            <h1 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Verify Your Email</h1>
            
            <div class="mb-4 p-3 bg-<?php echo $type === 'success' ? 'green' : 'red'; ?>-50 text-<?php echo $type === 'success' ? 'green' : 'red'; ?>-600 text-sm rounded-button flex items-center">
                <i class="ri-<?php echo $type === 'success' ? 'check-line' : 'error-warning-line'; ?> mr-2"></i>
                <?php echo $message; ?>
            </div>

            <?php if ($type === 'error' && strpos($message, 'expired') !== false): ?>
                <p class="text-center text-sm text-gray-600">
                    Need help? Contact <a href="contact.php" class="text-primary hover:text-blue-600">support</a>.
                </p>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>