<?php
// header.php
require_once 'config.php';

// Initialize user and balance data
$user = null;
$balance = ['available_balance' => 0.00];
$is_logged_in = isset($_SESSION['user_id']);

if ($is_logged_in) {
    try {
        // Fetch user data
        $user_id = $_SESSION['user_id'];
        $stmt = $pdo->prepare("SELECT full_name, email, account_status, phone_number FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch balance data
        if ($user && $user['account_status'] === 'active') {
            $stmt = $pdo->prepare("SELECT available_balance FROM user_balances WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $balance = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['available_balance' => 0.00];
        } else {
            // Invalidate session for non-active or invalid users
            session_destroy();
            header('Location: login.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log('Header Error: ' . $e->getMessage());
        session_destroy();
        header('Location: login.php');
        exit;
    }
}
?>
<header class="bg-white shadow-sm sticky top-0 z-50">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center">
            <a href="<?php echo $is_logged_in ? 'dashboard.php' : 'index.php'; ?>" class="text-2xl font-['Pacifico'] text-primary mr-8">CryptoMiner</a>
            <nav class="hidden md:flex space-x-6">
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php" class="text-<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'primary font-medium' : 'gray-600 hover:text-primary'; ?> transition-colors">Dashboard</a>
                    <a href="miners.php" class="text-<?php echo basename($_SERVER['PHP_SELF']) === 'miners.php' ? 'primary font-medium' : 'gray-600 hover:text-primary'; ?> transition-colors">Miners</a>
                    <a href="referrals.php" class="text-<?php echo basename($_SERVER['PHP_SELF']) === 'referrals.php' ? 'primary font-medium' : 'gray-600 hover:text-primary'; ?> transition-colors">Referrals</a>
                    <a href="wallet.php" class="text-<?php echo basename($_SERVER['PHP_SELF']) === 'wallet.php' ? 'primary font-medium' : 'gray-600 hover:text-primary'; ?> transition-colors">Wallet</a>
                    <a href="withdraw.php" class="text-<?php echo basename($_SERVER['PHP_SELF']) === 'withdraw.php' ? 'primary font-medium' : 'gray-600 hover:text-primary'; ?> transition-colors">Withdraw</a>
                    <a href="contact.php" class="text-<?php echo basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'primary font-medium' : 'gray-600 hover:text-primary'; ?> transition-colors">Support</a>
                <?php else: ?>
                    <a href="index.php" class="text-<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'primary font-medium' : 'gray-600 hover:text-primary'; ?> transition-colors">Home</a>
                    <a href="about.php" class="text-<?php echo basename($_SERVER['PHP_SELF']) === 'about.php' ? 'primary font-medium' : 'gray-600 hover:text-primary'; ?> transition-colors">About</a>
                    <a href="contact.php" class="text-<?php echo basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'primary font-medium' : 'gray-600 hover:text-primary'; ?> transition-colors">Contact</a>
                <?php endif; ?>
            </nav>
        </div>
        
        <div class="flex items-center space-x-4">
            <?php if ($is_logged_in): ?>
                <div class="hidden md:flex items-center bg-blue-50 rounded-full px-4 py-1.5">
                    <span class="text-xs text-gray-500 mr-2">Balance:</span>
                    <span class="text-sm font-semibold text-primary earnings-counter">$<?php echo number_format($balance['available_balance'], 2); ?></span>
                </div>
                
                <div class="relative">
                    <button id="userMenuButton" class="flex items-center space-x-2 focus:outline-none" aria-label="User menu" aria-haspopup="true" aria-expanded="false">
                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                            <span class="text-sm font-medium text-gray-600"><?php echo htmlspecialchars(substr($user['full_name'], 0, 1)); ?></span>
                        </div>
                        <div class="hidden md:block text-left">
                            <p class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-xs text-gray-500"><?php echo $user['phone_number'] ? htmlspecialchars($user['phone_number']) : 'No phone registered'; ?></p>
                        </div>
                        <div class="w-5 h-5 flex items-center justify-center text-gray-400">
                            <i class="ri-arrow-down-s-line"></i>
                        </div>
                    </button>
                    
                    <div id="userMenu" class="absolute right-0 mt-2 w-48 bg-white rounded shadow-lg py-1 hidden" role="menu" aria-labelledby="userMenuButton">
                        <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Profile</a>
                        <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Settings</a>
                        <a href="security.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" role="menuitem">Security</a>
                        <div class="border-t border-gray-100"></div>
                        <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100" role="menuitem">Sign out</a>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="text-gray-600 hover:text-primary transition-colors">Sign In</a>
                <a href="register.php" class="bg-primary text-white px-4 py-2 rounded-button font-medium hover:bg-blue-600 transition-colors whitespace-nowrap">Sign Up</a>
            <?php endif; ?>
            
            <button class="md:hidden w-10 h-10 flex items-center justify-center text-gray-500" id="mobileMenuButton" aria-label="Toggle mobile menu">
                <i class="ri-menu-line text-xl"></i>
            </button>
        </div>
    </div>
    
    <!-- Mobile menu -->
    <div id="mobileMenu" class="md:hidden bg-white border-t border-gray-100 hidden">
        <div class="px-4 py-3 space-y-3">
            <?php if ($is_logged_in): ?>
                <a href="dashboard.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'primary font-medium' : 'gray-600'; ?>">Dashboard</a>
                <a href="miners.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'miners.php' ? 'primary font-medium' : 'gray-600'; ?>">Miners</a>
                <a href="referrals.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'referrals.php' ? 'primary font-medium' : 'gray-600'; ?>">Referrals</a>
                <a href="wallet.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'wallet.php' ? 'primary font-medium' : 'gray-600'; ?>">Wallet</a>
                <a href="withdraw.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'withdraw.php' ? 'primary font-medium' : 'gray-600'; ?>">Withdraw</a>
                <a href="profile.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'primary font-medium' : 'gray-600'; ?>">Profile</a>
                <a href="settings.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'primary font-medium' : 'gray-600'; ?>">Settings</a>
                <a href="security.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'security.php' ? 'primary font-medium' : 'gray-600'; ?>">Security</a>
                <a href="contact.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'primary font-medium' : 'gray-600'; ?>">Support</a>
                <div class="flex items-center bg-blue-50 rounded-full px-4 py-2 mt-2">
                    <span class="text-xs text-gray-500 mr-2">Balance:</span>
                    <span class="text-sm font-semibold text-primary earnings-counter">$<?php echo number_format($balance['available_balance'], 2); ?></span>
                </div>
                <a href="logout.php" class="block py-2 text-red-600">Sign out</a>
            <?php else: ?>
                <a href="index.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'primary font-medium' : 'gray-600'; ?>">Home</a>
                <a href="about.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'about.php' ? 'primary font-medium' : 'gray-600'; ?>">About</a>
                <a href="contact.php" class="block py-2 text-<?php echo basename($_SERVER['PHP_SELF']) === 'contact.php' ? 'primary font-medium' : 'gray-600'; ?>">Contact</a>
                <a href="login.php" class="block py-2 text-gray-600">Sign In</a>
                <a href="register.php" class="block py-2 text-primary font-medium">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</header>