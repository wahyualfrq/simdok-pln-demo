<?php

declare(strict_types=1);

namespace App\Services;

class UnitNameNormalizerService
{
    /**
     * Alias Dictionary Mapping.
     * Mengatur nama kanonikal -> daftar variasi string yang ternormalisasi.
     */
    private const ALIAS_DICTIONARY = [
        'UID' => ['KANTORUID', 'KUID', 'UID', 'KANTOR', 'UNITINDUKDISTRIBUSIS2JB', 'UNITINDUKDISTRIBUSI', 'UIDS2JB'],
        'TELANAIPURA' => ['TELANAIPURA', 'TELANAIPURA'],
        'SEBERANGKOTA' => ['SEBERANGKOTA', 'SEBERANGKOTA'],
        'LUBUKLINGGAU' => ['LUBUKLINGGAU', 'LLG', 'ULPLLG', 'UP3LLG', 'MUP3LLG', 'MULPLUBUKLINGGAU', 'ULPLUBUKLINGGAU', 'MUP3LUBUKLINGGAU'],
        'MUARABUNGO' => ['MUARABUNGO', 'MBO', 'ULPMBO', 'MULPMUARABUNGO'],
        'OGANILIR' => ['OGANILIR', 'OGI', 'MUP3OGANILIR', 'MUP3OGI', 'UP3OGI', 'ULPOGI', 'ULPOGANILIR'],
        'PANGKALANBALAI' => ['PANGKALANBALAI', 'PKLBALAI', 'PBALAI'],
        'LAKDAN' => ['TLLAKDAN', 'LAKDAN', 'LAKSDAN', 'TLLAKSDAN', 'LAKSDA', 'TLLAKSDA', 'TLLAKDANJMB', 'LAKDANJMB', 'TLLAKSDANJMB', 'TLLAKDANPLG', 'TLLAKDANLLG', 'TLLAKDANMBO', 'TLLAKDANBGU', 'TLLAKDANOGI', 'TLLAKDANBTA', 'TLLAKDANMDA', 'TLPENGADAAN', 'PENGADAAN', 'TL PENGADAAN'],
        'MUARABELITI' => ['MUARABELITI', 'MBT', 'ULPMBT', 'MULPMUARABELITI', 'ULPMUARABELITI'],
        'MURATARA' => ['MURATARA', 'MRT', 'ULPMRT', 'MULPMURATARA', 'ULPMURATARA'],
        'PENDOPO' => ['PENDOPO', 'PDP', 'ULPPDP', 'MULPPENDOPO', 'ULPPENDOPO'],
        'TEBINGTINGGI' => ['TEBINGTINGGI', 'TBT', 'ULPTBT', 'MULPTEBINGTINGGI', 'ULPTEBINGTINGGI'],
        'BATURAJA' => ['BATURAJA', 'BTA', 'ULPBTA', 'MULPBATURAJA'],
        'LEMBAYUNG' => ['LEMBAYUNG', 'LBY', 'ULPLBY', 'MULPLEMBAYUNG'],
        'MUARADUA' => ['MUARADUA', 'MDA', 'ULPMDA', 'MULPMUARADUA'],
        'MUARAENIM' => ['MUARAENIM', 'ME', 'ULPME', 'MULPMUARAENIM'],
        'MARTAPURA' => ['MARTAPURA', 'MTP', 'ULPMTP', 'MULPMARTAPURA'],
        'PAGARALAM' => ['PAGARALAM', 'PGA', 'ULPPGA', 'MULPPAGARALAM'],
        'BANGKO' => ['BANGKO', 'BKO', 'ULPBKO', 'MULPBANGKO'],
        'KOTABUNGO' => ['KOTABUNGO', 'BKT', 'ULPBKT', 'MULPKOTABUNGO'],
        'KERSIKTUO' => ['KERSIKTUO', 'KST', 'ULPKST', 'MULPKERSIKTUO'],
        'RIMBOBUJANG' => ['RIMBOBUJANG', 'RBB', 'ULPRBB', 'MULPRIMBOBUJANG'],
        'SUNGAIPENUH' => ['SUNGAIPENUH', 'SPN', 'ULPSPN', 'MULPSUNGAIPENUH'],
        'SAROLANGUN' => ['SAROLANGUN', 'SRL', 'ULPSRL', 'MULPSAROLANGUN'],
        'MUARATEBO' => ['MUARATEBO', 'TBO', 'ULPTBO', 'MULPMUARATEBO'],
        'INDRALAYA' => ['INDRALAYA', 'IND', 'ULPIND', 'MULPINDRALAYA'],
        'KAYUAGUNG' => ['KAYUAGUNG', 'KYA', 'ULPKYA', 'MULPKAYUAGUNG'],
        'PRABUMULIH' => ['PRABUMULIH', 'PRB', 'ULPPRB', 'MULPPRABUMULIH'],
        'TUGUMULYO' => ['TUGUMULYO', 'TGM', 'ULPTGM', 'MULPTUGUMULYO'],
        'ARGAMAKMUR' => ['ARGAMAKMUR', 'ARGAMAMKMUR', 'MAMKMUR'],
    ];

