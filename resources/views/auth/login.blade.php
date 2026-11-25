<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}" class="h-full bg-slate-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — {{ __('erp.app_title') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    @vite(['resources/css/app.css'])

    <style>
        [dir="rtl"] { font-family: 'Noto Nastaliq Urdu', Tahoma, Arial, sans-serif; }
        [dir="ltr"] { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 selection:bg-indigo-600 selection:text-white">
    <div class="max-w-md w-full space-y-6">

        <!-- Language switcher on top -->
        <div class="flex justify-between items-center text-xs">
            <span class="text-slate-400 font-medium">Khalid Traders ERP</span>
            <div class="inline-flex rounded-lg border border-slate-700 p-0.5 bg-slate-800 text-xs font-bold">
                <a href="{{ route('locale.switch', 'en') }}" class="px-2.5 py-1 rounded {{ app()->getLocale() == 'en' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-white' }}">EN</a>
                <a href="{{ route('locale.switch', 'ur') }}" class="px-2.5 py-1 rounded {{ app()->getLocale() == 'ur' ? 'bg-indigo-600 text-white font-bold' : 'text-slate-400 hover:text-white' }}">اردو</a>
            </div>
        </div>

        <!-- Card Container -->
        <div class="bg-white rounded-3xl p-8 shadow-2xl border border-slate-800">
            <!-- Brand Icon & Title -->
            <div class="text-center space-y-2">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-tr from-amber-500 to-indigo-600 flex items-center justify-center text-white font-black text-2xl shadow-lg shadow-indigo-500/30">
                    KT
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">{{ __('erp.brand_name') }}</h1>
                <p class="text-xs text-slate-500 font-medium">{{ __('erp.tagline') }}</p>
            </div>

            @if(session('status'))
                <div class="mt-4 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium">
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mt-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium space-y-1">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @if(app()->environment('local'))
                <div class="mt-4 p-3.5 rounded-2xl bg-indigo-50/70 border border-indigo-100 text-xs text-slate-700 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-indigo-950 flex items-center gap-1.5">
                            <i data-lucide="key-round" class="w-4 h-4 text-indigo-600"></i> Admin Login Credentials
                        </span>
                        <button type="button" onclick="fillAdminCredentials()" class="px-2.5 py-1 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition">
                            Auto-Fill
                        </button>
                    </div>
                    <div class="font-mono text-[11px] text-slate-600 bg-white p-2.5 rounded-xl border border-indigo-100/60 space-y-1">
                        <div>Email: <strong class="text-indigo-900 select-all">khalid.owner@khalidtraders.pk</strong></div>
                        <div>Password: <strong class="text-indigo-900 select-all">Owner@Khalid2026!</strong> <span class="text-slate-400 font-sans">(or <strong>12345678</strong>)</span></div>
                    </div>
                </div>
            @endif

            <!-- Login Form -->
            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus
                           placeholder="user@khalidtraders.pk"
                           class="w-full text-sm font-semibold rounded-xl border border-slate-300 p-3 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Password</label>
                    <input type="password" name="password" required
                           placeholder="••••••••"
                           class="w-full text-sm font-semibold rounded-xl border border-slate-300 p-3 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                </div>

                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-600">
                        <input type="checkbox" name="remember" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span>Remember me</span>
                    </label>
                    <span class="text-slate-400">Enterprise Access</span>
                </div>

                <button type="submit" class="w-full py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-sm shadow-lg shadow-indigo-600/30 transition flex items-center justify-center gap-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span>Secure Sign In</span>
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-100 text-center">
                <p class="text-[11px] text-slate-400">
                    Public self-registration is disabled. Contact system administrator for account credentials.
                </p>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function fillAdminCredentials() {
            const emailInput = document.querySelector('input[name="email"]');
            const passwordInput = document.querySelector('input[name="password"]');
            if (emailInput && passwordInput) {
                emailInput.value = 'khalid.owner@khalidtraders.pk';
                passwordInput.value = 'Owner@Khalid2026!';
            }
        }
    </script>
</body>
</html>
