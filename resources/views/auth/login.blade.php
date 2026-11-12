<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — SIMDOK PLN S2JB</title>
    <meta name="description" content="Smart Document Automation Dashboard — Masuk ke akun Anda">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased" style="font-family:'Inter',ui-sans-serif,system-ui,sans-serif; background:#F8FAFC;">

<div class="min-h-full flex">

    {{-- ===== Kolom Kiri — Branding PLN ===== --}}
    <div class="hidden lg:flex lg:w-[52%] xl:w-[55%] relative overflow-hidden flex-col justify-between p-12" style="background:linear-gradient(135deg,#0a3a6e 0%,#0F6CBD 60%,#1e85d8 100%);">

        {{-- Background pattern --}}
        <div class="absolute inset-0 opacity-[0.05]" style="background-image:radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size:32px 32px;"></div>
        <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full opacity-10" style="background:white;"></div>
        <div class="absolute -bottom-20 -left-20 w-80 h-80 rounded-full opacity-10" style="background:white;"></div>

        {{-- Logo & App Name --}}
        <div class="relative z-10">
            <div class="flex items-center gap-3.5 mb-16">
                <img src="{{ asset('images/logo.webp') }}" alt="PLN" class="w-16 h-16 object-contain">
                <div>
                    <p class="text-white font-extrabold text-2xl leading-none">SIMDOK</p>
                    <p class="text-white/80 font-medium text-sm leading-tight mt-1">PLN S2JB</p>
                </div>
            </div>

            {{-- Hero Text --}}
            <div class="max-w-sm">
                <h1 class="text-4xl font-bold text-white leading-tight mb-4">
                    Smart Document<br>Automation
                </h1>
                <p class="text-white/70 text-base leading-relaxed">
                    Kelola, pantau, dan otomasi dokumen PLN Unit Induk Distribusi Sumatera Selatan, Jambi, dan Bengkulu dalam satu platform terpadu.
                </p>
            </div>

            {{-- Feature pills --}}
            <div class="flex flex-wrap gap-2 mt-8">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/15 text-white/90 text-xs font-medium backdrop-blur-sm">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                    Google Drive Terintegrasi
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/15 text-white/90 text-xs font-medium backdrop-blur-sm">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                    Monitoring Real-time
                </span>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/15 text-white/90 text-xs font-medium backdrop-blur-sm">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd"/></svg>
                    Laporan Otomatis
                </span>
            </div>
        </div>

        {{-- Footer --}}
        <div class="relative z-10">
            <p class="text-white/40 text-xs">© {{ date('Y') }} PT PLN (Persero) Unit Induk Distribusi S2JB</p>
        </div>
    </div>

    {{-- ===== Kolom Kanan — Form Login ===== --}}
    <div class="flex-1 flex flex-col justify-center px-6 py-12 sm:px-10 lg:px-16 xl:px-20">
        <div class="w-full max-w-sm mx-auto">

            {{-- Mobile logo (visible only on small screens) --}}
            <div class="flex items-center gap-3.5 mb-8 lg:hidden">
                <img src="{{ asset('images/logo.webp') }}" alt="PLN" class="w-14 h-14 object-contain">
                <div>
                    <p class="font-extrabold text-slate-800 text-xl leading-none">SIMDOK</p>
                    <p class="text-slate-500 text-xs font-medium leading-tight mt-0.5">PLN S2JB</p>
                </div>
            </div>

            {{-- Heading --}}
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-slate-900">Selamat datang</h2>
                <p class="text-slate-500 mt-1.5 text-sm">Masuk dengan akun SIMDOK Anda untuk melanjutkan.</p>
            </div>

            {{-- Error Alert --}}
            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-100">
                    <div class="flex items-start gap-3">
                        <svg class="w-4 h-4 text-red-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                        </svg>
                        <ul class="text-sm text-red-600 space-y-0.5 list-none">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- Login Form --}}
            <form action="{{ route('login') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700 mb-1.5">Username</label>
                    <input type="text" id="username" name="username" value="{{ old('username', config('demo.enabled') ? config('demo.username') : '') }}" {{-- DEMO: nilai awal akun demo --}}
                           required autofocus autocomplete="username"
                           class="block w-full px-4 py-2.5 text-sm text-slate-800 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pln-blue/30 focus:border-pln-blue transition-all duration-150 placeholder:text-slate-400"
                           placeholder="Masukkan username Anda">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <input type="password" id="password" name="password"
                           @if (config('demo.enabled'))
                               {{-- DEMO: nilai awal password akun demo (publik) --}}
                               value="{{ config('demo.password') }}"
                           @endif
                           required autocomplete="current-password"
                           class="block w-full px-4 py-2.5 text-sm text-slate-800 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pln-blue/30 focus:border-pln-blue transition-all duration-150 placeholder:text-slate-400"
                           placeholder="••••••••">
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-slate-300 text-pln-blue focus:ring-pln-blue/30">
                        <span class="text-sm text-slate-600">Ingat saya</span>
                    </label>
                </div>

                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-pln-blue hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm shadow-blue-200 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-pln-blue/50 focus:ring-offset-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                    </svg>
                    Masuk ke SIMDOK
                </button>
            </form>

            @if (config('demo.enabled'))
                {{-- DEMO: catatan akun demo (tanpa auto-submit; pengguna menekan tombol sendiri) --}}
                <x-demo-note class="mt-6">Akun demo sudah terisi. Tekan <strong>Masuk ke SIMDOK</strong> untuk masuk.</x-demo-note>
            @endif

        </div>
    </div>

</div>

</body>
</html>
