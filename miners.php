<?php
// miners.php
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
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['account_status'] !== 'active') {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Fetch balance data
$stmt = $pdo->prepare("SELECT available_balance FROM user_balances WHERE user_id = ?");
$stmt->execute([$user_id]);
$balance = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['available_balance' => 0.00];

// Fetch mining packages
$stmt = $pdo->prepare("SELECT package_id, name, price, daily_profit, duration_days, daily_return_percentage, is_active FROM mining_packages WHERE is_active = TRUE ORDER BY price ASC");
$stmt->execute();
$mining_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user miners
$stmt = $pdo->prepare("SELECT um.miner_id, mp.name, mp.price, mp.daily_profit, um.purchase_date, um.status, um.days_remaining, mp.duration_days FROM user_miners um JOIN mining_packages mp ON um.package_id = mp.package_id WHERE um.user_id = ? ORDER BY um.purchase_date DESC");
$stmt->execute([$user_id]);
$user_miners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle purchase form submission
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_package'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
        error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
    } else {
        $package_id = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
        
        // Validate package
        if (!$package_id) {
            $error = 'Invalid package selected.';
        } else {
            // Fetch package details
            $stmt = $pdo->prepare("SELECT package_id, name, price, duration_days, is_active FROM mining_packages WHERE package_id = ?");
            $stmt->execute([$package_id]);
            $package = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$package || !$package['is_active']) {
                $error = 'Selected package is not available.';
            } elseif ($package['price'] > $balance['available_balance']) {
                $error = 'Insufficient balance. Please deposit funds.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Deduct balance
                    $stmt = $pdo->prepare("UPDATE user_balances SET available_balance = available_balance - ? WHERE user_id = ?");
                    $stmt->execute([$package['price'], $user_id]);

                    // Insert miner
                    $stmt = $pdo->prepare("INSERT INTO user_miners (user_id, package_id, purchase_date, status, days_remaining) VALUES (?, ?, NOW(), 'active', ?)");
                    $stmt->execute([$user_id, $package_id, $package['duration_days']]);

                    // Record transaction
                    $transaction_hash = 'TX_' . bin2hex(random_bytes(8));
                    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, method, status, transaction_hash, created_at) VALUES (?, 'purchase', ?, 'balance', 'completed', ?, NOW())");
                    $stmt->execute([$user_id, -$package['price'], $transaction_hash]);

                    $pdo->commit();

                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    // Refresh balance
                    $stmt = $pdo->prepare("SELECT available_balance FROM user_balances WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    $balance = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['available_balance' => 0.00];

                    // Refresh miners list
                    $stmt = $pdo->prepare("SELECT um.miner_id, mp.name, mp.price, mp.daily_profit, um.purchase_date, um.status, um.days_remaining, mp.duration_days FROM user_miners um JOIN mining_packages mp ON um.package_id = mp.package_id WHERE um.user_id = ? ORDER BY um.purchase_date DESC");
                    $stmt->execute([$user_id]);
                    $user_miners = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $success = "Miner '{$package['name']}' purchased successfully!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Purchase failed: ' . htmlspecialchars($e->getMessage());
                    error_log('Purchase Error: ' . $e->getMessage());
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
    <title>Miners - CryptoMiner ERP</title>
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
        <!-- Header Section -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex flex-wrap items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-800 mb-2">Mining Packages</h1>
                        <p class="text-gray-600 text-sm max-w-xl">Purchase miners to start earning daily profits. Your available balance: <span class="font-medium text-primary">$<?php echo number_format($balance['available_balance'], 2); ?></span></p>
                    </div>
                    <a href="wallet.php" class="bg-primary text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">
                        <i class="ri-arrow-down-line mr-1"></i> Deposit Funds
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
        <section id="miners" class="mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-800">Mining Packages</h2>
                <button class="text-primary text-sm font-medium flex items-center">
                    <i class="ri-information-line mr-1"></i> How it works
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                <?php foreach ($mining_packages as $index => $package): ?>
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-100 hover:border-blue-200 transition-colors <?php echo $index === 2 ? 'ring-2 ring-blue-500 ring-opacity-20' : ''; ?>">
                        <div class="bg-<?php echo $index === 2 ? 'gradient-to-r from-blue-600 to-blue-500' : 'blue-50'; ?> p-4 relative">
                            <?php if ($index === 2): ?>
                                <div class="absolute top-0 right-0 bg-blue-700 text-white text-xs font-medium px-2 py-1 rounded-bl-lg">POPULAR</div>
                            <?php endif; ?>
                            <h3 class="text-lg font-semibold <?php echo $index === 2 ? 'text-white' : 'text-gray-800'; ?>"><?php echo htmlspecialchars($package['name']); ?></h3>
                            <div class="mt-2 flex items-baseline">
                                <span class="text-2xl font-bold <?php echo $index === 2 ? 'text-white' : 'text-primary'; ?>">$<?php echo number_format($package['price'], 2); ?></span>
                                <span class="text-<?php echo $index === 2 ? 'blue-100' : 'gray-500'; ?> text-sm ml-2">one-time</span>
                            </div>
                        </div>
                        <div class="p-4">
                            <ul class="space-y-2 mb-4">
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="ri-check-line text-green-500 mr-2"></i>
                                    <?php echo number_format($package['daily_return_percentage'], 2); ?>% daily return
                                </li>
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="ri-check-line text-green-500 mr-2"></i>
                                    <?php echo $package['duration_days']; ?> days duration
                                </li>
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="ri-check-line text-green-500 mr-2"></i>
                                    Total return: $<?php echo number_format($package['price'] * (1 + ($package['daily_return_percentage'] / 100) * $package['duration_days']), 2); ?>
                                </li>
                                <li class="flex items-center text-sm text-gray-600">
                                    <i class="ri-check-line text-green-500 mr-2"></i>
                                    Daily profit: $<?php echo number_format($package['daily_profit'], 2); ?>
                                </li>
                            </ul>
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="package_id" value="<?php echo $package['package_id']; ?>">
                                <button type="submit" name="purchase_package" class="w-full bg-primary text-white py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap <?php echo $package['price'] > $balance['available_balance'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $package['price'] > $balance['available_balance'] ? 'disabled' : ''; ?>>
                                    Purchase Now
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Active Miners -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Active Miners</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Miner</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purchase Date</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Profit</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Progress</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($user_miners)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                        No active miners. <a href="#miners" class="text-primary hover:text-blue-600">Purchase a miner now</a>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($user_miners as $miner): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                                    <i class="ri-cpu-line text-primary"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($miner['name']); ?></div>
                                                    <div class="text-sm text-gray-500">$<?php echo number_format($miner['price'], 2); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($miner['purchase_date'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo floor((time() - strtotime($miner['purchase_date'])) / 86400); ?> days ago</div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium <?php echo $miner['status'] === 'active' ? 'text-green-600' : 'text-gray-500'; ?>">+$<?php echo number_format($miner['status'] === 'active' ? $miner['daily_profit'] : 0, 2); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                                    <?php $progress = max(0, ($miner['duration_days'] - $miner['days_remaining']) / $miner['duration_days'] * 100); ?>
                                                    <div class="bg-<?php echo $miner['status'] === 'active' ? 'primary' : 'gray-400'; ?> h-2.5 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                                                </div>
                                                <span class="ml-2 text-xs text-gray-500"><?php echo ($miner['duration_days'] - $miner['days_remaining']); ?>/<?php echo $miner['duration_days']; ?> days</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $miner['status'] === 'active' ? 'green-100 text-green-800' : 'gray-100 text-gray-800'; ?>">
                                                <?php echo ucfirst($miner['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script src="scripts.js"></script>
</body>
</html>