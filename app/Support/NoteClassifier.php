<?php

namespace App\Support;

/**
 * Klasifikasi catatan pembeli (tanggal kirim & permintaan warna) + deteksi toko
 * dari nama produk Ginee. Port dari logika next-dashboard (src/lib/queries.ts:
 * extractDateNote / COLOR_SYNONYMS / extractColors / store inference) supaya
 * halaman Monitoring Notes iLook punya klasifikasi yang sama.
 */
class NoteClassifier
{
    private static function monthName(string $m): string
    {
        $map = [
            'jan' => 'Januari', 'januari' => 'Januari',
            'feb' => 'Februari', 'februari' => 'Februari',
            'mar' => 'Maret', 'maret' => 'Maret',
            'apr' => 'April', 'april' => 'April',
            'mei' => 'Mei', 'mey' => 'Mei',
            'jun' => 'Juni', 'juni' => 'Juni',
            'jul' => 'Juli', 'juli' => 'Juli', 'jully' => 'Juli', 'july' => 'Juli',
            'agu' => 'Agustus', 'agustus' => 'Agustus', 'agust' => 'Agustus',
            'sep' => 'September', 'september' => 'September',
            'okt' => 'Oktober', 'oktober' => 'Oktober',
            'nov' => 'November', 'november' => 'November',
            'des' => 'Desember', 'desember' => 'Desember',
        ];

        return $map[$m] ?? ucfirst($m);
    }

