<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BlacklistedTokensTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('blacklisted_tokens')->delete();
        
        \DB::table('blacklisted_tokens')->insert(array (
            0 => 
            array (
                'id' => 1,
                'token_hash' => 'd603b0426c700c903746b16fb642048373054c6ff06d65c34f4b5204a2598a79',
                'user_id' => 3,
                'blacklisted_at' => '2025-10-29 17:01:24',
                'expires_at' => NULL,
            ),
            1 => 
            array (
                'id' => 2,
                'token_hash' => '825428b2d078795345ad855c323ca2f8755319fb6ee255bccdf7d42feb96289c',
                'user_id' => 1,
                'blacklisted_at' => '2025-10-29 21:06:57',
                'expires_at' => NULL,
            ),
            2 => 
            array (
                'id' => 3,
                'token_hash' => 'f5d50cce06e345ac57bdee3b173e135814c2443721aeaa9e2eceaa6b9c2a0340',
                'user_id' => 3,
                'blacklisted_at' => '2025-10-29 21:12:47',
                'expires_at' => NULL,
            ),
            3 => 
            array (
                'id' => 4,
                'token_hash' => 'a4496c4f6cd7202a900675e51818111353aef3e03c3b9bffa41571d9f5b01e4c',
                'user_id' => 2,
                'blacklisted_at' => '2025-10-30 00:32:45',
                'expires_at' => NULL,
            ),
            4 => 
            array (
                'id' => 5,
                'token_hash' => 'eda1ab2321f0c8686f388fdf5d16382a5c7f6952f29a07366a7495cd21653229',
                'user_id' => 3,
                'blacklisted_at' => '2025-10-30 02:25:12',
                'expires_at' => NULL,
            ),
            5 => 
            array (
                'id' => 6,
                'token_hash' => 'c907fcaea352b6e9d570ca885f18ef59f20872ea319472af01030155ba21e61b',
                'user_id' => 1,
                'blacklisted_at' => '2025-10-30 11:51:37',
                'expires_at' => NULL,
            ),
            6 => 
            array (
                'id' => 7,
                'token_hash' => 'cb8a991d74b938ba760b0a1343770e7b790052516f11640d675407d7f3459fd9',
                'user_id' => 2,
                'blacklisted_at' => '2025-10-30 13:54:50',
                'expires_at' => NULL,
            ),
        ));
        
        
    }
}