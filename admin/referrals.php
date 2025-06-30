<?php
// admin/referrals.php
require_once '../config.php';
require_once '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages and variables
$error = '';
$success = '';
$admin_id = $_SESSION['admin_id'];
$search = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?? 'all';
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1;
$per_page = 10;

// Validate admin
try {
    $stmt = $pdo->prepare("SELECT username, email, account_status FROM admins WHERE admin_id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$admin || $admin['account_status'] !== 'active') {
        session_destroy();
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Admin Validation Error: ' . $e->getMessage());
    session_destroy();
    header('Location: login.php');
    exit;
}

// Handle referral status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
        error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
    } else {
        $referral_id = filter_input(INPUT_POST, 'referral_id', FILTER_VALIDATE_INT);
        $new_status = filter_input(INPUT_POST, 'new_status', FILTER_SANITIZE_STRING);
        if ($referral_id && in_array($new_status, ['active', 'inactive'])) {
            try {
                $pdo->beginTransaction();

                // Update referral status
                $stmt = $pdo->prepare("UPDATE referrals SET status = ?, updated_at = NOW() WHERE referral_id = ?");
                $stmt->execute([$new_status, $referral_id]);

                // Fetch referral details for notification
                $stmt = $pdo->prepare("SELECT r.referrer_id, r.referred_user_id, u1.email as referrer_email, u1.full_name as referrer_name, u2.email as referred_email, u2.full_name as referred_name 
                                       FROM referrals r 
                                       JOIN users u1 ON r.referrer_id = u1.user_id 
                                       JOIN users u2 ON r.referred_user_id = u2.user_id 
                                       WHERE r.referral_id = ?");
                $stmt->execute([$referral_id]);
                $referral = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($referral) {
                    // Send notification to referrer
                    $subject = "Referral Status Updated";
                    $body = "Hello {$referral['referrer_name']},<br><br>The referral status for {$referral['referred_name']} has been updated to '$new_status'.<br><br>Thank you!";
                    $alt_body = "Hello {$referral['referrer_name']},\n\nThe referral status for {$referral['referred_name']} has been updated to '$new_status'.\n\nThank you!";
                    if (!sendNotificationEmail($referral['referrer_email'], $referral['referrer_name'], $subject, $body, $alt_body)) {
                        error_log('Failed to send referral status notification to: ' . $referral['referrer_email']);
                    }

                    // Log admin action in login_activity (since admin_login_activity is not available)
                    $stmt = $pdo->prepare("INSERT INTO login_activity (user_id, device_type, browser, ip_address, location, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $device = strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false ? 'Mobile' : 'Desktop';
                    $browser = substr($_SERVER['HTTP_USER_AGENT'], 0, 100);
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $location = 'Unknown';
                    $stmt->execute([$admin_id, $device, $browser, $ip, $location, "Admin: Updated referral $referral_id to $new_status"]);

                    $pdo->commit();
                    $success = "Referral status updated to '$new_status' successfully!";
                } else {
                    $pdo->rollBack();
                    $error = 'Referral not found.';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Failed to update referral status. Please try again.';
                error_log('Referral Update Error: ' . $e->getMessage());
            }
        } else {
            $error = 'Invalid referral ID or status.';
        }
    }
}

// Build referral query
$where_clauses = [];
$params = [];
if ($status !== 'all') {
    $where_clauses[] = "r.status = ?";
    $params[] = $status;
}
if (!empty($search)) {
    $where_clauses[] = "(u1.email LIKE ? OR u1.full_name LIKE ? OR u2.email LIKE ? OR u2.full_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}
$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Fetch total referrals for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) 
                       FROM referrals r 
                       JOIN users u1 ON r.referrer_id = u1.user_id 
                       JOIN users u2 ON r.referred_user_id = u2.user_id 
                       $where_sql");
$stmt->execute($params);
$total_referrals = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_referrals / $per_page));
$page = max(1, min($page, $total_pages));
$offset = ($page - 1) * $per_page;

// Fetch referrals
$stmt = $pdo->prepare("SELECT r.referral_id, r.referrer_id, r.referred_user_id, r.joined_date, r.investment, r.miners_count, r.commission_earned, r.status, 
                       u1.full_name as referrer_name, u1.email as referrer_email, 
                       u2.full_name as referred_name, u2.email as referred_email 
                       FROM referrals r 
                       JOIN users u1 ON r.referrer_id = u1.user_id 
                       JOIN users u2 ON r.referred_user_id = u2.user_id 
                       $where_sql 
                       ORDER BY r.joined_date DESC 
                       LIMIT ? OFFSET ?");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referrals - CryptoMiner ERP Admin</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#3b82f6',secondary:'#10b981'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include '../header.php'; ?>

    <main class="flex-grow container mx-auto px-4 py-12">
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h1 class="text-2xl font-semibold text-gray-800 mb-6">Manage Referrals</h1>

            <!-- Messages -->
            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 text-red-600 text-sm rounded-button flex items-center">
                    <i class="ri-error-warning-line mr-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="mb-4 p-3 bg-green-50 text-green-600 text-sm rounded-button flex items-center">
                    <i class="ri-check-line mr-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Search and Filter -->
            <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <form method="GET" class="flex-1">
                    <div class="relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by email or name..." class="block w-full pl-10 pr-4 py-2 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="ri-search-line text-gray-400"></i>
                        </div>
                    </div>
                </form>
                <form method="GET" class="flex-1">
                    <select name="status" onchange="this.form.submit()" class="block w-full py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </form>
            </div>

            <!-- Referrals Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referrer</th>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referred User</th>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined Date</th>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Investment</th>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Miners</th>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission</th>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($referrals)): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-4 text-center text-gray-500">No referrals found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($referrals as $referral): ?>
                                <tr>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($referral['referrer_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($referral['referrer_email']); ?></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($referral['referred_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($referral['referred_email']); ?></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M d, Y', strtotime($referral['joined_date'])); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                        $<?php echo number_format($referral['investment'], 2); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo $referral['miners_count']; ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                        $<?php echo number_format($referral['commission_earned'], 2); ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $referral['status'] === 'active' ? 'green-100 text-green-800' : 'red-100 text-red-800'; ?>">
                                            <?php echo ucfirst($referral['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to <?php echo $referral['status'] === 'active' ? 'deactivate' : 'activate'; ?> this referral?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                            <input type="hidden" name="referral_id" value="<?php echo $referral['referral_id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $referral['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" name="toggle_status" class="text-<?php echo $referral['status'] === 'active' ? 'red' : 'green'; ?>-600 hover:text-<?php echo $referral['status'] === 'active' ? 'red' : 'green'; ?>-800 text-sm">
                                                <?php echo $referral['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
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
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-1.5 text-sm text-primary hover:bg-blue-50 rounded-button">Previous</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-1.5 text-sm <?php echo $i === $page ? 'bg-primary text-white' : 'text-gray-600 hover:bg-blue-50'; ?> rounded-button"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" class="px-3 py-1.5 text-sm text-primary hover:bg-blue-50 rounded-button">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include '../footer.php'; ?>
</body>
</html>