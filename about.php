<?php
// about.php
require_once 'config.php';

// Redirect logged-in users to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - CryptoMiner ERP</title>
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
                <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Empowering Wealth Creation with CryptoMiner ERP</h1>
                <p class="text-lg md:text-xl text-blue-100 mb-6 max-w-2xl mx-auto">We’re on a mission to make cryptocurrency mining accessible, profitable, and secure for everyone.</p>
                <div class="flex justify-center gap-4">
                    <a href="register.php" class="bg-primary text-white px-6 py-3 rounded-button font-medium hover:bg-blue-600 transition-colors text-lg">Join Us Today</a>
                    <a href="contact.php" class="bg-white text-primary px-6 py-3 rounded-button font-medium hover:bg-blue-50 transition-colors text-lg">Contact Us</a>
                </div>
            </div>
            <div class="absolute inset-0 bg-cover bg-center opacity-20" style="background-image: url('https://readdy.ai/api/search-image?query=abstract%20digital%20cryptocurrency%20mining%20concept%20with%20circuit%20board%20patterns%2C%20blue%20technology%20background%20with%20glowing%20nodes%20and%20connections%2C%20futuristic%20blockchain%20visualization%2C%20clean%20professional%20look%2C%20high%20resolution&width=1200&height=400&seq=2&orientation=landscape');"></div>
        </section>

        <!-- Our Story -->
        <section class="py-12 bg-white">
            <div class="container mx-auto px-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
                    <div>
                        <h2 class="text-3xl font-semibold text-gray-800 mb-4">Our Story</h2>
                        <p class="text-sm text-gray-600 mb-4">Founded in 2023, CryptoMiner ERP was born from a vision to democratize cryptocurrency mining. We saw an opportunity to simplify the complex world of blockchain technology, making it accessible to everyday investors seeking passive income.</p>
                        <p class="text-sm text-gray-600 mb-4">Today, we’re a trusted platform serving thousands of users worldwide, offering high-return mining packages, a robust referral program, and a user-friendly interface. Our commitment to innovation and security drives everything we do.</p>
                        <a href="register.php" class="text-primary font-medium text-sm hover:text-blue-600">Start your mining journey with us</a>
                    </div>
                    <div class="relative">
                        <img src="images/team.png" alt="Our Team" class="rounded-lg shadow-sm w-full">
                    </div>
                </div>
            </div>
        </section>

        <!-- Mission & Vision -->
        <section class="py-12 bg-gray-50">
            <div class="container mx-auto px-4">
                <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">Our Mission & Vision</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                        <i class="ri-rocket-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Our Mission</h3>
                        <p class="text-sm text-gray-600">To empower individuals worldwide with accessible, profitable, and secure cryptocurrency mining opportunities, fostering financial freedom through innovation.</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                        <i class="ri-eye-line text-primary text-3xl mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Our Vision</h3>
                        <p class="text-sm text-gray-600">To become the global leader in user-centric crypto mining, revolutionizing wealth creation with cutting-edge technology and unparalleled support.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Our Team -->
        <section class="py-12 bg-white">
            <div class="container mx-auto px-4">
                <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">Meet Our Team</h2>
                <p class="text-center text-gray-600 text-sm mb-10 max-w-xl mx-auto">Our dedicated team of experts drives CryptoMiner ERP’s success, combining expertise in blockchain, finance, and technology.</p>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                        <div class="w-24 h-24 mx-auto rounded-full bg-gray-200 mb-4 overflow-hidden">
                            <img src="images/ceo.png" alt="John Chris" class="w-full h-full object-cover">
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">John Doe</h3>
                        <p class="text-sm text-gray-500">CEO & Founder</p>
                        <p class="text-xs text-gray-600 mt-2">A blockchain visionary with over a decade in fintech innovation.</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                        <div class="w-24 h-24 mx-auto rounded-full bg-gray-200 mb-4 overflow-hidden">
                            <img src="images/ceo4.png" alt="Julian Smith" class="w-full h-full object-cover">
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Jane Smith</h3>
                        <p class="text-sm text-gray-500">CTO</p>
                        <p class="text-xs text-gray-600 mt-2">Leading our tech team with expertise in scalable systems.</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                        <div class="w-24 h-24 mx-auto rounded-full bg-gray-200 mb-4 overflow-hidden">
                            <img src="images/ceo3.png" alt="Michael Lee" class="w-full h-full object-cover">
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Michael Lee</h3>
                        <p class="text-sm text-gray-500">CFO</p>
                        <p class="text-xs text-gray-600 mt-2">Ensuring financial stability with deep investment expertise.</p>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-6 text-center">
                        <div class="w-24 h-24 mx-auto rounded-full bg-gray-200 mb-4 overflow-hidden">
                            <img src="images/ceo2.png" alt="Emily Chen" class="w-full h-full object-cover">
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Emily Chen</h3>
                        <p class="text-sm text-gray-500">CMO</p>
                        <p class="text-xs text-gray-600 mt-2">Crafting our global outreach with creative marketing.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Our Values -->
        <section class="py-12 bg-gray-50">
            <div class="container mx-auto px-4">
                <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">Our Core Values</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="text-center p-6">
                        <div class="w-16 h-16 mx-auto rounded-full bg-blue-100 flex items-center justify-center mb-4">
                            <i class="ri-shield-check-line text-primary text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Security First</h3>
                        <p class="text-sm text-gray-600">We prioritize your safety with robust encryption and 2FA.</p>
                    </div>
                    <div class="text-center p-6">
                        <div class="w-16 h-16 mx-auto rounded-full bg-green-100 flex items-center justify-center mb-4">
                            <i class="ri-lightbulb-line text-green-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Innovation</h3>
                        <p class="text-sm text-gray-600">We push boundaries with cutting-edge mining technology.</p>
                    </div>
                    <div class="text-center p-6">
                        <div class="w-16 h-16 mx-auto rounded-full bg-yellow-100 flex items-center justify-center mb-4">
                            <i class="ri-user-heart-line text-yellow-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">User-Centric</h3>
                        <p class="text-sm text-gray-600">Your success drives our platform’s design and support.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Why Us -->
        <section class="py-12 bg-white">
            <div class="container mx-auto px-4">
                <h2 class="text-3xl font-semibold text-gray-800 mb-8 text-center">Why Choose CryptoMiner ERP?</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    <div class="relative">
                        <img src="images/image.png" alt="Platform Dashboard" class="rounded-lg shadow-sm w-full">
                    </div>
                    <div>
                        <ul class="space-y-4">
                            <li class="flex items-start">
                                <i class="ri-check-line text-green-500 mr-2 mt-1"></i>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-800">High Returns</h3>
                                    <p class="text-xs text-gray-600">Earn up to 9% daily with our optimized mining packages.</p>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <i class="ri-check-line text-green-500 mr-2 mt-1"></i>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-800">Effortless Mining</h3>
                                    <p class="text-xs text-gray-600">No technical skills needed—our platform handles everything.</p>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <i class="ri-check-line text-green-500 mr-2 mt-1"></i>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-800">Referral Bonuses</h3>
                                    <p class="text-xs text-gray-600">Grow your earnings by inviting friends to join.</p>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <i class="ri-check-line text-green-500 mr-2 mt-1"></i>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-800">24/7 Support</h3>
                                    <p class="text-xs text-gray-600">Our team is here to help you succeed, anytime.</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-12 bg-gradient-to-r from-blue-600 to-blue-400">
            <div class="container mx-auto px-4 text-center">
                <h2 class="text-3xl font-bold text-white mb-4">Ready to Join the Future of Mining?</h2>
                <p class="text-lg text-blue-100 mb-6 max-w-xl mx-auto">Sign up today and start building your wealth with CryptoMiner ERP’s trusted platform.</p>
                <a href="register.php" class="bg-white text-primary px-6 py-3 rounded-button font-medium hover:bg-blue-50 transition-colors text-lg">Get Started Now</a>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <script src="scripts.js"></script>
</body>
</html>