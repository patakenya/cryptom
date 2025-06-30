<?php
// dashboard.php
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

// Initialize messages
$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

try {
    // Handle miner purchase
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_miner'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Invalid CSRF token. Please try again.';
            error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
        } else {
            $package_id = filter_input(INPUT_POST, 'package_id', FILTER_VALIDATE_INT);
            if ($package_id) {
                // Fetch package details
                $stmt = $pdo->prepare("SELECT name, price, daily_profit, duration_days FROM mining_packages WHERE package_id = ?");
                $stmt->execute([$package_id]);
                $package = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($package) {
                    // Fetch user balance
                    $stmt = $pdo->prepare("SELECT available_balance FROM user_balances WHERE user_id = ? FOR UPDATE");
                    $stmt->execute([$user_id]);
                    $balance = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$balance) {
                        $stmt = $pdo->prepare("INSERT INTO user_balances (user_id, available_balance, pending_balance, total_withdrawn) VALUES (?, 0.00, 0.00, 0.00)");
                        $stmt->execute([$user_id]);
                        $balance = ['available_balance' => 0.00];
                    }

                    if ($balance['available_balance'] >= $package['price']) {
                        // Begin transaction
                        $pdo->beginTransaction();
                        try {
                            // Deduct balance
                            $stmt = $pdo->prepare("UPDATE user_balances SET available_balance = available_balance - ? WHERE user_id = ?");
                            $stmt->execute([$package['price'], $user_id]);
                            
                            // Insert user miner
                            $stmt = $pdo->prepare("INSERT INTO user_miners (user_id, package_id, purchase_date, status, days_remaining) VALUES (?, ?, NOW(), 'active', ?)");
                            $stmt->execute([$user_id, $package_id, $package['duration_days']]);
                            
                            // Record transaction
                            $transaction_hash = 'TX_PURCHASE_' . bin2hex(random_bytes(8));
                            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, method, status, transaction_hash, created_at) VALUES (?, 'purchase', ?, 'wallet', 'completed', ?, NOW())");
                            $stmt->execute([$user_id, -$package['price'], $transaction_hash]);
                            
                            $pdo->commit();
                            $success = "Successfully purchased {$package['name']} for $" . number_format($package['price'], 2) . "!";
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $error = 'Purchase failed. Please try again.';
                            error_log('Purchase Error: ' . $e->getMessage());
                        }
                    } else {
                        $error = 'Insufficient balance to purchase this miner.';
                    }
                } else {
                    $error = 'Invalid mining package selected.';
                }
            } else {
                $error = 'Invalid package ID.';
            }
        }
    }

    // Fetch user data
    $stmt = $pdo->prepare("SELECT full_name, email, referral_code, account_status, created_at FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['account_status'] !== 'active') {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Ensure referral code exists
    $referral_code = $user['referral_code'] ?? null;
    if (empty($referral_code)) {
        try {
            $referral_code = 'REF' . str_pad($user_id, 7, '0', STR_PAD_LEFT); // e.g., REF0000001
            $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE user_id = ?");
            if ($stmt->execute([$referral_code, $user_id])) {
                $user['referral_code'] = $referral_code;
            } else {
                error_log("Failed to update referral_code for user_id: $user_id");
                $referral_code = 'TEMP' . $user_id; // Fallback
                $user['referral_code'] = $referral_code;
            }
        } catch (PDOException $e) {
            error_log("Referral Code Update Error: " . $e->getMessage());
            $referral_code = 'TEMP' . $user_id; // Fallback
            $user['referral_code'] = $referral_code;
        }
    }

    // Fetch balance data
    $stmt = $pdo->prepare("SELECT available_balance FROM user_balances WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $balance = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$balance) {
        $stmt = $pdo->prepare("INSERT INTO user_balances (user_id, available_balance, pending_balance, total_withdrawn) VALUES (?, 0.00, 0.00, 0.00)");
        $stmt->execute([$user_id]);
        $balance = ['available_balance' => 0.00];
    }

    // Fetch pending balance
    $stmt = $pdo->prepare("SELECT SUM(amount) as pending_balance FROM transactions WHERE user_id = ? AND type = 'withdrawal' AND status = 'pending'");
    $stmt->execute([$user_id]);
    $pending_balance = abs($stmt->fetch(PDO::FETCH_ASSOC)['pending_balance'] ?: 0.00);

    // Fetch total withdrawn
    $stmt = $pdo->prepare("SELECT SUM(amount) as total_withdrawn FROM transactions WHERE user_id = ? AND type = 'withdrawal' AND status = 'completed'");
    $stmt->execute([$user_id]);
    $total_withdrawn = abs($stmt->fetch(PDO::FETCH_ASSOC)['total_withdrawn'] ?: 0.00);

    // Fetch total investment
    $stmt = $pdo->prepare("SELECT SUM(mp.price) as total_investment FROM user_miners um JOIN mining_packages mp ON um.package_id = mp.package_id WHERE um.user_id = ?");
    $stmt->execute([$user_id]);
    $total_investment = $stmt->fetch(PDO::FETCH_ASSOC)['total_investment'] ?: 0.00;

    // Fetch active miners count
    $stmt = $pdo->prepare("SELECT COUNT(*) as active_miners FROM user_miners WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $active_miners = $stmt->fetch(PDO::FETCH_ASSOC)['active_miners'];

    // Fetch daily earnings
    $stmt = $pdo->prepare("SELECT SUM(mp.daily_profit) as daily_earnings FROM user_miners um JOIN mining_packages mp ON um.package_id = mp.package_id WHERE um.user_id = ? AND um.status = 'active'");
    $stmt->execute([$user_id]);
    $daily_earnings = $stmt->fetch(PDO::FETCH_ASSOC)['daily_earnings'] ?: 0.00;

    // Fetch referral earnings and count
    $stmt = $pdo->prepare("SELECT SUM(commission_earned) as referral_earnings, COUNT(*) as referral_count FROM referrals WHERE referrer_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $referrals = $stmt->fetch(PDO::FETCH_ASSOC);
    $referral_earnings = $referrals['referral_earnings'] ?: 0.00;
    $referral_count = $referrals['referral_count'];
    $referral_progress = min($referral_count / 3 * 100, 100);

    // Fetch active miners
    $stmt = $pdo->prepare("SELECT um.miner_id, mp.name, mp.price, mp.daily_profit, um.purchase_date, um.status, um.days_remaining, mp.duration_days 
                           FROM user_miners um JOIN mining_packages mp ON um.package_id = mp.package_id 
                           WHERE um.user_id = ? ORDER BY um.purchase_date DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $user_miners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch transactions
    $stmt = $pdo->prepare("SELECT transaction_id, type, amount, method, status, transaction_hash, created_at 
                           FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch mining packages
    $stmt = $pdo->prepare("SELECT package_id, name, price, daily_profit, daily_return_percentage, duration_days FROM mining_packages ORDER BY price ASC");
    $stmt->execute();
    $mining_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch earnings data for chart (last 7 days)
    $stmt = $pdo->prepare("SELECT DATE(created_at) as date, 
                           SUM(CASE WHEN type = 'earning' THEN amount ELSE 0 END) as daily_earnings,
                           SUM(CASE WHEN type = 'referral' THEN amount ELSE 0 END) as referral_earnings
                           FROM transactions 
                           WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                           GROUP BY DATE(created_at) 
                           ORDER BY DATE(created_at) ASC");
    $stmt->execute([$user_id]);
    $earnings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare chart data
    $chart_dates = [];
    $chart_daily_earnings = [];
    $chart_referral_earnings = [];
    $start_date = new DateTime('-7 days');
    for ($i = 0; $i < 7; $i++) {
        $date = $start_date->format('Y-m-d');
        $chart_dates[] = $start_date->format('M d');
        $found = array_filter($earnings_data, fn($row) => $row['date'] === $date);
        $found = reset($found);
        $chart_daily_earnings[] = $found ? (float)$found['daily_earnings'] : 0.00;
        $chart_referral_earnings[] = $found ? (float)$found['referral_earnings'] : 0.00;
        $start_date->modify('+1 day');
    }
} catch (PDOException $e) {
    error_log('Dashboard Error: ' . $e->getMessage());
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CryptoMiner ERP</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#3b82f6',secondary:'#10b981'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include 'header.php'; ?>

    <main class="container mx-auto px-4 py-6">
        <!-- Messages -->
        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 text-red-600 text-sm rounded-button flex items-center justify-center">
                <i class="ri-error-warning-line mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mb-4 p-3 bg-green-50 text-green-600 text-sm rounded-button flex items-center justify-center">
                <i class="ri-check-line mr-2"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Welcome Section -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-blue-400 opacity-90"></div>
                    <div class="h-64 bg-blue-200 bg-cover bg-center"></div>
                    <div class="absolute inset-0 flex items-center">
                        <div class="px-8 md:px-12 w-full">
                            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">Welcome, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
                            <p class="text-blue-100 mb-6 max-w-xl">Manage your mining operations, track earnings, and grow your referrals from your dashboard.</p>
                            <div class="flex flex-wrap gap-4">
                                <a href="#miners" class="bg-white text-primary px-5 py-2.5 rounded-button font-medium hover:bg-blue-50 transition-colors whitespace-nowrap">View Miners</a>
                                <a href="#wallet" class="bg-blue-700 text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-800 transition-colors whitespace-nowrap">Check Wallet</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Cards -->
        <section class="mb-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Total Investment</h3>
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="ri-funds-line text-primary"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($total_investment, 2); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-green-500 text-sm flex items-center">
                        <i class="ri-arrow-up-s-line"></i> <?php echo $active_miners > 0 ? 'Active' : 'No'; ?> Miners
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Active Miners</h3>
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="ri-cpu-line text-green-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900"><?php echo $active_miners; ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-green-500 text-sm flex items-center">
                        <i class="ri-arrow-up-s-line"></i> Running
                    </span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Daily Earnings</h3>
                    <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                        <i class="ri-coin-line text-yellow-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900 earnings-counter">$<?php echo number_format($daily_earnings, 2); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-gray-500">Next update at midnight</span>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Referral Earnings</h3>
                    <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                        <i class="ri-user-add-line text-purple-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($referral_earnings, 2); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-gray-500"><?php echo $referral_count; ?>/3 referrals</span>
                    <div class="w-24 h-2 bg-gray-200 rounded-full ml-2">
                        <div class="h-2 bg-purple-500 rounded-full" style="width: <?php echo $referral_progress; ?>%"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Earnings Chart -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex flex-wrap items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Earnings Overview</h2>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1.5 text-sm bg-blue-50 text-primary rounded-full">7 Days</button>
                        <button class="px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded-full">30 Days</button>
                        <button class="px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded-full">All Time</button>
                    </div>
                </div>
                <div id="earningsChart" class="w-full h-80"></div>
            </div>
        </section>

        <!-- Mining Packages -->
        <section class="py-12 bg-gray-50" id="miners">
            <div class="container mx-auto px-4">
                <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">Our Mining Packages</h2>
                <p class="text-center text-gray-600 text-sm mb-10 max-w-xl mx-auto">Choose a plan that fits your goals and start generating passive income today.</p>
                
                <?php if (empty($mining_packages)): ?>
                    <p class="text-center text-gray-500">No mining packages available at the moment.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
                        <?php foreach ($mining_packages as $index => $package): ?>
                            <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-100 hover:border-blue-200 transition-colors <?php echo $index === 2 ? 'ring-2 ring-blue-500 ring-opacity-20' : ''; ?>">
                                <div class="p-4 <?php echo $index === 2 ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white' : 'bg-blue-50'; ?>">
                                    <h3 class="text-lg font-semibold <?php echo $index === 2 ? 'text-white' : 'text-gray-800'; ?>"><?php echo htmlspecialchars($package['name']); ?></h3>
                                    <div class="mt-2 flex items-baseline">
                                        <span class="text-2xl font-bold <?php echo $index === 2 ? 'text-white' : 'text-primary'; ?>">$<?php echo number_format($package['price'], 2); ?></span>
                                        <span class="text-sm <?php echo $index === 2 ? 'text-blue-100' : 'text-gray-500'; ?> ml-2">one-time</span>
                                    </div>
                                    <?php if ($index === 2): ?>
                                        <div class="absolute top-0 right-0 bg-blue-700 text-white text-xs font-medium px-2 py-1 rounded-bl-lg">POPULAR</div>
                                    <?php endif; ?>
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
                                        <button type="submit" name="purchase_miner" class="block w-full bg-primary text-white py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors text-center <?php echo $balance['available_balance'] < $package['price'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $balance['available_balance'] < $package['price'] ? 'disabled' : ''; ?>>
                                            Purchase Now
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Active Miners -->
        <section id="miners" class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Your Active Miners</h2>
                    <a href="miners.php" class="text-primary text-sm font-medium flex items-center hover:text-blue-600">
                        View All <i class="ri-arrow-right-line ml-1"></i>
                    </a>
                </div>
                
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
                                        No active miners. Purchase a miner below.
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
                                            <div class="text-sm font-medium text-green-600">+$<?php echo number_format($miner['daily_profit'], 2); ?></div>
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

        
        <section id="transactions" class="mb-8">

            
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Recent Transactions</h2>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                    <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                            No transactions yet. <a href="deposit.php" class="text-primary hover:text-blue-600">Make a deposit</a>.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full bg-<?php echo $tx['type'] === 'deposit' ? 'green' : ($tx['type'] === 'withdrawal' ? 'red' : ($tx['type'] === 'referral' ? 'purple' : ($tx['type'] === 'purchase' ? 'blue' : 'blue'))); ?>-100 flex items-center justify-center mr-3">
                                                        <i class="ri-<?php echo $tx['type'] === 'deposit' ? 'arrow-down' : ($tx['type'] === 'withdrawal' ? 'arrow-up' : ($tx['type'] === 'referral' ? 'user-add' : ($tx['type'] === 'purchase' ? 'shopping-cart' : 'coins'))); ?>-line text-<?php echo $tx['type'] === 'deposit' ? 'green' : ($tx['type'] === 'withdrawal' ? 'red' : ($tx['type'] === 'referral' ? 'purple' : ($tx['type'] === 'purchase' ? 'blue' : 'blue'))); ?>-600"></i>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"><?php echo ucfirst($tx['type']); ?></div>
                                                        <div class="text-xs text-gray-500 mt-0.5">TX: <?php echo htmlspecialchars(substr($tx['transaction_hash'] ?? 'N/A', 0, 10)); ?>...</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($tx['created_at'])); ?></div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-<?php echo $tx['amount'] >= 0 ? 'green' : 'red'; ?>-600"><?php echo $tx['amount'] >= 0 ? '+' : ''; ?>$<?php echo number_format(abs($tx['amount']), 2); ?></div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex items-center text-sm text-gray-900">
                                                    <i class="ri-coin-line mr-1.5 text-yellow-500"></i> <?php echo htmlspecialchars($tx['method']); ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $tx['status'] === 'completed' ? 'green' : ($tx['status'] === 'pending' ? 'yellow' : 'red'); ?>-100 text-<?php echo $tx['status'] === 'completed' ? 'green' : ($tx['status'] === 'pending' ? 'yellow' : 'red'); ?>-800"><?php echo ucfirst($tx['status']); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            
        </section>

        <!-- Wallet Section -->
        <section id="wallet" class="mb-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Wallet Balance</h2>
                    
                    <div class="bg-gradient-to-r from-blue-600 to-blue-400 rounded-lg p-5 text-white mb-4">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <p class="text-blue-100 text-sm mb-1">Available Balance</p>
                                <h3 class="text-2xl font-bold">$<?php echo number_format($balance['available_balance'], 2); ?></h3>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                                <i class="ri-wallet-3-line text-white"></i>
                            </div>
                        </div>
                        <div class="flex justify-between text-sm">
                            <div>
                                <p class="text-blue-100 mb-1">Pending</p>
                                <p class="font-medium">$<?php echo number_format($pending_balance, 2); ?></p>
                            </div>
                            <div>
                                <p class="text-blue-100 mb-1">Total Withdrawn</p>
                                <p class="font-medium">$<?php echo number_format($total_withdrawn, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-sm font-medium text-gray-700 mb-3">Quick Actions</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="deposit.php" class="bg-primary text-white py-2 rounded-button text-sm hover:bg-blue-600 transition-colors whitespace-nowrap text-center">
                                <i class="ri-arrow-down-line mr-1"></i> Deposit
                            </a>
                            <a href="withdraw.php" class="bg-<?php echo $referral_count >= 3 ? 'primary text-white' : 'gray-200 text-gray-500 cursor-not-allowed'; ?> py-2 rounded-button text-sm <?php echo $referral_count >= 3 ? 'hover:bg-blue-600' : ''; ?> transition-colors whitespace-nowrap text-center">
                                <i class="ri-arrow-up-line mr-1"></i> Withdraw
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Account Overview</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-800 mb-1">Account Status</h3>
                                    <p class="text-sm text-gray-500 mb-4">Your account details</p>
                                </div>
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full"><?php echo ucfirst($user['account_status']); ?></span>
                            </div>
                            <p class="text-sm text-gray-600">Email: <?php echo htmlspecialchars($user['email']); ?></p>
                            <p class="text-sm text-gray-600">Registered: <?php echo date('M d, Y', strtotime($user['created_at'] ?? '2025-06-18')); ?></p>
                            <a href="profile.php" class="mt-2 inline-block text-primary text-sm hover:text-blue-600">Update Profile</a>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-start justify-between">
                                <h3 class="text-lg font-medium text-gray-800 mb-1">Recent Activity</h3>
                                <a href="activity.php" class="text-primary text-sm hover:text-blue-600">View All</a>
                            </div>
                            <div class="space-y-3 mt-4">
                                <p class="text-sm text-gray-500">No recent activity recorded.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script src="scripts.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const copyLinkBtn = document.getElementById('copyLinkBtn');
            const referralLink = document.getElementById('referralLink');
            if (copyLinkBtn && referralLink) {
                copyLinkBtn.addEventListener('click', function () {
                    referralLink.select();
                    try {
                        document.execCommand('copy');
                        copyLinkBtn.innerHTML = '<i class="ri-check-line"></i>';
                        setTimeout(() => {
                            copyLinkBtn.innerHTML = '<i class="ri-file-copy-line"></i>';
                        }, 2000);
                    } catch (err) {
                        console.error('Failed to copy: ', err);
                    }
                });
            }

            const chartDom = document.getElementById('earningsChart');
            if (chartDom) {
                const myChart = echarts.init(chartDom);
                
                const option = {
                    animation: false,
                    tooltip: {
                        trigger: 'axis',
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        textStyle: {
                            color: '#1f2937'
                        }
                    },
                    grid: {
                        left: '3%',
                        right: '3%',
                        bottom: '3%',
                        top: '3%',
                        containLabel: true
                    },
                    xAxis: {
                        type: 'category',
                        boundaryGap: false,
                        data: <?php echo json_encode($chart_dates); ?>,
                        axisLine: {
                            lineStyle: {
                                color: '#e2e8f0'
                            }
                        },
                        axisLabel: {
                            color: '#6b7280'
                        }
                    },
                    yAxis: {
                        type: 'value',
                        axisLine: {
                            show: false
                        },
                        axisLabel: {
                            color: '#6b7280'
                        },
                        splitLine: {
                            lineStyle: {
                                color: '#e2e8f0'
                            }
                        }
                    },
                    series: [
                        {
                            name: 'Daily Earnings',
                            type: 'line',
                            smooth: true,
                            symbol: 'none',
                            lineStyle: {
                                width: 3,
                                color: 'rgba(87, 181, 231, 1)'
                            },
                            areaStyle: {
                                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                    { offset: 0, color: 'rgba(87, 181, 231, 0.2)' },
                                    { offset: 1, color: 'rgba(87, 181, 231, 0.01)' }
                                ])
                            },
                            data: <?php echo json_encode($chart_daily_earnings); ?>
                        },
                        {
                            name: 'Referral Earnings',
                            type: 'line',
                            smooth: true,
                            symbol: 'none',
                            lineStyle: {
                                width: 3,
                                color: 'rgba(141, 211, 199, 1)'
                            },
                            areaStyle: {
                                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                                    { offset: 0, color: 'rgba(141, 211, 199, 0.2)' },
                                    { offset: 1, color: 'rgba(141, 211, 199, 0.01)' }
                                ])
                            },
                            data: <?php echo json_encode($chart_referral_earnings); ?>
                        }
                    ]
                };
                
                myChart.setOption(option);
                
                window.addEventListener('resize', function () {
                    myChart.resize();
                });
            }
        });
    </script>
</body>
</html>