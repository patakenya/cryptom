<?php
// referrals.php
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
try {
    $stmt = $pdo->prepare("SELECT full_name, email, account_status, referral_code FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['account_status'] !== 'active') {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Referrals Error: ' . $e->getMessage());
    session_destroy();
    header('Location: login.php');
    exit;
}

// Ensure user has a referral code
if (empty($user['referral_code'])) {
    $referral_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));
    $stmt = $pdo->prepare("UPDATE users SET referral_code = ? WHERE user_id = ?");
    $stmt->execute([$referral_code, $user_id]);
    $user['referral_code'] = $referral_code;
}

// Generate referral link
$referral_link = "http://localhost/miner/register.php?ref=" . urlencode($user['referral_code']);

// Fetch referred users and count
$stmt = $pdo->prepare("SELECT u.user_id, u.full_name, u.email, u.account_status, r.created_at 
                       FROM referrals r 
                       JOIN users u ON r.referred_user_id = u.user_id 
                       WHERE r.referrer_id = ? 
                       ORDER BY r.created_at DESC");
$stmt->execute([$user_id]);
$referred_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
$referral_count = count($referred_users);

// Fetch referral transactions
$stmt = $pdo->prepare("SELECT transaction_id, amount, method, status, transaction_hash, created_at 
                       FROM transactions 
                       WHERE user_id = ? AND type = 'referral' 
                       ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$referral_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total referral earnings
$stmt = $pdo->prepare("SELECT SUM(amount) as total_earnings 
                       FROM transactions 
                       WHERE user_id = ? AND type = 'referral' AND status = 'completed'");
$stmt->execute([$user_id]);
$total_earnings = $stmt->fetch(PDO::FETCH_ASSOC)['total_earnings'] ?? 0.00;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referrals - CryptoMiner ERP</title>
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
                        <h1 class="text-2xl font-semibold text-gray-800 mb-2">Referrals</h1>
                        <p class="text-gray-600 text-sm max-w-xl">Invite friends to CryptoMiner ERP and earn rewards for each successful referral. Three active referrals are required to withdraw funds via MPESA.</p>
                    </div>
                    <a href="wallet.php" class="bg-primary text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">
                        <i class="ri-wallet-line mr-1"></i> Wallet
                    </a>
                </div>
            </div>
        </section>

        <!-- Referral Link and Stats -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Your Referral Program</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Referral Link -->
                    <div>
                        <h3 class="text-sm font-medium text-gray-600 mb-2">Your Referral Link</h3>
                        <div class="flex items-center space-x-2">
                            <input type="text" id="referralLink" value="<?php echo htmlspecialchars($referral_link); ?>" readonly class="block w-full py-2 px-3 border border-gray-300 rounded-button text-sm bg-gray-50 truncate">
                            <button id="copyLinkBtn" class="bg-primary text-white px-4 py-2 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">
                                <i class="ri-clipboard-line mr-1"></i> Copy
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">Share this link to invite friends and earn rewards.</p>
                    </div>
                    <!-- Referral Stats -->
                    <div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-blue-50 rounded-lg">
                                <div class="flex items-center">
                                    <i class="ri-user-add-line text-primary text-2xl mr-2"></i>
                                    <h3 class="text-sm font-medium text-gray-600">Total Referrals</h3>
                                </div>
                                <p class="text-2xl font-bold text-primary mt-2"><?php echo $referral_count; ?></p>
                            </div>
                            <div class="p-4 bg-green-50 rounded-lg">
                                <div class="flex items-center">
                                    <i class="ri-coins-line text-green-600 text-2xl mr-2"></i>
                                    <h3 class="text-sm font-medium text-gray-600">Referral Earnings</h3>
                                </div>
                                <p class="text-2xl font-bold text-green-600 mt-2">$<?php echo number_format($total_earnings, 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Referred Users -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Referred Users</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Signup Date</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($referred_users)): ?>
                                <tr>
                                    <td colspan="3" class="px-4 py-4 text-center text-gray-500">
                                        No referred users yet. Share your referral link to get started.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($referred_users as $referred): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($referred['full_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($referred['email']); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($referred['created_at'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($referred['created_at'])); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $referred['account_status'] === 'active' ? 'green' : 'gray'; ?>-100 text-<?php echo $referred['account_status'] === 'active' ? 'green' : 'gray'; ?>-800"><?php echo ucfirst($referred['account_status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Referral Transactions -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Referral Earnings History</h2>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($referral_transactions)): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-gray-500">
                                        No referral earnings yet. Invite friends to earn rewards.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($referral_transactions as $tx): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                                    <i class="ri-coins-line text-blue-600"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">Referral</div>
                                                    <div class="text-xs text-gray-500 mt-0.5">TX: <?php echo htmlspecialchars(substr($tx['transaction_hash'] ?? 'N/A', 0, 10)); ?>...</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($tx['created_at'])); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-green-600">+$<?php echo number_format($tx['amount'], 2); ?></div>
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
    </main>

    <?php include 'footer.php'; ?>
    <script src="scripts.js"></script>
</body>
</html>