<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aktivasi akun eMPK</title>
</head>
<body>
    <main>
        <h1>Aktivasi akun eMPK</h1>
        @if ($errors->any())
            <ul role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
        <form method="post" action="{{ route('panel.activation.store') }}">
            @csrf
            <p><label>Email <input type="email" name="email" autocomplete="username" value="{{ old('email') }}" required></label></p>
            <p><label>Kode aktivasi <input type="password" name="activation_code" autocomplete="one-time-code" required></label></p>
            <p><label>Kata sandi baru <input type="password" name="password" autocomplete="new-password" minlength="14" required></label></p>
            <p><label>Konfirmasi kata sandi <input type="password" name="password_confirmation" autocomplete="new-password" minlength="14" required></label></p>
            <button type="submit">Aktifkan akun</button>
        </form>
        <p><a href="{{ route('filament.admin.auth.login') }}">Kembali ke login</a></p>
    </main>
</body>
</html>
