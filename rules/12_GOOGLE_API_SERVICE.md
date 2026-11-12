# 12_GOOGLE_API_SERVICE.md

# GOOGLE API SERVICE STANDARD

## SIMDOK - Sistem Monitoring Dokumen

Version : 1.0

---

# Tujuan

Dokumen ini menjadi standar implementasi seluruh integrasi Google API pada proyek SIMDOK.

Semua komunikasi dengan:

- Google Drive
- Google Sheets
- Google OAuth

harus melalui Service Layer.

Controller tidak boleh mengakses Google API secara langsung.

---

# Architecture

Laravel

↓

Controller

↓

Service

↓

Google API Client

↓

Google Drive API

Google Sheets API

Google OAuth

---

# Service Structure

app/

Services/

Google/

│

├── GoogleOAuthService.php

├── GoogleDriveService.php

├── GoogleSheetService.php

└── GoogleApiService.php

---

# Responsibility

## GoogleOAuthService

Bertanggung jawab untuk:

- Redirect OAuth
- Callback OAuth
- Menyimpan Refresh Token
- Mengambil Access Token
- Refresh Token otomatis
- Logout Google

Service ini menjadi satu-satunya tempat autentikasi Google.

---

## GoogleApiService

Service pusat.

Berfungsi membuat Google Client yang siap digunakan.

Contoh

Google\Client

↓

Access Token

↓

Refresh otomatis

↓

Return Client

Semua service lain wajib mengambil Google Client dari sini.

Jangan membuat Google Client baru di service lain.

---

## GoogleDriveService

Seluruh operasi Google Drive.

Contoh

listFolder()

listFiles()

createFolder()

upload()

download()

rename()

move()

delete()

search()

copy()

getMetadata()

Semua method hanya fokus pada Google Drive.

Tidak menangani autentikasi.

---

## GoogleSheetService

Seluruh operasi Google Spreadsheet.

Method

read()

write()

update()

append()

clear()

getRange()

findSemester()

findMonth()

readChecklist()

updateChecklist()

Tidak boleh mengakses OAuth.

Google Client diperoleh dari GoogleApiService.

---

# Dependency Flow

GoogleOAuthService

↓

GoogleApiService

↓

GoogleDriveService

↓

Controller

dan

GoogleOAuthService

↓

GoogleApiService

↓

GoogleSheetService

↓

Controller

---

# Storage

Folder

storage/

app/

google/

Berisi

oauth-client.json

token.json

Tidak boleh ada Service Account lagi.

---

# OAuth Token

Yang disimpan

Refresh Token

Contoh

{

"email":"user@example.com",

"refresh_token":"xxxxxxxx",

"created_at":"2026-07-08"

}

Jangan menyimpan Access Token.

Access Token selalu dibuat ulang.

---

# Environment

Gunakan

GOOGLE_CLIENT_ID=

GOOGLE_CLIENT_SECRET=

GOOGLE_REDIRECT_URI=

GOOGLE_DRIVE_ROOT_FOLDER_ID=

GOOGLE_SHEET_ID=

Hapus

GOOGLE_CREDENTIALS_PATH

---

# Google Client

Semua Google Client dibuat melalui

GoogleApiService

Contoh

GoogleDriveService

↓

GoogleApiService

↓

Google Client

Controller tidak boleh melakukan

new Google\Client()

---

# Folder Generator

GoogleDriveService

bertanggung jawab

createSemester()

createMonth()

createRegion()

createUnit()

createSubUnit()

Semua folder menggunakan

UPPERCASE

Contoh

JAN 2026

↓

PLN UP3 PALEMBANG

↓

TL LAKDAN

↓

A. MULP KENTEN

---

# Upload File

Flow

Upload Request

↓

Validation

↓

GoogleDriveService

↓

Google Drive

↓

Response

Tidak boleh upload langsung dari Controller.

---

# Rename

Rename Folder

Rename File

Semua melalui GoogleDriveService

---

# Delete

Delete Folder

Delete File

Gunakan Soft Confirmation pada UI.

GoogleDriveService bertanggung jawab menjalankan API.

---

# Monitoring

Monitoring tidak membaca Google Drive langsung.

Flow

GoogleSheetService

↓

Google Spreadsheet

↓

Mapping

↓

Monitoring Page

Status

Hijau

Merah

Tidak ada Business Logic di Blade.

---

# Error Handling

Tangani

Refresh Token Expired

Google Exception

404

403

401

429

500

Gunakan Exception khusus.

Contoh

GoogleAuthenticationException

GoogleDriveException

GoogleSheetException

---

# Logging

Gunakan

storage/logs/laravel.log

Log

Upload

Delete

Rename

OAuth Error

Google API Error

Refresh Token Error

Jangan pernah mencatat

Client Secret

Refresh Token

Access Token

ke log.

---

# Performance

Gunakan

Lazy Loading

Pagination

Chunk Upload

Caching bila diperlukan.

Jangan request Google API berulang.

---

# Security

Semua Credential berada di

.env

oauth-client.json

storage/app/google/

Tidak boleh berada di

public/

resources/

GitHub Repository

Tambahkan

storage/app/google/

ke

.gitignore

---

# Coding Rules

Gunakan

PHP 8.3

Laravel 12

Service Layer

Dependency Injection

Strict Typing

Return Type

Exception Handling

SOLID

Clean Code

Controller maksimal menangani:

- Request
- Validation
- Response

Seluruh Business Logic berada di Service.

---

# Naming Convention

Method

camelCase

Contoh

createFolder()

uploadFile()

renameFolder()

deleteFolder()

readSpreadsheet()

updateChecklist()

Class

PascalCase

Contoh

GoogleDriveService

GoogleSheetService

GoogleOAuthService

GoogleApiService

---

# Future Feature

Google Drive Preview

Google Drive Thumbnail

Multiple Upload

Drag and Drop Upload

Export Monitoring

Dashboard Statistics

Google Drive Search

Recycle Bin

Activity Log

Notification

---

# Final Target

SIMDOK memiliki satu standar integrasi Google.

✓ OAuth

✓ Google Drive

✓ Google Sheets

✓ Monitoring

✓ Folder Generator

✓ Upload

✓ Rename

✓ Delete

✓ Download

✓ Auto Checklist

Seluruh fitur menggunakan Service Layer dan Google API resmi sehingga arsitektur tetap bersih, konsisten, mudah dikembangkan, dan mudah dipelihara.