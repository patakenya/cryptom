<?php
// wallet.php
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

// Initialize messages and form data
$error = '';
$success = '';
$deposit_data = ['amount' => '', 'method' => 'MPESA'];
$withdrawal_data = ['amount' => '', 'method' => 'MPESA'];
$user_id = $_SESSION['user_id'];

try {
    // Handle deposit form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_deposit'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Invalid CSRF token. Please try again.';
            error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
        } else {
            $deposit_data['amount'] = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
            $deposit_data['method'] = 'MPESA'; // Hardcode to MPESA
            if (!$deposit_data['amount'] || $deposit_data['amount'] <= 0) {
                $error = 'Please enter a valid deposit amount.';
            } elseif ($deposit_data['amount'] < 10) {
                $error = 'Minimum deposit amount is $10.00.';
            } else {
                $pdo->beginTransaction();
                $transaction_hash = 'TX_DEPOSIT_' . bin2hex(random_bytes(8));
                $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, method, status, transaction_hash, created_at) VALUES (?, 'deposit', ?, ?, 'pending', ?, NOW())");
                $stmt->execute([$user_id, $deposit_data['amount'], $deposit_data['method'], $transaction_hash]);
                $pdo->commit();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $success = 'Deposit request submitted successfully! Awaiting confirmation.';
                $deposit_data = ['amount' => '', 'method' => 'MPESA'];
            }
        }
    }

    // Handle withdrawal form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Invalid CSRF token. Please try again.';
            error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
        } else {
            $withdrawal_data['amount'] = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
            $withdrawal_data['method'] = 'MPESA'; // Hardcode to MPESA
            $stmt = $pdo->prepare("SELECT COUNT(*) as referral_count FROM referrals WHERE referrer_id = ? AND status = 'active'");
            $stmt->execute([$user_id]);
            $referral_count = $stmt->fetchColumn() ?: 0;
            $stmt = $pdo->prepare("SELECT available_balance FROM user_balances WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$user_id]);
            $balance = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$balance) {
                $stmt = $pdo->prepare("INSERT INTO user_balances (user_id, available_balance, pending_balance, total_withdrawn) VALUES (?, 0.00, 0.00, 0.00)");
                $stmt->execute([$user_id]);
                $balance = ['available_balance' => 0.00];
            }
            if ($referral_count < 3) {
                $error = 'You need at least 3 referrals to withdraw funds.';
            } elseif (!$withdrawal_data['amount'] || $drawal_data['amount'] <= 0) {
                $error = 'Please enter a valid withdrawal amount.';
            } elseif ($withdrawal_data['amount'] < 10) {
                $error = 'Minimum withdrawal amount is $10.00.';
            } elseif ($withdrawal_data['amount'] > $balance['available_balance']) {
                $error = 'Insufficient available balance.';
            } else {
                $pdo->beginTransaction();
                $transaction_hash = 'TX_WITHDRAW_' . bin2hex(random_bytes(8));
                $stmt = $pdo->prepare("INSERT INTO transactions (user_id, type, amount, method, status, transaction_hash, created_at) VALUES (?, 'withdrawal', ?, ?, 'pending', ?, NOW())");
                $stmt->execute([$user_id, -$withdrawal_data['amount'], $withdrawal_data['method'], $transaction_hash]);
                $stmt = $pdo->prepare("UPDATE user_balances SET available_balance = available_balance - ?, pending_balance = pending_balance + ? WHERE user_id = ?");
                $stmt->execute([$withdrawal_data['amount'], $withdrawal_data['amount'], $user_id]);
                $pdo->commit();
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $success = 'Withdrawal request submitted successfully! Awaiting confirmation.';
                $withdrawal_data = ['amount' => '', 'method' => 'MPESA'];
            }
        }
    }

    // Fetch user data
    $stmt = $pdo->prepare("SELECT full_name, email, account_status FROM users WHERE user_id = ?");
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

    // Fetch recent transactions
    $stmt = $pdo->prepare("SELECT transaction_id, type, amount, method, status, transaction_hash, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Wallet Error: ' . $e->getMessage());
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
    <title>Wallet - CryptoMiner ERP</title>
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
                        <h1 class="text-2xl font-semibold text-gray-800 mb-2">Your Wallet</h1>
                        <p class="text-gray-600 text-sm max-w-xl">Manage your funds, deposit, withdraw, and view transaction history.</p>
                    </div>
                    <a href="dashboard.php#miners" class="bg-primary text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">
                        <i class="ri-cpu-line mr-1"></i> Buy Miners
                    </a>
                </div>
                
                <?php if ($error): ?>
                    <div class="mt-4 p-3 bg-red-50 text-red-600 text-sm rounded-button text-center">
                        <i class="ri-error-warning-line mr-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="mt-4 p-3 bg-green-50 text-green-600 text-sm rounded-button text-center">
                        <i class="ri-check-line mr-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Balance Overview -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-6">Balance Overview</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 bg-blue-50 rounded-lg shadow-sm">
                        <div class="flex items-center">
                            <i class="ri-wallet-line text-blue-600 text-2xl mr-2"></i>
                            <h3 class="text-sm font-medium text-gray-600">Available Balance</h3>
                        </div>
                        <p class="text-2xl font-bold text-blue-600 mt-2">$<?php echo number_format($balance['available_balance'], 2); ?></p>
                    </div>
                    <div class="p-4 bg-yellow-50 rounded-lg shadow-sm">
                        <div class="flex items-center">
                            <i class="ri-time-line text-yellow-600 text-2xl mr-2"></i>
                            <h3 class="text-sm font-medium text-gray-600">Pending Balance</h3>
                        </div>
                        <p class="text-2xl font-bold text-yellow-600 mt-2">$<?php echo number_format($pending_balance, 2); ?></p>
                    </div>
                    <div class="p-4 bg-green-50 rounded-lg shadow-sm">
                        <div class="flex items-center">
                            <i class="ri-arrow-up-line text-green-600 text-2xl mr-2"></i>
                            <h3 class="text-sm font-medium text-gray-600">Total Withdrawn</h3>
                        </div>
                        <p class="text-2xl font-bold text-green-600 mt-2">$<?php echo number_format($total_withdrawn, 2); ?></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Deposit and Withdrawal Forms -->
        <section class="mb-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Deposit Form -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Deposit Funds</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="method" value="MPESA">
                    
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (USD)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500">$</span>
                            </div>
                            <input type="number" name="amount" min="10" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars($deposit_data['amount']); ?>" required class="block w-full pl-7 pr-12 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:border-blue-600 focus:outline-none text-gray-900">
                            <div class="absolute inset-y-0 right-0 flex items-center">
                                <button type="button" class="h-full px-3 text-xs text-blue-500 font-medium bg-blue-50 rounded-r-button hover:bg-blue-100 focus:outline-none" onclick="this.form.amount.value = <?php echo floor($balance['available_balance'] * 100) / 100; ?>">MAX</button>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Minimum deposit: $10.00</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <div class="relative">
                            <input type="text" value="MPESA" disabled class="block w-full py-1.5 pl-3 pr-2 border border-gray-300 rounded-sm bg-gray-100 text-gray-500 text-sm">
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_deposit" class="w-full bg-primary text-white rounded-lg py-2.5 font-semibold hover:bg-blue-600 transition-colors duration-200">Submit Payment</button>
                </form>
            </div>

            <!-- Withdrawal Form -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Withdraw Funds</h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="method" value="MPESA">
                    
                    <div class="mb-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (USD)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-500">$</span>
                            </div>
                            <input type="number" name="amount" min="10" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars($withdrawal_data['amount']); ?>" required class="block w-full pl-7 pr-12 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:border-blue-600 focus:outline-none text-gray-900">
                            <div class="absolute inset-y-0 right-0 flex items-center">
                                <button type="button" class="h-full px-3 text-xs text-blue-500 font-medium bg-blue-50 rounded-r-button hover:bg-blue-100 focus:outline-none" onclick="this.form.amount.value = '<?php echo floor($balance['available_balance'] * 100) / 100; ?>'">MAX</button>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Minimum withdrawal: $10.00. Referrals: <?php echo $referral_count; ?>/3</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Wallet Method</label>
                        <div class="relative">
                            <input type="text" value="MPESA" disabled class="block w-full py-1.5 pl-3 pr-2 border border-gray-300 rounded-sm bg-gray-100 text-gray-500 text-sm">
                        </div>
                    </div>
                    
                    <button type="submit" name="submit_withdrawal" class="w-full bg-primary text-white rounded-lg py-2.5 font-semibold hover:bg-blue-600 transition-colors duration-200 <?php echo $referral_count < 3 || $balance['available_balance'] < 10 ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $referral_count < 3 || $balance['available_balance'] < 10 ? 'disabled' : ''; ?>>Confirm Withdrawal</button>
                </form>
            </div>
        </section>

        <!-- Transaction History -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex flex-wrap items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Transaction History</h2>
                    <div class="flex flex-wrap gap-x-2 gap-y-2">
                        <button class="px-3 py-1.5 text-sm font-medium bg-blue-50 rounded-full text-primary hover:bg-blue-100" data-filter="all">All</button>
                        <button class="px-3 py-1.5 text-sm font-medium text-gray-500 rounded-full hover:bg-gray-100" data-filter="deposit">Deposits</button>
                        <button class="px-3 py-1.5 text-sm font-medium text-gray-500 rounded-full hover:bg-gray-100" data-filter="withdrawal">Withdrawals</button>
                        <button class="px-3 py-1.5 text-sm font-medium text-gray-500 rounded-full hover:bg-gray-100" data-filter="earning">Earnings</button>
                        <button class="px-3 py-1.5 text-sm font-medium text-gray-500 rounded-full hover:bg-gray-100" data-filter="purchase">Purchases</button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="transactionTable">
                        <thead>
                            <tr>
                                <th class="px-2 py-3 bg-gray-50 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Transaction</th>
                                <th class="px-2 py-3 bg-gray-50 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Date</th>
                                <th class="px-2 py-3 bg-gray-50 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Amount</th>
                                <th class="px-2 py-3 bg-gray-50 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Method</th>
                                <th class="px-2 py-3 bg-gray-50 text-left text-sm font-medium text-gray-600 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" class="px-2 py-4 text-center text-gray-500">
                                        No transactions yet. Make a deposit to get started.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr data-type="<?php echo htmlspecialchars($tx['type']); ?>">
                                        <td class="px-2 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full bg-<?php echo $tx['type'] === 'deposit' ? 'green' : ($tx['type'] === 'withdrawal' ? 'red' : ($tx['type'] === 'earning' ? 'blue' : ($tx['type'] === 'purchase' ? 'purple' : 'gray'))); ?>-100 flex items-center justify-center mr-2">
                                                    <i class="ri-<?php echo $tx['type'] === 'deposit' ? 'arrow-down' : ($tx['type'] === 'withdrawal' ? 'arrow-up' : ($tx['type'] === 'earning' ? 'coins' : ($tx['type'] === 'purchase' ? 'shopping-cart' : 'question'))); ?>-line text-<?php echo $tx['type'] === 'deposit' ? 'green' : ($tx['type'] === 'withdrawal' ? 'red' : ($tx['type'] === 'earning' ? 'blue' : ($tx['type'] === 'purchase' ? 'purple' : 'gray'))); ?>-600"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900"><?php echo ucfirst($tx['type']); ?></div>
                                                    <div class="text-xs text-gray-500 mt-0.5">TX: <?php echo htmlspecialchars(substr($tx['transaction_hash'] ?? 'N/A', 0, 10)); ?>...</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($tx['created_at'])); ?></div>
                                        </td>
                                        <td class="px-2 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-<?php echo $tx['amount'] >= 0 ? 'green' : 'red'; ?>-600"><?php echo $tx['amount'] >= 0 ? '+' : ''; ?>$<?php echo number_format(abs($tx['amount']), 2); ?></div>
                                        </td>
                                        <td class="px-2 py-4 whitespace-nowrap">
                                            <div class="flex items-center text-sm text-gray-900">
                                                <i class="ri-coin-line mr-1 text-yellow-500"></i> <?php echo htmlspecialchars($tx['method']); ?>
                                            </div>
                                        </td>
                                        <td class="px-2 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-<?php echo $tx['status'] === 'completed' ? 'green' : ($tx['status'] === 'pending' ? 'yellow' : 'red'); ?>-100 text-<?php echo $tx['status'] === 'completed' ? 'green' : ($tx['status'] === 'pending' ? 'yellow' : 'red'); ?>-800"><?php echo ucfirst($tx['status']); ?></span>
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
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterButtons = document.querySelectorAll('[data-filter]');
            const transactionRows = document.querySelectorAll('#transactionTable tbody tr');

            filterButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const filter = this.getAttribute('data-filter');
                    filterButtons.forEach(btn => {
                        btn.classList.remove('bg-blue-50', 'text-primary');
                        btn.classList.add('text-gray-500', 'hover:bg-gray-100');
                    });
                    this.classList.add('bg-blue-50', 'text-primary');
                    this.classList.remove('text-gray-500', 'hover:bg-gray-100');

                    transactionRows.forEach(row => {
                        const type = row.getAttribute('data-type');
                        if (filter === 'all' || type === filter) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>