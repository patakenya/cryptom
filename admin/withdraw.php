<?php
// admin/withdraw.php
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
    // Handle withdrawal approval/rejection
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_withdrawal'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Invalid CSRF token. Please try again.';
            error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
        } else {
            $transaction_id = filter_input(INPUT_POST, 'transaction_id', FILTER_VALIDATE_INT);
            $action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
            if ($transaction_id && in_array($action, ['approve', 'reject'])) {
                $stmt = $pdo->prepare("SELECT t.id, t.user_id, t.amount, t.status, u.full_name, u.email, ub.available_balance 
                                       FROM transactions t JOIN users u ON t.user_id = u.user_id 
                                       JOIN user_balances ub ON u.user_id = ub.user_id 
                                       WHERE t.id = ? AND t.type = 'withdrawal'");
                $stmt->execute([$transaction_id]);
                $transaction = $stmt->fetch();
                if ($transaction) {
                    if ($transaction['status'] !== 'pending') {
                        $error = 'Withdrawal is already ' . $transaction['status'] . '.';
                    } elseif ($action === 'approve' && $transaction['available_balance'] < abs($transaction['amount'])) {
                        $error = 'Insufficient user balance for withdrawal.';
                    } else {
                        $pdo->beginTransaction();
                        $new_status = $action === 'approve' ? 'completed' : 'failed';
                        $stmt = $pdo->prepare("UPDATE transactions SET status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$new_status, $transaction_id]);

                        if ($action === 'approve') {
                            $stmt = $pdo->prepare("UPDATE user_balances SET available_balance = available_balance + ?, total_withdrawn = total_withdrawn + ?, updated_at = NOW() WHERE user_id = ?");
                            $stmt->execute([$transaction['amount'], abs($transaction['amount']), $transaction['user_id']]);
                        }

                        // Send notification email
                        $subject = 'Your Withdrawal Has Been ' . ucfirst($action) . 'd';
                        $body = 'Hello ' . htmlspecialchars($transaction['full_name']) . ',<br><br>Your withdrawal request of $' . number_format(abs($transaction['amount']), 2) . ' has been ' . $action . 'd by an admin. ' . ($action === 'approve' ? 'The funds have been processed.' : 'Please contact support for more information.') . '<br><br>Thank you!';
                        $alt_body = "Hello " . $transaction['full_name'] . ",\n\nYour withdrawal request of $" . number_format(abs($transaction['amount']), 2) . " has been " . $action . "d by an admin. " . ($action === 'approve' ? 'The funds have been processed.' : 'Please contact support for more information.') . "\n\nThank you!";
                        if (sendNotificationEmail($transaction['email'], $transaction['full_name'], $subject, $body, $alt_body)) {
                            $success = 'Withdrawal ' . $action . 'd successfully and notification sent!';
                        } else {
                            $success = 'Withdrawal ' . $action . 'd, but failed to send notification email.';
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

    // Fetch pending withdrawals
    $stmt = $pdo->prepare("SELECT t.id, t.user_id, t.amount, t.method, t.status, t.transaction_hash, t.created_at, u.full_name, u.email 
                           FROM transactions t JOIN users u ON t.user_id = u.user_id 
                           WHERE t.type = 'withdrawal' AND t.status = 'pending' 
                           ORDER BY t.created_at DESC");
    $stmt->execute();
    $withdrawals = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Withdrawal Management Error: ' . $e->getMessage());
    $error = 'An error occurred. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Withdrawals - CryptoMiner ERP</title>
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

        <!-- Withdrawals Section -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Manage Withdrawals</h2>
                    <a href="dashboard.php" class="text-primary text-sm font-medium flex items-center hover:text-blue-600">
                        Back to Dashboard <i class="ri-arrow-left-line ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Method</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($withdrawals)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500">No pending withdrawals found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($withdrawals as $withdrawal): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($withdrawal['full_name']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-red-600">$<?php echo number_format(abs($withdrawal['amount']), 2); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($withdrawal['method']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M d, Y H:i', strtotime($withdrawal['created_at'])); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800"><?php echo ucfirst($withdrawal['status']); ?></span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="transaction_id" value="<?php echo $withdrawal['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" name="update_withdrawal" class="text-green-600 hover:text-green-800 mr-2">Approve</button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="transaction_id" value="<?php echo $withdrawal['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" name="update_withdrawal" class="text-red-600 hover:text-red-800">Reject</button>
                                            </form>
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

    <?php include '../footer.php'; ?>
</body>
</html>