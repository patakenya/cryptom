<?php
// withdraw.php
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

// Fetch referral count
$stmt = $pdo->prepare("SELECT COUNT(*) as referral_count FROM referrals WHERE referrer_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$referral_count = $stmt->fetchColumn() ?: 0;

// Fetch balance data
$stmt = $pdo->prepare("SELECT available_balance, pending_balance, total_withdrawn FROM user_balances WHERE user_id = ?");
$stmt->execute([$user_id]);
$balance = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$balance) {
    // Insert default balance record if none exists
    $stmt = $pdo->prepare("INSERT INTO user_balances (user_id, available_balance, pending_balance, total_withdrawn) VALUES (?, 0.00, 0.00, 0.00)");
    $stmt->execute([$user_id]);
    $balance = ['available_balance' => 0.00, 'pending_balance' => 0.00, 'total_withdrawn' => 0.00];
}

// Fetch recent withdrawal transactions
$stmt = $pdo->prepare("SELECT transaction_id, type, amount, method, status, transaction_hash, created_at FROM transactions WHERE user_id = ? AND type = 'withdrawal' ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle withdrawal form submission
$error = '';
$success = '';
$form_data = ['amount' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
        error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
    } else {
        $form_data['amount'] = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

        // Validate inputs
        if (!$user['phone_number'] || !preg_match('/^\+254[0-9]{9}$/', $user['phone_number'])) {
            $error = 'A valid registered phone number (e.g., +254712345678) is required for MPESA withdrawals. Please update your profile.';
        } elseif ($referral_count < 3) {
            $error = 'You need at least 3 referrals to withdraw funds.';
        } elseif (!$form_data['amount'] || $form_data['amount'] <= 0) {
            $error = 'Please enter a valid withdrawal amount.';
        } elseif ($form_data['amount'] < 10) {
            $error = 'Minimum withdrawal amount is $10.00.';
        } elseif ($form_data['amount'] > $balance['available_balance']) {
            $error = 'Insufficient available balance.';
        } else {
            try {
                $pdo->beginTransaction();

                // Generate a transaction hash
                $transaction_hash = 'TX_WD_' . bin2hex(random_bytes(8));

                // Insert transaction (pending)
                $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, method, status, transaction_hash, created_at) VALUES (?, 'withdrawal', ?, 'MPESA', 'pending', ?, NOW())");
                $stmt->execute([$user_id, -$form_data['amount'], $transaction_hash]);

                // Update pending balance
                $stmt = $pdo->prepare("UPDATE user_balances SET pending_balance = pending_balance + ? WHERE user_id = ?");
                $stmt->execute([$form_data['amount'], $user_id]);

                $pdo->commit();

                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $success = 'Withdrawal request submitted successfully to MPESA number ' . htmlspecialchars($user['phone_number']) . '! Awaiting confirmation.';
                $form_data = ['amount' => ''];

                // Refresh transactions
                $stmt = $pdo->prepare("SELECT transaction_id, type, amount, method, status, transaction_hash, created_at FROM transactions WHERE user_id = ? AND type = 'withdrawal' ORDER BY created_at DESC LIMIT 5");
                $stmt->execute([$user_id]);
                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Withdrawal request failed: ' . htmlspecialchars($e->getMessage());
                error_log('Withdrawal Error: ' . $e->getMessage());
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
    <title>Withdraw - CryptoMiner ERP</title>
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
                        <h1 class="text-2xl font-semibold text-gray-800 mb-2">Withdraw Funds</h1>
                        <p class="text-gray-600 text-sm max-w-xl">Request a withdrawal from your available balance to your registered MPESA number. Current balance: <span class="font-medium text-primary">$<?php echo number_format($balance['available_balance'], 2); ?></span></p>
                    </div>
                    <a href="wallet.php" class="bg-primary text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">
                        <i class="ri-wallet-line mr-1"></i> Wallet Overview
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

        <!-- Withdrawal Form and Transaction History -->
        <section class="mb-8 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Make a Withdrawal</h2>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount (USD)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500">$</span>
                                </div>
                                <input type="number" name="amount" min="10" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars($form_data['amount']); ?>" required class="block w-full pl-7 pr-12 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm">
                                <div class="absolute inset-y-0 right-0 flex items-center">
                                    <button type="button" class="h-full px-3 text-xs text-primary font-medium bg-blue-50 rounded-r-button whitespace-nowrap" onclick="this.form.amount.value = <?php echo floor($balance['available_balance'] * 100) / 100; ?>">MAX</button>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Minimum withdrawal: $10.00. Referrals: <?php echo $referral_count; ?>/3</p>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">MPESA Phone Number</label>
                            <div class="relative">
                                <input type="text" value="<?php echo htmlspecialchars($user['phone_number'] ?: 'No number registered'); ?>" readonly class="block w-full py-2 pl-3 pr-8 border border-gray-300 rounded-button bg-gray-100 text-sm cursor-not-allowed">
                                <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                    <i class="ri-phone-line text-gray-400"></i>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Withdrawals will be sent to your registered MPESA number. <a href="profile.php" class="text-primary hover:underline">Update number</a></p>
                        </div>
                        
                        <button type="submit" name="submit_withdrawal" class="w-full bg-primary text-white py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap <?php echo $referral_count < 3 || $balance['available_balance'] < 10 || !$user['phone_number'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $referral_count < 3 || $balance['available_balance'] < 10 || !$user['phone_number'] ? 'disabled' : ''; ?>>Submit Withdrawal</button>
                    </form>
                </div>
            </div>
            
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-6">Withdrawal History</h2>
                    
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
                                            No withdrawal transactions yet. Submit a withdrawal to get started.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center mr-3">
                                                        <i class="ri-arrow-up-line text-red-600"></i>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">Withdrawal</div>
                                                        <div class="text-xs text-gray-500 mt-0.5">TX: <?php echo htmlspecialchars(substr($tx['transaction_hash'] ?? 'N/A', 0, 10)); ?>...</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($tx['created_at'])); ?></div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-red-600">-$<?php echo number_format(abs($tx['amount']), 2); ?></div>
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
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script src="scripts.js"></script>
</body>
</html>