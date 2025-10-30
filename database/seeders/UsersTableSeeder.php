<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('users')->delete();
        
        \DB::table('users')->insert(array (
            0 => 
            array (
                'id' => 1,
                'user_name' => 'Himanshu Admin',
                'email' => 'himanshu.adm@gmail.com',
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'role' => 'admin',
                'phone_number' => '9876543210',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            1 => 
            array (
                'id' => 2,
                'user_name' => 'Himanshu Institute',
                'email' => 'himanshu.ins@gmail.com',
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'role' => 'institute',
                'phone_number' => '9876543211',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            2 => 
            array (
                'id' => 3,
                'user_name' => 'Himanshu Recruiter',
                'email' => 'himanshu.rec@gmail.com',
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'role' => 'recruiter',
                'phone_number' => '9876543212',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            3 => 
            array (
                'id' => 4,
                'user_name' => 'Himanshu Student',
                'email' => 'himanshu.stu@gmail.com',
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'role' => 'student',
                'phone_number' => '9876543213',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            4 => 
            array (
                'id' => 5,
                'user_name' => 'Pooja Dhameja',
                'email' => 'poojadhameja15@gmail.com',
                'password' => '$2y$10$onmgA.FGdSjm8.a/ZJiuP.WgF56WPOJ3DvHNBJvBGOzce4P7rN5my',
                'role' => 'student',
                'phone_number' => '9876543214',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            5 => 
            array (
                'id' => 6,
                'user_name' => 'Pooj Dhameja',
                'email' => 'poojadhameja123@gmail.com',
                'password' => '$2y$10$Q.Onj8vMwCTpfLXPW9u5AOUWuPnTRn4smlRRyFA16Xc4QlZuKPqKy',
                'role' => 'admin',
                'phone_number' => '9825426785',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            6 => 
            array (
                'id' => 7,
                'user_name' => 'John Doe Updated',
                'email' => 'poojadhameja14@gmail.com',
                'password' => '$2y$10$kMU2gpuGX2dWEL9rgsE5hu7iHdcGV1oDMpWYINQLgaRmCGHwMuDeO',
                'role' => 'admin',
                'phone_number' => '9648148151',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            7 => 
            array (
                'id' => 9,
                'user_name' => 'Amit Sharma',
                'email' => 'amit.sharma@email.com',
                'password' => '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...',
                'role' => 'student',
                'phone_number' => '9876543215',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            8 => 
            array (
                'id' => 10,
                'user_name' => 'Kavya Patel',
                'email' => 'kavya.patel@email.com',
                'password' => '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...',
                'role' => 'student',
                'phone_number' => '9876543216',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            9 => 
            array (
                'id' => 11,
                'user_name' => 'Arjun Reddy',
                'email' => 'arjun.reddy@email.com',
                'password' => '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...',
                'role' => 'student',
                'phone_number' => '9876543217',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            10 => 
            array (
                'id' => 12,
                'user_name' => 'Neha Gupta',
                'email' => 'neha.gupta@email.com',
                'password' => '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...',
                'role' => 'student',
                'phone_number' => '9876543218',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            11 => 
            array (
                'id' => 13,
                'user_name' => 'Tech Institute Delhi',
                'email' => 'admin@techinstituteDelhi.com',
                'password' => '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...',
                'role' => 'institute',
                'phone_number' => '9876543219',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            12 => 
            array (
                'id' => 14,
                'user_name' => 'NIIT Mumbai',
                'email' => 'contact@niitmumbai.com',
                'password' => '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...',
                'role' => 'institute',
                'phone_number' => '9876543220',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            13 => 
            array (
                'id' => 15,
                'user_name' => 'Code Academy Bangalore',
                'email' => 'info@codeacademybangalore.com',
                'password' => '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...',
                'role' => 'institute',
                'phone_number' => '9876543221',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            14 => 
            array (
                'id' => 16,
                'user_name' => 'TechCorp Solutions',
                'email' => 'hr@techcorp.com',
                'password' => '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...',
                'role' => 'recruiter',
                'phone_number' => '9876543222',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            15 => 
            array (
                'id' => 17,
                'user_name' => 'InnovateLabs',
                'email' => 'careers@innovatelabs.com',
                'password' => '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...',
                'role' => 'recruiter',
                'phone_number' => '9876543223',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            16 => 
            array (
                'id' => 18,
                'user_name' => 'Pooj Dhameja',
                'email' => 'poojadhameja12@gmail.com',
                'password' => '$2y$10$lrCRP1e8FS8eRJzATfPQzeG3YEsSgLzGu.WqL4Bf8gvrAIU8nRQNC',
                'role' => 'institute',
                'phone_number' => '9825426786',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            17 => 
            array (
                'id' => 19,
                'user_name' => 'Bhumika Jain',
                'email' => 'jbhumika71@gmail.com',
                'password' => '$2y$10$qPigtHpy6Hso8788IjFi8e5gT8lZspLB2zZyddWpky0ftp6ddBBxy',
                'role' => 'recruiter',
                'phone_number' => '9876543224',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            18 => 
            array (
                'id' => 20,
                'user_name' => 'Pooj Dhameja',
                'email' => 'poojadhameja11@gmail.com',
                'password' => '$2y$10$018zQMF3tgN.AlMuk58heu3FhdtpdqsANVzo.lXfkIgkmU6lQhgMW',
                'role' => 'institute',
                'phone_number' => '9598632451',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            19 => 
            array (
                'id' => 21,
                'user_name' => 'himanshu shrirang',
                'email' => 'himanshushrirang@gmail.com',
                'password' => '$2y$10$v2p8Nd66zVuUVe/..9UsxOp8gLxS/mdnvreu6sKFkuhCo10qPvUue',
                'role' => 'institute',
                'phone_number' => '8818986352',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            20 => 
            array (
                'id' => 22,
                'user_name' => 'himanshu shrirang',
                'email' => 'himanshushrirang1@gmail.com',
                'password' => '$2y$10$ECNNgYrtwja98cypQJovFeOgPvBoPk.2otQUAXHvqgcXuXQkkqTEe',
                'role' => 'institute',
                'phone_number' => '8818986353',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            21 => 
            array (
                'id' => 28,
                'user_name' => 'himanshu shrirang',
                'email' => 'himanshushrirang3@gmail.com',
                'password' => '$2y$10$ezn7/hePJxi7Vw7wb8PakOVfANuVOgESjB84GKJdC.yTT5ZH2RX3y',
                'role' => 'institute',
                'phone_number' => '8818986355',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            22 => 
            array (
                'id' => 48,
                'user_name' => 'pooja dhameja',
                'email' => 'poojadhameja19@gmail.com',
                'password' => '$2y$10$WiVt17oc1JMy29X4XrHbdO0HmZ.xhL.Ymzdy/C2xtOfDR5nBDQGhm',
                'role' => 'student',
                'phone_number' => '7378862436',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            23 => 
            array (
                'id' => 49,
                'user_name' => 'pooja dhameja',
                'email' => 'poojadhameja20@gmail.com',
                'password' => '$2y$10$3Rqs7eT4vHi/GCk4mLB09ube2RQF1cedrt1lgSA1az3nTEoNqjcvy',
                'role' => 'recruiter',
                'phone_number' => '7378863436',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            24 => 
            array (
                'id' => 50,
                'user_name' => 'Bhumika Jain',
                'email' => 'jbhumika45@gmail.com',
                'password' => '$2y$10$V4nP0VzzaIpmdgRoZUSs6uudn.vN1qjaUI7CnJMpXGEdo4sZwVxuu',
                'role' => 'institute',
                'phone_number' => '7678864436',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 12:35:19',
            ),
            25 => 
            array (
                'id' => 51,
                'user_name' => 'Himanshu Shrirang',
                'email' => 'Stu@gmail.com',
                'password' => '$2y$10$uREeWImAl1hKwu0Ao7lod.pxmxy4tqezndsWepiycFXcmoTJQXT6e',
                'role' => 'student',
                'phone_number' => '2234567892',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => '2025-10-15 16:00:00',
            ),
            26 => 
            array (
                'id' => 55,
                'user_name' => 'Himanshu Shrirang',
                'email' => 'himanshu.app@gmail.com',
                'password' => '$2y$10$U1hQiogdyHJHDBs.kNnNmuZH5fTsvfI02WzwCfpOv8tQHhiLwYUry',
                'role' => 'student',
                'phone_number' => '8787878787',
                'is_verified' => 1,
                'status' => 'active',
                'last_activity' => NULL,
            ),
        ));
        
        
    }
}