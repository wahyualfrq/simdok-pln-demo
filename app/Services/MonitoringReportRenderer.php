<?php

declare(strict_types=1);

namespace App\Services;

/**
 * MonitoringReportRenderer
 *
 * Single Source of Layout untuk WYSIWYG Report Builder SIMDOK.
 * Menghasilkan HTML report yang 100% IDENTIK dengan Template Excel resmi PLN S2JB.
 */
class MonitoringReportRenderer
{
    /**
     * Structure unit resmi PLN S2JB sesuai Template Excel.
     */
    public const REPORT_STRUCTURE = [
        [
            'group_no' => 'a.',
            'group_title' => 'UID S2JB (KANTOR INDUK)',
            'units' => [
                ['name' => 'BIDANG KEUANGAN DAN UMUM', 'sub' => 'SENIOR MANAGER KEUANGAN DAN UMUM'],
                ['name' => 'BIDANG PERENCANAAN', 'sub' => 'SENIOR MANAGER PERENCANAAN'],
                ['name' => 'BIDANG DISTRIBUSI', 'sub' => 'SENIOR MANAGER DISTRIBUSI'],
                ['name' => 'BIDANG NIAGA DAN PELAYANAN PELANGGAN', 'sub' => 'SENIOR MANAGER NIAGA DAN PELAYANAN PELANGGAN'],
                ['name' => 'BIDANG TRANSMISI DAN POM', 'sub' => 'SENIOR MANAGER TRANSMISI DAN POM'],
                ['name' => 'BIDANG KOMUNIKASI DAN HUKUM', 'sub' => 'SENIOR MANAGER KOMUNIKASI DAN HUKUM'],
                ['name' => 'TIM KEPATUHAN DAN MANAJEMEN RISIKO', 'sub' => 'KETUA TIM KEPATUHAN'],
            ]
        ],
        [
            'group_no' => 'b.',
            'group_title' => 'UNIT PELAKSANA PELAYANAN PELANGGAN (UP3)',
            'units' => [
                ['name' => 'UP3 PALEMBANG', 'sub' => 'MANAGER UP3 PALEMBANG'],
                ['name' => 'UP3 OGAN ILIR', 'sub' => 'MANAGER UP3 OGAN ILIR'],
                ['name' => 'UP3 LAHAT', 'sub' => 'MANAGER UP3 LAHAT'],
                ['name' => 'UP3 LUBUKLINGGAU', 'sub' => 'MANAGER UP3 LUBUKLINGGAU'],
                ['name' => 'UP3 JAMBI', 'sub' => 'MANAGER UP3 JAMBI'],
                ['name' => 'UP3 MUARA BUNGO', 'sub' => 'MANAGER UP3 MUARA BUNGO'],
                ['name' => 'UP3 BENGKULU', 'sub' => 'MANAGER UP3 BENGKULU'],
            ]
        ],
        [
            'group_no' => 'c.',
            'group_title' => 'UNIT PELAKSANA PEMBANGKITAN (UPK)',
            'units' => [
                ['name' => 'UPK BENGKULU', 'sub' => 'MANAGER UPK BENGKULU'],
                ['name' => 'UPK BUKITTINGGI', 'sub' => 'MANAGER UPK BUKITTINGGI'],
            ]
        ],
        [
            'group_no' => 'd.',
            'group_title' => 'UNIT PELAKSANA PENGATUR DISTRIBUSI (UP2D)',
            'units' => [
                ['name' => 'UP2D S2JB', 'sub' => 'MANAGER UP2D S2JB'],
            ]
        ]
    ];

