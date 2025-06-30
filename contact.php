<?php
// contact.php
require_once 'config.php';

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user data if logged in
$user = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT full_name, email, account_status FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user || $user['account_status'] !== 'active') {
        session_destroy();
        header('Location: login.php');
        exit;
    }
}

// Initialize form data and messages
$form_data = [
    'name' => $user ? $user['full_name'] : '',
    'email' => $user ? $user['email'] : '',
    'subject' => '',
    'message' => ''
];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid CSRF token. Please try again.';
        error_log('CSRF Token Mismatch: Submitted=' . ($_POST['csrf_token'] ?? 'none') . ', Expected=' . $_SESSION['csrf_token']);
    } else {
        $form_data['name'] = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $form_data['email'] = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $form_data['subject'] = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
        $form_data['message'] = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

        // Validate inputs
        if (empty($form_data['name']) || empty($form_data['email']) || empty($form_data['subject']) || empty($form_data['message'])) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format.';
        } else {
            try {
                $pdo->beginTransaction();

                // Insert contact message
                $stmt = $pdo->prepare("INSERT INTO contact_messages (user_id, name, email, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $user ? $user_id : null,
                    $form_data['name'],
                    $form_data['email'],
                    $form_data['subject'],
                    $form_data['message']
                ]);

                // Log submission
                $log_message = "Contact form submitted by " . ($user ? "user ID $user_id ({$user['email']})" : $form_data['email']) . " at " . date('Y-m-d H:i:s');
                error_log($log_message, 3, 'admin_logs.txt');

                // Send auto-response email (Brevo SMTP placeholder)
                // Note: Replace with actual Brevo SMTP configuration from register.php
                /*
                $brevo_api_key = 'your_brevo_api_key';
                $to = $form_data['email'];
                $subject = 'Thank You for Contacting CryptoMiner ERP';
                $message = "Dear {$form_data['name']},\n\nThank you for reaching out. We have received your inquiry and will respond soon.\n\nDetails:\nSubject: {$form_data['subject']}\nMessage: {$form_data['message']}\n\nBest regards,\nCryptoMiner ERP Team";
                $headers = ['From: support@cryptominererp.com', 'Content-Type: text/plain'];
                // Use Brevo SMTP library to send email
                */

                // Send admin notification (placeholder)
                /*
                $admin_email = 'admin@cryptominererp.com';
                $admin_subject = 'New Contact Message';
                $admin_message = "New contact form submission:\n\nName: {$form_data['name']}\nEmail: {$form_data['email']}\nSubject: {$form_data['subject']}\nMessage: {$form_data['message']}\nUser ID: " . ($user ? $user_id : 'N/A');
                // Use Brevo SMTP library to send email
                */

                $pdo->commit();

                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                $success = 'Your message has been sent successfully! You will receive a confirmation email shortly.';
                $form_data = ['name' => $user ? $user['full_name'] : '', 'email' => $user ? $user['email'] : '', 'subject' => '', 'message' => ''];
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Failed to send message: ' . htmlspecialchars($e->getMessage());
                error_log('Contact Form Error: ' . $e->getMessage());
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
    <title>Contact Us - CryptoMiner ERP</title>
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
        <!-- Header Section -->
        <section class="mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h1 class="text-2xl font-semibold text-gray-800 mb-2 text-center">Contact Us</h1>
                <p class="text-gray-600 text-sm text-center max-w-xl mx-auto">Have questions or need support? Fill out the form below, and our team will get back to you promptly.</p>
            </div>
        </section>

        <!-- Contact Form and Info -->
        <section class="mb-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Contact Form -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Send Us a Message</h2>
                
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

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-user-line text-gray-400"></i>
                            </div>
                            <input type="text" name="name" required class="block w-full pl-10 py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" placeholder="Your name" value="<?php echo htmlspecialchars($form_data['name']); ?>" <?php echo $user ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-mail-line text-gray-400"></i>
                            </div>
                            <input type="email" name="email" required class="block w-full pl-10 py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" placeholder="you@example.com" value="<?php echo htmlspecialchars($form_data['email']); ?>" <?php echo $user ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Subject <span class="text-red-500">*</span></label>
                        <input type="text" name="subject" required class="block w-full py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" placeholder="What’s your inquiry about?" value="<?php echo htmlspecialchars($form_data['subject']); ?>">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message <span class="text-red-500">*</span></label>
                        <textarea name="message" required class="block w-full py-2 px-3 border border-gray-300 rounded-button focus:ring-2 focus:ring-primary focus:ring-opacity-20 focus:outline-none text-sm" rows="5" placeholder="Describe your issue or question..."><?php echo htmlspecialchars($form_data['message']); ?></textarea>
                    </div>
                    
                    <button type="submit" name="submit_contact" class="w-full bg-primary text-white py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">Send Message</button>
                </form>
            </div>

            <!-- How It Works and Benefits -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">How Our Contact System Works</h2>
                <p class="text-gray-600 text-sm mb-4">Our contact form is designed to make it easy for you to reach out to our support team. Here’s how it works:</p>
                <ul class="space-y-3 text-sm text-gray-600">
                    <li class="flex items-start">
                        <i class="ri-check-line text-green-600 mr-2 mt-1"></i>
                        <span><strong>Submit Your Inquiry:</strong> Fill out the form with your details and message. If logged in, your name and email are pre-filled.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-check-line text-green-600 mr-2 mt-1"></i>
                        <span><strong>Instant Confirmation:</strong> Receive an auto-response email confirming we’ve received your message.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-check-line text-green-600 mr-2 mt-1"></i>
                        <span><strong>Team Review:</strong> Our support team reviews your inquiry and responds promptly, typically within 24-48 hours.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-check-line text-green-600 mr-2 mt-1"></i>
                        <span><strong>Secure Storage:</strong> Your message is securely stored and tracked for follow-up.</span>
                    </li>
                </ul>

                <h2 class="text-xl font-semibold text-gray-800 mt-6 mb-4">Benefits of Contacting Us</h2>
                <p class="text-gray-600 text-sm mb-4">Reaching out to our team offers several advantages:</p>
                <ul class="space-y-3 text-sm text-gray-600">
                    <li class="flex items-start">
                        <i class="ri-star-line text-yellow-500 mr-2 mt-1"></i>
                        <span><strong>Quick Support:</strong> Resolve issues related to your account, miners, deposits, or withdrawals efficiently.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-star-line text-yellow-500 mr-2 mt-1"></i>
                        <span><strong>Personalized Assistance:</strong> Get tailored guidance from our dedicated support team.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-star-line text-yellow-500 mr-2 mt-1"></i>
                        <span><strong>Improved Experience:</strong> Your feedback helps us enhance the platform for all users.</span>
                    </li>
                    <li class="flex items-start">
                        <i class="ri-star-line text-yellow-500 mr-2 mt-1"></i>
                        <span><strong>Accessibility:</strong> Contact us anytime, whether you’re a registered user or a visitor.</span>
                    </li>
                </ul>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <script src="scripts.js"></script>
</body>
</html>