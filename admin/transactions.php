<?php
// admin/transactions.php
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

// Pagination and filtering
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$per_page = 10;
$offset = ($page - 1) * $per_page;

$type_filter = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING) ?: '';
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?: '';
$user_filter = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT) ?: '';

try {
    // Handle transaction approval/rejection
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_transaction'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Invalid CSRF token. Please try again.';
            error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
        } else {
            $transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
            $action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
            if ($transaction_id && in_array($action, ['approve', 'reject'])) {
                $stmt = $pdo->prepare("SELECT t.id, t.user_id, t.type, t.amount, t.status, u.full_name, u.email, ub.available_balance 
                                       FROM transactions t JOIN users u ON t.user_id = u.user_id 
                                       LEFT JOIN user_balances ub ON u.user_id = ub.user_id 
                                       WHERE t.id = ?");
                $stmt->execute([$transaction_id]);
                $transaction = $stmt->fetch();
                if ($transaction) {
                    if ($transaction['status'] !== 'pending') {
                        $error = 'Transaction is already ' . $transaction['status'] . '.';
                    } elseif ($action === 'approve' && $transaction['type'] === 'withdrawal' && $transaction['available_balance'] < abs($transaction['amount'])) {
                        $error = 'Insufficient user balance for withdrawal.';
                    } else {
                        $pdo->beginTransaction();
                        $new_status = $action === 'approve' ? 'completed' : 'failed';
                        $stmt = $pdo->prepare("UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$new_status, $transaction_id]);

                        if ($action === 'approve') {
                            if ($transaction['type'] === 'deposit') {
                                $stmt = $pdo->prepare("UPDATE user_balances SET available_balance = available_balance + ?, updated_at = NOW() WHERE user_id = ?");
                                $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                            } elseif ($transaction['type'] === 'withdrawal') {
                                $stmt = $pdo->prepare("UPDATE user_balances SET available_balance = available_balance + ?, total_withdrawn = total_withdrawn + ?, updated_at = NOW() WHERE user_id = ?");
                                $stmt->execute([$transaction['amount'], abs($transaction['amount']), $transaction['user_id']]);
                            } elseif ($transaction['type'] === 'earning' || $transaction['type'] === 'referral') {
                                $stmt = $pdo->prepare("UPDATE user_balances SET available_balance = available_balance + ?, updated_at = NOW() WHERE user_id = ?");
                                $stmt->execute([$transaction['amount'], $transaction['user_id']]);
                            }
                        }

                        // Send notification email
                        $subject = 'Your Transaction Has Been ' . ucfirst($action) . 'd';
                        $body = 'Hello ' . htmlspecialchars($transaction['full_name']) . ',<br><br>Your ' . $transaction['type'] . ' transaction of $' . number_format(abs($transaction['amount']), 2) . ' has been ' . $action . 'd by an admin. ' . ($action === 'approve' ? 'The funds have been processed.' : 'Please contact support for more information.') . '<br><br>Thank you!';
                        $alt_body = "Hello " . $transaction['full_name'] . ",\n\nYour " . $transaction['type'] . " transaction of $" . number_format(abs($transaction['amount']), 2) . " has been " . $action . "d by an admin. " . ($action === 'approve' ? 'The funds have been processed.' : 'Please contact support for more information.') . "\n\nThank you!";
                        if (sendNotificationEmail($transaction['email'], $transaction['full_name'], $subject, $body, $alt_body)) {
                            $success = 'Transaction ' . $action . 'd successfully and notification sent!';
                        } else {
                            $success = 'Transaction ' . $action . 'd, but failed to send notification email.';
                        }
                        $pdo->commit();
                    }
                } else {
                    $error = 'Invalid transaction ID.';
                }
            } else {
                $error = 'Invalid action or transaction ID.';
            }
        }
    }

    // Build query for transactions
    $query = "SELECT t.id, t.user_id, t.type, t.amount, t.method, t.status, t.transaction_hash, t.created_at, u.full_name, u.email 
              FROM transactions t JOIN users u ON t.user_id = u.user_id 
              WHERE 1=1";
    $params = [];

    if ($type_filter) {
        $query .= " AND t.type = ?";
        $params[] = $type_filter;
    }
    if ($status_filter) {
        $query .= " AND t.status = ?";
        $params[] = $status_filter;
    }
    if ($user_filter) {
        $query .= " AND t.user_id = ?";
        $params[] = $user_filter;
    }

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total " . str_replace('SELECT t.id, t.user_id, t.type, t.amount, t.method, t.status, t.transaction_hash, t.created_at, u.full_name, u.email', '', $query);
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_transactions = $stmt->fetch()['total'];
    $total_pages = ceil($total_transactions / $per_page);

    // Fetch transactions
    $query .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    // Fetch users for filter dropdown
    $stmt = $pdo->prepare("SELECT user_id, full_name FROM users ORDER BY full_name");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Transactions Management Error: ' . $e->getMessage());
    $error = 'An error occurred. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Transactions - CryptoMiner ERP</title>
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

        <!-- Transactions Section -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Manage Transactions</h2>
                    <a href="dashboard.php" class="text-primary text-sm font-medium flex items-center hover:text-blue-600">
                        Back to Dashboard <i class="ri-arrow-left-line ml-1"></i>
                    </a>
                </div>

                <!-- Filters -->
                <form method="GET" class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                        <select name="type" id="type" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                            <option value="">All Types</option>
                            <option value="deposit" <?php echo $type_filter === 'deposit' ? 'selected' : ''; ?>>Deposit</option>
                            <option value="withdrawal" <?php echo $type_filter === 'withdrawal' ? 'selected' : ''; ?>>Withdrawal</option>
                            <option value="purchase" <?php echo $type_filter === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                            <option value="earning" <?php echo $type_filter === 'earning' ? 'selected' : ''; ?>>Earning</option>
                            <option value="referral" <?php echo $type_filter === 'referral' ? 'selected' : ''; ?>>Referral</option>
                        </select>
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" id="status" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700">User</label>
                        <select name="user_id" id="user_id" class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-primary focus:border-primary">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>" <?php echo $user_filter == $user['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($user['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-3 flex justify-end">
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-button font-medium hover:bg-blue-600 transition-colors">Apply Filters</button>
                    </div>
                </form>

                <!-- Transactions Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-4 text-center text-gray-500">No transactions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($tx['full_name']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo ucfirst($tx['type']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-<?php echo $tx['amount'] >= 0 ? 'green' : 'red'; ?>-600"><?php echo $tx['amount'] >= 0 ? '+' : ''; ?>$<?php echo number_format(abs($tx['amount']), 2); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($tx['method']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M d, Y H:i', strtotime($tx['created_at'])); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $tx['status'] === 'completed' ? 'green' : ($tx['status'] === 'pending' ? 'yellow' : 'red'); ?>-100 text-<?php echo $tx['status'] === 'completed' ? 'green' : ($tx['status'] === 'pending' ? 'yellow' : 'red'); ?>-800"><?php echo ucfirst($tx['status']); ?></span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                            <?php if ($tx['status'] === 'pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="transaction_id" value="<?php echo $tx['id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" name="update_transaction" class="text-green-600 hover:text-green-800 mr-2">Approve</button>
                                                </form>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="transaction_id" value="<?php echo $tx['id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" name="update_transaction" class="text-red-600 hover:text-red-800">Reject</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="mt-6 flex justify-center space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&user_id=<?php echo urlencode($user_filter); ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-button hover:bg-gray-300">Previous</a>
                        <?php endif; ?>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&user_id=<?php echo urlencode($user_filter); ?>" class="px-3 py-1 <?php echo $i === $page ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700'; ?> rounded-button hover:bg-gray-300"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>&user_id=<?php echo urlencode($user_filter); ?>" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-button hover:bg-gray-300">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include '../footer.php'; ?>
</body>
</html>