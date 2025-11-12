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
                'token_hash' => '3745088275104f752422493a8801f6edf2ea102cec646528b70cd414bba06246',
                'user_id' => 51,
                'blacklisted_at' => '2025-10-05 20:08:04',
                'expires_at' => '2025-10-05 15:16:24',
            ),
            1 => 
            array (
                'id' => 6,
                'token_hash' => 'becb96056263492efede625eb105d0d09824e452a64a4f0d3bd8f4042db50331',
                'user_id' => 51,
                'blacklisted_at' => '2025-10-14 10:41:58',
                'expires_at' => '2025-10-14 06:09:56',
            ),
            2 => 
            array (
                'id' => 7,
                'token_hash' => '4c754d9782b0868ac3aafecae3e71b4b957680b5a7ab1931f0a88bb5ee44e5ad',
                'user_id' => 51,
                'blacklisted_at' => '2025-10-15 13:05:48',
                'expires_at' => '2025-10-15 08:29:54',
            ),
            3 => 
            array (
                'id' => 8,
                'token_hash' => '503e17f8270515c23836309b3a1ce1202ee2d7838c8fec4ffa1df10e13123770',
                'user_id' => 51,
                'blacklisted_at' => '2025-10-15 15:35:03',
                'expires_at' => '2026-10-15 10:04:08',
            ),
            4 => 
            array (
                'id' => 9,
                'token_hash' => '38f64a7c788caa322a636b25b7ee4cf673302cad4fa36449ae3ee17c13d7c2fd',
                'user_id' => 51,
                'blacklisted_at' => '2025-10-15 15:35:23',
                'expires_at' => '2026-10-15 10:05:13',
            ),
            5 => 
            array (
                'id' => 10,
                'token_hash' => '2feebf6dcf3012cc91514e98893ffae581545adcb2b6abec7adfd4d8650bebaf',
                'user_id' => 51,
                'blacklisted_at' => '2025-10-15 15:52:40',
                'expires_at' => '2026-10-15 10:21:48',
            ),
            6 => 
            array (
                'id' => 11,
                'token_hash' => '97caa1e5ba5d1b4d3a714e09f68b636accaee93274c79906dc5a14a8592d219a',
                'user_id' => 51,
                'blacklisted_at' => '2025-10-17 15:02:28',
                'expires_at' => NULL,
            ),
            7 => 
            array (
                'id' => 12,
                'token_hash' => 'bc6002131b3727c2f28c4a0d3ba00b72fa1d64278e0ebfbf63368aa5dd04a320',
                'user_id' => 55,
                'blacklisted_at' => '2025-10-26 21:49:16',
                'expires_at' => NULL,
            ),
        ));
        
        
    }
}