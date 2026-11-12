# 07_UI_UX_GUIDELINE.md

# Smart Document Automation Dashboard

## UI / UX Guideline

Version: 1.0

---

# 1. Design Philosophy

UI harus terasa seperti aplikasi dashboard modern, ringan, profesional,
dan mudah digunakan oleh administrator.

Inspirasi visual menggunakan dashboard modern seperti referensi yang
diberikan pengguna: - Sidebar sederhana - Card dengan sudut membulat -
Banyak ruang kosong (whitespace) - Warna lembut - Fokus pada data

Namun identitas visual mengikuti branding PLN, bukan warna ungu.

---

# 2. Design Goals

- Mudah dipahami pengguna non-teknis.
- Navigasi maksimal 2 klik menuju fitur utama.
- Fokus pada monitoring.
- Tampilan bersih.
- Responsif.

---

# 3. Color Palette

## Primary

PLN Blue

```text
#0F6CBD
```

## Success

```text
#16A34A
```

## Danger

```text
#DC2626
```

## Warning

```text
#F59E0B
```

## Background

```text
#F8FAFC
```

## Card

```text
#FFFFFF
```

---

# 4. Typography

Font:

- Inter

Ukuran:

- Heading : 30px
- Title : 22px
- Subtitle : 18px
- Body : 14-16px
- Caption : 12px

---

# 5. Layout

```text
+------------------------------------------------------+
| Header                                                |
+-----------+------------------------------------------+
| Sidebar   | Content                                  |
|           |                                          |
|           | Dashboard / Drive / Monitoring           |
|           |                                          |
+-----------+------------------------------------------+
```

---

# 6. Sidebar

Menu hanya terdiri dari:

- Dashboard
- Drive Explorer
- Monitoring

Sidebar:

- Lebar 260px
- Fixed
- Icon + Text
- Active Menu menggunakan warna biru PLN

---

# 7. Header

Header berisi:

- Judul halaman
- Search (opsional)
- Tombol Refresh
- Avatar pengguna (opsional)

Header selalu sticky.

---

# 8. Dashboard

Dashboard terdiri dari:

## Summary Cards

- Total Unit
- Sudah Upload
- Belum Upload
- Progress

Gunakan card horizontal.

## Progress Card

Progress Semester

Progress Bulan

Progress Daerah

---

# 9. Drive Explorer

Tampilan seperti File Explorer.

Komponen:

- Breadcrumb
- Toolbar
- Folder Table
- Upload Button
- Create Folder Button

Tidak menggunakan tampilan grid.

Gunakan tabel agar mudah membaca data.

---

# 10. Monitoring

Monitoring menjadi halaman utama setelah Dashboard.

Gunakan tabel.

Kolom:

| Unit \| Jan \| Feb \| Mar \| Apr \| Mei \| Jun \|

Status menggunakan badge bulat.

🟢 Complete

🔴 Incomplete

Klik badge merah membuka Drive Explorer.

---

# 11. Cards & Containers (Clean & Borderless)

Semua card dan container WAJIB mengikuti prinsip **Clean & Borderless**:

- **Borderless**: TIDAK boleh menggunakan border/outline (hapus `border`, `border-slate-100`, `border-slate-200`, `border-slate-50`).
- **Rounded 2XL**: Menggunakan sudut membulat `rounded-2xl`.
- **Soft Shadow**: Menggunakan bayangan sangat halus `shadow-sm` untuk pemisahan visual.
- **Padding**: Spacing & padding 20px–24px.
- **Hindari garis/outline pembatas**: Tidak menggunakan garis outline di sekeliling container, header card, maupun antar-item dropdown/accordion.

---

# 12. Buttons

Primary

- Biru PLN

Secondary

- Putih

Danger

- Merah

Success

- Hijau

Semua button memiliki icon.

---

# 13. Forms

Gunakan:

- Label di atas
- Border abu muda
- Focus biru PLN
- Error merah

---

# 14. Tables

Gunakan:

- Sticky Header
- Zebra Row ringan
- Hover Row
- Rounded Container

---

# 15. Icons

Gunakan Heroicons.

Contoh:

Dashboard

Folder

Upload

Download

Refresh

Search

Monitoring

---

# 16. Responsive

Desktop

Sidebar tampil.

Tablet

Sidebar collapse.

Mobile

Sidebar menjadi drawer.

---

# 17. Empty State

Jika belum ada data.

```text
📂

Belum ada dokumen.

Silakan upload dokumen terlebih dahulu.
```

---

# 18. Loading State

Gunakan:

- Skeleton Card
- Skeleton Table

Hindari spinner penuh.

---

# 19. Animation

Gunakan animasi ringan.

Durasi:

150--250ms.

Tidak menggunakan animasi berlebihan.

---

# 20. Tailwind CSS Rules

Gunakan utility class.

Hindari inline style.

Gunakan Blade Components untuk:

- Card
- Button
- Badge
- Table
- Modal

---

# 21. UI Principles

- Konsisten.
- Minimalis.
- Profesional.
- Cepat dipahami.
- Fokus pada produktivitas.

Referensi UI yang diberikan menjadi inspirasi tata letak dan kepadatan
informasi, sedangkan identitas visual disesuaikan dengan branding PLN
dan kebutuhan Smart Document Automation Dashboard.
