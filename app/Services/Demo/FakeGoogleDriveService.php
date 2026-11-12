<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Exceptions\GoogleDriveException;
use App\Services\Google\GoogleDriveService;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * FakeGoogleDriveService
 *
 * DEMO: Pengganti GoogleDriveService untuk versi demo (portofolio).
 * Di-bind lewat AppServiceProvider hanya saat config('demo.enabled').
 * Tidak ada jaringan, tidak ada Google\Service\Drive, tidak ada akses token.
 *
 * Data berasal dari pohon dummy (DemoDriveTree) + overlay mutasi per sesi
 * (DemoDriveStore). Return shape setiap method identik dengan versi asli
 * (key array & tipe data normalizeItem()), termasuk field yang HANYA terisi
 * bila diminta pada parameter `fields` method asli (selain itu bernilai
 * default: '' / '0' / [] / false).
 *
 * DIWARISI (logika murni yang hanya memanggil method publik lain via $this):
 *   - listContents()  → listFolders() + listFiles()
 *   - getFolder()     → getMetadata()
 *   - hasFiles()      → countFiles()
 *
 * DI-OVERRIDE (method asli menyentuh getDrive()/Google\Service/CacheService):
 *   listFolders, listRootFolders, listFiles, getMetadata, searchFolder,
 *   createFolder, uploadFile, downloadFile, deleteFile, countFiles,
 *   renameItem, listAllFolders, resolveFolderHierarchy.
 *
 * Sesuai dengan: rules/14_DEMO_MOCK_SPEC.md §5.3
 */
class FakeGoogleDriveService extends GoogleDriveService
{
    /**
     * DEMO: salinan KNOWN_REGIONS asli (private di parent, tidak bisa dipakai ulang).
     * Dijaga identik oleh uji ekuivalensi terhadap implementasi asli.
     */
    private const KNOWN_REGIONS = [
        'kantor uid', 'k uid',
        'up2d s2jb',
        'up2k bengkulu', 'up2k jambi', 'up2k sumsel',
        'up3 palembang',
        'up3 lubuklinggau', 'up3 llg',
        'up3 lahat',
        'up3 muara bungo', 'up3 mbo',
        'up3 jambi',
        'up3 bengkulu',
        'up3 ogan ilir', 'up3 ogi',
    ];

    // Field yang diminta setiap method pada versi asli (menentukan key yang terisi).
    private const FIELDS_LIST_FOLDERS = ['id', 'name', 'mimeType', 'modifiedTime', 'parents'];
    private const FIELDS_LIST_FILES   = ['id', 'name', 'mimeType', 'size', 'modifiedTime', 'webViewLink', 'parents'];
    private const FIELDS_SEARCH       = ['id', 'name', 'mimeType', 'parents'];
    private const FIELDS_MIN          = ['id', 'name', 'mimeType'];
    private const FIELDS_UPLOAD       = ['id', 'name', 'mimeType', 'webViewLink'];
    private const FIELDS_METADATA     = ['id', 'name', 'mimeType', 'modifiedTime', 'size', 'webViewLink', 'parents', 'createdTime'];
    private const FIELDS_ALL_FOLDERS  = ['id', 'name', 'mimeType', 'modifiedTime', 'createdTime', 'owners', 'parents'];

    /**
     * DEMO: sengaja tidak memanggil parent::__construct() (tanpa GoogleApiService/CacheService).
     */
    public function __construct(
        private readonly DemoDriveStore $store,
        private readonly DemoPlaceholder $placeholder,
    ) {}

    // ─────────────────────────────────────────────────────────
    // MEMBACA
    // ─────────────────────────────────────────────────────────

    public function listFolders(string $parentId): array
    {
        return $this->shapeAll(
            array_filter($this->store->children($parentId), fn (array $n) => $n['folder']),
            self::FIELDS_LIST_FOLDERS
        );
    }

    public function listRootFolders(): array
    {
        return $this->shapeAll(
            array_filter($this->store->children(DemoDriveTree::MY_DRIVE), fn (array $n) => $n['folder']),
            self::FIELDS_LIST_FOLDERS
        );
    }

