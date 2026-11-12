<?php

declare(strict_types=1);

namespace App\Services\Demo;

use RuntimeException;

/**
 * DemoDriveStore
 *
 * DEMO: Lapisan overlay mutasi di atas pohon dummy (DemoDriveTree).
 * Pohon dasar tidak pernah diubah; semua mutasi (buat folder, upload,
 * ganti nama, hapus) dicatat di berkas JSON dan digabung saat membaca.
 *
 * - Lokasi: storage_path('app/demo/drive_overlay_<kunci>.json')
 *   (di Vercel storage diarahkan ke /tmp). Tidak memakai Cache sehingga
 *   tidak ikut terhapus oleh CacheService::invalidateAll().
 * - <kunci> = hash ID session pengunjung (isolasi antar pengunjung);
 *   tanpa session (konsol) dipakai kunci 'shared'.
 * - Berkas overlay yang lebih tua dari config('demo.drive.overlay_ttl_hours')
 *   dibersihkan saat menulis.
 *
 * Struktur berkas: {seq, created: {id: node}, renamed: {id: name}, deleted: {id: true}}
 *
 * Sesuai dengan: rules/14_DEMO_MOCK_SPEC.md §5.3
 */
class DemoDriveStore
{
    public function __construct(private readonly DemoDriveTree $tree) {}

    // ─────────────────────────────────────────────────────────
    // MEMBACA (pohon dasar + overlay)
    // ─────────────────────────────────────────────────────────

    /**
     * Node (sudah digabung overlay) atau null bila tidak ada / sudah dihapus.
     */
    public function find(string $id): ?array
    {
        return $this->findIn($id, $this->loadOverlay());
    }

