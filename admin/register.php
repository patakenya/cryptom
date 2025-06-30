<?php
// admin/register.php
require_once '../config.php';

// Initialize message
$message = '';
$type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
    $full_name = filter_var($_POST['full_name'], FILTER_SANITIZE_STRING); // Added full_name
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Generate verification token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    try {
        // Check if email or username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        if ($stmt->fetchColumn() > 0) {
            $message = 'Email or username already registered.';
        } elseif (empty($full_name)) {
            $message = 'Please provide your full name.';
        } else {
            // Insert admin
            $stmt = $pdo->prepare("INSERT INTO admins (username, full_name, email, password_hash, verification_token, verification_token_expires, account_status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$username, $full_name, $email, $password, $token, $expires]);

            // Send verification email
            if (sendVerificationEmail($email, $username, $token)) {
                $message = 'A verification email has been sent to ' . htmlspecialchars($email) . '. Please check your inbox (or spam folder).';
                $type = 'success';
            } else {
                $message = 'Failed to send verification email. Please try again later.';
            }
        }
    } catch (PDOException $e) {
        $message = 'Registration failed: ' . htmlspecialchars($e->getMessage());
        error_log('Admin Registration Error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Register - CryptoMiner ERP</title>
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
            <h1 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Admin Registration</h1>
            
            <?php if ($message): ?>
                <div class="mb-4 p-3 bg-<?php echo $type === 'success' ? 'green' : 'red'; ?>-50 text-<?php echo $type === 'success' ? 'green' : 'red'; ?>-600 text-sm rounded-button flex items-center">
                    <i class="ri-<?php echo $type === 'success' ? 'check-line' : 'error-warning-line'; ?> mr-2"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label for="full_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" name="full_name" id="full_name" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                </div>
                <div class="mb-4">
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" name="username" id="username" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input type="email" name="email" id="email" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" name="password" id="password" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                </div>
                <button type="submit" class="w-full bg-primary text-white py-2 rounded-button font-medium hover:bg-blue-600 transition-colors">Register as Admin</button>
            </form>
            <p class="mt-4 text-center text-sm text-gray-600">
                Already have an account? <a href="login.php" class="text-primary hover:text-blue-600">Sign in</a>.
            </p>
        </div>
    </main>

    <?php include '../footer.php'; ?>
</body>
</html>