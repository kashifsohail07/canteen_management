<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImageService
{
    public const MAX_WIDTH = 1600;

    public const JPEG_QUALITY = 75;

    public const MAX_BYTES = 10 * 1024 * 1024;

    public const DISK = 'local'; // private disk (storage/app/private)

    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Validate, compress and store an invoice/bill photo. Returns the relative path on the private disk.
     */
    public function storeInvoice(UploadedFile $file, string $field = 'bill_image'): string
    {
        $this->validate($file, $field);

        $directory = 'invoices/'.now()->format('Y/m');
        $contents = null;
        $extension = 'jpg';

        if ($this->gdAvailable()) {
            $contents = $this->compress($file);
        }

        if ($contents === null) {
            // GD unavailable or image could not be processed: store original bytes
            $contents = file_get_contents($file->getRealPath());
            $extension = match ($file->getMimeType()) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };
        }

        $path = $directory.'/'.Str::uuid().'.'.$extension;
        Storage::disk(self::DISK)->put($path, $contents);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public function exists(?string $path): bool
    {
        return $path !== null && Storage::disk(self::DISK)->exists($path);
    }

    private function validate(UploadedFile $file, string $field): void
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([$field => 'The image upload failed. Please try again.']);
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field => 'The image must not be larger than 10 MB.']);
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([$field => 'The image must be a JPEG, PNG or WebP file.']);
        }

        if (@getimagesize($file->getRealPath()) === false) {
            throw ValidationException::withMessages([$field => 'The uploaded file is not a valid image.']);
        }
    }

    private function gdAvailable(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagecreatefromstring')
            && function_exists('imagejpeg');
    }

    /** Returns JPEG bytes (resized to max width, quality 75) or null on failure. */
    private function compress(UploadedFile $file): ?string
    {
        $raw = file_get_contents($file->getRealPath());
        $source = @imagecreatefromstring($raw);

        if ($source === false) {
            return null;
        }

        // Respect EXIF orientation from phone cameras
        if ($file->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file->getRealPath());
            $orientation = $exif['Orientation'] ?? 1;
            $angle = match ($orientation) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };
            if ($angle !== 0) {
                $rotated = imagerotate($source, $angle, 0);
                if ($rotated !== false) {
                    imagedestroy($source);
                    $source = $rotated;
                }
            }
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $newWidth = min($width, self::MAX_WIDTH);
        $newHeight = (int) round($height * ($newWidth / $width));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        // flatten transparency (PNG/WebP) onto white so the JPEG has no black background
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);
        $bytes = ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $bytes === false || $bytes === '' ? null : $bytes;
    }
}
