CREATE TABLE `users` (
  `user_id` int PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(50) UNIQUE NOT NULL,
  `email` varchar(100) UNIQUE NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone_number` varchar(20),
  `referral_code` varchar(20) UNIQUE,
  `referred_by` int,
  `account_status` enum(pending,active,suspended) NOT NULL DEFAULT 'pending',
  `verification_token` varchar(100),
  `verification_token_expires` datetime,
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'ON UPDATE CURRENT_TIMESTAMP'
);

CREATE TABLE `user_balances` (
  `balance_id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int UNIQUE NOT NULL,
  `available_balance` decimal(10,2) NOT NULL DEFAULT 0,
  `pending_balance` decimal(10,2) NOT NULL DEFAULT 0,
  `total_withdrawn` decimal(10,2) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'ON UPDATE CURRENT_TIMESTAMP'
);

CREATE TABLE `mining_packages` (
  `package_id` int PRIMARY KEY AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `daily_profit` decimal(10,2) NOT NULL,
  `daily_return_percentage` decimal(5,2) NOT NULL,
  `duration_days` int NOT NULL,
  `is_active` boolean NOT NULL DEFAULT true,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'ON UPDATE CURRENT_TIMESTAMP'
);

CREATE TABLE `user_miners` (
  `miner_id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `package_id` int NOT NULL,
  `purchase_date` datetime NOT NULL,
  `status` enum(active,completed,expired) NOT NULL DEFAULT 'active',
  `days_remaining` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'ON UPDATE CURRENT_TIMESTAMP'
);

CREATE TABLE `transactions` (
  `transaction_id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` enum(deposit,withdrawal,earning,purchase,referral) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `method` varchar(50),
  `status` enum(pending,completed,failed,cancelled) NOT NULL DEFAULT 'pending',
  `transaction_hash` varchar(100),
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'ON UPDATE CURRENT_TIMESTAMP'
);

CREATE TABLE `referrals` (
  `referral_id` int PRIMARY KEY AUTO_INCREMENT,
  `referrer_id` int NOT NULL,
  `referred_user_id` int NOT NULL,
  `commission_earned` decimal(10,2) NOT NULL DEFAULT 0,
  `status` enum(active,inactive) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'ON UPDATE CURRENT_TIMESTAMP'
);

CREATE TABLE `security_settings` (
  `setting_id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int UNIQUE NOT NULL,
  `two_factor_enabled` boolean NOT NULL DEFAULT false,
  `login_alerts` boolean NOT NULL DEFAULT true,
  `withdrawal_confirmations` boolean NOT NULL DEFAULT true,
  `referral_notifications` boolean NOT NULL DEFAULT true,
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'ON UPDATE CURRENT_TIMESTAMP'
);

CREATE TABLE `login_activity` (
  `activity_id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `device_type` varchar(20),
  `browser` varchar(100),
  `ip_address` varchar(45),
  `location` varchar(100),
  `action` varchar(20) DEFAULT 'login',
  `status` varchar(20) DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP)
);

CREATE TABLE `contact_messages` (
  `message_id` int PRIMARY KEY AUTO_INCREMENT,
  `user_id` int,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `status` enum(new,read,replied,closed) NOT NULL DEFAULT 'new',
  `admin_reply` text,
  `created_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP),
  `updated_at` timestamp NOT NULL DEFAULT (CURRENT_TIMESTAMP) COMMENT 'ON UPDATE CURRENT_TIMESTAMP'
);

CREATE INDEX `idx_email` ON `users` (`email`);

CREATE INDEX `idx_referral_code` ON `users` (`referral_code`);

CREATE INDEX `idx_referred_by` ON `users` (`referred_by`);

CREATE INDEX `idx_account_status` ON `users` (`account_status`);

CREATE INDEX `idx_users_created_date` ON `users` (`created_at`);

CREATE INDEX `idx_is_active` ON `mining_packages` (`is_active`);

CREATE INDEX `idx_price` ON `mining_packages` (`price`);

CREATE INDEX `idx_user_status` ON `user_miners` (`user_id`, `status`);

CREATE INDEX `idx_package` ON `user_miners` (`package_id`);

CREATE INDEX `idx_purchase_date` ON `user_miners` (`purchase_date`);

CREATE INDEX `idx_user_miners_status_date` ON `user_miners` (`status`, `purchase_date`);

CREATE INDEX `idx_user_type` ON `transactions` (`user_id`, `type`);

CREATE INDEX `idx_status` ON `transactions` (`status`);

CREATE INDEX `idx_created_at` ON `transactions` (`created_at`);

CREATE INDEX `idx_transaction_hash` ON `transactions` (`transaction_hash`);

CREATE INDEX `idx_transactions_user_type_status` ON `transactions` (`user_id`, `type`, `status`);

CREATE INDEX `idx_transactions_created_date` ON `transactions` (`created_at`);

CREATE UNIQUE INDEX `uk_referral` ON `referrals` (`referrer_id`, `referred_user_id`);

CREATE INDEX `idx_referrer` ON `referrals` (`referrer_id`);

CREATE INDEX `idx_referred_user` ON `referrals` (`referred_user_id`);

CREATE INDEX `idx_status` ON `referrals` (`status`);

CREATE INDEX `idx_user_activity` ON `login_activity` (`user_id`, `created_at`);

CREATE INDEX `idx_ip_address` ON `login_activity` (`ip_address`);

CREATE INDEX `idx_user_id` ON `contact_messages` (`user_id`);

CREATE INDEX `idx_status` ON `contact_messages` (`status`);

CREATE INDEX `idx_created_at` ON `contact_messages` (`created_at`);

CREATE INDEX `idx_contact_messages_status_date` ON `contact_messages` (`status`, `created_at`);

ALTER TABLE `users` ADD FOREIGN KEY (`user_id`) REFERENCES `user_balances` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `users` ADD FOREIGN KEY (`user_id`) REFERENCES `user_miners` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `users` ADD FOREIGN KEY (`user_id`) REFERENCES `transactions` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `users` ADD FOREIGN KEY (`user_id`) REFERENCES `referrals` (`referrer_id`) ON DELETE CASCADE;

ALTER TABLE `users` ADD FOREIGN KEY (`user_id`) REFERENCES `referrals` (`referred_user_id`) ON DELETE CASCADE;

ALTER TABLE `users` ADD FOREIGN KEY (`user_id`) REFERENCES `security_settings` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `users` ADD FOREIGN KEY (`user_id`) REFERENCES `login_activity` (`user_id`) ON DELETE CASCADE;

ALTER TABLE `users` ADD FOREIGN KEY (`user_id`) REFERENCES `contact_messages` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `users` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`referred_by`) ON DELETE SET NULL;

ALTER TABLE `mining_packages` ADD FOREIGN KEY (`package_id`) REFERENCES `user_miners` (`package_id`) ON DELETE CASCADE;
