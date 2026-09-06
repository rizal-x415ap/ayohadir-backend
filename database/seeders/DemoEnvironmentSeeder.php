<?php

namespace Database\Seeders;

use App\Models\Design;
use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\Invitation;
use App\Models\Media;
use App\Models\PageView;
use App\Models\Rsvp;
use App\Models\Template;
use App\Models\User;
use App\Models\Wedding;
use App\Services\PublishingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoEnvironmentSeeder extends Seeder
{
    /**
     * Run the demo environment seeder safely (non-production only).
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoEnvironmentSeeder cannot be executed in production environment.');
            return;
        }

        // 1. Seed Master Templates first
        $this->call(TemplateSeeder::class);

        // 2. Admin Demo User
        $admin = User::updateOrCreate(
            ['email' => 'admin@ayohadir.test'],
            [
                'name' => 'Admin Ayo Hadir',
                'password' => Hash::make('Admin123!'),
                'role' => 'admin',
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
            ]
        );

        // 3. Global Assets (Safe Vector SVGs)
        $globalAssets = [
            ['name' => 'Floral Corner Ornament', 'category' => 'ornaments', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" fill="none" stroke="#1ED760" stroke-width="2"><path d="M10 10 C 40 10, 60 30, 90 90 M10 10 C 10 40, 30 60, 90 90 M20 20 Q 50 20 80 80"/></svg>'],
            ['name' => 'Classic Vintage Divider', 'category' => 'dividers', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 40" fill="none" stroke="#8B9D83" stroke-width="1.5"><path d="M10 20 H 130 M170 20 H 290 M150 10 L 160 20 L 150 30 L 140 20 Z"/></svg>'],
            ['name' => 'Gold Leaf Frame', 'category' => 'frames', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" fill="none" stroke="#C5A880" stroke-width="2"><rect x="10" y="10" width="180" height="180" rx="8"/><circle cx="100" cy="100" r="60"/></svg>'],
            ['name' => 'Botanical Leaf Branch', 'category' => 'illustrations', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" fill="none" stroke="#15803D" stroke-width="2"><path d="M20 100 Q 60 60 100 20 M40 80 Q 30 60 45 50 M60 60 Q 50 40 65 30 M80 40 Q 70 20 85 10"/></svg>'],
            ['name' => 'Minimal Arch Background', 'category' => 'backgrounds', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 600" fill="#FAFAF9"><rect width="400" height="600" fill="#FAFAF9"/><path d="M 50 600 V 200 A 150 150 0 0 1 350 200 V 600 Z" fill="#F4F4F5"/></svg>'],
        ];

        foreach ($globalAssets as $asset) {
            $filename = Str::slug($asset['name']) . '.svg';
            $storagePath = "media/global/vector/{$filename}";
            Storage::disk('public')->put($storagePath, $asset['svg']);

            Media::updateOrCreate(
                ['filename' => $filename, 'is_system' => true],
                [
                    'user_id' => $admin->id,
                    'type' => 'vector',
                    'category' => $asset['category'],
                    'tags' => ['demo', $asset['category']],
                    'disk' => 'public',
                    'path' => $storagePath,
                    'mime_type' => 'image/svg+xml',
                    'size' => strlen($asset['svg']),
                    'processing_status' => 'done',
                ]
            );
        }

        // 4. Demo User
        $user = User::updateOrCreate(
            ['email' => 'demo@ayohadir.test'],
            [
                'name' => 'Alya Putri & Raka Pratama',
                'password' => Hash::make('Demo123!'),
                'role' => 'user',
                'timezone' => 'Asia/Jakarta',
                'locale' => 'id',
            ]
        );

        // 5. Demo Wedding
        $wedding = Wedding::updateOrCreate(
            ['slug' => 'demo-alya-raka'],
            [
                'user_id' => $user->id,
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
                'sections_config' => [
                    'sections' => [
                        ['id' => 'cover', 'name' => 'Sampul Undangan', 'visible' => true, 'order' => 1],
                        ['id' => 'couple', 'name' => 'Mempelai Pengantin', 'visible' => true, 'order' => 2],
                        ['id' => 'event', 'name' => 'Rangkaian Acara', 'visible' => true, 'order' => 3],
                        ['id' => 'story', 'name' => 'Cerita Cinta', 'visible' => true, 'order' => 4],
                        ['id' => 'gallery', 'name' => 'Galeri Foto', 'visible' => true, 'order' => 5],
                        ['id' => 'rsvp', 'name' => 'RSVP & Ucapan', 'visible' => true, 'order' => 6],
                        ['id' => 'gift', 'name' => 'Hadiah / Amplop Digital', 'visible' => true, 'order' => 7],
                    ],
                ],
            ]
        );

        // 6. User Media (Media Saya)
        $userPhotos = [
            ['name' => 'Cover Photo Romantic', 'category' => 'cover', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600" fill="#171A18"><rect width="800" height="600" fill="#1E2320"/><circle cx="400" cy="300" r="150" fill="#2D3748"/><text x="400" y="310" fill="#FAFAF9" font-family="sans-serif" font-size="28" font-weight="bold" text-anchor="middle">Alya &amp; Raka</text></svg>'],
            ['name' => 'Bride Solo Portrait', 'category' => 'bride', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" fill="#FAFAF9"><rect width="400" height="400" fill="#F3F4F6"/><circle cx="200" cy="160" r="70" fill="#E5E7EB"/><text x="200" y="300" fill="#171A18" font-family="sans-serif" font-size="18" text-anchor="middle">Alya Putri</text></svg>'],
            ['name' => 'Groom Solo Portrait', 'category' => 'groom', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" fill="#FAFAF9"><rect width="400" height="400" fill="#F3F4F6"/><circle cx="200" cy="160" r="70" fill="#CBD5E1"/><text x="200" y="300" fill="#171A18" font-family="sans-serif" font-size="18" text-anchor="middle">Raka Pratama</text></svg>'],
            ['name' => 'Prewedding Sunset Moment', 'category' => 'gallery', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 450" fill="#FAFAF9"><rect width="600" height="450" fill="#FEF3C7"/><circle cx="300" cy="225" r="80" fill="#F59E0B"/><text x="300" y="380" fill="#92400E" font-family="sans-serif" font-size="16" text-anchor="middle">Golden Hour Moment</text></svg>'],
            ['name' => 'Prewedding Garden Theme', 'category' => 'gallery', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 450" fill="#FAFAF9"><rect width="600" height="450" fill="#DCFCE7"/><rect x="100" y="100" width="400" height="250" rx="8" fill="#86EFAC"/><text x="300" y="235" fill="#166534" font-family="sans-serif" font-size="16" text-anchor="middle">Garden Theme</text></svg>'],
            ['name' => 'Engagement Ring Detail', 'category' => 'gallery', 'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 450" fill="#FAFAF9"><rect width="600" height="450" fill="#F5F3FF"/><circle cx="300" cy="225" r="60" fill="none" stroke="#8B5CF6" stroke-width="8"/><text x="300" y="360" fill="#5B21B6" font-family="sans-serif" font-size="16" text-anchor="middle">Engagement Rings</text></svg>'],
        ];

        $mediaIds = [];
        foreach ($userPhotos as $photo) {
            $filename = Str::slug($photo['name']) . '.svg';
            $storagePath = "media/weddings/{$wedding->id}/vector/{$filename}";
            Storage::disk('public')->put($storagePath, $photo['svg']);

            $media = Media::updateOrCreate(
                ['wedding_id' => $wedding->id, 'filename' => $filename],
                [
                    'user_id' => $user->id,
                    'type' => 'vector',
                    'category' => $photo['category'],
                    'tags' => ['demo', $photo['category']],
                    'is_system' => false,
                    'disk' => 'public',
                    'path' => $storagePath,
                    'mime_type' => 'image/svg+xml',
                    'size' => strlen($photo['svg']),
                    'processing_status' => 'done',
                ]
            );
            $mediaIds[$photo['category']][] = $media->id;
        }

        // Bind media IDs to wedding
        $wedding->bride_photo_id = $mediaIds['bride'][0] ?? null;
        $wedding->groom_photo_id = $mediaIds['groom'][0] ?? null;
        $wedding->couple_photo_id = $mediaIds['cover'][0] ?? null;
        $wedding->save();

        // 7. Design Instance & Published Schema
        $template = Template::first();
        $designSchema = $template ? $template->schema : [
            'schemaVersion' => 1,
            'metadata' => ['id' => 'tpl_demo', 'name' => 'Demo Theme'],
            'viewport' => ['baseWidth' => 1200, 'contentWidth' => 800, 'baseUnit' => 8],
            'theme' => ['colors' => ['primary' => '#1ED760', 'background' => '#FAFAF9', 'text' => '#171A18']],
            'sections' => [],
        ];

        $design = Design::updateOrCreate(
            ['wedding_id' => $wedding->id],
            [
                'schema_version' => 1,
                'schema' => $designSchema,
                'version' => 1,
                'published_at' => now(),
            ]
        );

        // 8. Publish Service Execution (Creates immutable frozen snapshot & primes Redis cache)
        $publishingService = app(PublishingService::class);
        $publishingService->publish($wedding);

        // 9. Guest Groups
        $groups = [
            'Keluarga' => GuestGroup::updateOrCreate(['wedding_id' => $wedding->id, 'name' => 'Keluarga']),
            'Sahabat' => GuestGroup::updateOrCreate(['wedding_id' => $wedding->id, 'name' => 'Sahabat']),
            'Rekan Kerja' => GuestGroup::updateOrCreate(['wedding_id' => $wedding->id, 'name' => 'Rekan Kerja']),
            'VIP' => GuestGroup::updateOrCreate(['wedding_id' => $wedding->id, 'name' => 'VIP']),
        ];

        // 10. Guests, Personalized Invitations & Realistic RSVPs
        $demoGuests = [
            [
                'name' => 'Budi Santoso',
                'group' => 'Keluarga',
                'max' => 2,
                'token' => 'budi872k99la',
                'attending' => true,
                'attendees' => 2,
                'wishes' => 'Selamat Alya & Raka! Semoga menjadi keluarga yang sakinah mawaddah warahmah.',
                'opened' => 3,
            ],
            [
                'name' => 'Sinta Maharani',
                'group' => 'Keluarga',
                'max' => 2,
                'token' => 'sinta431m88x',
                'attending' => true,
                'attendees' => 2,
                'wishes' => 'Barakallahu lakuma, bahagia selalu sampai akhir hayat.',
                'opened' => 2,
            ],
            [
                'name' => 'Andi Pratama',
                'group' => 'Sahabat',
                'max' => 1,
                'token' => 'andi771p12qq',
                'attending' => true,
                'attendees' => 1,
                'wishes' => 'Congrats bro Raka! Akhirnya sah juga, lancar-lancar acaranya.',
                'opened' => 4,
            ],
            [
                'name' => 'Dinda Putri',
                'group' => 'Sahabat',
                'max' => 2,
                'token' => 'dinda992p55z',
                'attending' => true,
                'attendees' => 2,
                'wishes' => 'Happy wedding Alya sayang! Cantik banget undangannya, can’t wait to be there!',
                'opened' => 5,
            ],
            [
                'name' => 'Fajar Ramadhan',
                'group' => 'Rekan Kerja',
                'max' => 2,
                'token' => 'fajar123r44w',
                'attending' => false,
                'attendees' => 0,
                'wishes' => 'Mohon maaf belum bisa hadir karena dinas luar kota. Doa terbaik untuk kalian berdua.',
                'opened' => 1,
            ],
            [
                'name' => 'Nabila Azzahra',
                'group' => 'Rekan Kerja',
                'max' => 1,
                'token' => 'nabila887a11',
                'attending' => true,
                'attendees' => 1,
                'wishes' => 'Selamat menempuh hidup baru Alya & Raka!',
                'opened' => 2,
            ],
            [
                'name' => 'Rizky Saputra',
                'group' => 'Sahabat',
                'max' => 2,
                'token' => 'rizky551s33t',
                'attending' => null, // Pending
                'attendees' => 0,
                'wishes' => null,
                'opened' => 1,
            ],
            [
                'name' => 'Maya Lestari',
                'group' => 'VIP',
                'max' => 2,
                'token' => 'maya332l77kk',
                'attending' => null, // Pending
                'attendees' => 0,
                'wishes' => null,
                'opened' => 0,
            ],
        ];

        foreach ($demoGuests as $gData) {
            $guest = Guest::updateOrCreate(
                ['wedding_id' => $wedding->id, 'name' => $gData['name']],
                [
                    'guest_group_id' => $groups[$gData['group']]->id,
                    'max_attendees' => $gData['max'],
                ]
            );

            $invitation = Invitation::updateOrCreate(
                ['wedding_id' => $wedding->id, 'guest_id' => $guest->id],
                [
                    'token' => $gData['token'],
                    'open_count' => $gData['opened'],
                    'opened_at' => $gData['opened'] > 0 ? now()->subDays(rand(1, 4)) : null,
                ]
            );

            if ($gData['attending'] !== null) {
                Rsvp::updateOrCreate(
                    ['invitation_id' => $invitation->id],
                    [
                        'wedding_id' => $wedding->id,
                        'guest_id' => $guest->id,
                        'attending' => $gData['attending'],
                        'attendee_count' => $gData['attendees'],
                        'wishes' => $gData['wishes'],
                        'responded_at' => now()->subDays(rand(1, 3)),
                    ]
                );
            }
        }

        // 11. Analytics Page Views (Past 7 days)
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $viewsCount = rand(5, 18);
            for ($v = 0; $v < $viewsCount; $v++) {
                PageView::create([
                    'wedding_id' => $wedding->id,
                    'session_hash' => hash('sha256', "demo-visitor-{$v}-{$i}"),
                    'source' => $v % 3 === 0 ? 'guest_token' : ($v % 2 === 0 ? 'shared' : 'direct'),
                    'created_at' => $date->copy()->addHours(rand(1, 20)),
                ]);
            }
        }
    }
}