    /**
     * Ekstrak permintaan tanggal kirim dari catatan pembeli (bahasa Indonesia
     * campur singkatan), mis. "tgl 5 agustus", "besok", "po 7", "3 hari lagi".
     * Null bila tidak ada permintaan tanggal yang terdeteksi.
     */
    public static function extractDateNote(?string $noteRaw): ?string
    {
        $note = mb_strtolower((string) $noteRaw);
        $month = 'januari|februari|maret|april|mei|mey|juni|juli|jully|july|agustus|agust|september|oktober|november|desember|jan|feb|mar|apr|jun|jul|agu|sep|okt|nov|des';

        $tglPrefixMatch = preg_match(
            "#(?:tgl|tggl|tggal|tg|tanggal)\s*:?\s*(\d{1,2}(?:\s*(?:-|/|dan)\s*\d{1,2})?)(?:\s*(?:bulan|bln)?\s*($month))?#",
            $note,
            $m1
        );
        $dateMonthMatch = preg_match(
            "#\b(\d{1,2}(?:\s*(?:-|/|dan)\s*\d{1,2})?)\s*(?:bulan|bln)?\s*($month)\b#",
            $note,
            $m2
        );

        $tglMatch = $tglPrefixMatch ? $m1 : ($dateMonthMatch ? $m2 : null);
        if ($tglMatch) {
            $d = preg_replace('/\s/', '', $tglMatch[1]);
            if (str_contains($d, '-')) {
                $parts = explode('-', $d);
                $d = end($parts);
            } elseif (str_contains($d, '/')) {
                $parts = explode('/', $d);
                $d = end($parts);
            } elseif (str_contains($d, 'dan')) {
                $parts = explode('dan', $d);
                $d = end($parts);
            }

            $m = !empty($tglMatch[2]) ? ' ' . self::monthName($tglMatch[2]) : '';
            return "Tanggal {$d}{$m}";
        }

        if (preg_match('#(\d+(?:\s*-\s*\d+)?)\s*(?:hari|hr|hri|day)#', $note, $m)) {
            $val = preg_replace('/\s/', '', $m[1]);
            if (str_contains($val, '-')) {
                $parts = explode('-', $val);
                $val = end($parts);
            }
            return "{$val} Hari";
        }

        if (preg_match('#(\d+(?:\s*-\s*\d+)?)\s*(?:minggu|mggu|mgg|week)#', $note, $m)) {
            $val = preg_replace('/\s/', '', $m[1]);
            if (str_contains($val, '-')) {
                $parts = explode('-', $val);
                $val = end($parts);
            }
            return "{$val} Minggu";
        }

        if (preg_match('#\bse(?:bulan|bln)\b#', $note)) {
            return '1 Bulan';
        }

        if (preg_match('#(\d+(?:\s*-\s*\d+)?|satu|dua|tiga|empat|lima)\s*(?:bulan|bln|month)#', $note, $m)) {
            $val = preg_replace('/\s/', '', $m[1]);
            $wordMap = ['satu' => '1', 'dua' => '2', 'tiga' => '3', 'empat' => '4', 'lima' => '5'];
            if (isset($wordMap[$val])) {
                $val = $wordMap[$val];
            } elseif (str_contains($val, '-')) {
                $parts = explode('-', $val);
                $val = end($parts);
            }
            return "{$val} Bulan";
        }

        // "akhir/awal/pertengahan [bulan] <namaBulan>" harus dicek sebelum match
        // nama bulan polos, kalau tidak "akhir juni" cuma kebaca jadi "Juni".
        if (preg_match("#\b(awal|akhir|pertengahan)\s+(?:bulan\s+)?($month)\b#", $note, $m)) {
            $pos = ucfirst($m[1]);
            return "{$pos} " . self::monthName($m[2]);
        }

        if (preg_match("#\b($month)\b#", $note, $m)) {
            return self::monthName($m[1]);
        }

        if (preg_match('#\b(?:po|pre[\s\-]*order)\s*(\d{1,2})\b#', $note, $m)) {
            return "{$m[1]} Hari";
        }

        if (preg_match('#\bawal\s*(?:bulan|bln)\b#', $note)) {
            return 'Awal Bulan';
        }
        if (preg_match('#\bakhir\s*(?:bulan|bln)\b#', $note)) {
            return 'Akhir Bulan';
        }
        if (preg_match('#\bpertengahan\b#', $note)) {
            return 'Pertengahan Bulan';
        }
        if (str_contains($note, 'besok')) {
            return 'Besok';
        }
        if (str_contains($note, 'lusa')) {
            return 'Lusa';
        }

        // "minggu" itu ambigu: bisa berarti hari Minggu atau "minggu" sebagai satuan
        // waktu (seminggu). Kalau diikuti depan/ini/lalu/kemarin itu jelas maksudnya
        // satuan waktu ("minggu depan" = next week), bukan nama hari -- harus dicek
        // sebelum regex nama hari di bawah supaya tidak salah kebaca jadi "Minggu"
        // (hari Minggu/Sunday).
        if (preg_match('#\bminggu\s+(depan|ini|lalu|kemarin)\b#', $note, $m)) {
            return 'Minggu ' . ucfirst($m[1]);
        }

        if (preg_match('#\b(senin|selasa|rabu|kamis|jumat|sabtu|minggu)\b#', $note, $m)) {
            return ucfirst($m[1]);
        }

        return null;
    }

    /**
     * Sinonim warna -> label kanonik. Pembeli menulis "putih/biru/dasty", SKU
     * memakai "PUTIH/BIRU/DUSTY" -- keduanya harus dianggap warna yang sama
     * supaya tidak salah tandai mismatch. Ditambah beberapa warna yang muncul
     * di SKU iLook nyata tapi tidak ada di daftar next-dashboard (coffee,
     * terakota, hazel).
     */
    private const COLOR_SYNONYMS = [
        'putih' => 'Putih', 'white' => 'Putih',
        'hitam' => 'Hitam', 'black' => 'Hitam',
        'biru' => 'Biru', 'blue' => 'Biru',
        'navy' => 'Navy',
        'merah' => 'Merah', 'red' => 'Merah',
        'hijau' => 'Hijau', 'green' => 'Hijau',
        'kuning' => 'Kuning', 'yellow' => 'Kuning',
        'pink' => 'Pink',
        'ungu' => 'Lavender', 'lavender' => 'Lavender', 'lilac' => 'Lavender', 'purple' => 'Lavender',
        'dusty' => 'Dusty', 'dasty' => 'Dusty', 'dusti' => 'Dusty',
        'tosca' => 'Tosca', 'toska' => 'Tosca',
        'cream' => 'Cream', 'krem' => 'Cream', 'creme' => 'Cream',
        'mint' => 'Mint',
        'olive' => 'Olive',
        'coklat' => 'Coklat', 'cokelat' => 'Coklat', 'brown' => 'Coklat',
        'mocca' => 'Mocca', 'mocha' => 'Mocca',
        'abu' => 'Abu', 'grey' => 'Abu', 'gray' => 'Abu',
        'maroon' => 'Maroon', 'marun' => 'Maroon',
        'emerald' => 'Emerald',
        'salem' => 'Salem', 'peach' => 'Peach', 'mustard' => 'Mustard',
        'beige' => 'Beige', 'khaki' => 'Khaki', 'sage' => 'Sage', 'milo' => 'Milo',
        'coffee' => 'Coffee', 'terakota' => 'Terakota', 'hazel' => 'Hazel',
    ];

