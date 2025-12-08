-- Add OAuth fields to users table
-- Run this SQL in phpMyAdmin

-- Check and add google_id column
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) NULL AFTER password;

-- Check and add linkedin_id column  
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS linkedin_id VARCHAR(255) NULL AFTER google_id;

-- Check and add auth_provider column
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS auth_provider ENUM('email', 'google', 'linkedin') DEFAULT 'email' AFTER linkedin_id;

-- Make password nullable (for OAuth users)
ALTER TABLE users 
MODIFY COLUMN password VARCHAR(255) NULL;

-- Add indexes for faster lookups
ALTER TABLE users 
ADD INDEX IF NOT EXISTS idx_google_id (google_id);

ALTER TABLE users 
ADD INDEX IF NOT EXISTS idx_linkedin_id (linkedin_id);

ALTER TABLE users 
ADD INDEX IF NOT EXISTS idx_auth_provider (auth_provider);

