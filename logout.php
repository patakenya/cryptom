<?php
// logout.php
require_once 'config.php';

// Check if user is logged in to log activity
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Log logout activity
    try {
        $stmt = $pdo->prepare("INSERT INTO login_activity (user_id, device_type, browser, ip_address, location, action) VALUES (?, ?, ?, ?, ?, 'logout')");
        $device = strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false ? 'Mobile' : 'Desktop';
        $browser = $_SERVER['HTTP_USER_AGENT'];
        $ip = $_SERVER['REMOTE_ADDR'];
        $location = 'Unknown'; // Implement geolocation API if needed
        $stmt->execute([$user_id, $device, substr($browser, 0, 50), $ip, $location]);
    } catch (Exception $e) {
        error_log('Logout Activity Log Error: ' . $e->getMessage());
    }
}

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Regenerate session ID to prevent fixation
session_regenerate_id(true);

// Redirect to login page with success message
header('Location: login.php?logged_out=1');
exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logging Out - CryptoMiner ERP</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#3b82f6',secondary:'#10b981'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col items-center justify-center">
    <div class="max-w-md mx-auto bg-white rounded-lg shadow-sm p-8 text-center">
        <i class="ri-logout-circle-line text-primary text-4xl mb-4"></i>
        <h1 class="text-xl font-semibold text-gray-800 mb-2">Logging Out</h1>
        <p class="text-gray-600 text-sm mb-4">You are being logged out. Please wait...</p>
        <a href="login.php" class="text-primary text-sm font-medium hover:text-blue-600">Click here if you are not redirected.</a>
    </div>
    <script>
        // Fallback redirect
        window.location.href = 'login.php?logged_out=1';
    </script>
</body>
</html>