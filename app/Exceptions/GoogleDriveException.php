<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * GoogleDriveException
 *
 * Dilempar ketika terjadi kegagalan operasi Google Drive API.
 * Contoh: folder tidak ditemukan, upload gagal, akses ditolak (403/404/429/500).
 *
 * Sesuai dengan: 12_GOOGLE_API_SERVICE.md
 */
class GoogleDriveException extends RuntimeException {}
