<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | StudyTrack</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-body">
    <main class="auth-layout">
        <section class="auth-showcase" aria-label="Presentación de StudyTrack">
            <a class="auth-brand" href="{{ route('login') }}">
                <span class="brandlogo"><img src="{{ asset('images/studytrack-logo.png') }}" alt="" aria-hidden="true"></span>
                <span>StudyTrack</span>
            </a>
            <div class="auth-showcase-content">
                <span class="auth-eyebrow">Tu espacio académico</span>
                <h1>Organiza tu semestre.<br><em>Avanza con claridad.</em></h1>
                <p>Ten tus cursos, actividades y prioridades en un mismo lugar para enfocarte en lo que más importa.</p>
            </div>
        </section>

        <section class="auth-form-area" aria-labelledby="login-heading">
            <div class="auth-form-container">
                <p class="auth-mobile-brand"><span class="brandlogo"><img src="{{ asset('images/studytrack-logo.png') }}" alt="" aria-hidden="true"></span> StudyTrack</p>
                <p class="eyebrow">Bienvenido de nuevo</p>
                <h2 id="login-heading" class="auth-title">Iniciar sesión</h2>
                <p class="auth-description">Accede a tu espacio para continuar organizando tu semestre.</p>

                @if ($errors->any())
                    <div class="auth-alert" role="alert">
                        <strong>No se pudo iniciar sesión.</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                    @csrf
                    <div class="auth-field">
                        <label for="email">Correo electrónico</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="nombre@universidad.edu" required autofocus>
                    </div>
                    <div class="auth-field">
                        <label for="password">Contraseña</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required>
                    </div>
                    <button type="submit" class="primary auth-submit">Entrar a StudyTrack <span aria-hidden="true">→</span></button>
                </form>
                <p class="auth-switch">¿No tienes una cuenta? <a href="{{ route('register') }}">Crear cuenta</a></p>
            </div>
        </section>
    </main>
</body>
</html>
