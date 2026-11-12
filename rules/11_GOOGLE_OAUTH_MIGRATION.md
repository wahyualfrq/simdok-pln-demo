# 11_GOOGLE_OAUTH_MIGRATION.md

# Google OAuth Migration
## SIMDOK - Sistem Monitoring Dokumen

---

# Tujuan

Mengganti seluruh mekanisme autentikasi Google dari Service Account menjadi OAuth 2.0.

Alasan:

- Project menggunakan Gmail Personal.
- Service Account memiliki keterbatasan storage (storageQuotaExceeded).
- OAuth menggunakan akun Google milik user sehingga seluruh file tersimpan pada Google Drive user.
- Seluruh operasi Google Drive dan Google Sheets menggunakan satu autentikasi.

Setelah migrasi selesai:

✓ Upload File
✓ Download File
✓ Rename Folder
✓ Rename File
✓ Delete Folder
✓ Delete File
✓ Create Folder
✓ Google Sheet Monitoring

semuanya menggunakan OAuth.

---

# Scope

Refactor autentikasi Google tanpa mengubah fitur yang sudah ada.

Jangan mengubah:

- Dashboard
- UI
- Folder Generator
- Monitoring
- Sidebar
- Route selain autentikasi Google

---

# Yang harus dihapus

Hapus seluruh penggunaan:

Service Account

Contoh:

GOOGLE_CREDENTIALS_PATH

storage/app/google/service-account.json

Google Client menggunakan Service Account

hapus dependency tersebut.

---

# Yang digunakan

Gunakan OAuth 2.0 resmi Google.

Library:

google/apiclient

Jangan menggunakan Socialite.

---

# Struktur Service

Buat service baru

app/Services/

GoogleOAuthService.php

Tugas:

- Redirect Login Google
- Handle Callback
- Menukar Authorization Code menjadi Access Token
- Menyimpan Refresh Token
- Mengambil Access Token baru dari Refresh Token
- Menghasilkan Google Client siap pakai

GoogleDriveService.php

Refactor agar menggunakan GoogleOAuthService.

Jangan ada lagi kode Service Account.

GoogleSheetService.php

Refactor agar menggunakan GoogleOAuthService.

---

# OAuth Flow

User membuka

/auth/google

↓

Redirect ke Google Login

↓

User login

↓

Google Callback

↓

Authorization Code

↓

Access Token

↓

Refresh Token

↓

Simpan Refresh Token

↓

Semua request berikutnya menggunakan Refresh Token.

Login hanya sekali.

---

# Penyimpanan Token

Buat folder

storage/app/google/

Buat file

token.json

Contoh

{
    "refresh_token": "...",
    "created_at": "...",
    "email": "..."
}

Refresh Token menjadi source utama autentikasi.

Jangan menyimpan Access Token.

Access Token selalu dibuat ulang otomatis.

---

# Environment

Tambahkan

GOOGLE_CLIENT_ID=

GOOGLE_CLIENT_SECRET=

GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback

Hapus

GOOGLE_CREDENTIALS_PATH

---

# Route

GET

/auth/google

Redirect OAuth

GET

/auth/google/callback

Handle Callback

---

# Controller

Buat

GoogleOAuthController

Method

redirect()

callback()

disconnect()

status()

---

# Google Client

Seluruh Google Client harus dibuat melalui

GoogleOAuthService

Tidak boleh membuat instance Google Client langsung di Controller.

---

# Drive Service

Semua method lama tetap dipakai.

Contoh

list()

upload()

download()

rename()

delete()

createFolder()

search()

Hanya sumber autentikasi yang berubah.

---

# Sheet Service

Semua method tetap.

Contoh

readChecklist()

updateChecklist()

findSemester()

findMonth()

Menggunakan OAuth Client.

---

# UI

Tambahkan halaman

Google Connection

Status

Connected

atau

Not Connected

Jika belum login tampilkan tombol

Connect Google

Jika sudah login tampilkan

Connected as

user@example.com

---

# Error Handling

Tangani

Expired Token

Refresh gagal

Refresh Token tidak ditemukan

Google API Error

Tampilkan pesan yang jelas.

---

# Coding Standard

Gunakan

Clean Architecture

SOLID

Repository Pattern bila diperlukan

Service Layer

Dependency Injection

Typed Property

PHP 8.3

Laravel 12

Tidak boleh ada Business Logic di Controller.

---

# Target

Setelah implementasi selesai:

✓ Login Google sekali

✓ Refresh Token tersimpan

✓ Upload berjalan

✓ Rename berjalan

✓ Delete berjalan

✓ Folder Generator berjalan

✓ Monitoring Google Sheet berjalan

✓ Tidak ada lagi Service Account

✓ Tidak ada lagi error

storageQuotaExceeded