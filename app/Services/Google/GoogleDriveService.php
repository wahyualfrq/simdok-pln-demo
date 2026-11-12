<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Exceptions\GoogleAuthException;
use App\Exceptions\GoogleDriveException;
use App\Services\CacheService;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * GoogleDriveService
 *
 * Service tunggal untuk semua komunikasi dengan Google Drive API.
 * Autentikasi dilakukan melalui GoogleApiService — tidak ada Service Account.
 * Semua modul (Dashboard, Drive Explorer, Monitoring) menggunakan service ini.
 *
 * Performance:
 * - Singleton Drive instance per request (lazy-init)
 * - Cache folder metadata (TTL 1 jam)
 * - Cache folder list per parentId (TTL 1 jam)
 *
 * Sesuai dengan: 04_GOOGLE_DRIVE_API.md, 12_GOOGLE_API_SERVICE.md
 */
class GoogleDriveService
{
    /**
     * Nama-nama region yang dikenali secara canonical (lowercase).
     * Digunakan oleh resolveFolderHierarchy() untuk mendeteksi level "region"
     * bahkan ketika folder monitoring berada di level lebih dalam dari biasanya.
     *
     * Canonical name = nama yang muncul di Google Sheet sebagai "region".
     * Alias juga didaftarkan (e.g., "k uid" untuk "kantor uid").
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

    /** Singleton Drive instance — dibuat sekali per request lifecycle. */
    private ?Drive $drive = null;

    public function __construct(
        private readonly GoogleApiService $apiService,
        private readonly CacheService $cacheService
    ) {}

    /**
     * Mendapatkan instance Google Service Drive yang terautentikasi.
     *
     * @return Drive
     * @throws GoogleAuthException
     */
    /**
     * Mendapatkan instance Google Service Drive yang terautentikasi.
     * Lazy-init: satu instance per request lifecycle (tidak re-create setiap panggilan).
     *
     * @return Drive
     * @throws GoogleAuthException
     */
    private function getDrive(): Drive
    {
        if ($this->drive === null) {
            $this->drive = new Drive($this->apiService->getClient());
        }
        return $this->drive;
    }

