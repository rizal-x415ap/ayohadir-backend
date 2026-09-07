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
     * Seed standardized admin and demo user accounts for both Local and Production.
     */
    public function run(): void
    {
        // 1. Admin Account (admin@ayohadir.id / rizalaza)
        $admin = User::updateOrCreate(
            ['email' => 'admin@ayohadir.id'],
            [
                'name' => 'Admin Ayo Hadir',
                'password' => Hash::make('rizalaza'),
                'role' => 'admin',
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
            ]
        );

        // 2. Demo User Account (demo@ayohadir.id / demo123)
        $demoUser = User::updateOrCreate(
            ['email' => 'demo@ayohadir.id'],
            [
                'name' => 'Rizal Efendi',
                'password' => Hash::make('demo123'),
                'role' => 'user',
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
            ]
        );

        // 3. Initial sample wedding project for the demo user
        $firstTemplate = Template::first();

        $wedding = Wedding::updateOrCreate(
            ['slug' => 'undangan-demo-alya-raka'],
            [
                'user_id' => $demoUser->id,
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

        // Attach design if template exists
        if ($firstTemplate) {
            $wedding->unlockTemplate($firstTemplate);

            if (!$wedding->design && $firstTemplate->schema) {
                Design::updateOrCreate(
                    ['wedding_id' => $wedding->id],
                    [
                        'template_id' => $firstTemplate->id,
                        'schema_version' => $firstTemplate->schema_version ?? 1,
                        'schema' => $firstTemplate->schema,
                        'version' => 1,
                        'published_at' => now(),
                    ]
                );
            }
        }
    }
}
