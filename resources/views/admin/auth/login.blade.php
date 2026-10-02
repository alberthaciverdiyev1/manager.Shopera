<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş — ShopEra Manager</title>
    @vite(['resources/admin/app.ts'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-900 p-4">
    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl">
        <div class="mb-6 flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-xl font-black text-white">M</span>
            <div>
                <p class="text-lg font-bold text-gray-800">ShopEra Manager</p>
                <p class="text-xs text-gray-400">Abunəlik & sahib idarəsi</p>
            </div>
        </div>
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('admin.login.attempt') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">E-poçt</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Şifrə</label>
                <input type="password" name="password" required
                       class="block w-full rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <button class="w-full rounded-lg bg-brand-600 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">Daxil ol</button>
        </form>
    </div>
</body>
</html>
