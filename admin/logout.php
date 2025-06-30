<?php
// admin/logout.php
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../index.php');
    exit;
}

try {
    // Fetch admin data
    $stmt = $pdo->prepare("SELECT admin_id, email, username, account_status FROM admins WHERE admin_id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if ($admin && $admin['account_status'] === 'active') {
        // Log logout activity
        $stmt = $pdo->prepare("INSERT INTO login_activity (user_id, device_type, browser, ip_address, location, status) VALUES (?, ?, ?, ?, ?, 'Logged Out')");
        $device = strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false ? 'Mobile' : 'Desktop';
        $browser = substr($_SERVER['HTTP_USER_AGENT'], 0, 100);
        $ip = $_SERVER['REMOTE_ADDR'];
        $location = 'Unknown';
        $stmt->execute([$admin['admin_id'], $device, $browser, $ip, $location]);
    }

    // Destroy session
    unset($_SESSION['admin_id']);
    session_destroy();
    header('Location: ../index.php');
    exit;
} catch (PDOException $e) {
    error_log('Admin Logout Error: ' . $e->getMessage());
    // Destroy session even if logging fails
    unset($_SESSION['admin_id']);
    session_destroy();
    header('Location: ../index.php');
    exit;
}
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
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include '../header.php'; ?>

    <main class="flex-grow container mx-auto px-4 py-12">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-sm p-8 text-center">
            <h1 class="text-2xl font-semibold text-gray-800 mb-6">Logging Out</h1>
            <div class="flex justify-center mb-4">
                <i class="ri-loader-4-line text-3xl text-primary animate-spin"></i>
            </div>
            <p class="text-sm text-gray-600">You are being logged out. Please wait...</p>
        </div>
    </main>

    <?php include '../footer.php'; ?>
    <script>
        // Redirect after a short delay to show the loading message
        setTimeout(() => {
            window.location.href = '../index.php';
        }, 1000);
    </script>
</body>
</html>