<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Template;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed Plans
        Plan::updateOrCreate(
            ['slug' => 'paket-satuan'],
            [
                'name' => 'Paket Satuan (Pay per Theme)',
                'price' => 0,
                'quota_invitations' => 1,
                'duration_days' => null,
                'description' => 'Bayar hanya sesuai harga tema template yang Anda pilih (Rp 0 s/d Rp 120.000).',
                'features' => [
                    'Akses 1 Proyek Undangan',
                    'Bayar sesuai harga template yang dipilih',
                    'Fitur RSVP & Buku Tamu Digital',
                    'Musik Latar & Galeri Foto',
                    'Masa aktif selamanya',
                ],
                'badge' => null,
                'is_active' => true,
                'order' => 1,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'paket-premium'],
            [
                'name' => 'Paket Bundling Premium (3 Undangan)',
                'price' => 150000,
                'quota_invitations' => 3,
                'duration_days' => null,
                'description' => 'Bayar 150 rb bisa membuat 3 undangan pernikahan dengan tema BEBAS harga berapapun.',
                'features' => [
                    'Kuota 3 Proyek Undangan Pernikahan',
                    'BEBAS pilih SEMUA tema (termasuk tema termahal)',
                    'Hemat hingga 60% dibanding beli satuan',
                    'Amplop Digital & Manajemen Rekening',
                    'Manajemen RSVP & Buku Tamu Real-time',
                    'Musik Latar & Galeri Foto Tanpa Batas',
                    'Masa aktif tanpa batas (Lifetime)',
                ],
                'badge' => 'Paling Populer ⭐',
                'is_active' => true,
                'order' => 2,
            ]
        );

        Plan::updateOrCreate(
            ['slug' => 'paket-pro-vendor'],
            [
                'name' => 'Paket Pro / Wedding Organizer (8 Undangan)',
                'price' => 299000,
                'quota_invitations' => 8,
                'duration_days' => null,
                'description' => 'Pilihan tepat untuk vendor WO, fotografer, atau keluarga dengan beberapa acara.',
                'features' => [
                    'Kuota 8 Proyek Undangan Pernikahan',
                    'BEBAS pilih SEMUA tema tanpa batasan harga',
                    'Export data tamu & konfirmasi kehadiran ke Excel',
                    'Prioritas support dari tim Ayo Hadir',
                    'Amplop Digital & Kustomisasi penuh',
                ],
                'badge' => 'Terhemat untuk Vendor',
                'is_active' => true,
                'order' => 3,
            ]
        );

        // 2. Set diverse prices on existing templates
        $templates = Template::all();
        foreach ($templates as $tpl) {
            if ($tpl->id === 1 || str_contains($tpl->slug, 'botanical')) {
                $tpl->update(['price' => 0, 'tier' => 'free']);
            } elseif ($tpl->id === 2 || str_contains($tpl->slug, 'minimalist')) {
                $tpl->update(['price' => 65000, 'tier' => 'regular']);
            } elseif ($tpl->id === 3 || str_contains($tpl->slug, 'royal') || str_contains($tpl->slug, 'gold')) {
                $tpl->update(['price' => 120000, 'tier' => 'exclusive']);
            } elseif ($tpl->id === 6 || str_contains($tpl->slug, 'mantap') || str_contains($tpl->slug, 'alam')) {
                $tpl->update(['price' => 85000, 'tier' => 'premium']);
            } else {
                $tpl->update(['price' => 50000, 'tier' => 'regular']);
            }
        }
    }
}
