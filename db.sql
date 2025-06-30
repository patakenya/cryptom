
-- Create Database
CREATE DATABASE IF NOT EXISTS cryptominer_erp;
USE cryptominer_erp;

-- Admins Table
CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique username for the admin',
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hashed password for security',
    full_name VARCHAR(255) NOT NULL COMMENT 'Admin’s full name',
    account_status ENUM('pending', 'active', 'suspended') DEFAULT 'pending' COMMENT 'Admin account status',
    verification_token VARCHAR(255) DEFAULT NULL COMMENT 'Token for email verification',
    verification_token_expires DATETIME DEFAULT NULL COMMENT 'Expiration time for verification token',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email)
) ENGINE=InnoDB COMMENT='Stores admin account details';

-- Users Table
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique username for the user',
    full_name VARCHAR(255) NOT NULL COMMENT 'User’s full name',
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL COMMENT 'Hashed password for security',
    referral_code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique referral code for the user',
    referred_by INT DEFAULT NULL COMMENT 'ID of the user who referred this user',
    account_status ENUM('pending', 'active', 'suspended') DEFAULT 'pending' COMMENT 'User account status',
    verification_token VARCHAR(255) DEFAULT NULL COMMENT 'Token for email verification',
    verification_token_expires DATETIME DEFAULT NULL COMMENT 'Expiration time for verification token',
    status ENUM('Free', 'Premium') DEFAULT 'Free' COMMENT 'User account type',
    referrals_count INT DEFAULT 0 COMMENT 'Number of successful referrals',
    two_factor_secret VARCHAR(100) DEFAULT NULL COMMENT 'Secret for two-factor authentication',
    two_factor_enabled BOOLEAN DEFAULT FALSE COMMENT 'Two-factor authentication status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referred_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_referral_code (referral_code)
) ENGINE=InnoDB COMMENT='Stores user account details';

-- User Balances Table
CREATE TABLE user_balances (
    user_id INT PRIMARY KEY,
    available_balance DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Available balance in USD',
    pending_balance DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Pending balance in USD',
    total_withdrawn DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Total withdrawn amount in USD',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB COMMENT='Stores user balance details';

-- Mining Packages Table
CREATE TABLE mining_packages (
    package_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Package name (e.g., Starter Miner)',
    price DECIMAL(15,2) NOT NULL COMMENT 'One-time purchase price in USD',
    daily_profit DECIMAL(15,2) NOT NULL COMMENT 'Daily profit in USD',
    daily_return_percentage DECIMAL(5,2) NOT NULL COMMENT 'Daily return percentage (e.g., 9.00)',
    duration_days INT NOT NULL COMMENT 'Duration of mining package in days',
    total_return DECIMAL(15,2) NOT NULL COMMENT 'Total return after duration in USD',
    is_popular BOOLEAN DEFAULT FALSE COMMENT 'Flag for popular packages',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Flag for active packages',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_price (price)
) ENGINE=InnoDB COMMENT='Stores available mining packages';

-- User Miners Table
CREATE TABLE user_miners (
    miner_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    package_id INT NOT NULL,
    purchase_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time of purchase',
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active' COMMENT 'Miner status',
    days_remaining INT NOT NULL COMMENT 'Days remaining for the miner',
    duration_days INT NOT NULL COMMENT 'Total duration in days',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES mining_packages(package_id) ON DELETE RESTRICT,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB COMMENT='Stores user-purchased miners';

-- Referrals Table
CREATE TABLE referrals (
    referral_id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL COMMENT 'ID of the user who referred',
    referred_user_id INT NOT NULL COMMENT 'ID of the referred user',
    joined_date DATE NOT NULL COMMENT 'Date the referred user joined',
    investment DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Total investment by referred user in USD',
    miners_count INT DEFAULT 0 COMMENT 'Number of miners purchased by referred user',
    commission_earned DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Commission earned by referrer in USD',
    status ENUM('active', 'inactive') DEFAULT 'active' COMMENT 'Referral status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE (referrer_id, referred_user_id),
    INDEX idx_referrer_id (referrer_id),
    INDEX idx_referred_user_id (referred_user_id)
) ENGINE=InnoDB COMMENT='Referral relationships and commissions';

-- Transactions Table
CREATE TABLE transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal', 'purchase', 'earning', 'referral') NOT NULL COMMENT 'Transaction type',
    amount DECIMAL(15,2) NOT NULL COMMENT 'Transaction amount in USD (negative for withdrawals/purchases)',
    method ENUM('USDT', 'BTC', 'ETH', 'MPESA', 'wallet', 'system') NOT NULL COMMENT 'Payment method',
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending' COMMENT 'Transaction status',
    transaction_hash VARCHAR(100) DEFAULT NULL COMMENT 'Transaction hash or identifier',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id_type (user_id, type),
    INDEX idx_status (status),
    INDEX idx_transaction_hash (transaction_hash)
) ENGINE=InnoDB COMMENT='Stores all financial transactions';

-- Withdrawal Requests Table
CREATE TABLE withdrawal_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL COMMENT 'Withdrawal amount in USD',
    payment_method ENUM('USDT', 'BTC', 'ETH', 'MPESA') NOT NULL COMMENT 'Payment method for withdrawal',
    wallet_address VARCHAR(255) NOT NULL COMMENT 'Wallet address for withdrawal',
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' COMMENT 'Withdrawal request status',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id_status (user_id, status)
) ENGINE=InnoDB COMMENT='Stores withdrawal requests';

