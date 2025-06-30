<?php
// admin/dashboard.php
require_once '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Check admin status
$stmt = $pdo->prepare("SELECT admin_id, username, email, account_status FROM admins WHERE admin_id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();
if (!$admin || $admin['account_status'] !== 'active') {
    session_destroy();
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

try {
    // Handle new mining package creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_package'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Invalid CSRF token. Please try again.';
            error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
        } else {
            $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
            $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
            $daily_profit = filter_input(INPUT_POST, 'daily_profit', FILTER_VALIDATE_FLOAT);
            $daily_return_percentage = filter_input(INPUT_POST, 'daily_return_percentage', FILTER_VALIDATE_FLOAT);
            $duration_days = filter_input(INPUT_POST, 'duration_days', FILTER_VALIDATE_INT);
            $is_popular = isset($_POST['is_popular']) ? 1 : 0;

            if ($name && $price && $daily_profit && $daily_return_percentage && $duration_days) {
                $total_return = $price + ($daily_profit * $duration_days);
                $stmt = $pdo->prepare("INSERT INTO mining_packages (name, price, daily_profit, daily_return_percentage, duration_days, total_return, is_popular, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$name, $price, $daily_profit, $daily_return_percentage, $duration_days, $total_return, $is_popular]);
                $success = 'Mining package added successfully!';
            } else {
                $error = 'Please fill in all fields correctly.';
            }
        }
    }

    // Fetch system stats
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_users FROM users");
    $stmt->execute();
    $total_users = $stmt->fetch()['total_users'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total_admins FROM admins WHERE account_status = 'active'");
    $stmt->execute();
    $total_admins = $stmt->fetch()['total_admins'];

    $stmt = $pdo->prepare("SELECT SUM(mp.price) as total_investment FROM user_miners um JOIN mining_packages mp ON um.package_id = mp.package_id");
    $stmt->execute();
    $total_investment = $stmt->fetch()['total_investment'] ?: 0.00;

    $stmt = $pdo->prepare("SELECT SUM(amount) as pending_withdrawals FROM transactions WHERE type = 'withdrawal' AND status = 'pending'");
    $stmt->execute();
    $pending_withdrawals = abs($stmt->fetch()['pending_withdrawals'] ?: 0.00);

    // Fetch recent users
    $stmt = $pdo->prepare("SELECT user_id, full_name, email, account_status, created_at FROM users ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $recent_users = $stmt->fetchAll();

    // Fetch recent transactions
    $stmt = $pdo->prepare("SELECT t.id, t.type, t.amount, t.method, t.status, t.transaction_hash, t.created_at, u.full_name 
                           FROM transactions t JOIN users u ON t.user_id = u.user_id 
                           ORDER BY t.created_at DESC LIMIT 5");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll();

    // Fetch mining packages
    $stmt = $pdo->prepare("SELECT package_id, name, price, daily_profit, daily_return_percentage, duration_days, is_popular FROM mining_packages WHERE is_active = 1 ORDER BY price ASC");
    $stmt->execute();
    $mining_packages = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Admin Dashboard Error: ' . $e->getMessage());
    $error = 'An error occurred. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CryptoMiner ERP</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#3b82f6',secondary:'#10b981'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <?php include '../header.php'; ?>

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
                            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">Welcome, <?php echo htmlspecialchars($admin['username']); ?>!</h1>
                            <p class="text-blue-100 mb-6 max-w-xl">Manage users, mining packages, and transactions for CryptoMiner ERP.</p>
                            <div class="flex flex-wrap gap-4">
                                <a href="#users" class="bg-white text-primary px-5 py-2.5 rounded-button font-medium hover:bg-blue-50 transition-colors whitespace-nowrap">Manage Users</a>
                                <a href="#packages" class="bg-blue-700 text-white px-5 py-2.5 rounded-button font-medium hover:bg-blue-800 transition-colors whitespace-nowrap">Manage Packages</a>
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
                    <h3 class="text-gray-500 text-sm font-medium">Total Users</h3>
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="ri-user-line text-primary"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_users; ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-green-500 text-sm flex items-center">
                        <i class="ri-arrow-up-s-line"></i> Active Users
                    </span>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Total Admins</h3>
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="ri-admin-line text-green-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900"><?php echo $total_admins; ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-green-500 text-sm flex items-center">
                        <i class="ri-arrow-up-s-line"></i> Active
                    </span>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Total Investments</h3>
                    <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                        <i class="ri-funds-line text-yellow-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($total_investment, 2); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-gray-500">All users</span>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-gray-500 text-sm font-medium">Pending Withdrawals</h3>
                    <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                        <i class="ri-arrow-up-line text-red-600"></i>
                    </div>
                </div>
                <p class="text-2xl font-bold text-gray-900">$<?php echo number_format($pending_withdrawals, 2); ?></p>
                <div class="flex items-center mt-2">
                    <span class="text-xs text-gray-500">Awaiting approval</span>
                </div>
            </div>
        </section>

        <!-- Manage Mining Packages -->
        <section id="packages" class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Manage Mining Packages</h2>
                    <button onclick="document.getElementById('addPackageModal').classList.remove('hidden')" class="bg-primary text-white px-4 py-2 rounded-button font-medium hover:bg-blue-600 transition-colors">Add Package</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Package Name</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Profit</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Popular</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($mining_packages)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500">No mining packages available.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($mining_packages as $package): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($package['name']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">$<?php echo number_format($package['price'], 2); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-green-600">$<?php echo number_format($package['daily_profit'], 2); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $package['duration_days']; ?> days</td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $package['is_popular'] ? 'Yes' : 'No'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Recent Users -->
        <section id="users" class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Users</h2>
                    <a href="users.php" class="text-primary text-sm font-medium flex items-center hover:text-blue-600">View All <i class="ri-arrow-right-line ml-1"></i></a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recent_users)): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-4 text-center text-gray-500">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $user['account_status'] === 'active' ? 'green' : ($user['account_status'] === 'pending' ? 'yellow' : 'red'); ?>-100 text-<?php echo $user['account_status'] === 'active' ? 'green' : ($user['account_status'] === 'pending' ? 'yellow' : 'red'); ?>-800"><?php echo ucfirst($user['account_status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Recent Transactions -->
        <section id="transactions" class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Transactions</h2>
                    <a href="transactions.php" class="text-primary text-sm font-medium flex items-center hover:text-blue-600">View All <i class="ri-arrow-right-line ml-1"></i></a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recent_transactions)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-500">No transactions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_transactions as $tx): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($tx['full_name']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo ucfirst($tx['type']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-<?php echo $tx['amount'] >= 0 ? 'green' : 'red'; ?>-600"><?php echo $tx['amount'] >= 0 ? '+' : ''; ?>$<?php echo number_format(abs($tx['amount']), 2); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($tx['method']); ?></td>
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

        <!-- Add Package Modal -->
        <div id="addPackageModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Add New Mining Package</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">Package Name</label>
                        <input type="text" name="name" id="name" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                    </div>
                    <div class="mb-4">
                        <label for="price" class="block text-sm font-medium text-gray-700">Price ($)</label>
                        <input type="number" name="price" id="price" step="0.01" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                    </div>
                    <div class="mb-4">
                        <label for="daily_profit" class="block text-sm font-medium text-gray-700">Daily Profit ($)</label>
                        <input type="number" name="daily_profit" id="daily_profit" step="0.01" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                    </div>
                    <div class="mb-4">
                        <label for="daily_return_percentage" class="block text-sm font-medium text-gray-700">Daily Return (%)</label>
                        <input type="number" name="daily_return_percentage" id="daily_return_percentage" step="0.01" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                    </div>
                    <div class="mb-4">
                        <label for="duration_days" class="block text-sm font-medium text-gray-700">Duration (Days)</label>
                        <input type="number" name="duration_days" id="duration_days" required class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_popular" class="mr-2">
                            <span class="text-sm text-gray-700">Mark as Popular</span>
                        </label>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('addPackageModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-button hover:bg-gray-300">Cancel</button>
                        <button type="submit" name="add_package" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-blue-600">Add Package</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php include '../footer.php'; ?>
</body>
</html>