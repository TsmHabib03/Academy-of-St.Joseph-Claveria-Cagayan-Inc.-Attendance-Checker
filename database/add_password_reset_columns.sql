-- Add password reset columns to admins table
-- Run this SQL query in phpMyAdmin or MySQL Workbench

ALTER TABLE `admins` 
ADD COLUMN `reset_token` VARCHAR(255) NULL DEFAULT NULL AFTER `password`,
ADD COLUMN `reset_token_expires_at` DATETIME NULL DEFAULT NULL AFTER `reset_token`;

-- Verify the changes
DESCRIBE `admins`;
