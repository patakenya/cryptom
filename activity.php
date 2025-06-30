<?php
// activity.php
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
    $stmt = $pdo->prepare("SELECT full_name, email, account_status FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['account_status'] !== 'active') {
        session_destroy();
        header('Location: login.php');
        exit;
    }

    // Fetch activities (combine transactions and referrals)
    $stmt = $pdo->prepare("
        SELECT 
            'transaction' AS source,
            transaction_id AS id,
            type AS activity_type,
            CASE 
                WHEN type = 'deposit' THEN CONCAT('Deposited $', FORMAT(amount, 2), ' via ', method)
                WHEN type = 'withdrawal' THEN CONCAT('Requested withdrawal of $', FORMAT(ABS(amount), 2), ' via ', method)
                WHEN type = 'earning' THEN CONCAT('Earned $', FORMAT(amount, 2), ' from mining')
                WHEN type = 'purchase' THEN CONCAT('Purchased miner for $', FORMAT(ABS(amount), 2))
                ELSE 'Unknown action'
            END AS description,
            amount,
            method,
            status,
            created_at
        FROM transactions
        WHERE user_id = ?
        UNION ALL
        SELECT 
            'referral' AS source,
            referred_user_id AS id,
            'referral' AS activity_type,
            CONCAT('Referred user ID ', referred_user_id, ', earned $', FORMAT(commission_earned, 2)) AS description,
            commission_earned AS amount,
            'Referral' AS method,
            status,
            created_at
        FROM referrals
        WHERE referrer_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id, $user_id]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Activity Error: ' . $e->getMessage());
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
    <title>Activity - CryptoMiner ERP</title>
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
                        <h1 class="text-2xl font-semibold text-gray-800 mb-2">Your Activity</h1>
                        <p class="text-gray-600 text-sm max-w-xl">View your recent actions, including deposits, withdrawals, purchases, earnings, and referrals.</p>
                    </div>
                    <a href="dashboard.php" class="bg-primary text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">
                        <i class="ri-home-4-line mr-1"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </section>

        <!-- Activity Log -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex flex-wrap items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Activity</h2>
                    <div class="flex space-x-2">
                        <button class="px-3 py-1.5 text-sm bg-blue-50 text-primary rounded-full" data-filter="all">All</button>
                        <button class="px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded-full" data-filter="deposit">Deposits</button>
                        <button class="px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded-full" data-filter="withdrawal">Withdrawals</button>
                        <button class="px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded-full" data-filter="earning">Earnings</button>
                        <button class="px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded-full" data-filter="purchase">Purchases</button>
                        <button class="px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 rounded-full" data-filter="referral">Referrals</button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="activityTable">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($activities)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                        No recent activity recorded. Start by <a href="wallet.php" class="text-primary hover:text-blue-600">making a deposit</a> or <a href="dashboard.php#miners" class="text-primary hover:text-blue-600">purchasing a miner</a>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activities as $activity): ?>
                                    <tr data-type="<?php echo htmlspecialchars($activity['activity_type']); ?>">
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 rounded-full bg-<?php echo $activity['activity_type'] === 'deposit' ? 'green' : ($activity['activity_type'] === 'withdrawal' ? 'red' : ($activity['activity_type'] === 'earning' ? 'blue' : ($activity['activity_type'] === 'purchase' ? 'purple' : ($activity['activity_type'] === 'referral' ? 'orange' : 'gray')))); ?>-100 flex items-center justify-center mr-3">
                                                    <i class="ri-<?php echo $activity['activity_type'] === 'deposit' ? 'arrow-down' : ($activity['activity_type'] === 'withdrawal' ? 'arrow-up' : ($activity['activity_type'] === 'earning' ? 'coins' : ($activity['activity_type'] === 'purchase' ? 'shopping-cart' : ($activity['activity_type'] === 'referral' ? 'user-add' : 'question')))); ?>-line text-<?php echo $activity['activity_type'] === 'deposit' ? 'green' : ($activity['activity_type'] === 'withdrawal' ? 'red' : ($activity['activity_type'] === 'earning' ? 'blue' : ($activity['activity_type'] === 'purchase' ? 'purple' : ($activity['activity_type'] === 'referral' ? 'orange' : 'gray')))); ?>-600"></i>
                                                </div>
                                                <div class="text-sm font-medium text-gray-900"><?php echo ucfirst($activity['activity_type']); ?></div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($activity['created_at'])); ?></div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($activity['description']); ?></div>
                                            <?php if ($activity['source'] === 'transaction' && !empty($activity['method'])): ?>
                                                <div class="text-xs text-gray-500 mt-0.5">Method: <?php echo htmlspecialchars($activity['method']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-<?php echo $activity['amount'] >= 0 ? 'green' : 'red'; ?>-600"><?php echo $activity['amount'] >= 0 ? '+' : ''; ?>$<?php echo number_format(abs($activity['amount']), 2); ?></div>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $activity['status'] === 'completed' ? 'green' : ($activity['status'] === 'pending' ? 'yellow' : ($activity['status'] === 'active' ? 'blue' : 'red')); ?>-100 text-<?php echo $activity['status'] === 'completed' ? 'green' : ($activity['status'] === 'pending' ? 'yellow' : ($activity['status'] === 'active' ? 'blue' : 'red')); ?>-800"><?php echo ucfirst($activity['status']); ?></span>
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
            const activityRows = document.querySelectorAll('#activityTable tbody tr');

            filterButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const filter = this.getAttribute('data-filter');
                    filterButtons.forEach(btn => {
                        btn.classList.remove('bg-blue-50', 'text-primary');
                        btn.classList.add('text-gray-500', 'hover:bg-gray-100');
                    });
                    this.classList.add('bg-blue-50', 'text-primary');
                    this.classList.remove('text-gray-500', 'hover:bg-gray-100');

                    activityRows.forEach(row => {
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