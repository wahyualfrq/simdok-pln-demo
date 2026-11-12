<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Contoh — SIMDOK PLN S2JB</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
{{-- DEMO: halaman placeholder preview file (pengganti drive.google.com). Dipakai di iframe modal preview & tab "Buka". --}}
<body class="h-full antialiased bg-slate-50 font-sans">

<div class="min-h-full flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-sm p-6 text-center">

        <div class="w-16 h-16 rounded-2xl bg-blue-50 text-pln-blue mx-auto mb-5 flex items-center justify-center">
            @if ($kind === 'image')
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
            @else
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
            @endif
        </div>

        <h1 class="text-base font-bold text-slate-800 break-words">{{ $file['name'] }}</h1>
        <p class="text-xs text-slate-400 mt-1">
            {{ strtoupper($ext) ?: 'FILE' }}
            @if ((int) $file['size'] > 0)
                &middot; {{ number_format($file['size'] / 1024, 1) }} KB
            @endif
            @if (! empty($file['modifiedTime']))
                &middot; {{ \Carbon\Carbon::parse($file['modifiedTime'])->locale('id')->translatedFormat('d F Y') }}
            @endif
        </p>

        <p class="text-sm text-slate-500 mt-4">
            @if ($kind === 'image')
                Pratinjau gambar contoh.
            @elseif ($kind === 'pdf')
                Pratinjau dokumen PDF contoh.
            @else
                Pratinjau berkas contoh.
            @endif
            Berkas ini tidak berisi dokumen asli. Gunakan tombol Download untuk mengunduh berkas contoh.
        </p>

    </div>
</div>

</body>
</html>
