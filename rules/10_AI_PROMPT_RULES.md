# 10_AI_PROMPT_RULES.md

# Smart Document Automation Dashboard

## AI Prompt Rules & Development Principles

Version: 1.0

------------------------------------------------------------------------

# Purpose

Dokumen ini menjadi aturan wajib bagi AI Coding Assistant (Cursor,
Claude Code, Codex, Gemini CLI, ChatGPT, dsb.) saat menghasilkan kode.

Prioritas utama:

-   Clean Architecture
-   Clean Code
-   High Performance
-   Maintainability
-   Scalability
-   Simplicity

AI harus mengikuti seluruh aturan pada dokumen ini.

------------------------------------------------------------------------

# Core Principles

AI harus selalu mengutamakan:

-   Readability
-   Maintainability
-   Reusability
-   Performance
-   Security

Jangan menghasilkan kode yang hanya "berjalan", tetapi sulit dipelihara.

------------------------------------------------------------------------

# Technology Stack

Gunakan HANYA:

-   Laravel
-   Blade
-   Tailwind CSS
-   Google Drive API

Jangan gunakan:

-   React
-   Vue
-   Livewire
-   Inertia
-   Alpine sebagai fondasi utama aplikasi
-   JQuery

------------------------------------------------------------------------

# Architecture

Wajib mengikuti alur berikut.

``` text
Routes
   │
Controller
   │
Service
   │
Google Drive API
```

Business Logic hanya boleh berada di Service.

------------------------------------------------------------------------

# Clean Architecture Rules

Controller bertugas:

-   menerima request
-   validasi
-   memanggil service
-   redirect / response

Controller tidak boleh:

-   menghitung progress
-   scan Google Drive
-   upload logic
-   create folder logic
-   business rule

------------------------------------------------------------------------

Service bertugas:

-   seluruh business logic
-   komunikasi Google Drive
-   monitoring
-   progress calculation

------------------------------------------------------------------------

Blade bertugas:

-   hanya menampilkan data

Blade tidak boleh:

-   query
-   business logic
-   perhitungan

------------------------------------------------------------------------

# Clean Code Rules

Kode harus:

-   mudah dibaca
-   mudah diuji
-   mudah dikembangkan

Gunakan:

-   meaningful variable names
-   meaningful method names
-   single responsibility
-   early return
-   guard clause

Hindari:

-   nested if berlebihan
-   method panjang
-   duplicate code
-   magic number
-   hardcoded value

------------------------------------------------------------------------

# Performance Rules

Target:

Dashboard terasa cepat.

Drive Explorer responsif.

Monitoring efisien.

AI harus:

-   mengambil field Google Drive yang diperlukan saja
-   menghindari request berulang
-   menggunakan eager processing seperlunya
-   tidak melakukan loop API yang tidak perlu

Hindari N+1 request.

------------------------------------------------------------------------

# Laravel Rules

Gunakan:

-   Form Request
-   Blade Components
-   Service Layer
-   Config
-   Helper bila diperlukan

Jangan:

-   query Google API di Controller
-   menaruh credential di source code
-   hardcode Folder ID

------------------------------------------------------------------------

# Google Drive Rules

Google Drive adalah:

Single Source of Truth.

Tidak membuat tabel:

-   folders
-   files
-   monitoring

Status dihitung secara langsung dari Google Drive.

------------------------------------------------------------------------

# UI Rules

Selalu mengikuti:

07_UI_UX_GUIDELINE.md

Prioritas:

-   sederhana
-   modern
-   whitespace
-   responsive
-   konsisten

------------------------------------------------------------------------

# Blade Components

Gunakan komponen reusable.

Contoh:

-   x-card
-   x-button
-   x-badge
-   x-modal
-   x-table
-   x-breadcrumb

------------------------------------------------------------------------

# Error Handling

Gunakan:

try/catch

Logging Laravel

Flash Message

Jangan menampilkan stack trace.

------------------------------------------------------------------------

# Security

Validasi:

-   ukuran file
-   mime type
-   input

Gunakan CSRF.

Escape output Blade.

------------------------------------------------------------------------

# Naming Convention

Method:

listFolders()

uploadFile()

createFolder()

calculateProgress()

scanFolder()

Gunakan nama yang jelas.

------------------------------------------------------------------------

# Fast Web Principles

Target:

-   halaman cepat dirender
-   sedikit query API
-   minim JavaScript
-   Blade rendering
-   lazy loading bila diperlukan
-   asset ringan

Utamakan server-side rendering.

------------------------------------------------------------------------

# AI Output Rules

Saat menghasilkan kode, AI wajib:

-   mengikuti seluruh dokumentasi proyek
-   tidak membuat struktur baru tanpa alasan
-   menjaga konsistensi arsitektur
-   menulis kode yang siap production
-   mengutamakan maintainability dibanding kompleksitas

Jika terdapat beberapa solusi, pilih solusi yang:

1.  paling sederhana
2.  paling mudah dipelihara
3.  paling cepat dijalankan
4.  paling sesuai dengan Laravel Best Practice

------------------------------------------------------------------------

# Definition of Good Code

Kode dianggap baik apabila:

-   mudah dibaca
-   mudah dipahami developer lain
-   minim duplikasi
-   mengikuti SOLID
-   mengikuti KISS
-   mengikuti DRY
-   mengikuti YAGNI
-   performa baik
-   aman
-   mudah dikembangkan

------------------------------------------------------------------------

# Final Principle

Smart Document Automation Dashboard adalah aplikasi internal yang harus:

-   sederhana
-   cepat
-   stabil
-   scalable
-   clean architecture
-   clean code
-   fast web

AI harus selalu menghasilkan implementasi yang mendukung tujuan
tersebut.
