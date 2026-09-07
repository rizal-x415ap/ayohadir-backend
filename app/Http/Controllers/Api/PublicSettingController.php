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
        $whatsappNumber = AppSetting::get('admin_whatsapp_number', env('ADMIN_WHATSAPP_NUMBER', '081234567890'));
        $whatsappMessage = AppSetting::get('admin_whatsapp_message', "Halo Admin Ayo Hadir, saya tertarik membuat undangan digital. Mohon info langkah selanjutnya. Terima kasih!");
        $shopeeUrl = AppSetting::get('shopee_url', 'https://shopee.co.id/ayohadir');
        $instagramUrl = AppSetting::get('instagram_url', 'https://instagram.com/ayohadir.id');
        $tiktokUrl = AppSetting::get('tiktok_url', 'https://tiktok.com/@ayohadir.id');

        return response()->json([
            'data' => [
                'brandName' => 'Ayo Hadir',
                'tagline' => 'Undangan Digital Elegan untuk Momen Istimewa',
                'description' => 'Platform undangan digital pernikahan profesional dengan visual luxury, visual builder, dan konfirmasi kehadiran terpadu.',
                'contact' => [
                    'whatsappNumber' => $whatsappNumber,
                    'whatsappMessage' => $whatsappMessage,
                    'shopeeUrl' => $shopeeUrl,
                    'instagramUrl' => $instagramUrl,
                    'tiktokUrl' => $tiktokUrl,
                ],
            ],
            'meta' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }
}
