-- Manual Table Creation for user_margin
-- Run this if you want to create the table manually instead of using migrations

-- Step 1: Drop table if it exists (cleanup from failed migration)
DROP TABLE IF EXISTS `user_margin`;

-- Step 2: Create the user_margin table
CREATE TABLE `user_margin` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'References users.id',
    `margin_percentage` DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Global margin percentage for all countries',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether the margin is currently active',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `user_margin_user_id_unique` (`user_id`),
    KEY `user_margin_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify table was created
SHOW CREATE TABLE `user_margin`;

-- You're done! Now continue with:
-- php artisan sms:update-pricing
-- php artisan cache:clear
