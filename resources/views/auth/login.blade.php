<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | StudyTrack</title>
</head>
<body>
    <main>
        <h1>StudyTrack</h1>
        <h2>Iniciar sesión</h2>

        @if ($errors->any())
            <div role="alert">
                <p>No se pudo iniciar sesión:</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div>
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            </div>
            <div>
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button type="submit">Entrar</button>
        </form>

        <p>¿No tienes una cuenta? <a href="{{ route('register') }}">Crear cuenta</a></p>
    </main>
</body>
</html>
