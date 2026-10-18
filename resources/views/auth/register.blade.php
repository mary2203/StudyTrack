<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta | StudyTrack</title>
</head>
<body>
    <main>
        <h1>StudyTrack</h1>
        <h2>Crear cuenta</h2>

        @if ($errors->any())
            <div role="alert">
                <p>Revisa los siguientes errores:</p>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}">
            @csrf
            <div>
                <label for="name">Nombre</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required autofocus>
            </div>
            <div>
                <label for="email">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
            </div>
            <div>
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
            </div>
            <div>
                <label for="password_confirmation">Confirmar contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>
            <button type="submit">Registrarme</button>
        </form>

        <p>¿Ya tienes una cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
    </main>
</body>
</html>
