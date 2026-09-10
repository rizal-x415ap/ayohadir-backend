<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;

class PublicSettingController extends Controller
{
    /**
     * Return public settings for landing page, contact, and branding.
     */
    public function index(): JsonResponse
    {
        $whatsappNumber = AppSetting::get('admin_whatsapp_number', env('ADMIN_WHATSAPP_NUMBER', '085156476048'));
        $whatsappMessage = AppSetting::get('admin_whatsapp_message', "Halo Admin Ayo Hadir, saya tertarik membuat undangan digital. Mohon info langkah selanjutnya. Terima kasih!");
        $email = AppSetting::get('admin_email', 'support@ayohadir.id');
        $phoneNumber = AppSetting::get('admin_phone_number', '085156476048');
        $address = AppSetting::get('admin_address', 'Pematang Sidamanik, Kab.Simalungung, Sumatera Utara');
        $shopeeUrl = AppSetting::get('shopee_url', 'https://shopee.co.id/ayohadir');
        $instagramUrl = AppSetting::get('instagram_url', 'https://instagram.com/ayohadir.id');
        $tiktokUrl = AppSetting::get('tiktok_url', 'https://tiktok.com/@ayohadir.id');

        $defaultHeroImage = 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=800&q=80';
        $defaultBuilderImage = 'https://images.unsplash.com/photo-1581291518857-4e27b48ff24e?auto=format&fit=crop&w=1600&h=1000&q=80';

        $heroImage1 = AppSetting::get('landing_hero_phone_image', $defaultHeroImage);
        $heroImage2 = AppSetting::get('landing_hero_phone_image_2', '');
        $heroImage3 = AppSetting::get('landing_hero_phone_image_3', '');
        $phoneImages = array_values(array_filter([$heroImage1, $heroImage2, $heroImage3]));
        if (empty($phoneImages)) {
            $phoneImages = [$defaultHeroImage];
        }

        return response()->json([
            'data' => [
                'brandName' => 'Ayo Hadir',
                'tagline' => AppSetting::get('landing_hero_tagline', 'Platform Undangan Digital #1 di Indonesia'),
                'description' => AppSetting::get('landing_hero_subtitle', 'Platform undangan digital pernikahan profesional dengan visual luxury, visual builder, dan konfirmasi kehadiran terpadu.'),
                'hero' => [
                    'tagline' => AppSetting::get('landing_hero_tagline', 'Platform Undangan Digital #1 di Indonesia'),
                    'title' => AppSetting::get('landing_hero_title', 'Buat Undangan Pernikahan Digital dalam Hitungan Menit'),
                    'subtitle' => AppSetting::get('landing_hero_subtitle', 'Pilih desain elegan, sesuaikan data pengantin dengan mudah, dan bagikan langsung ke WhatsApp keluarga dan kerabat.'),
                    'phoneImage' => $heroImage1,
                    'phoneImage2' => $heroImage2,
                    'phoneImage3' => $heroImage3,
                    'phoneImages' => $phoneImages,
                    'phoneLink' => AppSetting::get('landing_hero_phone_link', ''),
                    'templatePreviewUrl' => AppSetting::get('landing_hero_template_preview_url', ''),
                    'ctaPrimary' => AppSetting::get('landing_hero_cta_primary', 'Lihat Template'),
                    'ctaSecondary' => AppSetting::get('landing_hero_cta_secondary', 'Buat Undangan Sendiri'),
                ],
                'builder' => [
                    'image' => AppSetting::get('landing_builder_image', $defaultBuilderImage),
                ],
                'stats' => [
                    'stat1' => [
                        'value' => AppSetting::get('landing_stat1_value', '50+'),
                        'label' => AppSetting::get('landing_stat1_label', 'Template Premium'),
                    ],
                    'stat2' => [
                        'value' => AppSetting::get('landing_stat2_value', 'Real-Time'),
                        'label' => AppSetting::get('landing_stat2_label', 'Konfirmasi RSVP'),
                    ],
                    'stat3' => [
                        'value' => AppSetting::get('landing_stat3_value', '1-Klik'),
                        'label' => AppSetting::get('landing_stat3_label', 'Kirim WhatsApp'),
                    ],
                ],
                'contact' => [
                    'whatsappNumber' => $whatsappNumber,
                    'whatsappMessage' => $whatsappMessage,
                    'email' => $email,
                    'phoneNumber' => $phoneNumber,
                    'address' => $address,
                    'shopeeUrl' => $shopeeUrl,
                    'instagramUrl' => $instagramUrl,
                    'tiktokUrl' => $tiktokUrl,
                ],
                'protection' => [
                    'enabled' => AppSetting::get('protection_enabled', 'true') === 'true',
                    'blockRightClick' => AppSetting::get('protection_block_right_click', 'true') === 'true',
                    'blockShortcuts' => AppSetting::get('protection_block_shortcuts', 'true') === 'true',
                    'blockDrag' => AppSetting::get('protection_block_drag', 'true') === 'true',
                    'showToast' => AppSetting::get('protection_show_toast', 'true') === 'true',
                    'toastMessage' => AppSetting::get('protection_toast_message', 'Konten dan desain undangan ini dilindungi hak cipta Ayo Hadir.'),
                ],
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