    /** Cari warna kanonik yang disebut dalam sebuah teks (catatan pembeli atau SKU). */
    public static function extractColors(?string $text): array
    {
        $t = mb_strtolower((string) $text);
        $found = [];
        foreach (self::COLOR_SYNONYMS as $key => $canonical) {
            if (preg_match('#\b' . preg_quote($key, '#') . '\b#', $t)) {
                $found[$canonical] = true;
            }
        }
        return array_keys($found);
    }

    /**
     * Tebak toko/brand dari nama produk Ginee (kolom order_items.product_name),
     * mis. "AC | [Ready] One Set Khairat..." -> "AC". Data produksi tidak selalu
     * punya prefix bersih (typo "Gomieo"/"Icih", atau tanpa prefix sama sekali)
     * jadi ini best-effort, bukan kolom asli -- default "Lainnya".
     */
    public static function inferStore(?string $firstProductName): string
    {
        $upper = mb_strtoupper((string) $firstProductName);

        if (str_contains($upper, 'AC ') || str_contains($upper, 'AC|') || str_contains($upper, 'AC-')) {
            return 'AC';
        }
        if (str_contains($upper, 'SH ') || str_contains($upper, 'SH|') || str_contains($upper, 'SH-')) {
            return 'SH';
        }
        if (str_contains($upper, 'AF ') || str_contains($upper, 'AF|') || str_contains($upper, 'AF-')) {
            return 'AF';
        }
        if (str_contains($upper, 'ILOOK')) {
            return 'iLook';
        }
        if (str_contains($upper, 'GOEMIO') || str_contains($upper, 'GOMIEO')) {
            return 'Goemio';
        }
        if (str_contains($upper, 'BLESS')) {
            return 'Bless';
        }
        if (str_contains($upper, 'ICHI') || str_contains($upper, 'ICIH')) {
            return 'Ichi';
        }

        return 'Lainnya';
    }

    /**
     * Ringkasan klasifikasi lengkap untuk satu order: permintaan tanggal,
     * permintaan warna, dan mismatch warna (diminta pembeli vs warna di SKU
     * order itu sendiri).
     *
     * @param string|null $buyerMessage
     * @param string[] $itemSkus daftar `order_items.sku` untuk order ini
     */
    public static function classify(?string $buyerMessage, array $itemSkus): array
    {
        $extractedDate = self::extractDateNote($buyerMessage);
        $requestedColors = self::extractColors($buyerMessage);

        $orderedColors = [];
        foreach ($itemSkus as $sku) {
            foreach (self::extractColors($sku) as $c) {
                $orderedColors[$c] = true;
            }
        }
        $orderedColors = array_keys($orderedColors);

        $isColorRequest = count($requestedColors) > 0;
        $mismatchColors = $isColorRequest && count($orderedColors) > 0
            ? array_values(array_diff($requestedColors, $orderedColors))
            : [];
        $colorMismatch = $isColorRequest && count($mismatchColors) > 0;

        return [
            'extracted_date' => $extractedDate,
            'is_date_request' => $extractedDate !== null,
            'is_color_request' => $isColorRequest,
            'requested_colors' => $requestedColors,
            'ordered_colors' => $orderedColors,
            'mismatch_colors' => $mismatchColors,
            'color_mismatch' => $colorMismatch,
        ];
    }
}
