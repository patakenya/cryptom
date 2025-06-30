-- Create database for CryptoMiner ERP
CREATE DATABASE IF NOT EXISTS cryptominer_erp;
USE cryptominer_erp;

-- Table for storing admin accounts
CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hashed password for security',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB COMMENT='Stores admin account details';

-- Table for storing user information
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL COMMENT 'Unique username for the user',
    full_name VARCHAR(255) NOT NULL COMMENT 'User’s full name',
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hashed password for security',
    referral_code VARCHAR(50) UNIQUE NOT NULL COMMENT 'Unique referral code for the user',
    referred_by INT DEFAULT NULL COMMENT 'ID of the user who referred this user',
    account_status ENUM('pending', 'active', 'suspended') DEFAULT 'pending' COMMENT 'User account status',
    verification_token VARCHAR(255) DEFAULT NULL COMMENT 'Token for email verification',
    verification_token_expires DATETIME DEFAULT NULL COMMENT 'Expiration time for verification token',
    status ENUM('Free', 'Premium') DEFAULT 'Free' COMMENT 'User account type',
    balance DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Available balance in USD',
    pending_balance DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Pending balance in USD',
    withdrawn_balance DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Total withdrawn balance in USD',
    referrals_count INT DEFAULT 0 COMMENT 'Number of successful referrals',
    two_factor_enabled BOOLEAN DEFAULT FALSE COMMENT 'Two-factor authentication status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referred_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_referral_code (referral_code)
) ENGINE=InnoDB COMMENT='Stores user account details';

-- Table for storing user balances
CREATE TABLE user_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    available_balance DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Available balance in USD',
    pending_balance DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Pending balance in USD',
    total_withdrawn DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Total withdrawn amount in USD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB COMMENT='Stores user balance details';

-- Table for storing mining packages available for purchase
CREATE TABLE mining_packages (
    package_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Package name (e.g., Starter Miner)',
    price DECIMAL(12,2) NOT NULL COMMENT 'One-time purchase price in USD',
    daily_return_percentage DECIMAL(5,2) NOT NULL COMMENT 'Daily return percentage (e.g., 9.00)',
    duration_days INT NOT NULL COMMENT 'Duration of mining package in days',
    total_return DECIMAL(12,2) NOT NULL COMMENT 'Total return after duration in USD',
    daily_profit DECIMAL(12,2) NOT NULL COMMENT 'Daily profit in USD',
    is_popular BOOLEAN DEFAULT FALSE COMMENT 'Flag for popular packages',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Flag for active packages',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB COMMENT='Stores available mining packages';

-- Table for storing purchased miners
CREATE TABLE user_miners (
    miner_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    purchase_date DATE NOT NULL COMMENT 'Date of purchase',
    status ENUM('active', 'expired') DEFAULT 'active' COMMENT 'Miner status',
    days_remaining INT NOT NULL COMMENT 'Days remaining for the miner',
    duration_days INT NOT NULL COMMENT 'Total duration in days',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES mining_packages(package_id) ON DELETE RESTRICT,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB COMMENT='Stores user-purchased miners';

-- Table for storing referral relationships
CREATE TABLE referrals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL COMMENT 'ID of the user who referred',
    referred_user_id INT NOT NULL COMMENT 'ID of the referred user',
    joined_date DATE NOT NULL COMMENT 'Date the referred user joined',
    investment DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Total investment by referred user in USD',
    miners_count INT DEFAULT 0 COMMENT 'Number of miners purchased by referred user',
    commission_earned DECIMAL(12,2) DEFAULT 0.00 COMMENT 'Commission earned by referrer in USD',
    status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'Referral status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_referrer_id (referrer_id),
    INDEX idx_referred_user_id (referred_user_id)
) ENGINE=InnoDB COMMENT='Referral relationships and commissions';

-- Table for storing financial transactions
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('deposit', 'purchase', 'earning', 'referral', 'withdrawal') NOT NULL COMMENT 'Transaction type',
    amount DECIMAL(12,2) NOT NULL COMMENT 'Transaction amount in USD (negative for withdrawals/purchases)',
    method VARCHAR(50) NOT NULL COMMENT 'Payment method (e.g., wallet, USDT, Bitcoin)',
    status ENUM('completed', 'pending', 'failed') DEFAULT 'pending' COMMENT 'Transaction status',
    transaction_id VARCHAR(100) DEFAULT NULL COMMENT 'Unique transaction ID',
    transaction_hash VARCHAR(100) DEFAULT NULL COMMENT 'Transaction hash or identifier',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_status (status)
) ENGINE=InnoDB COMMENT='Stores all financial transactions';

