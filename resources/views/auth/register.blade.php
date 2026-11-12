<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun — SIMDOK PLN S2JB</title>
    <meta name="description" content="Smart Document Automation Dashboard — Daftar akun baru">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full antialiased" style="font-family:'Inter',ui-sans-serif,system-ui,sans-serif; background:#F8FAFC;">

<div class="min-h-full flex">

    {{-- ===== Kolom Kiri — Branding PLN ===== --}}
    <div class="hidden lg:flex lg:w-[52%] xl:w-[55%] relative overflow-hidden flex-col justify-between p-12" style="background:linear-gradient(135deg,#0a3a6e 0%,#0F6CBD 60%,#1e85d8 100%);">
        <div class="absolute inset-0 opacity-[0.05]" style="background-image:radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size:32px 32px;"></div>
        <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full opacity-10" style="background:white;"></div>
        <div class="absolute -bottom-20 -left-20 w-80 h-80 rounded-full opacity-10" style="background:white;"></div>

        <div class="relative z-10">
            <div class="flex items-center gap-3.5 mb-16">
                <img src="{{ asset('images/logo.webp') }}" alt="PLN" class="w-16 h-16 object-contain">
                <div>
                    <p class="text-white font-extrabold text-2xl leading-none">SIMDOK</p>
                    <p class="text-white/80 font-medium text-sm leading-tight mt-1">PLN S2JB</p>
                </div>
            </div>

            <div class="max-w-sm">
                <h1 class="text-4xl font-bold text-white leading-tight mb-4">
                    Bergabung &<br>Mulai Kelola
                </h1>
                <p class="text-white/70 text-base leading-relaxed">
                    Daftarkan akun baru Anda untuk mengakses platform Smart Document Automation PLN S2JB.
                </p>
            </div>
        </div>

        <div class="relative z-10">
            <p class="text-white/40 text-xs">© {{ date('Y') }} PT PLN (Persero) Unit Induk Distribusi S2JB</p>
        </div>
    </div>

    {{-- ===== Kolom Kanan — Form Register ===== --}}
    <div class="flex-1 flex flex-col justify-center px-6 py-12 sm:px-10 lg:px-16 xl:px-20">
        <div class="w-full max-w-sm mx-auto">

            {{-- Mobile logo --}}
            <div class="flex items-center gap-3.5 mb-8 lg:hidden">
                <img src="{{ asset('images/logo.webp') }}" alt="PLN" class="w-14 h-14 object-contain">
                <div>
                    <p class="font-extrabold text-slate-800 text-xl leading-none">SIMDOK</p>
                    <p class="text-slate-500 text-xs font-medium leading-tight mt-0.5">PLN S2JB</p>
                </div>
            </div>

            {{-- Heading --}}
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-slate-900">Buat akun baru</h2>
                <p class="text-slate-500 mt-1.5 text-sm">Isi formulir berikut untuk mendaftar.</p>
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

            {{-- Register Form --}}
            <form action="{{ route('register') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-slate-700 mb-1.5">Nama Lengkap</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           required autofocus autocomplete="name"
                           class="block w-full px-4 py-2.5 text-sm text-slate-800 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pln-blue/30 focus:border-pln-blue transition-all duration-150 placeholder:text-slate-400"
                           placeholder="Nama sesuai identitas">
                </div>

                <div>
                    <label for="username" class="block text-sm font-medium text-slate-700 mb-1.5">Username</label>
                    <input type="text" id="username" name="username" value="{{ old('username') }}"
                           required autocomplete="username"
                           class="block w-full px-4 py-2.5 text-sm text-slate-800 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pln-blue/30 focus:border-pln-blue transition-all duration-150 placeholder:text-slate-400"
                           placeholder="Pilih username unik">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <input type="password" id="password" name="password"
                           required autocomplete="new-password"
                           class="block w-full px-4 py-2.5 text-sm text-slate-800 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pln-blue/30 focus:border-pln-blue transition-all duration-150 placeholder:text-slate-400"
                           placeholder="Minimal 8 karakter">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700 mb-1.5">Konfirmasi Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           required autocomplete="new-password"
                           class="block w-full px-4 py-2.5 text-sm text-slate-800 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-pln-blue/30 focus:border-pln-blue transition-all duration-150 placeholder:text-slate-400"
                           placeholder="Ulangi password">
                </div>

                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-pln-blue hover:bg-blue-700 text-white text-sm font-semibold rounded-xl shadow-sm shadow-blue-200 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-pln-blue/50 focus:ring-offset-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" />
                    </svg>
                    Daftar Akun
                </button>
            </form>

            <p class="mt-8 text-center text-sm text-slate-500">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-semibold text-pln-blue hover:text-blue-700 transition-colors ml-1">Masuk di sini</a>
            </p>

        </div>
    </div>

</div>

</body>
</html>
