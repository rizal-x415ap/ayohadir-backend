<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminLandingSettingController extends Controller
{
    /**
     * Retrieve all landing page settings for Admin.
     */
    public function getSettings(): JsonResponse
    {
        $defaultHeroImage = 'https://images.unsplash.com/photo-1519741497674-611481863552?auto=format&fit=crop&w=800&q=80';
        $defaultBuilderImage = 'https://images.unsplash.com/photo-1581291518857-4e27b48ff24e?auto=format&fit=crop&w=1600&h=1000&q=80';

        $savedTemplateIdsRaw = AppSetting::get('landing_template_ids', null);
        $savedTemplateIds = $savedTemplateIdsRaw ? json_decode($savedTemplateIdsRaw, true) : [];

        $allTemplates = \App\Models\Template::where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'thumbnail', 'tier', 'category', 'price']);

        $heroImage1 = AppSetting::get('landing_hero_phone_image', $defaultHeroImage);
        $heroImage2 = AppSetting::get('landing_hero_phone_image_2', '');
        $heroImage3 = AppSetting::get('landing_hero_phone_image_3', '');
        $phoneImages = array_values(array_filter([$heroImage1, $heroImage2, $heroImage3]));
        if (empty($phoneImages)) {
            $phoneImages = [$defaultHeroImage];
        }

        $data = [
            'hero' => [
                'tagline' => AppSetting::get('landing_hero_tagline', 'Platform Undangan Digital #1 di Indonesia'),
                'title' => AppSetting::get('landing_hero_title', 'Buat Undangan Pernikahan Digital dalam Hitungan Menit'),
                'subtitle' => AppSetting::get('landing_hero_subtitle', 'Pilih desain elegan, sesuaikan data pengantin dengan mudah, dan bagikan langsung ke WhatsApp keluarga dan kerabat.'),
                'phone_image' => $heroImage1,
                'phone_image_2' => $heroImage2,
                'phone_image_3' => $heroImage3,
                'phone_images' => $phoneImages,
                'phone_link' => AppSetting::get('landing_hero_phone_link', ''),
                'template_preview_url' => AppSetting::get('landing_hero_template_preview_url', ''),
                'cta_primary_text' => AppSetting::get('landing_hero_cta_primary', 'Lihat Template'),
                'cta_secondary_text' => AppSetting::get('landing_hero_cta_secondary', 'Buat Undangan Sendiri'),
            ],
            'builder' => [
                'image' => AppSetting::get('landing_builder_image', $defaultBuilderImage),
            ],
            'stats' => [
                'stat1_value' => AppSetting::get('landing_stat1_value', '50+'),
                'stat1_label' => AppSetting::get('landing_stat1_label', 'Template Premium'),
                'stat2_value' => AppSetting::get('landing_stat2_value', 'Real-Time'),
                'stat2_label' => AppSetting::get('landing_stat2_label', 'Konfirmasi RSVP'),
                'stat3_value' => AppSetting::get('landing_stat3_value', '1-Klik'),
                'stat3_label' => AppSetting::get('landing_stat3_label', 'Kirim WhatsApp'),
            ],
            'links' => [
                'whatsapp_number' => AppSetting::get('admin_whatsapp_number', env('ADMIN_WHATSAPP_NUMBER', '085156476048')),
                'whatsapp_message' => AppSetting::get('admin_whatsapp_message', "Halo Admin Ayo Hadir, saya tertarik membuat undangan digital. Mohon info langkah selanjutnya. Terima kasih!"),
                'email' => AppSetting::get('admin_email', 'support@ayohadir.id'),
                'phone_number' => AppSetting::get('admin_phone_number', '085156476048'),
                'address' => AppSetting::get('admin_address', 'Pematang Sidamanik, Kab.Simalungung, Sumatera Utara'),
                'shopee_url' => AppSetting::get('shopee_url', 'https://shopee.co.id/ayohadir'),
                'instagram_url' => AppSetting::get('instagram_url', 'https://instagram.com/ayohadir.id'),
                'tiktok_url' => AppSetting::get('tiktok_url', 'https://tiktok.com/@ayohadir.id'),
            ],
            'templates' => [
                'selected_ids' => is_array($savedTemplateIds) ? $savedTemplateIds : [],
                'all' => $allTemplates,
            ],
            'protection' => [
                'enabled' => AppSetting::get('protection_enabled', 'true') === 'true',
                'block_right_click' => AppSetting::get('protection_block_right_click', 'true') === 'true',
                'block_shortcuts' => AppSetting::get('protection_block_shortcuts', 'true') === 'true',
                'block_drag' => AppSetting::get('protection_block_drag', 'true') === 'true',
                'show_toast' => AppSetting::get('protection_show_toast', 'true') === 'true',
                'toast_message' => AppSetting::get('protection_toast_message', 'Konten dan desain undangan ini dilindungi hak cipta Ayo Hadir.'),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update landing page texts and settings.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hero.tagline' => 'nullable|string|max:100',
            'hero.title' => 'nullable|string|max:200',
            'hero.subtitle' => 'nullable|string|max:500',
            'hero.phone_image' => 'nullable|string|max:1000',
            'hero.phone_image_2' => 'nullable|string|max:1000',
            'hero.phone_image_3' => 'nullable|string|max:1000',
            'hero.phone_link' => 'nullable|string|max:1000',
            'hero.template_preview_url' => 'nullable|string|max:1000',
            'hero.cta_primary_text' => 'nullable|string|max:50',
            'hero.cta_secondary_text' => 'nullable|string|max:50',

            'stats.stat1_value' => 'nullable|string|max:30',
            'stats.stat1_label' => 'nullable|string|max:50',
            'stats.stat2_value' => 'nullable|string|max:30',
            'stats.stat2_label' => 'nullable|string|max:50',
            'stats.stat3_value' => 'nullable|string|max:30',
            'stats.stat3_label' => 'nullable|string|max:50',

            'links.whatsapp_number' => 'nullable|string|max:30',
            'links.whatsapp_message' => 'nullable|string|max:1000',
            'links.email' => 'nullable|string|max:255',
            'links.phone_number' => 'nullable|string|max:50',
            'links.address' => 'nullable|string|max:500',
            'links.shopee_url' => 'nullable|string|max:500',
            'links.instagram_url' => 'nullable|string|max:500',
            'links.tiktok_url' => 'nullable|string|max:500',

            'templates.selected_ids' => 'nullable|array',
            'templates.selected_ids.*' => 'integer',

            'builder.image' => 'nullable|string|max:1000',

            'protection.enabled' => 'nullable|boolean',
            'protection.block_right_click' => 'nullable|boolean',
            'protection.block_shortcuts' => 'nullable|boolean',
            'protection.block_drag' => 'nullable|boolean',
            'protection.show_toast' => 'nullable|boolean',
            'protection.toast_message' => 'nullable|string|max:255',
        ]);

        // Save Protection Settings
        if (isset($validated['protection'])) {
            if (array_key_exists('enabled', $validated['protection'])) {
                AppSetting::set('protection_enabled', $validated['protection']['enabled'] ? 'true' : 'false', 'protection');
            }
            if (array_key_exists('block_right_click', $validated['protection'])) {
                AppSetting::set('protection_block_right_click', $validated['protection']['block_right_click'] ? 'true' : 'false', 'protection');
            }
            if (array_key_exists('block_shortcuts', $validated['protection'])) {
                AppSetting::set('protection_block_shortcuts', $validated['protection']['block_shortcuts'] ? 'true' : 'false', 'protection');
            }
            if (array_key_exists('block_drag', $validated['protection'])) {
                AppSetting::set('protection_block_drag', $validated['protection']['block_drag'] ? 'true' : 'false', 'protection');
            }
            if (array_key_exists('show_toast', $validated['protection'])) {
                AppSetting::set('protection_show_toast', $validated['protection']['show_toast'] ? 'true' : 'false', 'protection');
            }
            if (array_key_exists('toast_message', $validated['protection']) && $validated['protection']['toast_message'] !== null) {
                AppSetting::set('protection_toast_message', trim($validated['protection']['toast_message']), 'protection');
            }
        }

        // Save Selected Templates for Landing Page
        if (isset($validated['templates']['selected_ids'])) {
            $selectedIds = array_values(array_map('intval', $validated['templates']['selected_ids']));
            AppSetting::set('landing_template_ids', json_encode($selectedIds), 'landing');
        }

        // Save Builder Image
        if (isset($validated['builder']['image'])) {
            AppSetting::set('landing_builder_image', trim($validated['builder']['image']), 'landing');
        }

        // Save Hero
        if (isset($validated['hero']['tagline'])) {
            AppSetting::set('landing_hero_tagline', trim($validated['hero']['tagline']), 'landing');
        }
        if (isset($validated['hero']['title'])) {
            AppSetting::set('landing_hero_title', trim($validated['hero']['title']), 'landing');
        }
        if (isset($validated['hero']['subtitle'])) {
            AppSetting::set('landing_hero_subtitle', trim($validated['hero']['subtitle']), 'landing');
        }
        if (array_key_exists('phone_image', $request->input('hero', []))) {
            AppSetting::set('landing_hero_phone_image', trim($request->input('hero.phone_image') ?? ''), 'landing');
        }
        if (array_key_exists('phone_image_2', $request->input('hero', []))) {
            AppSetting::set('landing_hero_phone_image_2', trim($request->input('hero.phone_image_2') ?? ''), 'landing');
        }
        if (array_key_exists('phone_image_3', $request->input('hero', []))) {
            AppSetting::set('landing_hero_phone_image_3', trim($request->input('hero.phone_image_3') ?? ''), 'landing');
        }
        if (array_key_exists('phone_link', $request->input('hero', []))) {
            AppSetting::set('landing_hero_phone_link', trim($request->input('hero.phone_link') ?? ''), 'landing');
        }
        if (array_key_exists('template_preview_url', $request->input('hero', []))) {
            AppSetting::set('landing_hero_template_preview_url', trim($request->input('hero.template_preview_url') ?? ''), 'landing');
        }
        if (isset($validated['hero']['cta_primary_text'])) {
            AppSetting::set('landing_hero_cta_primary', trim($validated['hero']['cta_primary_text']), 'landing');
        }
        if (isset($validated['hero']['cta_secondary_text'])) {
            AppSetting::set('landing_hero_cta_secondary', trim($validated['hero']['cta_secondary_text']), 'landing');
        }

        // Save Stats
        if (isset($validated['stats']['stat1_value'])) {
            AppSetting::set('landing_stat1_value', trim($validated['stats']['stat1_value']), 'landing');
        }
        if (isset($validated['stats']['stat1_label'])) {
            AppSetting::set('landing_stat1_label', trim($validated['stats']['stat1_label']), 'landing');
        }
        if (isset($validated['stats']['stat2_value'])) {
            AppSetting::set('landing_stat2_value', trim($validated['stats']['stat2_value']), 'landing');
        }
        if (isset($validated['stats']['stat2_label'])) {
            AppSetting::set('landing_stat2_label', trim($validated['stats']['stat2_label']), 'landing');
        }
        if (isset($validated['stats']['stat3_value'])) {
            AppSetting::set('landing_stat3_value', trim($validated['stats']['stat3_value']), 'landing');
        }
        if (isset($validated['stats']['stat3_label'])) {
            AppSetting::set('landing_stat3_label', trim($validated['stats']['stat3_label']), 'landing');
        }

        // Save Links & Contacts
        if (isset($validated['links']['whatsapp_number'])) {
            AppSetting::set('admin_whatsapp_number', trim($validated['links']['whatsapp_number']), 'contact');
        }
        if (isset($validated['links']['whatsapp_message'])) {
            AppSetting::set('admin_whatsapp_message', trim($validated['links']['whatsapp_message']), 'contact');
        }
        if (isset($validated['links']['email'])) {
            AppSetting::set('admin_email', trim($validated['links']['email']), 'contact');
        }
        if (isset($validated['links']['phone_number'])) {
            AppSetting::set('admin_phone_number', trim($validated['links']['phone_number']), 'contact');
        }
        if (isset($validated['links']['address'])) {
            AppSetting::set('admin_address', trim($validated['links']['address']), 'contact');
        }
        if (isset($validated['links']['shopee_url'])) {
            AppSetting::set('shopee_url', trim($validated['links']['shopee_url']), 'contact');
        }
        if (isset($validated['links']['instagram_url'])) {
            AppSetting::set('instagram_url', trim($validated['links']['instagram_url']), 'contact');
        }
        if (isset($validated['links']['tiktok_url'])) {
            AppSetting::set('tiktok_url', trim($validated['links']['tiktok_url']), 'contact');
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan landing page berhasil diperbarui.',
        ]);
    }

    /**
     * Upload an image specifically for the smartphone mockup frame.
     */
    public function uploadHeroImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120', // Max 5MB
        ]);

        $slot = (int) $request->input('slot', 1);
        if ($slot < 1 || $slot > 3) {
            $slot = 1;
        }

        $file = $request->file('image');
        $fileName = 'hero_phone_' . $slot . '_' . Str::random(12) . '.' . $file->getClientOriginalExtension();
        
        // Save to public storage disk under landing directory
        $path = $file->storeAs('landing', $fileName, 'public');
        $url = Storage::disk('public')->url($path);

        // Update corresponding slot setting
        $settingKey = $slot === 1 ? 'landing_hero_phone_image' : "landing_hero_phone_image_{$slot}";
        AppSetting::set($settingKey, $url, 'landing');

        return response()->json([
            'success' => true,
            'message' => "Gambar frame HP (Slot {$slot}) berhasil diunggah.",
            'url' => $url,
            'slot' => $slot,
        ]);
    }

    /**
     * Upload a screenshot image specifically for the visual builder mockup.
     */
    public function uploadBuilderImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120', // Max 5MB
        ]);

        $file = $request->file('image');
        $fileName = 'builder_screen_' . Str::random(12) . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs('landing', $fileName, 'public');
        $url = Storage::disk('public')->url($path);

        AppSetting::set('landing_builder_image', $url, 'landing');

        return response()->json([
            'success' => true,
            'message' => 'Gambar screenshot visual builder berhasil diunggah.',
            'url' => $url,
        ]);
    }
}
