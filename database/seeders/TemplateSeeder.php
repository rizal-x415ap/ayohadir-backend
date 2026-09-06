<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * All template schemas use Mobile (390px) as the canonical base viewport.
     * Responsive engine scales UP for tablet/desktop automatically.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Emerald Botanical',
                'slug' => 'emerald-botanical',
                'category' => 'botanical',
                'description' => 'Tema alam tropis dengan aksen dedaunan hijau zamrud dan tipografi serif klasik.',
                'is_active' => true,
                'order' => 1,
                'schema_version' => 1,
                'schema' => [
                    'schemaVersion' => 1,
                    'metadata' => ['id' => 'tpl_botanical', 'name' => 'Emerald Botanical', 'category' => 'botanical', 'slug' => 'emerald-botanical'],
                    'viewport' => ['baseWidth' => 390, 'contentWidth' => 390, 'baseUnit' => 8],
                    'theme' => ['colors' => ['primary' => '#03AC0E', 'background' => '#FAFAF9', 'text' => '#171A18']],
                    'desktopCover' => [
                        'enabled' => true,
                        'widthRatio' => 40,
                        'backgroundColor' => '#FAFAF9',
                        'backgroundOpacity' => 100,
                        'layout' => [
                            'enabled' => true,
                            'direction' => 'vertical',
                            'gap' => 16,
                            'padding' => ['top' => 48, 'right' => 32, 'bottom' => 48, 'left' => 32],
                            'align' => 'center',
                            'distribution' => 'center',
                            'widthSizing' => 'fill',
                            'heightSizing' => 'fill',
                        ],
                        'elements' => [
                            [
                                'id' => 'el_dc_botanical_greeting',
                                'name' => 'Salam Pembuka',
                                'type' => 'text',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 320, 'height' => 28, 'rotation' => 0, 'zIndex' => 1],
                                'style' => ['fontSize' => 12, 'fontWeight' => '600', 'color' => '#8B9D83', 'textAlign' => 'center', 'textTransform' => 'uppercase', 'letterSpacing' => '3px'],
                                'props' => ['text' => 'The Wedding Celebration of'],
                                'bindingKey' => 'customContent.coverGreeting',
                            ],
                            [
                                'id' => 'el_dc_botanical_photo',
                                'name' => 'Foto Pasangan',
                                'type' => 'image',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 220, 'height' => 220, 'rotation' => 0, 'zIndex' => 2],
                                'style' => ['borderRadius' => 110, 'objectFit' => 'cover', 'boxShadow' => '0 20px 25px -5px rgba(0, 0, 0, 0.1)'],
                                'props' => ['url' => 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=800&q=80', 'alt' => 'Foto Pasangan'],
                                'bindingKey' => 'couple.couplePhotoUrl',
                            ],
                            [
                                'id' => 'el_dc_botanical_names',
                                'name' => 'Nama Mempelai',
                                'type' => 'text',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 360, 'height' => 48, 'rotation' => 0, 'zIndex' => 3],
                                'style' => ['fontSize' => 32, 'fontWeight' => '700', 'fontFamily' => 'Playfair Display, serif', 'color' => '#171A18', 'textAlign' => 'center'],
                                'props' => ['text' => 'Alya & Raka'],
                                'bindingKey' => 'bride_name',
                            ],
                            [
                                'id' => 'el_dc_botanical_date',
                                'name' => 'Tanggal Pernikahan',
                                'type' => 'text',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 280, 'height' => 24, 'rotation' => 0, 'zIndex' => 4],
                                'style' => ['fontSize' => 13, 'fontWeight' => '500', 'color' => '#52525B', 'textAlign' => 'center'],
                                'props' => ['text' => 'Minggu, 15 November 2026'],
                                'bindingKey' => 'wedding_date',
                            ],
                            [
                                'id' => 'el_dc_botanical_btn',
                                'name' => 'Tombol Buka Undangan',
                                'type' => 'button',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 44, 'rotation' => 0, 'zIndex' => 5],
                                'style' => ['backgroundColor' => '#03AC0E', 'color' => '#171A18', 'borderRadius' => 22, 'fontWeight' => '700', 'fontSize' => 13],
                                'props' => ['label' => 'Buka Undangan', 'actionType' => 'open-invitation'],
                            ],
                        ],
                    ],
                    'sections' => [
                        [
                            'id' => 'sec_cover',
                            'name' => 'Sampul Utama',
                            'type' => 'cover',
                            'height' => 720,
                            'backgroundColor' => '#FAFAF9',
                            'elements' => [
                                [
                                    'id' => 'el_cov_greeting',
                                    'name' => 'Salam Pembuka',
                                    'type' => 'text',
                                    'transform' => ['x' => 20, 'y' => 120, 'width' => 350, 'height' => 30, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['fontSize' => 12, 'fontWeight' => '600', 'textAlign' => 'center', 'color' => '#8B9D83', 'textTransform' => 'uppercase', 'letterSpacing' => '3px'],
                                    'props' => ['text' => 'The Wedding Of'],
                                    'bindingKey' => 'customContent.coverGreeting',
                                ],
                                [
                                    'id' => 'el_cov_names',
                                    'name' => 'Nama Pasangan',
                                    'type' => 'text',
                                    'transform' => ['x' => 15, 'y' => 165, 'width' => 360, 'height' => 70, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['fontSize' => 36, 'fontWeight' => '700', 'fontFamily' => 'Playfair Display, serif', 'textAlign' => 'center', 'color' => '#171A18'],
                                    'props' => ['text' => 'Alya & Raka'],
                                ],
                                [
                                    'id' => 'el_cov_date',
                                    'name' => 'Tanggal Acara',
                                    'type' => 'text',
                                    'transform' => ['x' => 45, 'y' => 250, 'width' => 300, 'height' => 30, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['fontSize' => 13, 'fontWeight' => '500', 'textAlign' => 'center', 'color' => '#52525B'],
                                    'props' => ['text' => 'Minggu, 15 November 2026'],
                                ],
                                [
                                    'id' => 'el_cov_photo',
                                    'name' => 'Foto Sampul',
                                    'type' => 'image',
                                    'transform' => ['x' => 45, 'y' => 310, 'width' => 300, 'height' => 280, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['borderRadius' => 16, 'objectFit' => 'cover', 'boxShadow' => '0 20px 25px -5px rgba(0, 0, 0, 0.1)'],
                                    'props' => ['url' => 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=800&q=80'],
                                ],
                            ],
                        ],
                        [
                            'id' => 'sec_couple',
                            'name' => 'Profil Mempelai',
                            'type' => 'couple',
                            'height' => 580,
                            'backgroundColor' => '#F4F5F4',
                            'elements' => [
                                [
                                    'id' => 'el_cpl_title',
                                    'name' => 'Judul Bagian',
                                    'type' => 'text',
                                    'transform' => ['x' => 20, 'y' => 40, 'width' => 350, 'height' => 45, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['fontSize' => 24, 'fontWeight' => '700', 'fontFamily' => 'Playfair Display, serif', 'textAlign' => 'center', 'color' => '#171A18'],
                                    'props' => ['text' => 'Kedua Mempelai'],
                                ],
                                [
                                    'id' => 'el_cpl_bride',
                                    'name' => 'Mempelai Wanita',
                                    'type' => 'text',
                                    'transform' => ['x' => 20, 'y' => 110, 'width' => 350, 'height' => 40, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['fontSize' => 17, 'fontWeight' => '700', 'textAlign' => 'center', 'color' => '#171A18'],
                                    'props' => ['text' => 'Alya Putri Lestari, S.Ked'],
                                ],
                                [
                                    'id' => 'el_cpl_groom',
                                    'name' => 'Mempelai Pria',
                                    'type' => 'text',
                                    'transform' => ['x' => 20, 'y' => 170, 'width' => 350, 'height' => 40, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['fontSize' => 17, 'fontWeight' => '700', 'textAlign' => 'center', 'color' => '#171A18'],
                                    'props' => ['text' => 'Raka Pratama Kusuma, S.T'],
                                ],
                                [
                                    'id' => 'el_cpl_quote',
                                    'name' => 'Kutipan Doa',
                                    'type' => 'quote',
                                    'transform' => ['x' => 20, 'y' => 350, 'width' => 350, 'height' => 140, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['backgroundColor' => '#FFFFFF', 'borderRadius' => 12, 'boxShadow' => '0 4px 6px -1px rgba(0, 0, 0, 0.05)'],
                                    'props' => [
                                        'quoteText' => 'Dan di antara tanda-tanda kebesaran-Nya ialah Dia menciptakan pasangan-pasangan untukmu dari jenismu sendiri...',
                                        'source' => 'QS. Ar-Rum: 21',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'id' => 'sec_event',
                            'name' => 'Lokasi & Jadwal',
                            'type' => 'event',
                            'height' => 600,
                            'backgroundColor' => '#FAFAF9',
                            'elements' => [
                                [
                                    'id' => 'el_evt_loc',
                                    'name' => 'Lokasi Gedung',
                                    'type' => 'location',
                                    'transform' => ['x' => 20, 'y' => 50, 'width' => 350, 'height' => 220, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['backgroundColor' => '#FFFFFF', 'borderRadius' => 16, 'boxShadow' => '0 10px 15px -3px rgba(0, 0, 0, 0.08)'],
                                    'props' => [
                                        'venueName' => 'Grand Ballroom Hotel Indonesia Kempinski',
                                        'address' => 'Jl. M.H. Thamrin No. 1, Menteng, Jakarta Pusat',
                                        'buttonLabel' => 'Buka Google Maps',
                                    ],
                                ],
                                [
                                    'id' => 'el_evt_countdown',
                                    'name' => 'Hitung Mundur',
                                    'type' => 'countdown',
                                    'transform' => ['x' => 20, 'y' => 310, 'width' => 350, 'height' => 120, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['backgroundColor' => '#171A18', 'color' => '#FAFAF9', 'borderRadius' => 16],
                                    'props' => [
                                        'daysLabel' => 'Hari',
                                        'hoursLabel' => 'Jam',
                                        'minutesLabel' => 'Menit',
                                        'secondsLabel' => 'Detik',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'id' => 'sec_story',
                            'name' => 'Kisah Cinta Kami',
                            'type' => 'story',
                            'height' => 760,
                            'backgroundColor' => '#FAFAF9',
                            'elements' => [
                                [
                                    'id' => 'el_story_heading',
                                    'name' => 'Judul Kisah',
                                    'type' => 'text',
                                    'transform' => ['x' => 20, 'y' => 30, 'width' => 350, 'height' => 40, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['fontSize' => 24, 'fontWeight' => '700', 'fontFamily' => 'Playfair Display, serif', 'textAlign' => 'center', 'color' => '#171A18'],
                                    'props' => ['text' => 'Perjalanan Cinta Kami'],
                                ],
                                [
                                    'id' => 'el_story_widget',
                                    'name' => 'Love Story Timeline',
                                    'type' => 'story',
                                    'transform' => ['x' => 20, 'y' => 80, 'width' => 350, 'height' => 640, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['backgroundColor' => 'transparent'],
                                    'props' => [
                                        'theme' => 'editorial-timeline',
                                        'variant' => 'editorial-timeline',
                                        'containerGap' => 36,
                                        'titleFont' => 'Playfair Display, serif',
                                        'titleColor' => '#171A18',
                                        'timelineColor' => '#059669',
                                        'dotColor' => '#059669',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'id' => 'sec_rsvp',
                            'name' => 'RSVP & Ucapan',
                            'type' => 'rsvp',
                            'height' => 540,
                            'backgroundColor' => '#F4F5F4',
                            'elements' => [
                                [
                                    'id' => 'el_rsvp_widget',
                                    'name' => 'Widget RSVP',
                                    'type' => 'rsvp',
                                    'transform' => ['x' => 20, 'y' => 40, 'width' => 350, 'height' => 440, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['backgroundColor' => '#FFFFFF', 'borderRadius' => 16, 'boxShadow' => '0 10px 15px -3px rgba(0, 0, 0, 0.08)'],
                                    'props' => [
                                        'title' => 'Konfirmasi Kehadiran',
                                        'subtitle' => 'Merupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir.',
                                        'submitLabel' => 'Kirim Konfirmasi Kehadiran',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'contract' => [
                    'sections' => [
                        ['sectionId' => 'sec_cover', 'name' => 'Sampul Utama', 'isRequired' => true, 'isReorderable' => false, 'isRemovable' => false, 'defaultVisible' => true],
                        ['sectionId' => 'sec_couple', 'name' => 'Profil Mempelai', 'isRequired' => true, 'isReorderable' => true, 'isRemovable' => false, 'defaultVisible' => true],
                        ['sectionId' => 'sec_event', 'name' => 'Lokasi & Jadwal', 'isRequired' => true, 'isReorderable' => true, 'isRemovable' => false, 'defaultVisible' => true],
                        ['sectionId' => 'sec_story', 'name' => 'Kisah Cinta Kami', 'isRequired' => false, 'isReorderable' => true, 'isRemovable' => true, 'defaultVisible' => true],
                        ['sectionId' => 'sec_rsvp', 'name' => 'RSVP & Ucapan', 'isRequired' => false, 'isReorderable' => true, 'isRemovable' => true, 'defaultVisible' => true],
                    ],
                    'editableFields' => ['bride_name', 'groom_name', 'wedding_date', 'venue_name', 'venue_address'],
                ],
            ],
            [
                'name' => 'Minimalist Monochrome',
                'slug' => 'minimalist-monochrome',
                'category' => 'minimalist',
                'description' => 'Desain monokrom minimalis modern dengan layout lapang dan tipografi sans-serif kontemporer.',
                'is_active' => true,
                'order' => 2,
                'schema_version' => 1,
                'schema' => [
                    'schemaVersion' => 1,
                    'metadata' => ['id' => 'tpl_minimalist', 'name' => 'Minimalist Monochrome', 'category' => 'minimalist', 'slug' => 'minimalist-monochrome'],
                    'viewport' => ['baseWidth' => 390, 'contentWidth' => 390, 'baseUnit' => 8],
                    'theme' => ['colors' => ['primary' => '#171A18', 'background' => '#FFFFFF', 'text' => '#171A18']],
                    'desktopCover' => [
                        'enabled' => true,
                        'widthRatio' => 40,
                        'backgroundColor' => '#FFFFFF',
                        'backgroundOpacity' => 100,
                        'layout' => [
                            'enabled' => true,
                            'direction' => 'vertical',
                            'gap' => 20,
                            'padding' => ['top' => 48, 'right' => 32, 'bottom' => 48, 'left' => 32],
                            'align' => 'center',
                            'distribution' => 'center',
                            'widthSizing' => 'fill',
                            'heightSizing' => 'fill',
                        ],
                        'elements' => [
                            [
                                'id' => 'el_dc_min_title',
                                'name' => 'Judul',
                                'type' => 'text',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 340, 'height' => 32, 'rotation' => 0, 'zIndex' => 1],
                                'style' => ['fontSize' => 14, 'fontWeight' => '800', 'fontFamily' => 'Inter, sans-serif', 'textAlign' => 'center', 'color' => '#171A18', 'letterSpacing' => '2px'],
                                'props' => ['text' => 'THE WEDDING OF'],
                                'bindingKey' => 'customContent.coverGreeting',
                            ],
                            [
                                'id' => 'el_dc_min_names',
                                'name' => 'Nama Pasangan',
                                'type' => 'text',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 340, 'height' => 48, 'rotation' => 0, 'zIndex' => 2],
                                'style' => ['fontSize' => 28, 'fontWeight' => '600', 'textAlign' => 'center', 'color' => '#171A18', 'fontFamily' => 'Inter, sans-serif'],
                                'props' => ['text' => 'Alya & Raka'],
                                'bindingKey' => 'bride_name',
                            ],
                            [
                                'id' => 'el_dc_min_btn',
                                'name' => 'Tombol Buka',
                                'type' => 'button',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 48, 'rotation' => 0, 'zIndex' => 3],
                                'style' => ['backgroundColor' => '#171A18', 'color' => '#FFFFFF', 'borderRadius' => 9999, 'fontSize' => 13, 'fontWeight' => '600'],
                                'props' => ['label' => 'Buka Undangan', 'actionType' => 'open-invitation'],
                            ],
                        ],
                    ],
                    'sections' => [
                        [
                            'id' => 'sec_cover',
                            'name' => 'Sampul',
                            'type' => 'cover',
                            'height' => 680,
                            'backgroundColor' => '#FFFFFF',
                            'elements' => [
                                [
                                    'id' => 'el_title',
                                    'name' => 'Judul',
                                    'type' => 'text',
                                    'transform' => ['x' => 20, 'y' => 180, 'width' => 350, 'height' => 55, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['fontSize' => 28, 'fontWeight' => '800', 'fontFamily' => 'Inter, sans-serif', 'textAlign' => 'center', 'color' => '#171A18', 'letterSpacing' => '-1px'],
                                    'props' => ['text' => 'WE ARE GETTING MARRIED'],
                                ],
                                [
                                    'id' => 'el_names',
                                    'name' => 'Nama Pasangan',
                                    'type' => 'text',
                                    'transform' => ['x' => 20, 'y' => 250, 'width' => 350, 'height' => 40, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['fontSize' => 18, 'fontWeight' => '500', 'textAlign' => 'center', 'color' => '#71717A'],
                                    'props' => ['text' => 'Alya & Raka'],
                                ],
                                [
                                    'id' => 'el_btn_open',
                                    'name' => 'Tombol Buka',
                                    'type' => 'button',
                                    'transform' => ['x' => 95, 'y' => 350, 'width' => 200, 'height' => 48, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['backgroundColor' => '#171A18', 'color' => '#FFFFFF', 'borderRadius' => 9999, 'fontSize' => 13, 'fontWeight' => '600'],
                                    'props' => ['label' => 'Buka Undangan'],
                                ],
                            ],
                        ],
                        [
                            'id' => 'sec_event',
                            'name' => 'Waktu & Tempat',
                            'type' => 'event',
                            'height' => 520,
                            'backgroundColor' => '#FAFAFA',
                            'elements' => [
                                [
                                    'id' => 'el_loc_min',
                                    'name' => 'Lokasi Acara',
                                    'type' => 'location',
                                    'transform' => ['x' => 20, 'y' => 60, 'width' => 350, 'height' => 200, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['backgroundColor' => '#FFFFFF', 'borderRadius' => 8, 'borderWidth' => 1, 'borderColor' => '#E4E4E7'],
                                    'props' => [
                                        'venueName' => 'The Glass House Ballroom',
                                        'address' => 'Jl. Senopati No. 88, Jakarta Selatan',
                                        'buttonLabel' => 'Petunjuk Arah Maps',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'contract' => [
                    'sections' => [
                        ['sectionId' => 'sec_cover', 'name' => 'Sampul', 'isRequired' => true, 'isReorderable' => false, 'isRemovable' => false, 'defaultVisible' => true],
                        ['sectionId' => 'sec_event', 'name' => 'Waktu & Tempat', 'isRequired' => true, 'isReorderable' => true, 'isRemovable' => false, 'defaultVisible' => true],
                    ],
                    'editableFields' => ['bride_name', 'groom_name', 'wedding_date'],
                ],
            ],
            [
                'name' => 'Royal Classic Gold',
                'slug' => 'royal-classic-gold',
                'category' => 'luxury',
                'description' => 'Kemewahan nuansa emas dan font kaligrafi anggun untuk resepsi pesta pernikahan formal.',
                'is_active' => true,
                'order' => 3,
                'schema_version' => 1,
                'schema' => [
                    'schemaVersion' => 1,
                    'metadata' => ['id' => 'tpl_royal', 'name' => 'Royal Classic Gold', 'category' => 'luxury', 'slug' => 'royal-classic-gold'],
                    'viewport' => ['baseWidth' => 390, 'contentWidth' => 390, 'baseUnit' => 8],
                    'theme' => ['colors' => ['primary' => '#C5A880', 'background' => '#0F1210', 'text' => '#FAFAF9']],
                    'desktopCover' => [
                        'enabled' => true,
                        'widthRatio' => 40,
                        'backgroundColor' => '#0F1210',
                        'backgroundOpacity' => 100,
                        'layout' => [
                            'enabled' => true,
                            'direction' => 'vertical',
                            'gap' => 18,
                            'padding' => ['top' => 48, 'right' => 32, 'bottom' => 48, 'left' => 32],
                            'align' => 'center',
                            'distribution' => 'center',
                            'widthSizing' => 'fill',
                            'heightSizing' => 'fill',
                        ],
                        'elements' => [
                            [
                                'id' => 'el_dc_royal_title',
                                'name' => 'Judul',
                                'type' => 'text',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 340, 'height' => 36, 'rotation' => 0, 'zIndex' => 1],
                                'style' => ['fontSize' => 16, 'fontWeight' => '700', 'fontFamily' => 'Cinzel, serif', 'textAlign' => 'center', 'color' => '#C5A880', 'letterSpacing' => '4px'],
                                'props' => ['text' => 'ROYAL WEDDING CELEBRATION'],
                                'bindingKey' => 'customContent.coverGreeting',
                            ],
                            [
                                'id' => 'el_dc_royal_names',
                                'name' => 'Nama Pasangan',
                                'type' => 'text',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 340, 'height' => 48, 'rotation' => 0, 'zIndex' => 2],
                                'style' => ['fontSize' => 32, 'fontWeight' => '400', 'fontFamily' => 'Playfair Display, serif', 'textAlign' => 'center', 'color' => '#FAFAF9'],
                                'props' => ['text' => 'Alya & Raka'],
                                'bindingKey' => 'bride_name',
                            ],
                            [
                                'id' => 'el_dc_royal_btn',
                                'name' => 'Tombol Buka',
                                'type' => 'button',
                                'transform' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 44, 'rotation' => 0, 'zIndex' => 3],
                                'style' => ['backgroundColor' => '#C5A880', 'color' => '#0F1210', 'borderRadius' => 22, 'fontSize' => 13, 'fontWeight' => '700'],
                                'props' => ['label' => 'Buka Undangan', 'actionType' => 'open-invitation'],
                            ],
                        ],
                    ],
                    'sections' => [
                        [
                            'id' => 'sec_cover',
                            'name' => 'Sampul Mewah',
                            'type' => 'cover',
                            'height' => 740,
                            'backgroundColor' => '#0F1210',
                            'elements' => [
                                [
                                    'id' => 'el_royal_title',
                                    'name' => 'Judul',
                                    'type' => 'text',
                                    'transform' => ['x' => 20, 'y' => 160, 'width' => 350, 'height' => 60, 'rotation' => 0, 'zIndex' => 1],
                                    'style' => ['fontSize' => 30, 'fontWeight' => '700', 'fontFamily' => 'Cinzel, serif', 'textAlign' => 'center', 'color' => '#C5A880', 'letterSpacing' => '4px'],
                                    'props' => ['text' => 'ROYAL WEDDING'],
                                ],
                                [
                                    'id' => 'el_royal_names',
                                    'name' => 'Nama Pasangan',
                                    'type' => 'text',
                                    'transform' => ['x' => 20, 'y' => 240, 'width' => 350, 'height' => 50, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['fontSize' => 24, 'fontWeight' => '400', 'fontFamily' => 'Playfair Display, serif', 'textAlign' => 'center', 'color' => '#FAFAF9'],
                                    'props' => ['text' => 'Alya & Raka'],
                                ],
                                [
                                    'id' => 'el_royal_countdown',
                                    'name' => 'Hitung Mundur',
                                    'type' => 'countdown',
                                    'transform' => ['x' => 20, 'y' => 350, 'width' => 350, 'height' => 120, 'rotation' => 0, 'zIndex' => 2],
                                    'style' => ['backgroundColor' => '#1C201D', 'color' => '#C5A880', 'borderRadius' => 16, 'borderWidth' => 1, 'borderColor' => '#C5A880/30'],
                                    'props' => [
                                        'daysLabel' => 'Hari',
                                        'hoursLabel' => 'Jam',
                                        'minutesLabel' => 'Menit',
                                        'secondsLabel' => 'Detik',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'contract' => [
                    'sections' => [
                        ['sectionId' => 'sec_cover', 'name' => 'Sampul Mewah', 'isRequired' => true, 'isReorderable' => false, 'isRemovable' => false, 'defaultVisible' => true],
                    ],
                    'editableFields' => ['bride_name', 'groom_name', 'wedding_date', 'venue_name'],
                ],
            ],
        ];

        foreach ($templates as $data) {
            Template::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
