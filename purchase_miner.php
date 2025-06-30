<?php
// purchase_miner.php
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
$stmt = $pdo->prepare("SELECT full_name, email, account_status FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user || $user['account_status'] !== 'active') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch balance data
$stmt = $pdo->prepare("SELECT available_balance, pending_balance FROM user_balances WHERE user_id = ?");
$stmt->execute([$user_id]);
$balance = $stmt->fetch() ?: ['available_balance' => 0.00, 'pending_balance' => 0.00];

// Fetch available mining packages
$stmt = $pdo->prepare("SELECT package_id, name, price, daily_profit, duration_days, daily_return_percentage FROM mining_packages WHERE is_active = TRUE ORDER BY price ASC");
$stmt->execute();
$packages = $stmt->fetchAll();

// Handle purchase form submission
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_miner'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
        error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
    } else {
        $package_id = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
        
        // Validate package
        if (!$package_id) {
            $error = 'Please select a valid mining package.';
        } else {
            // Fetch package details
            $stmt = $pdo->prepare("SELECT name, price, duration_days FROM mining_packages WHERE package_id = ? AND is_active = TRUE");
            $stmt->execute([$package_id]);
            $package = $stmt->fetch();
            
            if (!$package) {
                $error = 'Selected mining package is not available.';
            } elseif ($package['price'] > $balance['available_balance']) {
                $error = 'Insufficient balance to purchase this package. Please deposit funds.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Generate transaction hash
                    $transaction_hash = 'TX_MINER_' . bin2hex(random_bytes(8));

                    // Insert transaction
                    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, method, status, transaction_hash, created_at) VALUES (?, 'purchase', ?, 'Balance', 'completed', ?, NOW())");
                    $stmt->execute([$user_id, -$package['price'], $transaction_hash]);

                    // Insert user miner
                    $stmt = $pdo->prepare("INSERT INTO user_miners (user_id, package_id, purchase_date, status, days_remaining) VALUES (?, ?, NOW(), 'active', ?)");
                    $stmt->execute([$user_id, $package_id, $package['duration_days']]);

                    // Update balance
                    $stmt = $pdo->prepare("UPDATE user_balances SET available_balance = available_balance - ? WHERE user_id = ?");
                    $stmt->execute([$package['price'], $user_id]);

                    // Log purchase
                    $log_message = "User {$user['email']} purchased miner '{$package['name']}' (ID: $package_id, Price: {$package['price']}) at " . date('Y-m-d H:i:s');
                    error_log($log_message, 3, 'admin_logs.txt');

                    $pdo->commit();

                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    $success = "Successfully purchased {$package['name']} miner! It is now active.";
                    
                    // Refresh balance
                    $stmt = $pdo->prepare("SELECT available_balance, pending_balance FROM user_balances WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $balance = $stmt->fetch() ?: ['available_balance' => 0.00, 'pending_balance' => 0.00];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Purchase failed: ' . htmlspecialchars($e->getMessage());
                    error_log('Miner Purchase Error: ' . $e->getMessage());
                }
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
    <title>Purchase Miner - CryptoMiner ERP</title>
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
        <div class="max-w-3xl mx-auto">
            <!-- Header Section -->
            <section class="mb-8">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex flex-wrap items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-800 mb-2">Purchase a Miner</h1>
                            <p class="text-gray-600 text-sm max-w-xl">Select a mining package to start earning daily profits. Available balance: <span class="font-medium text-primary">$<?php echo number_format($balance['available_balance'], 2); ?></span></p>
                        </div>
                        <a href="miners.php" class="bg-primary text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">
                            <i class="ri-cpu-line mr-1"></i> View Miners
                        </a>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="mt-4 p-3 bg-red-50 text-red-600 text-sm rounded-button flex items-center">
                            <i class="ri-error-warning-line mr-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="mt-4 p-3 bg-green-50 text-green-600 text-sm rounded-button flex items-center">
                            <i class="ri-check-line mr-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Mining Packages -->
            <section>
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Available Mining Packages</h2>
                    
                    <?php if (empty($packages)): ?>
                        <p class="text-center text-gray-500">No mining packages available at the moment.</p>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php foreach ($packages as $package): ?>
                                    <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow <?php echo $package['price'] > $balance['available_balance'] ? 'opacity-50' : ''; ?>">
                                        <div class="flex items-center mb-3">
                                            <i class="ri-cpu-line text-primary text-2xl mr-2"></i>
                                            <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($package['name']); ?></h3>
                                        </div>
                                        <p class="text-sm text-gray-600 mb-2">Price: <span class="font-medium text-primary">$<?php echo number_format($package['price'], 2); ?></span></p>
                                        <p class="text-sm text-gray-600 mb-2">Daily Profit: <span class="font-medium text-green-600">$<?php echo number_format($package['daily_profit'], 2); ?></span> (<?php echo number_format($package['daily_return_percentage'], 2); ?>%)</p>
                                        <p class="text-sm text-gray-600 mb-4">Duration: <span class="font-medium"><?php echo $package['duration_days']; ?> days</span></p>
                                        <label class="flex items-center">
                                            <input type="radio" name="package_id" value="<?php echo $package['package_id']; ?>" required class="form-radio text-primary h-4 w-4" <?php echo $package['price'] > $balance['available_balance'] ? 'disabled' : ''; ?>>
                                            <span class="ml-2 text-sm text-gray-700">Select this package</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button type="submit" name="purchase_miner" class="mt-6 w-full bg-primary text-white py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap <?php echo $balance['available_balance'] < min(array_column($packages, 'price')) ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $balance['available_balance'] < min(array_column($packages, 'price')) ? 'disabled' : ''; ?>>Purchase Miner</button>
                        </form>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <script src="scripts.js"></script>
</body>
</html>