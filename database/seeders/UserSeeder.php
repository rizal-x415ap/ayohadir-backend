<?php

namespace Database\Seeders;

use App\Models\Design;
use App\Models\Template;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed production-ready admin and demo user accounts.
     */
    public function run(): void
    {
        // 1. Admin Users (Production & Testing aliases)
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD', 'Admin123!');

        $adminCom = User::updateOrCreate(
            ['email' => 'admin@ayohadir.com'],
            [
                'name' => 'Admin Ayo Hadir',
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@ayohadir.test'],
            [
                'name' => 'Admin Ayo Hadir (Test)',
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
            ]
        );

        // 2. Demo Users (Production & Testing aliases)
        $demoPassword = env('DEMO_DEFAULT_PASSWORD', 'Demo123!');

        $demoUserCom = User::updateOrCreate(
            ['email' => 'demo@ayohadir.com'],
            [
                'name' => 'Alya Putri & Raka Pratama',
                'password' => Hash::make($demoPassword),
                'role' => 'user',
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
            ]
        );

        $demoUserTest = User::updateOrCreate(
            ['email' => 'demo@ayohadir.test'],
            [
                'name' => 'Pengguna Demo (Test)',
                'password' => Hash::make($demoPassword),
                'role' => 'user',
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
            ]
        );

        // 3. Create initial demo wedding for demo users so dashboard & editor are ready
        $firstTemplate = Template::first();

        foreach ([$demoUserCom, $demoUserTest] as $user) {
            $weddingSlug = $user->email === 'demo@ayohadir.com' ? 'undangan-demo-alya-raka' : 'demo-alya-raka';

            $wedding = Wedding::updateOrCreate(
                ['slug' => $weddingSlug],
                [
                    'user_id' => $user->id,
                    'applied_template_id' => $firstTemplate?->id,
                    'bride_name' => 'Alya Putri',
                    'groom_name' => 'Raka Pratama',
                    'bride_parents' => 'Bpk. Hendra Wijaya & Ibu Ratna Sari',
                    'groom_parents' => 'Bpk. Bambang Pratama & Ibu Sri Wahyuni',
                    'wedding_date' => '2026-11-15',
                    'wedding_time' => '09:00:00',
                    'venue_name' => 'Gedung Serbaguna Nusantara',
                    'venue_address' => 'Jl. MH Thamrin No. 1, Jakarta Pusat',
                    'venue_map_url' => 'https://maps.google.com/?q=-6.1955,106.8233',
                    'rsvp_enabled' => true,
                    'rsvp_deadline' => '2026-11-01',
                    'wishes_enabled' => true,
                    'status' => 'published',
                    'published_at' => now(),
                    'is_premium_unlocked' => true,
                    'custom_content' => [
                        'coverGreeting' => 'The Wedding of',
                        'loveStory' => 'Pertemuan pertama kami berawal di bangku kuliah pada tahun 2019, hingga akhirnya kami memutuskan untuk melangkah bersama menuju babak baru kehidupan pernikahan.',
                        'akadTime' => '08:00 WIB',
                        'resepsiTime' => '11:00 - 14:00 WIB',
                        'bankAccounts' => [
                            ['bank' => 'BCA', 'accountNumber' => '1234567890', 'accountHolder' => 'Alya Putri'],
                            ['bank' => 'Mandiri', 'accountNumber' => '9876543210', 'accountHolder' => 'Raka Pratama'],
                        ],
                    ],
                ]
            );

            // Grant template license for this wedding
            if ($firstTemplate) {
                $wedding->unlockTemplate($firstTemplate);

                // Create Design instance if not exists
                if (!$wedding->design && $firstTemplate->schema) {
                    Design::updateOrCreate(
                        ['wedding_id' => $wedding->id],
                        [
                            'template_id' => $firstTemplate->id,
                            'schema_version' => 1,
                            'schema' => $firstTemplate->schema,
                            'version' => 1,
                            'published_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