    /**
     * Membersihkan dan menormalisasi nama unit/sub-unit menjadi nama kanonikal standar.
     *
     * Contoh:
     *   "01. MULP TELANAIPURA" -> "TELANAIPURA"
     *   "ULP TELANAI PURA"     -> "TELANAIPURA"
     *   "KANTOR UID"           -> "UID"
     *   "01. MUP3 BENGKULU"    -> "BENGKULU"
     *
     * @param  string  $name
     * @return string
     */
    public function normalize(string $name): string
    {
        $raw = trim($name);
        if ($raw === '') {
            return '';
        }

        // 1. Convert to UPPERCASE
        $upper = strtoupper($raw);

        // 2. Hapus nomor urut di awal string (contoh: "01. ", "1. ", "a. ")
        $cleaned = preg_replace('/^[A-Z0-9]+\.\s*/i', '', $upper);

        // 3. Hapus karakter khusus non-alphanumeric (dash, dot, underscore, slash)
        $cleaned = preg_replace('/[^A-Z0-9\s]/i', '', $cleaned);

        // 4. Hapus prefix organisasi yang sering muncul
        $prefixes = [
            'MULP ', 'ULP ', 'MUP3 ', 'UP3 ', 'UP2D ', 'UP2K ',
            'KANTOR ',
            'TL ', 'MAN ', 'SM '
        ];

        foreach ($prefixes as $prefix) {
            if (str_starts_with($cleaned, $prefix)) {
                $cleaned = substr($cleaned, strlen($prefix));
            }
        }

        // 5. Hapus semua spasi tersisa
        $normalized = preg_replace('/\s+/', '', $cleaned);

        if ($normalized === '') {
            $normalized = preg_replace('/\s+/', '', $upper);
        }

        // 6. Pencocokan via Alias Dictionary
        foreach (self::ALIAS_DICTIONARY as $canonical => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $canonical;
            }
        }

        return $normalized;
    }

    /**
     * Membersihkan nama Region (contoh: "PLN UP3 PALEMBANG" -> "UP3 PALEMBANG" atau "PALEMBANG").
     *
     * @param  string  $regionName
     * @return string
     */
    public function normalizeRegion(string $regionName): string
    {
        $raw = strtoupper(trim($regionName));
        $raw = preg_replace('/^[A-Z0-9]+\.\s*/i', '', $raw);
        $raw = preg_replace('/^PLN\s+/i', '', $raw);
        $raw = preg_replace('/[^A-Z0-9\s]/i', '', $raw);
        
        $normalized = preg_replace('/\s+/', '', $raw);

        foreach (self::ALIAS_DICTIONARY as $canonical => $aliases) {
            if (in_array($normalized, $aliases, true)) {
                return $canonical;
            }
        }

        return $raw;
    }

    /**
     * Memeriksa apakah dua nama unit dianggap SAMA secara kanonikal.
     *
     * @param  string  $name1
     * @param  string  $name2
     * @return bool
     */
    public function isMatch(string $name1, string $name2): bool
    {
        $norm1 = $this->normalize($name1);
        $norm2 = $this->normalize($name2);

        if ($norm1 === $norm2) {
            return true;
        }

        // Substring / Contains fallback matching
        if (strlen($norm1) >= 4 && strlen($norm2) >= 4) {
            if (str_contains($norm1, $norm2) || str_contains($norm2, $norm1)) {
                return true;
            }
        }

        return false;
    }
}
