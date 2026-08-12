-- ============================================================================
-- SQL Script: Migrate from Old User Rates to New Global Margin System
-- ============================================================================
-- Run this AFTER creating the new user_margin table
-- This helps migrate existing user_rates data to the new global margin system
-- ============================================================================

-- Step 1: Calculate average margin percentage for each user from existing rates
-- ============================================================================
-- This calculates what margin percentage each user was using on average
-- Formula: ((user_rate - base_cost) / base_cost) * 100

INSERT INTO user_margin (user_id, margin_percentage, is_active, created_at, updated_at)
SELECT 
    ur.user_id,
    ROUND(AVG(
        CASE 
            WHEN c.cost_price_gbp > 0 AND c.cost_price_gbp IS NOT NULL 
            THEN ((ur.rate - c.cost_price_gbp) / c.cost_price_gbp * 100)
            ELSE 0 
        END
    ), 2) as margin_percentage,
    1 as is_active,
    NOW() as created_at,
    NOW() as updated_at
FROM user_rates ur
INNER JOIN country c ON ur.country_id = c.id
WHERE c.cost_price_gbp > 0 
  AND c.cost_price_gbp IS NOT NULL
  AND ur.status = 1
GROUP BY ur.user_id
ON DUPLICATE KEY UPDATE 
    margin_percentage = VALUES(margin_percentage),
    updated_at = NOW();

-- ============================================================================
-- Step 2: Verify the migration
-- ============================================================================
-- Check how many users were migrated
SELECT 
    'Total users with margin set' as description,
    COUNT(*) as count 
FROM user_margin;

-- Check margin distribution
SELECT 
    CONCAT(FLOOR(margin_percentage / 10) * 10, '-', FLOOR(margin_percentage / 10) * 10 + 10, '%') as margin_range,
    COUNT(*) as user_count
FROM user_margin
GROUP BY FLOOR(margin_percentage / 10)
ORDER BY FLOOR(margin_percentage / 10);

-- ============================================================================
-- Step 3: Compare old vs new rates for verification
-- ============================================================================
-- This shows the difference between old individual rates and new calculated rates
SELECT 
    u.id as user_id,
    u.email,
    c.name as country_name,
    c.cost_price_gbp as base_cost_gbp,
    ur.rate as old_user_rate,
    um.margin_percentage,
    ROUND(c.cost_price_gbp + (c.cost_price_gbp * um.margin_percentage / 100), 6) as new_calculated_rate,
    ROUND(ur.rate - (c.cost_price_gbp + (c.cost_price_gbp * um.margin_percentage / 100)), 6) as difference
FROM users u
INNER JOIN user_margin um ON u.id = um.user_id
INNER JOIN user_rates ur ON u.id = ur.user_id
INNER JOIN country c ON ur.country_id = c.id
WHERE ur.status = 1
  AND c.cost_price_gbp > 0
LIMIT 20;

-- ============================================================================
-- Step 4: Backup old user_rates table (OPTIONAL)
-- ============================================================================
-- Create a backup of the old table before you delete it

-- CREATE TABLE user_rates_backup_20251129 AS 
-- SELECT * FROM user_rates;

-- Verify backup
-- SELECT COUNT(*) FROM user_rates_backup_20251129;

-- ============================================================================
-- Step 5: Clean up old data (ONLY AFTER VERIFYING NEW SYSTEM WORKS)
-- ============================================================================
-- DANGER: Only run this after you've fully tested the new system!
-- Uncomment when ready:

-- -- Disable old rates (don't delete yet, just disable)
-- UPDATE user_rates SET status = 0;

-- -- After 30 days of successful operation, you can drop the old table:
-- -- DROP TABLE user_rates;
-- -- DROP TABLE user_rates_backup_20251129;

-- ============================================================================
-- Useful Queries
-- ============================================================================

-- Get user margin info
-- SELECT * FROM user_margin WHERE user_id = 1;

-- Calculate rate for a specific user and country
-- SELECT 
--     c.name,
--     c.cost_price_gbp,
--     um.margin_percentage,
--     ROUND(c.cost_price_gbp + (c.cost_price_gbp * um.margin_percentage / 100), 6) as user_rate
-- FROM country c
-- CROSS JOIN user_margin um
-- WHERE um.user_id = 1
--   AND c.cost_price_gbp > 0
-- ORDER BY c.name;

-- Find users without margin set
-- SELECT u.id, u.email
-- FROM users u
-- LEFT JOIN user_margin um ON u.id = um.user_id
-- WHERE um.id IS NULL;

-- Set default margin for users without one
-- INSERT INTO user_margin (user_id, margin_percentage, is_active, created_at, updated_at)
-- SELECT 
--     u.id,
--     20.00 as margin_percentage,
--     1 as is_active,
--     NOW() as created_at,
--     NOW() as updated_at
-- FROM users u
-- LEFT JOIN user_margin um ON u.id = um.user_id
-- WHERE um.id IS NULL;

-- ============================================================================
-- NOTES:
-- ============================================================================
-- 1. Always backup before running migration scripts
-- 2. Test on staging/development environment first
-- 3. Verify calculations match expected results
-- 4. Keep old data for at least 30 days before deletion
-- 5. Monitor system performance after migration
-- ============================================================================
