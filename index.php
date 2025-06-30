<?php
// index.php
require_once 'config.php';

// Redirect logged-in users to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Fetch mining packages
$stmt = $pdo->query("SELECT package_id, name, price, daily_profit, duration_days, daily_return_percentage FROM mining_packages WHERE is_active = TRUE ORDER BY price ASC");
$mining_packages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoMiner ERP - Start Earning Passive Income Today</title>
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

    <main class="flex-grow">
        <!-- Hero Section -->
        <section class="relative bg-gradient-to-r from-blue-600 to-blue-400 py-16 md:py-24">
            <div class="container mx-auto px-4 text-center">
                <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Unlock Passive Income with Crypto Mining</h1>
                <p class="text-lg md:text-xl text-blue-100 mb-6 max-w-2xl mx-auto">Join CryptoMiner ERP and start earning daily profits with our cutting-edge mining packages. No expertise required!</p>
                <div class="flex justify-center gap-4">
                    <a href="register.php" class="bg-primary text-white px-6 py-3 rounded-button font-medium hover:bg-blue-600 transition-colors text-lg">Start Mining Now</a>
                    <a href="login.php" class="bg-white text-primary px-6 py-3 rounded-button font-medium hover:bg-blue-50 transition-colors text-lg">Login</a>
                </div>
            </div>
            <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image: url('https://readdy.ai/api/search-image?query=abstract%20digital%20cryptocurrency%20mining%20concept%20with%20circuit%20board%20patterns%2C%20blue%20technology%20background%20with%20glowing%20nodes%20and%20connections%2C%20futuristic%20blockchain%20visualization%2C%20clean%20professional%20look%2C%20high%20resolution&width=1200&height=400&seq=2&orientation=landscape');"></div>
        </section>

        <!-- How It Works -->
        <section class="py-12 bg-white">
            <div class="container mx-auto px-4">
                <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">How It Works</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center p-6">
                        <div class="w-16 h-16 mx-auto rounded-full bg-blue-100 flex items-center justify-center mb-4">
                            <i class="ri-user-add-line text-primary text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">1. Sign Up Free</h3>
                        <p class="text-sm text-gray-600">Create your account in minutes and join thousands of miners worldwide.</p>
                    </div>
                    <div class="text-center p-6">
                        <div class="w-16 h-16 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-4">
                            <i class="ri-cpu-line text-green-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">2. Choose a Miner</h3>
                        <p class="text-sm text-gray-600">Select from our high-return mining packages to start earning.</p>
                    </div>
                    <div class="text-center p-6">
                        <div class="w-16 h-16 mx-auto rounded-full bg-yellow-100 flex items-center justify-center mb-4">
                            <i class="ri-coins-line text-yellow-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">3. Earn Daily Profits</h3>
                        <p class="text-sm text-gray-600">Watch your earnings grow daily with our secure, automated platform.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mining Packages -->
        <section class="py-12 bg-gray-50" id="miners">
            <div class="container mx-auto px-4">
                <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">Our Mining Packages</h2>
                <p class="text-center text-gray-600 text-sm mb-10 max-w-xl mx-auto">Choose a plan that fits your goals and start generating passive income today.</p>
                
                <?php if (empty($mining_packages)): ?>
                    <p class="text-center text-gray-500">No mining packages available at the moment.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
                        <?php foreach ($mining_packages as $index => $package): ?>
                            <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-100 hover:border-blue-200 transition-colors <?php echo $index === 2 ? 'ring-2 ring-blue-500 ring-opacity-20' : ''; ?>">
                                <div class="p-4 <?php echo $index === 2 ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white' : 'bg-blue-50'; ?>">
                                    <h3 class="text-lg font-semibold <?php echo $index === 2 ? 'text-white' : 'text-gray-800'; ?>"><?php echo htmlspecialchars($package['name']); ?></h3>
                                    <div class="mt-2 flex items-baseline">
                                        <span class="text-2xl font-bold <?php echo $index === 2 ? 'text-white' : 'text-primary'; ?>">$<?php echo number_format($package['price'], 2); ?></span>
                                        <span class="text-sm <?php echo $index === 2 ? 'text-blue-100' : 'text-gray-500'; ?> ml-2">one-time</span>
                                    </div>
                                    <?php if ($index === 2): ?>
                                        <div class="absolute top-0 right-0 bg-blue-700 text-white text-xs font-medium px-2 py-1 rounded-bl-lg">POPULAR</div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-4">
                                    <ul class="space-y-2 mb-4">
                                        <li class="flex items-center text-sm text-gray-600">
                                            <i class="ri-check-line text-green-500 mr-2"></i>
                                            <?php echo number_format($package['daily_return_percentage'], 2); ?>% daily return
                                        </li>
                                        <li class="flex items-center text-sm text-gray-600">
                                            <i class="ri-check-line text-green-500 mr-2"></i>
                                            <?php echo $package['duration_days']; ?> days duration
                                        </li>
                                        <li class="flex items-center text-sm text-gray-600">
                                            <i class="ri-check-line text-green-500 mr-2"></i>
                                            Total return: $<?php echo number_format($package['price'] * (1 + ($package['daily_return_percentage'] / 100) * $package['duration_days']), 2); ?>
                                        </li>
                                        <li class="flex items-center text-sm text-gray-600">
                                            <i class="ri-check-line text-green-500 mr-2"></i>
                                            Daily profit: $<?php echo number_format($package['daily_profit'], 2); ?>
                                        </li>
                                    </ul>
                                    <a href="register.php" class="block w-full bg-primary text-white py-2.5 rounded-button font-medium hover:bg-blue-600 transition-colors text-center">Get Started</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Benefits -->
        <section class="py-12 bg-white">
            <div class="container mx-auto px-4">
                <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">Why Choose CryptoMiner ERP?</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="p-6 text-center">
                        <i class="ri-coins-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">High Returns</h3>
                        <p class="text-sm text-gray-600">Earn up to 9% daily with our optimized mining packages.</p>
                    </div>
                    <div class="p-6 text-center">
                        <i class="ri-shield-check-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Secure Platform</h3>
                        <p class="text-sm text-gray-600">Your funds and data are protected with advanced security.</p>
                    </div>
                    <div class="p-6 text-center">
                        <i class="ri-user-add-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Referral Rewards</h3>
                        <p class="text-sm text-gray-600">Invite friends and earn generous commissions.</p>
                    </div>
                    <div class="p-6 text-center">
                        <i class="ri-smartphone-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">User-Friendly</h3>
                        <p class="text-sm text-gray-600">Manage your miners effortlessly, anytime, anywhere.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="py-12 bg-gray-50">
            <div class="container mx-auto px-4">
                <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">What Our Users Say</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-sm text-gray-600 mb-4">"CryptoMiner ERP made mining so easy! I started earning daily profits within days of signing up."</p>
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center mr-3">
                                <span class="text-blue-600 font-medium">JS</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">James Smith</p>
                                <p class="text-xs text-gray-500">Investor</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-sm text-gray-600 mb-4">"The referral program is amazing! I’ve earned extra income just by inviting friends."</p>
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-pink-100 flex items-center justify-center mr-3">
                                <span class="text-pink-600 font-medium">EJ</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Emily Johnson</p>
                                <p class="text-xs text-gray-500">Affiliate</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6">
                        <p class="text-sm text-gray-600 mb-4">"Secure and reliable. I trust CryptoMiner ERP with my investments."</p>
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center mr-3">
                                <span class="text-green-600 font-medium">MT</span>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">Michael Tan</p>
                                <p class="text-xs text-gray-500">Miner</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="py-12 bg-white">
            <div class="container mx-auto px-4">
                <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">Frequently Asked Questions</h2>
                <div class="max-w-3xl mx-auto space-y-4">
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-medium text-gray-800 mb-2">What is CryptoMiner ERP?</h3>
                        <p class="text-sm text-gray-600">CryptoMiner ERP is a platform that allows you to invest in cryptocurrency mining packages and earn daily profits without technical expertise.</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-medium text-gray-800 mb-2">How do I start mining?</h3>
                        <p class="text-sm text-gray-600">Sign up for a free account, choose a mining package, and start earning daily profits. It’s that simple!</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-medium text-gray-800 mb-2">Is my investment secure?</h3>
                        <p class="text-sm text-gray-600">Yes, we use advanced security measures, including encryption and two-factor authentication, to protect your funds and data.</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h3 class="text-lg font-medium text-gray-800 mb-2">What are the referral rewards?</h3>
                        <p class="text-sm text-gray-600">Invite friends to join and earn commissions on their mining investments, plus unlock withdrawal privileges.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Footer -->
        <section class="py-12 bg-gradient-to-r from-blue-600 to-blue-400">
            <div class="container mx-auto px-4 text-center">
                <h2 class="text-3xl font-bold text-white mb-4">Ready to Start Earning?</h2>
                <p class="text-lg text-blue-100 mb-6 max-w-xl mx-auto">Join CryptoMiner ERP today and turn your investment into daily profits. Don’t wait—start your mining journey now!</p>
                <a href="register.php" class="bg-white text-primary px-6 py-3 rounded-button font-medium hover:bg-blue-50 transition-colors text-lg">Join Free Today</a>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <script src="scripts.js"></script>
</body>
</html>