<?php

declare(strict_types=1);

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Services\Google\GoogleDriveService;
use Illuminate\View\View;

/**
 * DemoPreviewController
 *
 * DEMO: Halaman placeholder preview file untuk versi demo. Menggantikan
 * pemanggilan drive.google.com dari browser. Route hanya terdaftar saat
 * config('demo.enabled') (lihat routes/web.php).
 */
class DemoPreviewController extends Controller
{
    /**
     * Menampilkan placeholder bergaya SIMDOK untuk sebuah file demo.
     */
    public function __invoke(string $fileId, GoogleDriveService $driveService): View
    {
        try {
            $file = $driveService->getMetadata($fileId);
        } catch (\Exception) {
            abort(404, 'File contoh tidak ditemukan.');
        }

        if ($file['isFolder']) {
            abort(404, 'File contoh tidak ditemukan.');
        }

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $kind = match (true) {
            in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) => 'image',
            $ext === 'pdf'                                              => 'pdf',
            default                                                     => 'file',
        };

        return view('drive.preview-demo', [
            'file' => $file,
            'kind' => $kind,
            'ext'  => $ext,
        ]);
    }
}
