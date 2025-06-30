<?php
// profile.php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT full_name, email, phone_number, account_status FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['account_status'] !== 'active') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle form submission
$error = '';
$success = '';
$form_data = [
    'full_name' => $user['full_name'],
    'email' => $user['email'],
    'phone_number' => $user['phone_number'] ?? ''
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
        error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
    } else {
        $form_data['full_name'] = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
        $form_data['email'] = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $form_data['phone_number'] = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);

        // Validate inputs
        if (!$form_data['full_name'] || strlen($form_data['full_name']) < 2) {
            $error = 'Please enter a valid full name (minimum 2 characters).';
        } elseif (!$form_data['email']) {
            $error = 'Please enter a valid email address.';
        } elseif ($form_data['phone_number'] && !preg_match('/^\+254[0-9]{9}$/', $form_data['phone_number'])) {
            $error = 'Invalid phone number. Use format: +254712345678';
        } else {
            try {
                $pdo->beginTransaction();

                // Check for email uniqueness (excluding current user)
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
                $stmt->execute([$form_data['email'], $user_id]);
                if ($stmt->fetch()) {
                    $error = 'This email address is already in use.';
                } else {
                    // Update user profile
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone_number = ? WHERE user_id = ?");
                    $stmt->execute([
                        $form_data['full_name'],
                        $form_data['email'],
                        $form_data['phone_number'] ?: NULL,
                        $user_id
                    ]);

                    $pdo->commit();

                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    $success = 'Profile updated successfully!';
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT full_name, email, phone_number, account_status FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $form_data = [
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'phone_number' => $user['phone_number'] ?? ''
                    ];
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Profile update failed: ' . htmlspecialchars($e->getMessage());
                error_log('Profile Update Error: ' . $e->getMessage());
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
    <title>Profile - CryptoMiner ERP</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#3b82f6',secondary:'#10b981'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'header.php'; ?>

    <main class="container mx-auto px-4 py-6">
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6 max-w-md mx-auto">
                <div class="flex items-center justify-between mb-4">
                    <h1 class="text-2xl font-semibold text-gray-800">Update Profile</h1>
                    <a href="wallet.php" class="text-primary text-sm font-medium flex items-center hover:underline">
                        <i class="ri-arrow-left-line mr-1"></i> Back to Wallet
                    </a>
                </div>
                
                <?php if ($error): ?>
                    <div class="mb-4 p-3 bg-red-50 text-red-600 text-sm rounded-button flex items-center">
                        <i class="ri-error-warning-line mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="mb-4 p-3 bg-green-50 text-green-600 text-sm rounded-button flex items-center">
                        <i class="ri-check-line mr-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($form_data['full_name']); ?>" required class="block w-full py-2 pl-3 pr-8 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm">
                        <p class="mt-1 text-xs text-gray-500">Enter your full name.</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>" required class="block w-full py-2 pl-3 pr-8 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm">
                        <p class="mt-1 text-xs text-gray-500">Enter a valid email address.</p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">MPESA Phone Number</label>
                        <div class="relative">
                            <input type="text" name="phone_number" value="<?php echo htmlspecialchars($form_data['phone_number']); ?>" placeholder="+254712345678" class="block w-full py-2 pl-3 pr-8 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm">
                            <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                <i class="ri-phone-line text-gray-400"></i>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Enter your MPESA registered number (e.g., +254712345678). Leave blank if not applicable.</p>
                    </div>

                    <button type="submit" class="w-full bg-primary text-white py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">Update Profile</button>
                </form>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script src="scripts.js"></script>
</body>
</html>