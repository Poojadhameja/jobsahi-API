-- Add OAuth fields to users table
-- Run this SQL in phpMyAdmin (one by one if errors occur)

-- Step 1: Add google_id column
ALTER TABLE users 
ADD COLUMN google_id VARCHAR(255) NULL AFTER password;

-- Step 2: Add linkedin_id column  
ALTER TABLE users 
ADD COLUMN linkedin_id VARCHAR(255) NULL AFTER google_id;

-- Step 3: Add auth_provider column
ALTER TABLE users 
ADD COLUMN auth_provider ENUM('email', 'google', 'linkedin') DEFAULT 'email' AFTER linkedin_id;

-- Step 4: Make password nullable (for OAuth users)
ALTER TABLE users 
MODIFY COLUMN password VARCHAR(255) NULL;

-- Step 5: Add indexes (optional but recommended)
ALTER TABLE users 
ADD INDEX idx_google_id (google_id);

ALTER TABLE users 
ADD INDEX idx_linkedin_id (linkedin_id);

ALTER TABLE users 
ADD INDEX idx_auth_provider (auth_provider);