    /**
     * Anak langsung sebuah parent, terurut nama (seperti orderBy=name Drive).
     * Parent yang sudah dihapus atau tidak ada → daftar kosong.
     *
     * @return list<array>
     */
    public function children(string $parentId): array
    {
        $overlay = $this->loadOverlay();

        if ($parentId !== DemoDriveTree::MY_DRIVE && $this->findIn($parentId, $overlay) === null) {
            return [];
        }

        $ids = $this->tree->childIds($parentId);
        foreach ($overlay['created'] as $id => $node) {
            if ($node['parent'] === $parentId) {
                $ids[] = $id;
            }
        }

        $result = [];
        foreach ($ids as $id) {
            $node = $this->findIn($id, $overlay);
            if ($node !== null) {
                $result[] = $node;
            }
        }

        usort($result, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * Semua folder yang masih ada (untuk pencarian folder global), terurut nama.
     *
     * @return list<array>
     */
    public function allFolders(): array
    {
        $overlay = $this->loadOverlay();
        $result  = [];

        foreach (array_merge($this->tree->nodes(), $overlay['created']) as $id => $node) {
            if (! $node['folder']) {
                continue;
            }
            $merged = $this->findIn($id, $overlay);
            if ($merged !== null) {
                $result[] = $merged;
            }
        }

        usort($result, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $result;
    }

    // ─────────────────────────────────────────────────────────
    // MENULIS (overlay)
    // ─────────────────────────────────────────────────────────

    /**
     * Membuat node baru (folder atau metadata file) di bawah parent.
     *
     * @return array Node yang dibuat
     */
    public function create(string $name, string $parentId, bool $folder, ?string $mime = null, int $size = 0): array
    {
        $overlay = $this->loadOverlay();
        $overlay['seq']++;

        $id  = 'demo_new_' . str_pad((string) $overlay['seq'], 4, '0', STR_PAD_LEFT);
        $now = now()->utc()->format('Y-m-d\TH:i:s.000\Z');

        $node = [
            'id'       => $id,
            'name'     => $name,
            'mime'     => $folder ? DemoDriveTree::FOLDER_MIME : (string) $mime,
            'folder'   => $folder,
            'parent'   => $parentId,
            'size'     => $folder ? 0 : $size,
            'created'  => $now,
            'modified' => $now,
            'kind'     => $folder ? 'folder' : 'file',
        ];

        $overlay['created'][$id] = $node;
        $this->saveOverlay($overlay);

        return $node;
    }

    /**
     * Mengganti nama node. Null bila node tidak ada.
     */
    public function rename(string $id, string $newName): ?array
    {
        $overlay = $this->loadOverlay();

        if ($this->findIn($id, $overlay) === null) {
            return null;
        }

        if (isset($overlay['created'][$id])) {
            $overlay['created'][$id]['name']     = $newName;
            $overlay['created'][$id]['modified'] = now()->utc()->format('Y-m-d\TH:i:s.000\Z');
        } else {
            $overlay['renamed'][$id] = $newName;
        }

        $this->saveOverlay($overlay);

        return $this->findIn($id, $overlay);
    }

    /**
     * Menghapus node beserta seluruh turunannya (disembunyikan).
     *
     * @return bool False bila node tidak ada.
     */
    public function delete(string $id): bool
    {
        $overlay = $this->loadOverlay();

        if ($this->findIn($id, $overlay) === null) {
            return false;
        }

        $overlay['deleted'][$id] = true;
        $this->saveOverlay($overlay);

        return true;
    }

    // ─────────────────────────────────────────────────────────
    // CHECKLIST SHEET (FakeGoogleSheetService)
    // ─────────────────────────────────────────────────────────

    /**
     * Perubahan checklist sheet milik sesi ini: key slot ("bulan|region|unit") => 'v' atau ''.
     * Hanya berisi sel yang diubah; sel lain mengikuti nilai dasar dari pohon dummy.
     *
     * @return array<string, string>
     */
    public function checklist(): array
    {
        return $this->loadOverlay()['checklist'];
    }

    /**
     * Mengubah (menimpa) nilai checklist satu atau lebih slot sekaligus.
     *
     * @param  array<string, string>  $changes  key slot => 'v' atau ''
     */
    public function setChecklist(array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $overlay = $this->loadOverlay();
        foreach ($changes as $key => $value) {
            $overlay['checklist'][$key] = $value;
        }
        $this->saveOverlay($overlay);
    }

    // ─────────────────────────────────────────────────────────
    // INTERNAL
    // ─────────────────────────────────────────────────────────

    /**
     * Node mentah (pohon dasar atau overlay) tanpa memeriksa penghapusan.
     */
    private function rawNode(string $id, array $overlay): ?array
    {
        $node = $overlay['created'][$id] ?? $this->tree->find($id);

        if ($node === null) {
            return null;
        }

        if (isset($overlay['renamed'][$id])) {
            $node['name'] = $overlay['renamed'][$id];
        }

        return $node;
    }

    private function findIn(string $id, array $overlay): ?array
    {
        $node = $this->rawNode($id, $overlay);
        if ($node === null) {
            return null;
        }

        // Tersembunyi bila node atau salah satu leluhurnya dihapus.
        $current = $node;
        $guard   = 0;
        while ($guard++ < 20) {
            if (isset($overlay['deleted'][$current['id']])) {
                return null;
            }
            $parent = $current['parent'];
            if ($parent === DemoDriveTree::MY_DRIVE) {
                break;
            }
            $current = $this->rawNode($parent, $overlay);
            if ($current === null) {
                break;
            }
        }

        return $node;
    }

    private function loadOverlay(): array
    {
        $empty = ['seq' => 0, 'created' => [], 'renamed' => [], 'deleted' => [], 'checklist' => []];
        $path  = $this->path();

        if (! is_file($path)) {
            return $empty;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? array_merge($empty, $decoded) : $empty;
    }

    private function saveOverlay(array $overlay): void
    {
        $path = $this->path();
        $dir  = dirname($path);

        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Direktori overlay demo tidak dapat dibuat.');
        }

        if (file_put_contents($path, json_encode($overlay, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new RuntimeException('Overlay demo tidak dapat disimpan.');
        }

        $this->pruneOldOverlays($dir);
    }

    private function pruneOldOverlays(string $dir): void
    {
        $threshold = time() - ((int) config('demo.drive.overlay_ttl_hours') * 3600);

        foreach (glob($dir . DIRECTORY_SEPARATOR . 'drive_overlay_*.json') ?: [] as $file) {
            if (filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }

    /**
     * Berkas overlay milik pengunjung (per session) di storage_path('app/demo/').
     */
    private function path(): string
    {
        $key     = 'shared';
        $request = request();

        if ($request->hasSession()) {
            $key = substr(sha1($request->session()->getId()), 0, 16);
        }

        return storage_path('app/demo/drive_overlay_' . $key . '.json');
    }
}
