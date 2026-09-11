<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public const MAX_IMAGE_DIMENSION = 1920;
    public const WEBP_QUALITY = 82;
    public const THUMB_DIMENSION = 320;
    public const THUMB_QUALITY = 75;
    public const MAX_WEDDING_STORAGE_BYTES = 31457280; // 30 MB (30 * 1024 * 1024)

    /**
     * Upload and sanitize a media file for a wedding or as an admin global asset.
     */
    public function upload(
        UploadedFile $file,
        User $user,
        ?Wedding $wedding = null,
        ?string $category = 'general',
        ?array $tags = [],
        bool $isSystem = false,
        ?string $title = null,
        ?string $artist = null
    ): Media {
        $disk = 'public';
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize();
        $originalFilename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        $audioExtensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'wma'];
        $videoExtensions = ['mp4', 'webm', 'mov', 'avi', 'mkv'];

        // Determine media classification type
        $type = match (true) {
            $category === 'music' || in_array($extension, $audioExtensions) || str_starts_with($mime, 'audio/') => 'audio',
            $category === 'video' || in_array($extension, $videoExtensions) || str_starts_with($mime, 'video/') => 'video',
            $extension === 'svg' || $mime === 'image/svg+xml' => 'vector',
            str_starts_with($mime, 'image/') => 'image',
            default => 'image',
        };

        // Normalize mime type for audio if misdetected by finfo (e.g. converted MP3 misidentified as video/mp4)
        if ($type === 'audio' && (str_starts_with($mime, 'video/') || $mime === 'application/octet-stream')) {
            $mime = match ($extension) {
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'm4a' => 'audio/mp4',
                default => 'audio/mpeg',
            };
        }

        // Pre-check wedding media storage quota (30MB max per wedding)
        if (!$isSystem && $wedding) {
            $this->validateStoragePreCheck($wedding);
        }

        $uuid = (string) Str::uuid();
        $directory = $isSystem
            ? "media/global/{$type}"
            : "media/weddings/{$wedding?->id}/{$type}";

        $width = null;
        $height = null;
        $variants = null;

        // Process based on type
        if ($type === 'image') {
            $imageResult = $this->processAndStoreImage($file, $disk, $directory, $uuid, $wedding, $isSystem);
            $storagePath = $imageResult['path'];
            $mime = $imageResult['mime'];
            $size = $imageResult['size'];
            $width = $imageResult['width'];
            $height = $imageResult['height'];
            $variants = $imageResult['variants'];
        } elseif ($type === 'vector') {
            // SVG Sanitization
            $sanitizedSvg = $this->sanitizeSvg(file_get_contents($file->getRealPath()));
            $targetFilename = "{$uuid}.{$extension}";
            $storagePath = "{$directory}/{$targetFilename}";
            $size = strlen($sanitizedSvg);

            $this->assertStorageWithinQuota($wedding, $size, $isSystem);
            Storage::disk($disk)->put($storagePath, $sanitizedSvg);
        } else {
            // Audio or Video
            $this->assertStorageWithinQuota($wedding, $size, $isSystem);
            $targetFilename = "{$uuid}.{$extension}";
            $storagePath = $file->storeAs($directory, $targetFilename, $disk);
        }

        // If title is not specified and type is audio or video, fallback to clean filename
        if (empty($title) && ($type === 'audio' || $type === 'video')) {
            $title = pathinfo($originalFilename, PATHINFO_FILENAME);
        }

        // Create Media Model
        return Media::create([
            'user_id' => $user->id,
            'wedding_id' => $isSystem ? null : $wedding?->id,
            'type' => $type,
            'title' => $title,
            'artist' => $artist,
            'category' => $category,
            'tags' => $tags,
            'is_system' => $isSystem,
            'filename' => $originalFilename,
            'disk' => $disk,
            'path' => $storagePath,
            'mime_type' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'variants' => $variants,
            'processing_status' => 'done',
            'usage_count' => 0,
        ]);
    }

    /**
     * Process raster image: convert to WebP, auto-downscale if exceeding max dimension (1920px),
     * auto-orient based on EXIF, and generate lightweight thumbnail.
     */
    protected function processAndStoreImage(
        UploadedFile $file,
        string $disk,
        string $directory,
        string $uuid,
        ?Wedding $wedding,
        bool $isSystem
    ): array {
        $realPath = $file->getRealPath();
        $ext = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        $fileMime = $file->getMimeType() ?: 'image/jpeg';

        // Animated GIF: preserve original format without WebP conversion to keep animation frames, loop, and alpha transparency intact
        if ($ext === 'gif' || $fileMime === 'image/gif') {
            $rawPath = $file->storeAs($directory, "{$uuid}.gif", $disk);
            $size = $file->getSize();
            $this->assertStorageWithinQuota($wedding, $size, $isSystem);
            $info = @getimagesize($realPath);

            return [
                'path' => $rawPath,
                'mime' => 'image/gif',
                'size' => $size,
                'width' => $info[0] ?? null,
                'height' => $info[1] ?? null,
                'variants' => null,
            ];
        }

        $targetFilename = "{$uuid}.webp";
        $storagePath = "{$directory}/{$targetFilename}";

        // If GD or imagewebp is not available or file is unreadable, fallback to direct storage
        if (!extension_loaded('gd') || !function_exists('imagewebp') || !file_exists($realPath)) {
            $ext = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
            $rawPath = $file->storeAs($directory, "{$uuid}.{$ext}", $disk);
            $size = $file->getSize();
            $this->assertStorageWithinQuota($wedding, $size, $isSystem);
            $info = @getimagesize($realPath);

            return [
                'path' => $rawPath,
                'mime' => $file->getMimeType() ?: 'image/jpeg',
                'size' => $size,
                'width' => $info[0] ?? null,
                'height' => $info[1] ?? null,
                'variants' => null,
            ];
        }

        $fileContent = @file_get_contents($realPath);
        if ($fileContent === false) {
            abort(422, 'Gagal membaca file gambar yang diunggah.');
        }

        $srcImage = @imagecreatefromstring($fileContent);
        if (!$srcImage) {
            // Fallback for formats GD cannot decode
            $ext = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
            $rawPath = $file->storeAs($directory, "{$uuid}.{$ext}", $disk);
            $size = $file->getSize();
            $this->assertStorageWithinQuota($wedding, $size, $isSystem);
            $info = @getimagesize($realPath);

            return [
                'path' => $rawPath,
                'mime' => $file->getMimeType() ?: 'image/jpeg',
                'size' => $size,
                'width' => $info[0] ?? null,
                'height' => $info[1] ?? null,
                'variants' => null,
            ];
        }

        // 1. Auto-orient according to EXIF if available (JPEG images from cameras/phones)
        $srcImage = $this->autoOrientImage($srcImage, $realPath);

        $origW = imagesx($srcImage);
        $origH = imagesy($srcImage);

        // 2. Downscale if dimension exceeds MAX_IMAGE_DIMENSION (1920px), otherwise preserve exact dimensions
        if ($origW > self::MAX_IMAGE_DIMENSION || $origH > self::MAX_IMAGE_DIMENSION) {
            if ($origW >= $origH) {
                $targetW = self::MAX_IMAGE_DIMENSION;
                $targetH = (int) max(1, round(($origH / $origW) * self::MAX_IMAGE_DIMENSION));
            } else {
                $targetH = self::MAX_IMAGE_DIMENSION;
                $targetW = (int) max(1, round(($origW / $origH) * self::MAX_IMAGE_DIMENSION));
            }
        } else {
            $targetW = $origW;
            $targetH = $origH;
        }

        // Create main canvas with alpha transparency support
        $mainCanvas = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($mainCanvas, false);
        imagesavealpha($mainCanvas, true);
        $transparent = imagecolorallocatealpha($mainCanvas, 0, 0, 0, 127);
        imagefilledrectangle($mainCanvas, 0, 0, $targetW, $targetH, $transparent);

        imagecopyresampled($mainCanvas, $srcImage, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

        // Encode main image to WebP buffer
        ob_start();
        imagewebp($mainCanvas, null, self::WEBP_QUALITY);
        $webpData = (string) ob_get_clean();

        // 3. Generate thumbnail variant (max 320px)
        if ($origW > self::THUMB_DIMENSION || $origH > self::THUMB_DIMENSION) {
            if ($origW >= $origH) {
                $thumbW = self::THUMB_DIMENSION;
                $thumbH = (int) max(1, round(($origH / $origW) * self::THUMB_DIMENSION));
            } else {
                $thumbH = self::THUMB_DIMENSION;
                $thumbW = (int) max(1, round(($origW / $origH) * self::THUMB_DIMENSION));
            }
        } else {
            $thumbW = $origW;
            $thumbH = $origH;
        }

        $thumbCanvas = imagecreatetruecolor($thumbW, $thumbH);
        imagealphablending($thumbCanvas, false);
        imagesavealpha($thumbCanvas, true);
        $thumbTransparent = imagecolorallocatealpha($thumbCanvas, 0, 0, 0, 127);
        imagefilledrectangle($thumbCanvas, 0, 0, $thumbW, $thumbH, $thumbTransparent);

        imagecopyresampled($thumbCanvas, $srcImage, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);

        ob_start();
        imagewebp($thumbCanvas, null, self::THUMB_QUALITY);
        $thumbWebpData = (string) ob_get_clean();

        // Clean up GD resources
        imagedestroy($srcImage);
        imagedestroy($mainCanvas);
        imagedestroy($thumbCanvas);

        $mainSize = strlen($webpData);
        $thumbSize = strlen($thumbWebpData);
        $totalNewSize = $mainSize + $thumbSize;

        // Check storage quota with the actual WebP byte size
        $this->assertStorageWithinQuota($wedding, $totalNewSize, $isSystem);

        // Save main WebP image
        Storage::disk($disk)->put($storagePath, $webpData);

        // Save thumbnail variant
        $thumbStoragePath = "{$directory}/thumbs/{$uuid}_thumb.webp";
        Storage::disk($disk)->put($thumbStoragePath, $thumbWebpData);

        return [
            'path' => $storagePath,
            'mime' => 'image/webp',
            'size' => $mainSize,
            'width' => $targetW,
            'height' => $targetH,
            'variants' => ['thumb' => $thumbStoragePath],
        ];
    }

    /**
     * Auto-orient image based on EXIF metadata (e.g. mobile photos).
     */
    protected function autoOrientImage(\GdImage $image, string $filePath): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data($filePath);
            if (!empty($exif['Orientation'])) {
                $orientation = (int) $exif['Orientation'];
                $rotated = match ($orientation) {
                    3 => imagerotate($image, 180, 0),
                    6 => imagerotate($image, -90, 0),
                    8 => imagerotate($image, 90, 0),
                    default => null,
                };

                if ($rotated !== null && $rotated !== false) {
                    imagedestroy($image);
                    return $rotated;
                }
            }
        } catch (\Throwable) {
            // Ignore any EXIF parse error
        }

        return $image;
    }

    /**
     * Validate that wedding storage is not already at or exceeding the 30MB limit.
     */
    public function validateStoragePreCheck(Wedding $wedding): void
    {
        $currentUsage = (int) Media::where('wedding_id', $wedding->id)->sum('size');
        if ($currentUsage >= self::MAX_WEDDING_STORAGE_BYTES) {
            abort(422, 'Kapasitas penyimpanan media untuk undangan ini telah mencapai batas maksimal (30 MB). Silakan hapus media yang tidak terpakai.');
        }
    }

    /**
     * Assert that adding the incoming file size will not breach the 30MB wedding storage quota.
     */
    public function assertStorageWithinQuota(?Wedding $wedding, int $incomingBytes, bool $isSystem = false): void
    {
        if ($isSystem || !$wedding) {
            return;
        }

        $currentUsage = (int) Media::where('wedding_id', $wedding->id)->sum('size');
        if (($currentUsage + $incomingBytes) > self::MAX_WEDDING_STORAGE_BYTES) {
            $remaining = max(0, self::MAX_WEDDING_STORAGE_BYTES - $currentUsage);
            $remainingFormatted = $this->formatBytes($remaining);
            abort(422, "Ukuran file melebihi sisa kapasitas penyimpanan undangan (Maksimal 30 MB per undangan). Sisa kapasitas: {$remainingFormatted}. Silakan hapus media yang tidak terpakai.");
        }
    }

    /**
     * Get storage usage statistics for a specific wedding.
     */
    public function getWeddingStorageUsage(int|string $weddingId): array
    {
        $usedBytes = (int) Media::where('wedding_id', $weddingId)->sum('size');
        $maxBytes = self::MAX_WEDDING_STORAGE_BYTES;
        $percentage = $maxBytes > 0 ? round(($usedBytes / $maxBytes) * 100, 1) : 0;

        return [
            'usedBytes' => $usedBytes,
            'maxBytes' => $maxBytes,
            'usedFormatted' => $this->formatBytes($usedBytes),
            'maxFormatted' => $this->formatBytes($maxBytes),
            'percentage' => min(100.0, $percentage),
            'remainingBytes' => max(0, $maxBytes - $usedBytes),
            'remainingFormatted' => $this->formatBytes(max(0, $maxBytes - $usedBytes)),
        ];
    }

    /**
     * Format bytes into human-readable string (B, KB, MB, GB).
     */
    public function formatBytes(int $bytes, int $precision = 1): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = max(0, min($power, count($units) - 1));

        $value = $bytes / pow(1024, $power);

        return round($value, $precision) . ' ' . $units[$power];
    }

    /**
     * Sanitize SVG markup by removing dangerous scripts, foreign objects, and event handlers.
     */
    public function sanitizeSvg(string $svgContent): string
    {
        // 1. Remove XML declarations and DOCTYPE with entity declarations to prevent XXE
        $svgContent = preg_replace('/<\?xml.*?\?>/i', '', $svgContent);
        $svgContent = preg_replace('/<!DOCTYPE.*?>/i', '', $svgContent);

        // 2. Strip dangerous tags: <script>, <foreignObject>, <iframe>, <embed>, <object>
        $svgContent = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $svgContent);
        $svgContent = preg_replace('/<foreignObject\b[^>]*>(.*?)<\/foreignObject>/is', '', $svgContent);
        $svgContent = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $svgContent);
        $svgContent = preg_replace('/<object\b[^>]*>(.*?)<\/object>/is', '', $svgContent);

        // 3. Strip all inline JS event handlers: on* (e.g. onload, onclick, onerror)
        $svgContent = preg_replace('/\s+on[a-z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)/i', '', $svgContent);

        // 4. Strip javascript: URLs in href / xlink:href
        $svgContent = preg_replace('/href\s*=\s*["\']\s*javascript:[^"\']*["\']/i', '', $svgContent);
        $svgContent = preg_replace('/xlink:href\s*=\s*["\']\s*javascript:[^"\']*["\']/i', '', $svgContent);

        return trim($svgContent);
    }

    /**
     * Delete media item and clean up physical storage.
     */
    public function deleteMedia(Media $media): array
    {
        // Delete physical file from storage disk to free up server storage
        if ($media->path && Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }

        // Clean up any generated variant files if present
        if (!empty($media->variants) && is_array($media->variants)) {
            foreach ($media->variants as $variantPath) {
                if (is_string($variantPath) && Storage::disk($media->disk)->exists($variantPath)) {
                    Storage::disk($media->disk)->delete($variantPath);
                }
            }
        }

        if ($media->usage_count > 0) {
            // Protected from hard deletion; soft delete only with warning
            $media->delete();
            return [
                'deleted' => true,
                'softDeleted' => true,
                'message' => 'Media sedang digunakan dalam undangan dan telah dipindahkan ke sampah.',
            ];
        }

        $media->delete();

        return [
            'deleted' => true,
            'softDeleted' => true,
            'message' => 'Media berhasil dihapus dari galeri dan penyimpanan.',
        ];
    }
}

