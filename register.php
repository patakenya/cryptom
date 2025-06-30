<?php
// register.php
require_once 'config.php';
// Load PHPMailer (adjust path if not using Composer)
require_once 'vendor/autoload.php'; // For Composer
// require_once 'vendor/PHPMailer/src/PHPMailer.php'; // For manual installation
// require_once 'vendor/PHPMailer/src/SMTP.php';
// require_once 'vendor/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Generate CSRF token only if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize messages and form data
$error = '';
$success = '';
$form_data = [
    'full_name' => '',
    'email' => '',
    'referral_code' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token. Please refresh the page and try again.';
        error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
    } else {
        $form_data['full_name'] = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
        $form_data['email'] = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $form_data['referral_code'] = filter_input(INPUT_POST, 'referral_code', FILTER_SANITIZE_STRING);
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $terms = isset($_POST['terms']);

        // Validate inputs
        if (empty($form_data['full_name']) || empty($form_data['email']) || empty($password) || empty($confirm_password) || !$terms) {
            $error = 'Please fill in all required fields and accept the terms.';
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            // Check for duplicate email or username
            $username = strtolower(str_replace(' ', '_', $form_data['full_name']));
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$form_data['email'], $username]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Email or username already exists.';
            } else {
                // Validate referral code
                $referred_by = null;
                if (!empty($form_data['referral_code'])) {
                    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE referral_code = ?");
                    $stmt->execute([$form_data['referral_code']]);
                    $referred_by = $stmt->fetchColumn();
                    if (!$referred_by) {
                        $error = 'Invalid referral code.';
                    }
                }

                if (empty($error)) {
                    // Generate unique referral code and verification token
                    $user_referral_code = $username . '_' . substr(uniqid(), -4);
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $verification_token = bin2hex(random_bytes(32));
                    $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

                    try {
                        $pdo->beginTransaction();

                        // Insert user with verification token
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, referral_code, referred_by, account_status, verification_token, verification_token_expires) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
                        $stmt->execute([$username, $form_data['email'], $password_hash, $form_data['full_name'], $user_referral_code, $referred_by, $verification_token, $token_expiry]);

                        $user_id = $pdo->lastInsertId();

                        // Initialize user balance
                        $stmt = $pdo->prepare("INSERT INTO user_balances (user_id, available_balance, pending_balance, total_withdrawn) VALUES (?, 0.00, 0.00, 0.00)");
                        $stmt->execute([$user_id]);

                        // Initialize security settings
                        $stmt = $pdo->prepare("INSERT INTO security_settings (user_id, two_factor_enabled, login_alerts, withdrawal_confirmations, referral_notifications) VALUES (?, FALSE, TRUE, TRUE, TRUE)");
                        $stmt->execute([$user_id]);

                        // If referred, add referral record
                        if ($referred_by) {
                            $stmt = $pdo->prepare("INSERT INTO referrals (referrer_id, referred_user_id, commission_earned, status) VALUES (?, ?, 0.00, 'active')");
                            $stmt->execute([$referred_by, $user_id]);
                        }

                        // Send verification email
                        $mail = new PHPMailer(true);
                        try {
                            // Brevo SMTP settings
                            $mail->isSMTP();
                            $mail->Host = 'smtp-relay.brevo.com';
                            $mail->SMTPAuth = true;
                            $mail->Username = '8f3ce1001@smtp-brevo.com';
                            $mail->Password = '0AZMnX4sCbTS62hp';
                            $mail->SMTPSecure = 'tls';
                            $mail->Port = 587;

                            // Email content
                            $mail->setFrom('no-reply@cryptominer.com', 'CryptoMiner ERP');
                            $mail->addAddress($form_data['email'], $form_data['full_name']);
                            $mail->isHTML(true);
                            $mail->Subject = 'Verify Your CryptoMiner ERP Account';
                            $verification_link = "http://localhost/verify_email.php?token=" . urlencode($verification_token); // Update domain for production
                            $mail->Body = "
                                <h2>Welcome to CryptoMiner ERP!</h2>
                                <p>Please verify your email address by clicking the link below:</p>
                                <p><a href='$verification_link' style='background-color: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 8px;'>Verify Email</a></p>
                                <p>This link expires in 24 hours.</p>
                                <p>If you didn't sign up, please ignore this email.</p>
                            ";
                            $mail->AltBody = "Please verify your email by visiting: $verification_link\nThis link expires in 24 hours.";

                            $mail->send();
                        } catch (Exception $e) {
                            throw new Exception("Failed to send verification email: {$mail->ErrorInfo}");
                        }

                        $pdo->commit();

                        // Regenerate CSRF token
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                        $success = 'Registration successful! Please check your email to verify your account.';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Registration failed: ' . htmlspecialchars($e->getMessage());
                        error_log('Registration Error: ' . $e->getMessage());
                    }
                }
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
    <title>Register - CryptoMiner ERP</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config={theme:{extend:{colors:{primary:'#3b82f6',secondary:'#10b981'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-gray-50 min-h-screen flex flex-col">
    <?php include 'header.php'; ?>

    <main class="flex-grow container mx-auto px-4 py-12">
        <div class="max-w-md mx-auto bg-white rounded-lg shadow-sm p-8">
            <h1 class="text-2xl font-semibold text-gray-800 mb-6 text-center">Create an Account</h1>
            
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

            <?php if (!$success): ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-user-line text-gray-400"></i>
                            </div>
                            <input type="text" name="full_name" required class="block w-full pl-10 py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" placeholder="John Doe" value="<?php echo htmlspecialchars($form_data['full_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-mail-line text-gray-400"></i>
                            </div>
                            <input type="email" name="email" required class="block w-full pl-10 py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" placeholder="you@example.com" value="<?php echo htmlspecialchars($form_data['email']); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-lock-line text-gray-400"></i>
                            </div>
                            <input type="password" name="password" required class="block w-full pl-10 py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" placeholder="••••••••">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Minimum 8 characters</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-lock-line text-gray-400"></i>
                            </div>
                            <input type="password" name="confirm_password" required class="block w-full pl-10 py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" placeholder="••••••••">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Referral Code (Optional)</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-user-add-line text-gray-400"></i>
                            </div>
                            <input type="text" name="referral_code" class="block w-full pl-10 py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" placeholder="e.g., michael85" value="<?php echo htmlspecialchars($form_data['referral_code']); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label class="flex items-center">
                            <span class="custom-checkbox mr-2">
                                <input type="checkbox" name="terms" required>
                                <span class="checkmark"></span>
                            </span>
                            <span class="text-sm text-gray-600">I agree to the <a href="terms.php" class="text-primary hover:text-blue-600">Terms of Service</a> and <a href="privacy.php" class="text-primary hover:text-blue-600">Privacy Policy</a></span>
                        </label>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary text-white py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">Sign Up</button>
                </form>
                
                <p class="mt-6 text-center text-sm text-gray-600">
                    Already have an account? <a href="login.php" class="text-primary font-medium hover:text-blue-600">Sign In</a>
                </p>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>