    /**
     * Render HTML report lengkap dari dataset & footer.
     *
     * @param  array  $dataset
     * @param  array  $footer
     * @return string
     */
    public function renderHtml(array $dataset, array $footer): string
    {
        $semester   = $dataset['semester'] ?? 'Semester 1';
        $months     = $dataset['months'] ?? ['Desember', 'Januari', 'Februari', 'Maret', 'April', 'Mei'];
        $statusMap  = $dataset['status_map'] ?? [];

        $lokasi    = $footer['lokasi'] ?? 'Palembang';
        $tanggal   = $footer['tanggal'] ?? now()->locale('id')->isoFormat('DD MMMM YYYY');
        $disusun   = $footer['disusun_oleh'] ?? 'Nama Penyusun (Demo)';
        $jabDisusun = $footer['jabatan_penyusun'] ?? 'JABATAN PENYUSUN (DEMO)';
        $know      = $footer['mengetahui'] ?? 'Nama Pejabat (Demo)';
        $jabKnow   = $footer['jabatan'] ?? 'JABATAN PEJABAT (DEMO)';

        $semesterLabel = strtoupper($semester) . ' TAHUN ' . date('Y');

        ob_start();
        ?>
        <div class="wysiwyg-report-page" style="width: 1123px; min-height: 794px; background: #ffffff; color: #0f172a; font-family: 'Inter', Arial, sans-serif; padding: 32px 40px; box-sizing: border-box; margin: 0 auto; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); border: 1px solid #cbd5e1;">
            
            {{-- Header Laporan PLN --}}
            <div style="display: flex; align-items: center; justify-content: space-between; border-b: 3px double #0284c7; padding-bottom: 12px; margin-bottom: 16px;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <div style="width: 44px; height: 52px; background: #0284c7; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #ffffff; font-weight: 900; font-size: 20px; letter-spacing: 1px;">
                        PLN
                    </div>
                    <div>
                        <h1 style="font-size: 14px; font-weight: 800; color: #0f172a; margin: 0; text-transform: uppercase; letter-spacing: 0.5px;">PT PLN (PERSERO) UNIT INDUK DISTRIBUSI SUMATERA SELATAN, JAMBI, DAN BENGKULU</h1>
                        <h2 style="font-size: 16px; font-weight: 900; color: #0284c7; margin: 3px 0 0 0; text-transform: uppercase; letter-spacing: 0.5px;">MONITORING KEPATUHAN DAN KELENGKAPAN DOKUMEN (SMK)</h2>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span style="display: inline-block; background: #e0f2fe; color: #0369a1; font-weight: 800; font-size: 11px; padding: 4px 12px; border-radius: 9999px; border: 1px solid #bae6fd;">
                        <?= htmlspecialchars($semesterLabel) ?>
                    </span>
                </div>
            </div>

            {{-- Table Grid --}}
            <table style="width: 100%; border-collapse: collapse; font-size: 10.5px; border: 1.5px solid #334155;">
                <thead>
                    <tr style="background: #0f172a; color: #ffffff; text-align: center; font-weight: 700; height: 32px;">
                        <th style="width: 36px; border: 1px solid #475569;">NO</th>
                        <th style="border: 1px solid #475569; text-align: left; padding-left: 10px;">NAMA UNIT / REGION</th>
                        <th style="border: 1px solid #475569; text-align: left; padding-left: 10px;">SUB-UNIT / JABATAN</th>
                        <?php foreach ($months as $m): ?>
                            <th style="width: 72px; border: 1px solid #475569; text-transform: uppercase; font-size: 10px;"><?= htmlspecialchars($m) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowCounter = 1; ?>
                    <?php foreach (self::REPORT_STRUCTURE as $group): ?>
                        {{-- Group Header Row --}}
                        <tr style="background: #f1f5f9; font-weight: 800; color: #1e293b; height: 26px;">
                            <td style="text-align: center; border: 1px solid #cbd5e1;"><?= htmlspecialchars($group['group_no']) ?></td>
                            <td colspan="<?= count($months) + 2 ?>" style="padding-left: 10px; border: 1px solid #cbd5e1; text-transform: uppercase; letter-spacing: 0.3px;">
                                <?= htmlspecialchars($group['group_title']) ?>
                            </td>
                        </tr>

                        <?php foreach ($group['units'] as $unit): ?>
                            <?php 
                                $normKey = strtolower(trim(preg_replace('/[^a-z0-9 ]/i', '', $unit['name'])));
                                $unitStatuses = $statusMap[$normKey] ?? [];
                            ?>
                            <tr style="height: 24px; background: <?= ($rowCounter % 2 === 0) ? '#ffffff' : '#f8fafc' ?>;">
                                <td style="text-align: center; border: 1px solid #e2e8f0; color: #64748b; font-size: 10px;"><?= $rowCounter++ ?></td>
                                <td style="padding-left: 12px; border: 1px solid #e2e8f0; font-weight: 600; color: #1e293b;"><?= htmlspecialchars($unit['name']) ?></td>
                                <td style="padding-left: 12px; border: 1px solid #e2e8f0; color: #475569; font-size: 10px;"><?= htmlspecialchars($unit['sub']) ?></td>
                                
                                <?php foreach ($months as $m): ?>
                                    <?php 
                                        $st = strtolower($unitStatuses[$m] ?? 'incomplete');
                                        $badgeBg = '#ffffff';
                                        $badgeColor = '#94a3b8';
                                        $symbol = '-';
                                        if ($st === 'complete') {
                                            $badgeBg = '#d1fae5';
                                            $badgeColor = '#047857';
                                            $symbol = '✓';
                                        } elseif ($st === 'mismatch') {
                                            $badgeBg = '#fef3c7';
                                            $badgeColor = '#b45309';
                                            $symbol = '!';
                                        }
                                    ?>
                                    <td style="text-align: center; border: 1px solid #e2e8f0; background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; font-weight: 900; font-size: 12px;">
                                        <?= $symbol ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            {{-- Footer Tanda Tangan --}}
            <div style="margin-top: 36px; display: flex; justify-content: space-between; font-size: 11px; color: #0f172a; page-break-inside: avoid;">
                {{-- Left Signature --}}
                <div style="width: 320px; text-align: center;">
                    <div style="font-weight: 700; text-transform: uppercase; height: 32px;" id="preview-footer-jabatan-penyusun">
                        <?= htmlspecialchars($jabDisusun) ?>
                    </div>
                    <div style="height: 60px;"></div>
                    <div style="font-weight: 900; text-decoration: underline; font-size: 12px;" id="preview-footer-disusun-oleh">
                        <?= htmlspecialchars($disusun) ?>
                    </div>
                </div>

                {{-- Right Signature --}}
                <div style="width: 340px; text-align: center;">
                    <div style="margin-bottom: 4px; font-weight: 600; color: #475569;" id="preview-footer-lokasi-tanggal">
                        <?= htmlspecialchars($lokasi) ?>, <?= htmlspecialchars($tanggal) ?>
                    </div>
                    <div style="font-weight: 700; text-transform: uppercase; height: 28px;" id="preview-footer-jabatan">
                        <?= htmlspecialchars($jabKnow) ?>
                    </div>
                    <div style="height: 60px;"></div>
                    <div style="font-weight: 900; text-decoration: underline; font-size: 12px;" id="preview-footer-mengetahui">
                        <?= htmlspecialchars($know) ?>
                    </div>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }
}