-- Table for storing security settings
CREATE TABLE security_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    two_factor_secret VARCHAR(255) DEFAULT NULL COMMENT 'Secret for two-factor authentication',
    two_factor_enabled BOOLEAN DEFAULT FALSE COMMENT 'Two-factor authentication status',
    login_alerts BOOLEAN DEFAULT TRUE COMMENT 'Enable login alerts',
    withdrawal_confirmations BOOLEAN DEFAULT TRUE COMMENT 'Enable withdrawal confirmations',
    referral_notifications BOOLEAN DEFAULT TRUE COMMENT 'Enable referral notifications',
    daily_earnings_reports_enabled BOOLEAN DEFAULT FALSE COMMENT 'Enable daily earnings reports',
    marketing_notifications_enabled BOOLEAN DEFAULT FALSE COMMENT 'Enable marketing notifications',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB COMMENT='Stores user security settings';

-- Table for storing login activity
CREATE TABLE login_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_type VARCHAR(50) NOT NULL COMMENT 'Device type (e.g., Computer, Mobile)',
    location VARCHAR(100) DEFAULT NULL COMMENT 'Location of login (e.g., New York, USA)',
    browser VARCHAR(100) DEFAULT NULL COMMENT 'Browser or app used',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP address of the device',
    login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Active', 'Logged_Out') DEFAULT 'Active' COMMENT 'Session status',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB COMMENT='Stores user login activity';

-- Insert sample admin
INSERT INTO admins (admin_id, username, email, password_hash) VALUES
(1, 'admin', 'admin@cryptominer.com', '$2y$10$examplehashedpassword1234567890');

-- Insert sample users (both verified with account_status = 'active')
INSERT INTO users (user_id, username, full_name, email, password_hash, referral_code, referred_by, account_status, verification_token, verification_token_expires, status, balance, pending_balance, withdrawn_balance, referrals_count, two_factor_enabled, created_at) VALUES
(1, 'michael_chen', 'Michael Chen', 'michael.chen@example.com', '$2y$10$examplehashedpassword1234567890', 'michael85', NULL, 'active', NULL, NULL, 'Premium', 1245.78, 87.50, 3450.00, 2, FALSE, '2025-06-18 00:00:00'),
(2, 'jane_doe', 'Jane Doe', 'jane.doe@example.com', '$2y$10$examplehashedpassword1234567890', 'jane1234', 1, 'active', NULL, NULL, 'Free', 0.00, 0.00, 0.00, 0, FALSE, '2025-06-19 00:00:00');

-- Insert sample user balances
INSERT INTO user_balances (user_id, available_balance, pending_balance, total_withdrawn) VALUES
(1, 1245.78, 87.50, 3450.00),
(2, 0.00, 0.00, 0.00);

-- Insert sample security settings
INSERT INTO security_settings (user_id, two_factor_enabled, login_alerts, withdrawal_confirmations, referral_notifications) VALUES
(1, FALSE, TRUE, TRUE, TRUE),
(2, FALSE, TRUE, TRUE, TRUE);

-- Insert sample mining packages
INSERT INTO mining_packages (package_id, name, price, daily_return_percentage, duration_days, total_return, daily_profit, is_popular, is_active) VALUES
(1, 'Starter Miner', 10.00, 9.00, 20, 18.00, 0.90, FALSE, TRUE),
(2, 'Basic Miner', 25.00, 9.00, 20, 45.00, 2.25, FALSE, TRUE),
(3, 'Standard Miner', 50.00, 9.00, 20, 90.00, 4.50, TRUE, TRUE),
(4, 'Advanced Miner', 75.00, 9.00, 20, 135.00, 6.75, FALSE, TRUE),
(5, 'Premium Miner', 120.00, 9.00, 20, 216.00, 10.80, FALSE, TRUE);

-- Insert sample user miners
INSERT INTO user_miners (miner_id, user_id, package_id, purchase_date, status, days_remaining, duration_days) VALUES
(1, 1, 3, '2025-06-18', 'active', 15, 20);

-- Insert sample referral
INSERT INTO referrals (referrer_id, referred_user_id, joined_date, commission_earned, status) VALUES
(1, 2, '2025-06-19', 0.00, 'active');

-- Insert sample transactions
INSERT INTO transactions (user_id, type, amount, method, status, transaction_hash, created_at) VALUES
(1, 'purchase', -50.00, 'wallet', 'completed', 'TX_PURCHASE_abcdef123456', '2025-06-18 12:00:00');