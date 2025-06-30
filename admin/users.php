<?php
// admin/users.php
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
    // Handle user verification
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_user'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Invalid CSRF token. Please try again.';
            error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
        } else {
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            if ($user_id) {
                $stmt = $pdo->prepare("SELECT user_id, full_name, email, account_status FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                if ($user) {
                    if ($user['account_status'] === 'active') {
                        $error = 'User is already verified.';
                    } else {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("UPDATE users SET account_status = 'active', verification_token = NULL, verification_token_expires = NULL WHERE user_id = ?");
                        $stmt->execute([$user_id]);

                        // Send notification email
                        $subject = 'Your Account Has Been Verified';
                        $body = 'Hello ' . htmlspecialchars($user['full_name']) . ',<br><br>Your account has been verified by an admin. You can now <a href="http://localhost/login.php">sign in</a> to CryptoMiner ERP.<br><br>Thank you!';
                        $alt_body = "Hello " . $user['full_name'] . ",\n\nYour account has been verified by an admin. You can now sign in at http://localhost/login.php.\n\nThank you!";
                        if (sendNotificationEmail($user['email'], $user['full_name'], $subject, $body, $alt_body)) {
                            $success = 'User verified successfully and notification sent!';
                        } else {
                            $success = 'User verified, but failed to send notification email.';
                        }
                        $pdo->commit();
                    }
                } else {
                    $error = 'Invalid user ID.';
                }
            } else {
                $error = 'Invalid user ID.';
            }
        }
    }

    // Handle user suspension/unsuspension
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_suspend'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = 'Invalid CSRF token. Please try again.';
            error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
        } else {
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
            if ($user_id && in_array($action, ['suspend', 'unsuspend'])) {
                $stmt = $pdo->prepare("SELECT user_id, full_name, email, account_status FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                if ($user) {
                    $new_status = $action === 'suspend' ? 'suspended' : 'active';
                    if ($user['account_status'] === $new_status) {
                        $error = 'User is already ' . $new_status . '.';
                    } else {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare("UPDATE users SET account_status = ? WHERE user_id = ?");
                        $stmt->execute([$new_status, $user_id]);

                        // Send notification email
                        $subject = 'Your Account Status Has Changed';
                        $body = 'Hello ' . htmlspecialchars($user['full_name']) . ',<br><br>Your account has been ' . $new_status . ' by an admin. ' . ($new_status === 'active' ? 'You can now <a href="http://localhost/login.php">sign in</a>.' : 'Please contact support for more information.') . '<br><br>Thank you!';
                        $alt_body = "Hello " . $user['full_name'] . ",\n\nYour account has been " . $new_status . " by an admin. " . ($new_status === 'active' ? 'You can now sign in at http://localhost/login.php.' : 'Please contact support for more information.') . "\n\nThank you!";
                        if (sendNotificationEmail($user['email'], $user['full_name'], $subject, $body, $alt_body)) {
                            $success = 'User ' . $new_status . ' successfully and notification sent!';
                        } else {
                            $success = 'User ' . $new_status . ', but failed to send notification email.';
                        }
                        $pdo->commit();
                    }
                } else {
                    $error = 'Invalid user ID.';
                }
            } else {
                $error = 'Invalid action or user ID.';
            }
        }
    }

    // Fetch all users
    $stmt = $pdo->prepare("SELECT user_id, username, full_name, email, account_status, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Users Management Error: ' . $e->getMessage());
    $error = 'An error occurred. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - CryptoMiner ERP</title>
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

        <!-- Users Section -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-semibold text-gray-800">Manage Users</h2>
                    <a href="dashboard.php" class="text-primary text-sm font-medium flex items-center hover:text-blue-600">
                        Back to Dashboard <i class="ri-arrow-left-line ml-1"></i>
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-4 text-center text-gray-500">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-<?php echo $user['account_status'] === 'active' ? 'green' : ($user['account_status'] === 'pending' ? 'yellow' : 'red'); ?>-100 text-<?php echo $user['account_status'] === 'active' ? 'green' : ($user['account_status'] === 'pending' ? 'yellow' : 'red'); ?>-800"><?php echo ucfirst($user['account_status']); ?></span>
                                        </td>
                                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                                            <?php if ($user['account_status'] === 'pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <button type="submit" name="verify_user" class="text-green-600 hover:text-green-800 mr-2">Verify</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="action" value="<?php echo $user['account_status'] === 'suspended' ? 'unsuspend' : 'suspend'; ?>">
                                                <button type="submit" name="toggle_suspend" class="text-<?php echo $user['account_status'] === 'suspended' ? 'blue' : 'red'; ?>-600 hover:text-<?php echo $user['account_status'] === 'suspended' ? 'blue' : 'red'; ?>-800"><?php echo $user['account_status'] === 'suspended' ? 'Unsuspend' : 'Suspend'; ?></button>
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