    /**
     * Mendapatkan daftar folder di dalam parent folder tertentu.
     *
     * @param  string  $parentId  Google Drive Folder ID
     * @return array<int, array{id: string, name: string, mimeType: string, modifiedTime: string}>
     * @throws GoogleDriveException
     */
    public function listFolders(string $parentId): array
    {
        // Cek cache terlebih dahulu
        $cached = $this->cacheService->getFolderList($parentId);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $query = sprintf(
                "'%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                $parentId
            );

            $response = $this->getDrive()->files->listFiles([
                'q'        => $query,
                'fields'   => 'files(id, name, mimeType, modifiedTime, parents)',
                'orderBy'  => 'name',
                'pageSize' => 1000,
            ]);

            $result = $this->normalizeItems($response->getFiles());

            // Simpan ke cache
            $this->cacheService->putFolderList($parentId, $result);

            return $result;
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::listFolders gagal', [
                'parentId' => $parentId,
                'error'    => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal memuat daftar folder: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Mendapatkan daftar folder langsung di bawah root Drive pengguna.
     *
     * @return array<int, array>
     * @throws GoogleDriveException
     */
    public function listRootFolders(): array
    {
        try {
            $query = "'root' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";

            $response = $this->getDrive()->files->listFiles([
                'q'        => $query,
                'fields'   => 'files(id, name, mimeType, modifiedTime, parents)',
                'orderBy'  => 'name',
                'pageSize' => 1000,
            ]);

            return $this->normalizeItems($response->getFiles());
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::listRootFolders gagal', [
                'error'    => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal memuat folder root Google Drive: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Mendapatkan daftar file (bukan folder) di dalam folder tertentu.
     *
     * @param  string  $parentId  Google Drive Folder ID
     * @return array<int, array{id: string, name: string, mimeType: string, size: string, modifiedTime: string, webViewLink: string}>
     * @throws GoogleDriveException
     */
    public function listFiles(string $parentId): array
    {
        $cached = $this->cacheService->getFileList($parentId);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $query = sprintf(
                "'%s' in parents and mimeType != 'application/vnd.google-apps.folder' and trashed = false",
                $parentId
            );

            $response = $this->getDrive()->files->listFiles([
                'q'        => $query,
                'fields'   => 'files(id, name, mimeType, size, modifiedTime, webViewLink, parents)',
                'orderBy'  => 'name',
                'pageSize' => 1000,
            ]);

            $result = $this->normalizeItems($response->getFiles());
            $this->cacheService->putFileList($parentId, $result);

            return $result;
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::listFiles gagal', [
                'parentId' => $parentId,
                'error'    => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal memuat daftar file: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Mendapatkan semua item (folder + file) di dalam parent folder.
     *
     * @param  string  $parentId  Google Drive Folder ID
     * @return array{folders: array, files: array}
     * @throws GoogleDriveException
     */
    public function listContents(string $parentId): array
    {
        return [
            'folders' => $this->listFolders($parentId),
            'files'   => $this->listFiles($parentId),
        ];
    }

    /**
     * Mendapatkan metadata folder/file berdasarkan ID.
     *
     * @param  string  $folderId  Google Drive Folder ID
     * @return array{id: string, name: string, mimeType: string, parents: array}
     * @throws GoogleDriveException
     */
    public function getFolder(string $folderId): array
    {
        return $this->getMetadata($folderId);
    }

    /**
     * Mencari folder berdasarkan nama dan parent folder.
     *
     * @param  string  $name      Nama folder yang dicari
     * @param  string  $parentId  ID folder induk
     * @return array|null  Metadata folder jika ditemukan, null jika tidak
     * @throws GoogleDriveException
     */
    public function searchFolder(string $name, string $parentId): ?array
    {
        try {
            $query = sprintf(
                "name = '%s' and '%s' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                addslashes($name),
                $parentId
            );

            $response = $this->getDrive()->files->listFiles([
                'q'      => $query,
                'fields' => 'files(id, name, mimeType, parents)',
            ]);

            $files = $response->getFiles();

            if (empty($files)) {
                return null;
            }

            return $this->normalizeItem($files[0]);
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::searchFolder gagal', [
                'name'     => $name,
                'parentId' => $parentId,
                'error'    => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal mencari folder: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Membuat folder baru di dalam parent folder yang ditentukan.
     *
     * @param  string  $name      Nama folder yang akan dibuat
     * @param  string  $parentId  ID folder induk
     * @return array{id: string, name: string, mimeType: string}
     * @throws GoogleDriveException
     */
    public function createFolder(string $name, string $parentId): array
    {
        try {
            Log::info("[AUTO-GENERATE 6/6 GOOGLE DRIVE API] Memanggil Google Drive API files->create()", [
                'folderName' => $name,
                'parentId'   => $parentId,
            ]);

            $fileMetadata = new DriveFile([
                'name'     => $name,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents'  => [$parentId],
            ]);

            $folder = $this->getDrive()->files->create($fileMetadata, [
                'fields' => 'id, name, mimeType',
            ]);

            Log::info("[AUTO-GENERATE 6/6 GOOGLE DRIVE API] Folder berhasil dibuat di Google Drive", [
                'name'     => $name,
                'parentId' => $parentId,
                'folderId' => $folder->getId(),
            ]);

            return $this->normalizeItem($folder);
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::createFolder gagal', [
                'name'     => $name,
                'parentId' => $parentId,
                'error'    => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal membuat folder \"{$name}\": {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Mengunggah file ke folder Google Drive yang ditentukan.
     * File tidak disimpan di server — langsung diteruskan ke Google Drive.
     * Menggunakan akun OAuth sehingga file tersimpan di Drive pengguna (tidak ada quota error).
     *
     * @param  string        $folderId  ID folder tujuan
     * @param  UploadedFile  $file      File yang di-upload
     * @return array{id: string, name: string, mimeType: string, webViewLink: string}
     * @throws GoogleDriveException
     */
    public function uploadFile(string $folderId, UploadedFile $file): array
    {
        try {
            $fileMetadata = new DriveFile([
                'name'    => $file->getClientOriginalName(),
                'parents' => [$folderId],
            ]);

            $uploaded = $this->getDrive()->files->create($fileMetadata, [
                'data'       => file_get_contents($file->getRealPath()),
                'mimeType'   => $file->getMimeType(),
                'uploadType' => 'multipart',
                'fields'     => 'id, name, mimeType, webViewLink',
            ]);

            Log::info('GoogleDriveService::uploadFile berhasil', [
                'fileName' => $file->getClientOriginalName(),
                'folderId' => $folderId,
                'fileId'   => $uploaded->getId(),
            ]);

            return $this->normalizeItem($uploaded);
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::uploadFile gagal', [
                'fileName' => $file->getClientOriginalName(),
                'folderId' => $folderId,
                'error'    => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal mengunggah file \"{$file->getClientOriginalName()}\": {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Mengunduh file dari Google Drive.
     *
     * @param  string  $fileId  ID file Google Drive
     * @return mixed  HTTP response berisi konten file
     * @throws GoogleDriveException
     */
    public function downloadFile(string $fileId): mixed
    {
        try {
            $response = $this->getDrive()->files->get($fileId, [
                'alt' => 'media',
            ]);

            return $response;
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::downloadFile gagal', [
                'fileId' => $fileId,
                'error'  => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal mengunduh file: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Menghapus file atau folder dari Google Drive.
     *
     * @param  string  $fileId  ID file/folder Google Drive
     * @throws GoogleDriveException
     */
    public function deleteFile(string $fileId): void
    {
        try {
            $this->getDrive()->files->delete($fileId);

            Log::info('GoogleDriveService::deleteFile berhasil', ['fileId' => $fileId]);
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::deleteFile gagal', [
                'fileId' => $fileId,
                'error'  => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal menghapus item: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Mendapatkan metadata item (file/folder) dari Google Drive.
     *
     * @param  string  $id  Google Drive item ID
     * @return array{id: string, name: string, mimeType: string, modifiedTime: string, size: string, webViewLink: string, parents: array}
     * @throws GoogleDriveException
     */
    public function getMetadata(string $id): array
    {
        // Cek cache folder metadata terlebih dahulu
        $cached = $this->cacheService->getFolderMeta($id);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $file = $this->getDrive()->files->get($id, [
                'fields' => 'id, name, mimeType, modifiedTime, size, webViewLink, parents, createdTime',
            ]);

            $result = $this->normalizeItem($file);

            // Simpan ke cache
            $this->cacheService->putFolderMeta($id, $result);

            return $result;
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::getMetadata gagal', [
                'id'    => $id,
                'error' => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal mendapatkan metadata: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Menghitung jumlah file (bukan folder) di dalam sebuah folder.
     * Digunakan oleh MonitoringService untuk menentukan status.
     *
     * @param  string  $folderId  Google Drive Folder ID
     * @return int  Jumlah file dalam folder
     * @throws GoogleDriveException
     */
    public function countFiles(string $folderId): int
    {
        try {
            $query = sprintf(
                "'%s' in parents and mimeType != 'application/vnd.google-apps.folder' and trashed = false",
                $folderId
            );

            $response = $this->getDrive()->files->listFiles([
                'q'        => $query,
                'fields'   => 'files(id)',
                'pageSize' => 1000,
            ]);

            return count($response->getFiles());
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::countFiles gagal', [
                'folderId' => $folderId,
                'error'    => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal menghitung file: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Mengganti nama file atau folder di Google Drive.
     *
     * @param  string  $id       Google Drive item ID
     * @param  string  $newName  Nama baru
     * @return array{id: string, name: string, mimeType: string}
     * @throws GoogleDriveException
     */
    public function renameItem(string $id, string $newName): array
    {
        try {
            $fileMetadata = new DriveFile([
                'name' => $newName,
            ]);

            $updatedFile = $this->getDrive()->files->update($id, $fileMetadata, [
                'fields' => 'id, name, mimeType',
            ]);

            Log::info('GoogleDriveService::renameItem berhasil', [
                'id'      => $id,
                'newName' => $newName,
            ]);

            return $this->normalizeItem($updatedFile);
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::renameItem gagal', [
                'id'      => $id,
                'newName' => $newName,
                'error'   => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal mengganti nama item: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Mendapatkan semua folder (global atau berdasarkan filter pencarian).
     *
     * @param  string  $search  Kata kunci pencarian nama folder (opsional)
     * @return array<int, array>
     * @throws GoogleDriveException
     */
    public function listAllFolders(string $search = ''): array
    {
        try {
            $query = "mimeType = 'application/vnd.google-apps.folder' and trashed = false";
            if (! empty($search)) {
                $query .= sprintf(" and name contains '%s'", addslashes($search));
            }

            $folders = [];
            $pageToken = null;

            do {
                $response = $this->getDrive()->files->listFiles([
                    'q'         => $query,
                    'fields'    => 'nextPageToken, files(id, name, mimeType, modifiedTime, createdTime, owners, parents)',
                    'orderBy'   => 'name',
                    'pageSize'  => 1000,
                    'pageToken' => $pageToken,
                ]);

                $filesList = $response->getFiles();
                if ($filesList) {
                    foreach ($filesList as $file) {
                        $folders[] = $file;
                    }
                }

                $pageToken = $response->getNextPageToken();
            } while ($pageToken);

            return $this->normalizeItems($folders);
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('GoogleDriveService::listAllFolders gagal', [
                'search' => $search,
                'error'  => $e->getMessage(),
            ]);
            throw new GoogleDriveException(
                "Gagal mencari folder: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Memeriksa apakah folder masih memiliki berkas yang tersisa (tidak termasuk subfolder).
     *
     * @param  string  $folderId  Google Drive Folder ID
     * @return bool
     * @throws GoogleDriveException
     */
    public function hasFiles(string $folderId): bool
    {
        return $this->countFiles($folderId) >= 1;
    }

    /**
     * Menelusuri hirarki parent folder ke atas untuk menentukan nama Semester, Bulan, Daerah, dan Unit.
     *
     * VERSI BARU: Naik secara iteratif hingga max 6 level ke atas. Mendeteksi
     * "region" berdasarkan KNOWN_REGIONS, sehingga berfungsi meskipun folder
     * monitoring berada di level 5-6 (e.g., upload ke SS/MULP BKO/).
     *
     * @param  string  $folderId  Google Drive Folder ID (bisa folder unit ATAU leaf nested)
     * @return array{semester: string, month: string, region: string, unit: string}|null
     */
    public function resolveFolderHierarchy(string $folderId): ?array
    {
        $cached = $this->cacheService->getFolderHierarchy($folderId);
        if ($cached !== null) {
            return $cached;
        }

        try {
            // Kumpulkan ancestor chain dari bawah ke atas (max 6 langkah)
            $chain = []; // [0] = leaf/unit, [1] = parent, [2] = grandparent, dst.

            $currentId = $folderId;
            for ($i = 0; $i < 6; $i++) {
                $meta = $this->getMetadata($currentId);
                $chain[] = [
                    'id'   => $meta['id'],
                    'name' => $meta['name'],
                ];

                $parents = $meta['parents'] ?? [];
                if (empty($parents)) {
                    break; // Sudah di root
                }
                $currentId = $parents[0];
            }

            // chain[0] = folder yang di-upload (leaf/unit)
            // chain[n-1] = ancestor tertinggi yang berhasil diambil

            if (count($chain) < 3) {
                // Minimal butuh: unit, region, month (3 level)
                Log::warning('GoogleDriveService::resolveFolderHierarchy - chain terlalu pendek', [
                    'folderId' => $folderId,
                    'chain'    => $chain,
                ]);
                return null;
            }

            // Cari posisi "region" dan "month" dalam chain menggunakan KNOWN_REGIONS dan Month resolver
            $regionIndex = null;
            $monthIndex  = null;

            foreach ($chain as $i => $node) {
                if (in_array(strtolower(trim($node['name'])), self::KNOWN_REGIONS, true)) {
                    // Cari di atas node ini (i+1, i+2, dst) untuk folder bulan yang valid
                    for ($j = $i + 1; $j < count($chain); $j++) {
                        if ($this->resolveStandardMonthName($chain[$j]['name']) !== null) {
                            $regionIndex = $i;
                            $monthIndex  = $j;
                            break 2;
                        }
                    }
                }
            }

            $hierarchy = null;

            if ($regionIndex !== null && $monthIndex !== null) {
                $regionName = $chain[$regionIndex]['name'];
                $monthName  = $chain[$monthIndex]['name'];

                // unit_name: ambil dari leaf folder (node index 0)
                $unitName = $chain[0]['name'];

                // Semester ditentukan secara logis dari nama bulan
                $semesterName = $this->resolveSemesterFromMonth($monthName);

                $hierarchy = [
                    'semester' => $semesterName,
                    'month'    => $monthName,
                    'region'   => $regionName,
                    'unit'     => $unitName,
                ];
            } else {
                // Fallback: temukan ancestor pertama yang merupakan folder bulan
                foreach ($chain as $j => $node) {
                    if ($j > 0 && $this->resolveStandardMonthName($node['name']) !== null) {
                        $monthName    = $node['name'];
                        $regionName   = $chain[$j - 1]['name'];
                        $unitName     = $chain[0]['name'];
                        $semesterName = $this->resolveSemesterFromMonth($monthName);

                        $hierarchy = [
                            'semester' => $semesterName,
                            'month'    => $monthName,
                            'region'   => $regionName,
                            'unit'     => $unitName,
                        ];
                        break;
                    }
                }
            }

            if ($hierarchy !== null) {
                $this->cacheService->putFolderHierarchy($folderId, $hierarchy);
                return $hierarchy;
            }

            Log::warning('GoogleDriveService::resolveFolderHierarchy - Tidak dapat menentukan hirarki', [
                'folderId' => $folderId,
                'chain'    => array_column($chain, 'name'),
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('GoogleDriveService::resolveFolderHierarchy gagal', [
                'folderId' => $folderId,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Menentukan Semester secara dinamis berdasarkan nama bulan ("Semester 1" atau "Semester 2").
     */
    private function resolveSemesterFromMonth(string $monthNameRaw): string
    {
        $sem1Months = ['Desember', 'Januari', 'Februari', 'Maret', 'April', 'Mei'];
        $stdMonth = $this->resolveStandardMonthName($monthNameRaw);
        if ($stdMonth && in_array($stdMonth, $sem1Months, true)) {
            return 'Semester 1';
        }
        return 'Semester 2';
    }

    /**
     * Resolusi nama bulan standar dari nama folder Google Drive.
     */
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

    /**
     * Normalisasi collection item Google Drive menjadi array PHP biasa.
     *
     * @param  array  $items  Array DriveFile objects
     * @return array<int, array>
     */
    private function normalizeItems(array $items): array
    {
        return array_map(fn ($item) => $this->normalizeItem($item), $items);
    }

    /**
     * Normalisasi satu item DriveFile menjadi array PHP biasa.
     *
     * @param  DriveFile  $item  DriveFile object
     * @return array{id: string, name: string, mimeType: string, modifiedTime: string, size: string, webViewLink: string, parents: array, isFolder: bool, owner: string}
     */
    private function normalizeItem(DriveFile $item): array
    {
        $owners = $item->getOwners();
        $ownerName = '';
        if (! empty($owners)) {
            $ownerName = $owners[0]->getDisplayName() ?? $owners[0]->getEmailAddress() ?? '';
        }

        return [
            'id'           => $item->getId() ?? '',
            'name'         => $item->getName() ?? '',
            'mimeType'     => $item->getMimeType() ?? '',
            'modifiedTime' => $item->getModifiedTime() ?? '',
            'createdTime'  => $item->getCreatedTime() ?? '',
            'size'         => $item->getSize() ?? '0',
            'webViewLink'  => $item->getWebViewLink() ?? '',
            'parents'      => $item->getParents() ?? [],
            'isFolder'     => $item->getMimeType() === 'application/vnd.google-apps.folder',
            'owner'        => $ownerName,
        ];
    }
}
