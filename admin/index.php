<?php
// admin/index.php
require_once '../config.php';

// Initialize message
$error = '';

try {
    // Check if admin is logged in
    if (isset($_SESSION['admin_id'])) {
        // Verify admin status
        $stmt = $pdo->prepare("SELECT admin_id, account_status FROM admins WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch();
        
        if ($admin && $admin['account_status'] === 'active') {
            // Redirect to dashboard
            header('Location: dashboard.php');
            exit;
        } else {
            // Invalid or inactive admin account
            session_destroy();
            $error = 'Your account is not active. Please contact support.';
        }
    } else {
        // No admin session, redirect to login
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Admin Index Error: ' . $e->getMessage());
    $error = 'An error occurred. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CryptoMiner ERP</title>
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
            <h1 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Admin Panel</h1>
            
            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 text-red-600 text-sm rounded-button flex items-center">
                    <i class="ri-error-warning-line mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <p class="text-center text-sm text-gray-600">
                    <a href="login.php" class="text-primary hover:text-blue-600">Sign in</a> or contact <a href="../contact.php" class="text-primary hover:text-blue-600">support</a>.
                </p>
            <?php endif; ?>
        </div>
    </main>

    <?php include '../footer.php'; ?>
</body>
</html>