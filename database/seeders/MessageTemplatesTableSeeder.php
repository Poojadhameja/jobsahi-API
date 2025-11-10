<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MessageTemplatesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('message_templates')->delete();
        
        
        
    }
}