    public function listFiles(string $parentId): array
    {
        return $this->shapeAll(
            array_filter($this->store->children($parentId), fn (array $n) => ! $n['folder']),
            self::FIELDS_LIST_FILES
        );
    }

    public function getMetadata(string $id): array
    {
        $node = $this->store->find($id);

        if ($node === null) {
            throw $this->notFound('Gagal mendapatkan metadata', $id);
        }

        return $this->shape($node, self::FIELDS_METADATA);
    }

    public function searchFolder(string $name, string $parentId): ?array
    {
        foreach ($this->store->children($parentId) as $node) {
            if ($node['folder'] && $node['name'] === $name) {
                return $this->shape($node, self::FIELDS_SEARCH);
            }
        }

        return null;
    }

    public function countFiles(string $folderId): int
    {
        return count(array_filter($this->store->children($folderId), fn (array $n) => ! $n['folder']));
    }

    public function listAllFolders(string $search = ''): array
    {
        $folders = $this->store->allFolders();

        if ($search !== '') {
            $folders = array_filter($folders, fn (array $n) => $this->nameContains($n['name'], $search));
        }

        return $this->shapeAll($folders, self::FIELDS_ALL_FOLDERS);
    }

    /**
     * Meniru operator `name contains` Drive API: pencocokan AWALAN KATA
     * (name "HelloWorld" cocok dengan 'Hello', tidak dengan 'World'), tanpa beda huruf.
     * Setiap kata pada $search harus menjadi awalan salah satu kata pada nama.
     */
    private function nameContains(string $name, string $search): bool
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach (preg_split('/\s+/u', mb_strtolower(trim($search)), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $matched = false;
            foreach ($words as $word) {
                if (str_starts_with($word, $term)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────
    // MUTASI (overlay)
    // ─────────────────────────────────────────────────────────

    public function createFolder(string $name, string $parentId): array
    {
        $parent = $this->store->find($parentId);

        if ($parent === null || ! $parent['folder']) {
            throw $this->notFound("Gagal membuat folder \"{$name}\"", $parentId);
        }

        $folder = $this->store->create($name, $parentId, true);

        Log::info('FakeGoogleDriveService::createFolder (overlay demo)', ['name' => $name, 'parentId' => $parentId, 'folderId' => $folder['id']]);

        return $this->shape($folder, self::FIELDS_MIN);
    }

    /**
     * Isi file TIDAK disimpan — hanya metadata (nama, ukuran, mime).
     * Ukuran dibatasi config('demo.upload_max_kb').
     */
    public function uploadFile(string $folderId, UploadedFile $file): array
    {
        $fileName = $file->getClientOriginalName();
        $parent   = $this->store->find($folderId);

        if ($parent === null || ! $parent['folder']) {
            throw $this->notFound("Gagal mengunggah file \"{$fileName}\"", $folderId);
        }

        $size    = (int) $file->getSize();
        $maxKb   = (int) config('demo.upload_max_kb');

        if ($size > $maxKb * 1024) {
            $message = sprintf('Batas ukuran upload pada versi demo adalah %s MB.', rtrim(rtrim(number_format($maxKb / 1024, 1), '0'), '.'));
            $this->flashWarning($message);

            throw new GoogleDriveException("Gagal mengunggah file \"{$fileName}\": {$message}", 413);
        }

        $node = $this->store->create($fileName, $folderId, false, $file->getMimeType() ?: 'application/octet-stream', $size);

        Log::info('FakeGoogleDriveService::uploadFile (overlay demo, isi tidak disimpan)', ['fileName' => $fileName, 'folderId' => $folderId, 'fileId' => $node['id']]);

        return $this->shape($node, self::FIELDS_UPLOAD);
    }

    public function renameItem(string $id, string $newName): array
    {
        $node = $this->store->rename($id, $newName);

        if ($node === null) {
            throw $this->notFound('Gagal mengganti nama item', $id);
        }

        return $this->shape($node, self::FIELDS_MIN);
    }

    public function deleteFile(string $fileId): void
    {
        if ($fileId === (string) config('demo.root_folder_id')) {
            throw new GoogleDriveException('Gagal menghapus item: Mode demo: Root Folder demo tidak dapat dihapus.', 403);
        }

        if (! $this->store->delete($fileId)) {
            throw $this->notFound('Gagal menghapus item', $fileId);
        }

        Log::info('FakeGoogleDriveService::deleteFile (overlay demo)', ['fileId' => $fileId]);
    }

    /**
     * Berkas contoh dibuat on-the-fly. Mengembalikan objek PSR-7 seperti respons
     * `alt=media` versi asli (->getBody()->getContents()).
     */
    public function downloadFile(string $fileId): mixed
    {
        $node = $this->store->find($fileId);

        if ($node === null || $node['folder']) {
            throw $this->notFound('Gagal mengunduh file', $fileId);
        }

        [$content, $contentType] = $this->placeholder->forFile($node['name']);

        return new Response(200, ['Content-Type' => $contentType], $content);
    }

    // ─────────────────────────────────────────────────────────
    // HIERARKI (salinan algoritma asli di atas pohon dummy)
    // ─────────────────────────────────────────────────────────

    /**
     * Menelusuri leluhur (maks 6 level) untuk menentukan semester/bulan/region/unit.
     * Algoritma identik dengan GoogleDriveService::resolveFolderHierarchy() asli
     * (tanpa cache), karena method asli memakai CacheService & helper private.
     */
    public function resolveFolderHierarchy(string $folderId): ?array
    {
        $chain     = [];
        $currentId = $folderId;

        for ($i = 0; $i < 6; $i++) {
            $node = $this->store->find($currentId);
            if ($node === null) {
                return null;
            }

            $chain[] = ['id' => $node['id'], 'name' => $node['name']];

            if ($node['parent'] === DemoDriveTree::MY_DRIVE) {
                break; // sudah di root
            }
            $currentId = $node['parent'];
        }

        if (count($chain) < 3) {
            return null;
        }

        $regionIndex = null;
        $monthIndex  = null;

        foreach ($chain as $i => $node) {
            if (in_array(strtolower(trim($node['name'])), self::KNOWN_REGIONS, true)) {
                for ($j = $i + 1; $j < count($chain); $j++) {
                    if ($this->resolveStandardMonthName($chain[$j]['name']) !== null) {
                        $regionIndex = $i;
                        $monthIndex  = $j;
                        break 2;
                    }
                }
            }
        }

        if ($regionIndex !== null && $monthIndex !== null) {
            $monthName = $chain[$monthIndex]['name'];

            return [
                'semester' => $this->resolveSemesterFromMonth($monthName),
                'month'    => $monthName,
                'region'   => $chain[$regionIndex]['name'],
                'unit'     => $chain[0]['name'],
            ];
        }

        foreach ($chain as $j => $node) {
            if ($j > 0 && $this->resolveStandardMonthName($node['name']) !== null) {
                return [
                    'semester' => $this->resolveSemesterFromMonth($node['name']),
                    'month'    => $node['name'],
                    'region'   => $chain[$j - 1]['name'],
                    'unit'     => $chain[0]['name'],
                ];
            }
        }

        return null;
    }

    /** DEMO: salinan resolveSemesterFromMonth() asli (private di parent). */
    private function resolveSemesterFromMonth(string $monthNameRaw): string
    {
        $sem1Months = ['Desember', 'Januari', 'Februari', 'Maret', 'April', 'Mei'];
        $stdMonth   = $this->resolveStandardMonthName($monthNameRaw);

        return ($stdMonth && in_array($stdMonth, $sem1Months, true)) ? 'Semester 1' : 'Semester 2';
    }

    /** DEMO: salinan resolveStandardMonthName() asli (private di parent). */
    private function resolveStandardMonthName(string $folderName): ?string
    {
        $folderNameLower = strtolower($folderName);

        $textPatterns = [
            'Januari'   => ['januari', 'jan'],
            'Februari'  => ['februari', 'feb'],
            'Maret'     => ['maret', 'mar'],
            'April'     => ['april', 'apr'],
            'Mei'       => ['mei'],
            'Juni'      => ['juni', 'jun'],
            'Juli'      => ['juli', 'jul'],
            'Agustus'   => ['agustus', 'agu', 'agt'],
            'September' => ['september', 'sept', 'sep'],
            'Oktober'   => ['oktober', 'okt'],
            'November'  => ['november', 'nov'],
            'Desember'  => ['desember', 'des'],
        ];

        foreach ($textPatterns as $monthName => $searches) {
            foreach ($searches as $search) {
                if (str_contains($folderNameLower, $search)) {
                    return $monthName;
                }
            }
        }

        $numericPatterns = [
            'Oktober'   => ['10.'],
            'November'  => ['11.'],
            'Desember'  => ['12.'],
            'Januari'   => ['1.'],
            'Februari'  => ['2.'],
            'Maret'     => ['3.'],
            'April'     => ['4.'],
            'Mei'       => ['5.'],
            'Juni'      => ['6.'],
            'Juli'      => ['7.'],
            'Agustus'   => ['8.'],
            'September' => ['9.'],
        ];

        foreach ($numericPatterns as $monthName => $searches) {
            foreach ($searches as $search) {
                if (str_contains($folderNameLower, $search)) {
                    return $monthName;
                }
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────
    // NORMALISASI (tiruan normalizeItem() asli)
    // ─────────────────────────────────────────────────────────

    /**
     * @param  iterable<array>  $nodes
     * @param  list<string>     $fields
     * @return list<array>
     */
    private function shapeAll(iterable $nodes, array $fields): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $result[] = $this->shape($node, $fields);
        }

        return $result;
    }

    /**
     * Menghasilkan array dengan key & tipe IDENTIK normalizeItem() asli:
     * id, name, mimeType, modifiedTime, createdTime, size (string), webViewLink,
     * parents (array), isFolder (bool), owner (string). Field yang tidak ada di
     * $fields (tidak diminta ke API pada versi asli) bernilai default.
     *
     * @param  array         $node    Node internal (DemoDriveTree)
     * @param  list<string>  $fields  Nama field Drive API yang diminta
     */
    private function shape(array $node, array $fields): array
    {
        $has = fn (string $field) => in_array($field, $fields, true);

        $mimeType = $has('mimeType') ? $node['mime'] : '';

        return [
            'id'           => $has('id') ? $node['id'] : '',
            'name'         => $has('name') ? $node['name'] : '',
            'mimeType'     => $mimeType,
            'modifiedTime' => $has('modifiedTime') ? $node['modified'] : '',
            'createdTime'  => $has('createdTime') ? $node['created'] : '',
            'size'         => ($has('size') && ! $node['folder']) ? (string) $node['size'] : '0',
            'webViewLink'  => ($has('webViewLink') && ! $node['folder']) ? $this->previewUrl($node['id']) : '',
            'parents'      => ($has('parents') && $node['parent'] !== DemoDriveTree::MY_DRIVE) ? [$node['parent']] : [],
            'isFolder'     => $mimeType === DemoDriveTree::FOLDER_MIME,
            'owner'        => $has('owners') ? (string) config('demo.name') : '',
        ];
    }

    /**
     * Tautan "buka" file demo: halaman placeholder internal (bukan drive.google.com).
     */
    private function previewUrl(string $fileId): string
    {
        return Route::has('drive.file.preview-demo') ? route('drive.file.preview-demo', ['fileId' => $fileId]) : '';
    }

    private function notFound(string $context, string $id): GoogleDriveException
    {
        return new GoogleDriveException("{$context}: File not found: {$id}.", 404);
    }

    private function flashWarning(string $message): void
    {
        $request = request();

        if ($request->hasSession()) {
            $request->session()->flash('warning', $message);
        }
    }
}
