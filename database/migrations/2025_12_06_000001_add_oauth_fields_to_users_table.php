<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Check if users table exists first
        if (!Schema::hasTable('users')) {
            // Users table doesn't exist yet, OAuth fields will be added in create_users_table migration
            return;
        }
        
        // Only add OAuth fields if they don't already exist (for existing databases)
        Schema::table('users', function (Blueprint $table) {
            // Add google_id column if it doesn't exist
            if (!Schema::hasColumn('users', 'google_id')) {
                DB::statement('ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL AFTER password');
            }
            
            // Add linkedin_id column if it doesn't exist
            if (!Schema::hasColumn('users', 'linkedin_id')) {
                DB::statement('ALTER TABLE users ADD COLUMN linkedin_id VARCHAR(255) NULL AFTER google_id');
            }
            
            // Add auth_provider column if it doesn't exist
            if (!Schema::hasColumn('users', 'auth_provider')) {
                DB::statement("ALTER TABLE users ADD COLUMN auth_provider ENUM('email', 'google', 'linkedin') DEFAULT 'email' AFTER linkedin_id");
            }
            
            // Make password nullable (for OAuth users) - only if it's not already nullable
            try {
                $columnInfo = DB::select("SHOW COLUMNS FROM users WHERE Field = 'password'");
                if (!empty($columnInfo) && $columnInfo[0]->Null === 'NO') {
                    DB::statement('ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL');
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        });
        
        // Add indexes if they don't exist
        try {
            $indexes = DB::select("SHOW INDEXES FROM users WHERE Key_name = 'users_google_id_index'");
            if (empty($indexes)) {
                DB::statement('ALTER TABLE users ADD INDEX users_google_id_index (google_id)');
            }
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
        
        try {
            $indexes = DB::select("SHOW INDEXES FROM users WHERE Key_name = 'users_linkedin_id_index'");
            if (empty($indexes)) {
                DB::statement('ALTER TABLE users ADD INDEX users_linkedin_id_index (linkedin_id)');
            }
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
        
        try {
            $indexes = DB::select("SHOW INDEXES FROM users WHERE Key_name = 'users_auth_provider_index'");
            if (empty($indexes)) {
                DB::statement('ALTER TABLE users ADD INDEX users_auth_provider_index (auth_provider)');
            }
        } catch (\Exception $e) {
            // Index might already exist, ignore
        }
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'auth_provider')) {
                $table->dropColumn('auth_provider');
            }
            if (Schema::hasColumn('users', 'linkedin_id')) {
                $table->dropColumn('linkedin_id');
            }
            if (Schema::hasColumn('users', 'google_id')) {
                $table->dropColumn('google_id');
            }
        });
    }
};