-- Security Settings Table
CREATE TABLE security_settings (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
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
    UNIQUE (user_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB COMMENT='Stores user security and notification settings';

-- Login Activity Table
CREATE TABLE login_activity (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_type VARCHAR(50) NOT NULL COMMENT 'Device type (e.g., Computer, Mobile)',
    browser VARCHAR(100) NOT NULL COMMENT 'Browser or app used',
    ip_address VARCHAR(45) NOT NULL COMMENT 'IP address of the device',
    location VARCHAR(100) DEFAULT 'Unknown' COMMENT 'Location of login (e.g., New York, USA)',
    status ENUM('Active', 'Logged In', 'Logged Out', 'Failed') NOT NULL COMMENT 'Session or login attempt status',
    login_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB COMMENT='Stores user login activity';

-- Trigger to update referrals_count in users table
DELIMITER //
CREATE TRIGGER update_referrals_count_after_insert
AFTER INSERT ON referrals
FOR EACH ROW
BEGIN
    UPDATE users
    SET referrals_count = (
        SELECT COUNT(*) 
        FROM referrals 
        WHERE referrer_id = NEW.referrer_id AND status = 'active'
    )
    WHERE user_id = NEW.referrer_id;
END//

CREATE TRIGGER update_referrals_count_after_update
AFTER UPDATE ON referrals
FOR EACH ROW
BEGIN
    UPDATE users
    SET referrals_count = (
        SELECT COUNT(*) 
        FROM referrals 
        WHERE referrer_id = NEW.referrer_id AND status = 'active'
    )
    WHERE user_id = NEW.referrer_id;
END//

CREATE TRIGGER update_referrals_count_after_delete
AFTER DELETE ON referrals
FOR EACH ROW
BEGIN
    UPDATE users
    SET referrals_count = (
        SELECT COUNT(*) 
        FROM referrals 
        WHERE referrer_id = OLD.referrer_id AND status = 'active'
    )
    WHERE user_id = OLD.referrer_id;
END//
DELIMITER ;

-- Insert Sample Data
-- Sample Admins
INSERT INTO admins (admin_id, username, email, password_hash, full_name, account_status, created_at)
VALUES (1, 'admin', 'admin@cryptominer.com', '$2y$10$examplehashedpassword1234567890', 'Admin One', 'active', '2025-06-01 00:00:00');

-- Sample Users
INSERT INTO users (user_id, username, full_name, email, password_hash, referral_code, referred_by, account_status, status, referrals_count, created_at)
VALUES
    (1, 'michael_chen', 'Michael Chen', 'michael.chen@example.com', '$2y$10$examplehashedpassword1234567890', 'michael85', NULL, 'active', 'Premium', 2, '2025-06-18 00:00:00'),
    (2, 'jane_doe', 'Jane Doe', 'jane.doe@example.com', '$2y$10$examplehashedpassword1234567890', 'jane1234', 1, 'active', 'Free', 0, '2025-06-19 00:00:00');

-- Sample User Balances
INSERT INTO user_balances (user_id, available_balance, pending_balance, total_withdrawn)
VALUES
    (1, 1245.78, 87.50, 3450.00),
    (2, 0.00, 0.00, 0.00);

-- Sample Security Settings
INSERT INTO security_settings (user_id, two_factor_enabled, login_alerts, withdrawal_confirmations, referral_notifications)
VALUES
    (1, FALSE, TRUE, TRUE, TRUE),
    (2, FALSE, TRUE, TRUE, TRUE);

-- Sample Mining Packages
INSERT INTO mining_packages (package_id, name, price, daily_profit, daily_return_percentage, duration_days, total_return, is_popular, is_active)
VALUES
    (1, 'Starter Miner', 10.00, 0.90, 9.00, 20, 28.00, FALSE, TRUE),
    (2, 'Basic Miner', 25.00, 2.25, 9.00, 20, 70.00, FALSE, TRUE),
    (3, 'Standard Miner', 50.00, 4.50, 9.00, 20, 140.00, TRUE, TRUE),
    (4, 'Advanced Miner', 75.00, 6.75, 9.00, 20, 210.00, FALSE, TRUE),
    (5, 'Premium Miner', 120.00, 10.80, 9.00, 20, 336.00, FALSE, TRUE);

-- Sample User Miners
INSERT INTO user_miners (miner_id, user_id, package_id, purchase_date, status, days_remaining, duration_days)
VALUES
    (1, 1, 3, '2025-06-18 00:00:00', 'active', 15, 20),
    (2, 1, 5, '2025-05-28 00:00:00', 'active', 10, 20),
    (3, 1, 2, '2025-06-10 00:00:00', 'active', 4, 20),
    (4, 1, 4, '2025-05-25 00:00:00', 'expired', 0, 20);

-- Sample Referrals
INSERT INTO referrals (referral_id, referrer_id, referred_user_id, joined_date, investment, miners_count, commission_earned, status)
VALUES
    (1, 1, 2, '2025-06-19', 0.00, 0, 0.00, 'active');

-- Sample Transactions
INSERT INTO transactions (transaction_id, user_id, type, amount, method, status, transaction_hash, created_at)
VALUES
    (1, 1, 'purchase', -50.00, 'wallet', 'completed', 'TX_PURCHASE_abcdef123456', '2025-06-18 12:00:00'),
    (2, 1, 'deposit', 120.00, 'USDT', 'completed', 'TX_DEPOSIT_0x3a4b7f2', '2025-06-12 10:23:00'),
    (3, 1, 'earning', 52.20, 'system', 'completed', 'TX_EARNING_daily', '2025-06-13 00:00:00'),
    (4, 1, 'referral', 70.00, 'system', 'completed', 'TX_REFERRAL_emilyj', '2025-06-10 15:45:00'),
    (5, 1, 'withdrawal', -500.00, 'USDT', 'completed', 'TX_WITHDRAWAL_0x7b2c9e4', '2025-06-05 14:12:00'),
    (6, 1, 'withdrawal', -250.00, 'USDT', 'pending', 'TX_WITHDRAWAL_pending', '2025-06-14 09:30:00');
