<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * GoogleAuthException
 *
 * Dilempar ketika terjadi kegagalan autentikasi Google OAuth.
 * Contoh: Refresh Token tidak ditemukan, expired, atau gagal diperbarui.
 *
 * Sesuai dengan: 12_GOOGLE_API_SERVICE.md
 */
class GoogleAuthException extends RuntimeException {}
