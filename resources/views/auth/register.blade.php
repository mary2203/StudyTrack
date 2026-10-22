<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta | StudyTrack</title>
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
                <span class="auth-eyebrow">Un nuevo comienzo</span>
                <h1>Tus metas merecen<br><em>un buen plan.</em></h1>
                <p>Organiza tus cursos, conoce tu progreso y mantén tus actividades a la vista durante todo el semestre.</p>
            </div>
        </section>

        <section class="auth-form-area" aria-labelledby="register-heading">
            <div class="auth-form-container">
                <p class="auth-mobile-brand"><span class="brandlogo"><img src="{{ asset('images/studytrack-logo.png') }}" alt="" aria-hidden="true"></span> StudyTrack</p>
                <p class="eyebrow">Empieza hoy</p>
                <h2 id="register-heading" class="auth-title">Crear cuenta</h2>
                <p class="auth-description">Regístrate para tener tu propio espacio académico.</p>

                @if ($errors->any())
                    <div class="auth-alert" role="alert">
                        <strong>Revisa los siguientes errores:</strong>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('register.store') }}" class="auth-form">
                    @csrf
                    <div class="auth-field">
                        <label for="name">Nombre</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" placeholder="Tu nombre completo" required autofocus>
                    </div>
                    <div class="auth-field">
                        <label for="email">Correo electrónico</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="nombre@universidad.edu" required>
                    </div>
                    <div class="auth-field">
                        <label for="password">Contraseña</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" placeholder="Crea una contraseña" required>
                    </div>
                    <div class="auth-field">
                        <label for="password_confirmation">Confirmar contraseña</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repite tu contraseña" required>
                    </div>
                    <button type="submit" class="primary auth-submit">Registrarme <span aria-hidden="true">→</span></button>
                </form>
                <p class="auth-switch">¿Ya tienes una cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
            </div>
        </section>
    </main>
</body>
</html>
