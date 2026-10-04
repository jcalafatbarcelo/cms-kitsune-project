<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $text('admin.login.title') }}</title>
</head>
<body>
<main>
    <h1>{{ $text('admin.login.title') }}</h1>
    @if(session('login_throttled'))
        <p role="alert">{{ $text('admin.login.throttled') }}</p>
    @elseif($errors->any())
        <p role="alert">{{ $text('admin.login.failed') }}</p>
    @endif
    <form method="POST" action="{{ route('admin.login.store') }}">
        @csrf
        <label for="email">{{ $text('admin.login.email') }}</label>
        <input id="email" name="email" type="email" maxlength="255" autocomplete="username" value="{{ old('email') }}" required>
        <label for="password">{{ $text('admin.login.password') }}</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">{{ $text('admin.login.submit') }}</button>
    </form>
</main>
</body>
</html>
