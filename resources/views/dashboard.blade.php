<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>StudyTrack | {{ $user->name }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-studytrack-dashboard data-user-id="{{ $user->id }}" data-user-email="{{ $user->email }}">
    <svg class="svg-library" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <symbol id="i-home" viewBox="0 0 24 24"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="i-book" viewBox="0 0 24 24"><path d="M4 5h6c2 0 2 1 2 2v14c0-2-2-2-4-2H4V5Zm16 0h-6c-2 0-2 1-2 2v14c0-2 2-2 4-2h4V5Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></symbol>
        <symbol id="i-list" viewBox="0 0 24 24"><path d="m4 6 2 2 3-3M12 7h9M4 16l2 2 3-3m3 2h9" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="1.7"/><path d="M7 3v4m10-4v4M3 10h18" fill="none" stroke="currentColor" stroke-width="1.7"/></symbol>
        <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4 20V4m0 16h17M8 15l4-5 4 2 5-7" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8c0-4-2-6-6-6S6 4 6 8c0 7-3 7-3 10h18c0-3-3-3-3-10ZM10 22h4" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></symbol>
        <symbol id="i-logout" viewBox="0 0 24 24"><path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5m4-4 4-4-4-4m4 4H9" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    </svg>

    <div class="app">
        <aside class="sidebar">
            <a class="brand" href="{{ route('dashboard') }}#inicio">
                <span class="brandlogo"><img src="{{ asset('images/studytrack-logo.png') }}" alt="" aria-hidden="true"></span>
                <span>StudyTrack</span>
            </a>

            <p class="tinytitle">Mi espacio</p>
            <nav class="nav" aria-label="Navegación principal">
                <a href="#inicio" data-page="inicio" class="active"><svg aria-hidden="true"><use href="#i-home"/></svg><span>Inicio</span></a>
                <a href="#cursos" data-page="cursos"><svg aria-hidden="true"><use href="#i-book"/></svg><span>Mis cursos</span></a>
                <a href="#actividades" data-page="actividades"><svg aria-hidden="true"><use href="#i-list"/></svg><span>Actividades</span></a>
                <a href="#calendario" data-page="calendario"><svg aria-hidden="true"><use href="#i-calendar"/></svg><span>Calendario</span></a>
                <a href="#analitica" data-page="analitica"><svg aria-hidden="true"><use href="#i-chart"/></svg><span>Mi analítica</span></a>
            </nav>

            <section class="sidebarbottom" aria-label="Cuenta">
                <p class="tinytitle">Cuenta</p>
                <div class="profile">
                    <div class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                    <div class="profile-details">
                        <b>{{ $user->name }}</b>
                        <small>{{ $dashboard !== null ? $dashboard['semester']['name'] : 'Sin semestre actual' }}</small>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="logout-form">
                    @csrf
                    <button type="submit" class="logout-button"><svg aria-hidden="true"><use href="#i-logout"/></svg><span>Cerrar sesión</span></button>
                </form>
            </section>
        </aside>

        <div class="main">
            <nav class="mobilebar" aria-label="Navegación móvil">
                <a href="#inicio" data-page="inicio" class="active">Inicio</a>
                <a href="#cursos" data-page="cursos">Cursos</a>
                <a href="#actividades" data-page="actividades">Actividades</a>
                <a href="#calendario" data-page="calendario">Calendario</a>
                <a href="#analitica" data-page="analitica">Analítica</a>
            </nav>

            <header class="top">
                <p class="crumb">Mi espacio / <b id="breadcrumb-title">Inicio</b></p>
                <div class="topright">
                    <button class="st-header-button" type="button" data-st-open="switch-semester" title="Consultar y activar semestres">Semestres</button>
                    <span class="pill"><span class="dot"></span>{{ $dashboard !== null ? 'Semestre activo' : 'Sin semestre activo' }}</span>
                    <span class="unread" title="Recomendaciones sin leer"><svg aria-hidden="true"><use href="#i-bell"/></svg>{{ $dashboard['recommendations_unread'] ?? $recommendations->count() }}</span>
                    <div class="avatar avatar-small" title="{{ $user->name }}" aria-label="Cuenta: {{ $user->name }}">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                </div>
            </header>

            <main class="content">
                <section class="classroom-panel" aria-labelledby="classroom-heading">
                    <div class="classroom-panel-header">
                        <div class="classroom-brand">
                            <div class="classroom-brand-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none"><path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m3.5 7.8 8.5 4.4 8.5-4.4M12 12.2V21" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m7 5.5 9 4.7" stroke="currentColor" stroke-width="1.7"/></svg>
                            </div>
                            <div>
                                <p class="eyebrow">Sincronización académica</p>
                                <h2 id="classroom-heading">Google Classroom</h2>
                                <p class="classroom-intro">Tus cursos y entregas, organizados en un solo lugar.</p>
                            </div>
                        </div>
                        @if ($classroomConnection)
                            <span class="classroom-connection-badge"><span></span>Conectado</span>
                        @else
                            <span class="classroom-connection-badge is-disconnected"><span></span>Sin conectar</span>
                        @endif
                    </div>

                    @if ($classroomConnection)
                        <div class="classroom-account">
                            <div class="classroom-account-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($classroomConnection->google_email, 0, 1)) }}</div>
                            <div class="classroom-account-copy">
                                <span>Cuenta vinculada</span>
                                <strong>{{ $classroomConnection->google_email }}</strong>
                            </div>
                            <div class="classroom-sync-time">
                                <span>Última sincronización</span>
                                <strong>{{ $classroomConnection->last_synced_at?->format('d/m/Y · H:i') ?? 'Aún no sincronizada' }}</strong>
                            </div>
                            <div class="classroom-actions">
                                <form method="POST" action="{{ route('classroom.sync') }}" data-classroom-sync>
                                    @csrf
                                    <button class="primary classroom-sync-button" type="submit">
                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.1 9A6.5 6.5 0 0 1 17 6.5L20 12M4 12l3 5.5A6.5 6.5 0 0 0 18 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        Sincronizar ahora
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('classroom.disconnect') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="classroom-disconnect-button" type="submit">Desconectar</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="classroom-connect-prompt">
                            <div class="classroom-connect-copy">
                                <strong>Importa tu espacio académico</strong>
                                <p>Conecta tu cuenta para traer cursos, tareas y calificaciones. Solo solicitamos permisos de lectura.</p>
                            </div>
                            <a class="primary classroom-connect-button" href="{{ route('classroom.connect') }}">
                                Conectar cuenta
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        </div>
                    @endif

                    @if (session('classroom_status'))
                        <div class="classroom-feedback is-success" role="status">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12 4 4L19 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ session('classroom_status') }}
                        </div>
                    @endif
                    @error('classroom')
                        <div class="classroom-feedback is-error" role="alert">{{ $message }}</div>
                    @enderror

                    @if ($pendingClassroomImports->isNotEmpty())
                        <div class="classroom-imports">
                            <div class="classroom-imports-heading">
                                <div>
                                    <p class="eyebrow">Organiza tus cursos</p>
                                    <h3>Cursos por asignar <span>{{ $pendingClassroomImports->count() }}</span></h3>
                                    <p>Elige un semestre para cada curso. Classroom no indica a qué periodo académico pertenece.</p>
                                </div>
                            </div>
                            <div class="classroom-import-list">
                                @foreach ($pendingClassroomImports as $import)
                                    <article class="classroom-import-card">
                                        <div class="classroom-import-course">
                                            <div class="classroom-course-icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v17H6.5A2.5 2.5 0 0 1 4 17.5v-12Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M4 17.5A2.5 2.5 0 0 1 6.5 15H20M8 7h8m-8 4h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                                            </div>
                                            <div>
                                                <h4>{{ $import->name }}</h4>
                                                <div class="classroom-import-meta">
                                                    <span class="classroom-course-state {{ $import->course_state === 'ARCHIVED' ? 'is-archived' : '' }}">{{ $import->course_state === 'ARCHIVED' ? 'Archivado' : 'Activo' }}</span>
                                                    @if ($import->code)<span>{{ $import->code }}</span>@endif
                                                    @if ($import->section)<span>{{ $import->section }}</span>@endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="classroom-import-options">
                                            @if ($semesters->isNotEmpty())
                                                <form class="classroom-option-card" method="POST" action="{{ route('classroom.imports.resolve', $import) }}">
                                                    @csrf
                                                    <div class="classroom-option-heading"><span class="classroom-option-number">1</span><div><strong>Usar semestre existente</strong><small>Agrega este curso a uno de tus periodos</small></div></div>
                                                    <label class="visually-hidden" for="classroom-semester-{{ $import->id }}">Seleccionar semestre existente</label>
                                                    <select id="classroom-semester-{{ $import->id }}" name="semester_id" required>
                                                        <option value="">Seleccionar semestre</option>
                                                        @foreach ($semesters as $semester)
                                                            <option value="{{ $semester->id }}">{{ $semester->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('semester_id')<span class="classroom-field-error">{{ $message }}</span>@enderror
                                                    <button class="primary classroom-option-submit" type="submit">Asignar curso</button>
                                                </form>
                                            @endif
                                            <form class="classroom-option-card" method="POST" action="{{ route('classroom.imports.resolve', $import) }}">
                                                @csrf
                                                <input type="hidden" name="create_semester" value="1">
                                                <div class="classroom-option-heading"><span class="classroom-option-number">{{ $semesters->isNotEmpty() ? '2' : '1' }}</span><div><strong>Crear un semestre</strong><small>Ideal para un curso de otro periodo</small></div></div>
                                                <label class="visually-hidden" for="classroom-semester-name-{{ $import->id }}">Nombre del semestre</label>
                                                <input id="classroom-semester-name-{{ $import->id }}" name="semester_name" maxlength="100" required placeholder="Ej. Segundo semestre 2025">
                                                <div class="classroom-date-fields">
                                                    <label>Fecha de inicio<input type="date" name="start_date" required></label>
                                                    <label>Fecha de fin<input type="date" name="end_date" required></label>
                                                </div>
                                                @error('semester_name')<span class="classroom-field-error">{{ $message }}</span>@enderror
                                                @error('start_date')<span class="classroom-field-error">{{ $message }}</span>@enderror
                                                @error('end_date')<span class="classroom-field-error">{{ $message }}</span>@enderror
                                                <button class="outline classroom-option-submit" type="submit">Crear y asignar</button>
                                            </form>
                                        </div>
                                        <form class="classroom-ignore-form" method="POST" action="{{ route('classroom.imports.ignore', $import) }}">
                                            @csrf
                                            <button type="submit">Omitir este curso</button>
                                        </form>
                                    </article>
                                @endforeach
                            </div>
                            <p class="classroom-import-note">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8v4m0 4h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3l-7.5-13.1a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Omitir solo oculta el curso en StudyTrack; no modifica ni elimina nada en Classroom.
                            </p>
                        </div>
                    @endif
                </section>
                <div class="classroom-sync-backdrop" id="classroom-sync-modal" hidden>
                    <div class="classroom-sync-dialog" role="dialog" aria-modal="true" aria-labelledby="classroom-sync-title" aria-describedby="classroom-sync-description" tabindex="-1">
                        <div class="classroom-sync-spinner" aria-hidden="true"></div>
                        <p class="eyebrow">Google Classroom</p>
                        <h2 id="classroom-sync-title">Sincronizando tus cursos</h2>
                        <p id="classroom-sync-description">Estamos actualizando cursos, actividades y calificaciones. Esto puede tardar unos segundos.</p>
                        <span class="classroom-sync-progress"><span></span></span>
                        <span class="visually-hidden" role="status" aria-live="polite">Sincronización en curso</span>
                    </div>
                </div>
                <section class="page active" id="inicio" aria-labelledby="welcome-heading">
                    <div class="hero">
                        <div>
                            <p class="eyebrow">Tu panel académico</p>
                            <h1 id="welcome-heading">Tu semestre, bajo control <span class="hero-star" aria-hidden="true"></span></h1>
                            <p>Consulta tus cursos, organiza tus pendientes y conoce tus prioridades.</p>
                            @if ($dashboard !== null)
                                <p class="mini-note">{{ $dashboard['semester']['name'] }} · {{ $dashboard['semester']['start_date'] }} al {{ $dashboard['semester']['end_date'] }}</p>
                            @endif
                        </div>
                        <div class="heroactions">
                            <button type="button" class="st-action-button" data-st-open="semester">+ Nuevo semestre</button>
                            <a class="outline" href="#calendario" data-page="calendario">Ver calendario</a>
                            <a class="primary" href="#actividades" data-page="actividades">Mis actividades →</a>
                        </div>
                    </div>

                    @if ($dashboard === null)
                        <div class="card empty-state">
                            <div class="bubble" aria-hidden="true">
                                <img src="{{ asset('images/studytrack-logo.png') }}" alt="StudyTrack" class="empty-semester-logo">
                            </div>
                            <h2>Aún no tienes un semestre actual</h2>
                            <p>Crea un semestre o marca uno como actual para consultar tus cursos y actividades.</p>
                            <div class="st-empty-actions">
                                <button type="button" class="primary" data-st-open="semester">Crear mi primer semestre</button>
                                <button type="button" class="outline" data-st-open="switch-semester">Elegir uno existente</button>
                            </div>
                        </div>
                    @else
                        <section class="stats" aria-label="Resumen del semestre">
                            <div class="stat"><div class="stat-top"><span>Cursos registrados</span><span class="iconbox purple"><svg aria-hidden="true"><use href="#i-book"/></svg></span></div><div class="statnum">{{ count($dashboard['courses']) }}</div><div class="statfoot">{{ $dashboard['semester']['name'] }}</div></div>
                            <div class="stat"><div class="stat-top"><span>Actividades</span><span class="iconbox orange"><svg aria-hidden="true"><use href="#i-list"/></svg></span></div><div class="statnum">{{ $dashboard['summary']['total'] }}</div><div class="statfoot">{{ $dashboard['summary']['pending'] }} pendientes · {{ $dashboard['summary']['completed'] }} completadas</div></div>
                            <div class="stat"><div class="stat-top"><span>Cursos al día</span><span class="iconbox green"><svg aria-hidden="true"><use href="#i-chart"/></svg></span></div><div class="statnum">{{ collect($dashboard['courses'])->where('priority.code', 'SAFE_GREEN')->count() }}</div><div class="statfoot">Prioridad baja (verde)</div></div>
                            <div class="stat"><div class="stat-top"><span>Por revisar</span><span class="iconbox pink"><svg aria-hidden="true"><use href="#i-bell"/></svg></span></div><div class="statnum">{{ collect($dashboard['courses'])->whereIn('priority.code', ['WARNING_YELLOW', 'CRITICAL_RED'])->count() }}</div><div class="statfoot">Cursos con prioridad media o crítica</div></div>
                        </section>

                        <div class="panelgrid">
                            <section class="card" aria-labelledby="course-status-heading">
                                <div class="card-head"><div><h2 id="course-status-heading">Estado de tus cursos</h2><p>Promedios y prioridades calculados por StudyTrack.</p></div><a class="textlink" href="#cursos" data-page="cursos">Ver todos →</a></div>
                                @forelse ($dashboard['courses'] as $course)
                                    @php $coursePriority = data_get($course, 'priority.code', ''); @endphp
                                    <article class="course">
                                        <div class="course-row">
                                            <div class="course-title">
                                                <span class="course-icon" aria-hidden="true">{{ mb_strtoupper(mb_substr($course['name'], 0, 1)) }}</span>
                                                <div><b>{{ $course['name'] }}</b><small>{{ $course['code'] ?: 'Sin código' }} · {{ $course['priority']['name'] ?? 'Sin clasificar' }}</small></div>
                                            </div>
                                            <div class="course-grade">{{ number_format($course['current_average'], 2) }}<small>Promedio / 100</small></div>
                                        </div>
                                        <div class="progress" role="progressbar" aria-label="Promedio de {{ $course['name'] }}" aria-valuenow="{{ max(0, min(100, $course['current_average'])) }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="bar {{ $coursePriority === 'SAFE_GREEN' ? 'green' : ($coursePriority === 'CRITICAL_RED' ? 'red' : 'yellow') }}" style="width: {{ max(0, min(100, $course['current_average'])) }}%"></div>
                                        </div>
                                    </article>
                                @empty
                                    <p class="empty">Aún no hay cursos registrados.</p>
                                @endforelse
                            </section>

                            <div class="rightstack">
                                <section class="card" aria-labelledby="recommendations-heading">
                                    <div class="card-head"><div><h2 id="recommendations-heading">Recomendaciones</h2><p>Orientación basada en tus resultados.</p></div><span class="bubble" aria-hidden="true"></span></div>
                                    @forelse ($recommendations as $recommendation)
                                        <article class="priority {{ $recommendation->urgency_level === 'critical' ? 'priority-critical' : '' }}">
                                            <span class="priority-icon" aria-hidden="true">!</span>
                                            <div><b>{{ $recommendation->title }}</b><p>{{ $recommendation->message }}</p><small>{{ $recommendation->course->name ?? 'General' }} · Urgencia: {{ $recommendation->urgency_level }}</small></div>
                                        </article>
                                    @empty
                                        <p class="empty">No tienes recomendaciones pendientes.</p>
                                    @endforelse
                                </section>

                                <section class="card" aria-labelledby="upcoming-heading">
                                    <div class="card-head"><div><h2 id="upcoming-heading">Próximas actividades</h2><p>Las siguientes entregas de tu semestre.</p></div><a class="textlink" href="#actividades" data-page="actividades">Ver todas →</a></div>
                                    @forelse ($dashboard['upcoming'] as $activity)
                                        @php $due = \Carbon\Carbon::parse($activity['due_datetime']); @endphp
                                        <article class="agendarow">
                                            <div class="datebox"><b>{{ $due->format('d') }}</b><small>{{ $due->locale('es')->isoFormat('MMM') }}</small></div>
                                            <div class="agenda-info"><b>{{ $activity['title'] }}</b><small>{{ $activity['course']['name'] ?? 'Sin curso' }} · {{ $due->format('H:i') }}</small></div>
                                            <span class="tag blue">Próxima</span>
                                        </article>
                                    @empty
                                        <p class="empty">No tienes actividades próximas.</p>
                                    @endforelse
                                </section>
                            </div>
                        </div>
                        <section class="banner" aria-label="Resumen del avance">
                            <div><strong>Tu progreso académico en un solo lugar</strong><p>{{ $dashboard['summary']['progress_percent'] }}% de actividades completadas · {{ $dashboard['summary']['overdue'] }} vencidas · {{ $dashboard['summary']['due_next_7_days'] }} con vencimiento en los próximos 7 días.</p></div>
                            <a href="#analitica" data-page="analitica">Explorar analítica →</a>
                        </section>
                    @endif
                </section>

                {{-- sección de cursos --}}
                <section class="page" id="cursos" aria-labelledby="courses-heading">
                    <div class="st-page-heading">
                        <header class="sectionhead"><p class="eyebrow">Organización académica</p><h1 id="courses-heading">Mis cursos</h1><p>Cursos del semestre actual y sus prioridades calculadas.</p></header>
                        <button type="button" class="primary" data-st-open="course">+ Nuevo curso</button>
                    </div>
                    @if ($dashboard === null)
                        <div class="card empty-state"><h2>No hay semestre activo</h2><p>Marca un semestre como actual para consultar tus cursos.</p></div>
                    @else
                        <div class="section-summary">{{ $dashboard['semester']['name'] }} · {{ count($dashboard['courses']) }} cursos</div>
                        <div class="coursesgrid">
                            @forelse ($dashboard['courses'] as $course)
                                @php $coursePriority = data_get($course, 'priority.code', ''); @endphp
                                <article class="large-course">
                                    <div class="between">
                                        <span class="iconbox {{ $coursePriority === 'SAFE_GREEN' ? 'green' : ($coursePriority === 'CRITICAL_RED' ? 'pink' : 'orange') }}"><svg aria-hidden="true"><use href="#i-book"/></svg></span>
                                        <span class="tag {{ $coursePriority === 'SAFE_GREEN' ? 'green' : ($coursePriority === 'CRITICAL_RED' ? 'red' : 'yellow') }}">{{ $course['priority']['name'] ?? 'Sin clasificar' }}</span>
                                    </div>
                                    <h3>{{ $course['name'] }}</h3>
                                    <p>Código: {{ $course['code'] ?: 'Sin código' }} · Nota objetivo: {{ number_format($course['target_grade'], 2) }}</p>
                                    <div class="large-grade">{{ number_format($course['current_average'], 2) }} <small>/ 100</small></div>
                                    <div class="progress progress-full" role="progressbar" aria-label="Promedio de {{ $course['name'] }}" aria-valuenow="{{ max(0, min(100, $course['current_average'])) }}" aria-valuemin="0" aria-valuemax="100"><div class="bar {{ $coursePriority === 'SAFE_GREEN' ? 'green' : ($coursePriority === 'CRITICAL_RED' ? 'red' : 'yellow') }}" style="width: {{ max(0, min(100, $course['current_average'])) }}%"></div></div>
                                    <div class="line"></div>
                                    <p class="course-counts">{{ $course['pending'] }} pendientes · {{ $course['overdue'] }} vencidas · {{ $course['completed'] }} completadas</p>
                                    @if ($course['next_due'])
                                        <p class="course-next">Próxima: {{ $course['next_due']['title'] }} ({{ $course['next_due']['due_datetime'] }})</p>
                                    @endif
                                    <div class="st-course-actions">
                                        <a class="textlink" href="{{ route('dashboard', ['activity_search' => $course['name']]) }}#actividades">Ver actividades →</a>
                                        @if ($course['is_classroom_synced'])
                                            <span class="tag">Google Classroom · solo lectura</span>
                                        @else
                                            <button type="button" class="st-table-button" data-st-edit-course data-id="{{ $course['id'] ?? '' }}" data-name="{{ $course['name'] }}" data-code="{{ $course['code'] }}" data-grade="{{ $course['target_grade'] }}" data-color="{{ $course['color_hex'] ?? '#526531' }}">Editar</button>
                                            <button type="button" class="st-table-button st-button-danger" data-st-delete-course data-id="{{ $course['id'] ?? '' }}" data-name="{{ $course['name'] }}" data-code="{{ $course['code'] }}">Eliminar</button>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <p class="card empty">Aún no hay cursos en el semestre actual.</p>
                            @endforelse
                        </div>
                    @endif
                </section>

                {{-- sección de actividades --}}
                <section class="page" id="actividades" aria-labelledby="activities-heading">
                    <div class="st-page-heading">
                        <header class="sectionhead"><p class="eyebrow">Planificación</p><h1 id="activities-heading">Mis actividades</h1><p>Consulta entregas, fechas límite, cursos, estados y calificaciones.</p></header>
                        <button type="button" class="primary" data-st-open="activity">+ Nueva actividad</button>
                    </div>
                    @if ($dashboard === null)
                        <div class="card empty-state"><h2>No hay semestre activo</h2><p>Marca un semestre como actual para consultar tus actividades.</p></div>
                    @else
                        <form class="activity-filters" method="GET" action="{{ route('dashboard') }}#actividades">
                            <div class="filter-field filter-search"><label for="activity_search">Buscar por actividad o curso</label><input class="search" id="activity_search" name="activity_search" type="search" value="{{ $activity_search }}" placeholder="Buscar una actividad..."></div>
                            <div class="filter-field"><label for="activity_status">Estado</label><select class="filter" id="activity_status" name="activity_status"><option value="">Todos los estados</option>@foreach (['pending' => 'Pendiente', 'in_progress' => 'En progreso', 'completed' => 'Completada', 'overdue' => 'Vencida'] as $value => $label)<option value="{{ $value }}" @selected($activity_status === $value)>{{ $label }}</option>@endforeach</select></div>
                            <button class="primary" type="submit">Filtrar</button>
                            <button class="outline" type="button" id="st-clear-filters" data-dashboard-url="{{ route('dashboard') }}">Limpiar</button>
                        </form>
                        <div class="card activity-list-card">
                            @if ($activities->isEmpty())
                                <p class="empty">No se encontraron actividades con los filtros actuales.</p>
                            @else
                                <div class="table-scroll">
                                    <table class="activity-table">
                                        <caption class="visually-hidden">Actividades del semestre actual</caption>
                                        <thead><tr><th scope="col">Actividad</th><th scope="col">Curso</th><th scope="col">Fecha límite</th><th scope="col">Estado</th><th scope="col">Calificación</th><th scope="col">Acciones</th></tr></thead>
                                        <tbody>
                                            @foreach ($activities as $activity)
                                                <tr>
                                                    <th scope="row"><strong>{{ $activity->title }}</strong><small>{{ ucfirst(str_replace('_', ' ', $activity->activity_type)) }}</small></th>
                                                    <td>{{ $activity->course->name ?? 'Sin curso' }}</td>
                                                    <td>{{ $activity->due_datetime?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                                    <td>
                                                        @if ($activity->classroom_coursework_id)
                                                            <span class="tag">{{ ['TURNED_IN' => 'Entregada', 'RETURNED' => 'Calificada', 'NEW' => 'No entregada', 'CREATED' => 'No entregada', 'RECLAIMED_BY_STUDENT' => 'No entregada'][$activity->classroom_submission_state] ?? 'Classroom' }}</span>
                                                        @else
                                                            <label class="visually-hidden" for="st-status-{{ $activity->id }}">Estado de {{ $activity->title }}</label>
                                                            <select class="st-status-select" id="st-status-{{ $activity->id }}" data-st-status data-id="{{ $activity->id }}" data-current="{{ $activity->status }}" title="Cambiar estado de {{ $activity->title }}">
                                                                @foreach (['pending' => 'Pendiente', 'in_progress' => 'En progreso', 'completed' => 'Completada', 'overdue' => 'Vencida'] as $statusCode => $statusLabel)
                                                                    <option value="{{ $statusCode }}" @selected($activity->status === $statusCode)>{{ $statusLabel }}</option>
                                                                @endforeach
                                                            </select>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if ($activity->is_graded && $activity->points_obtained !== null)
                                                            {{ $activity->points_obtained }} / {{ $activity->points_possible }}
                                                        @elseif ($activity->is_graded)
                                                            Sin calificar
                                                        @else
                                                            No calificable
                                                        @endif
                                                    </td>
                                                    <td>
                                                        <div class="st-row-actions">
                                                            @if ($activity->is_graded && !$activity->classroom_coursework_id)
                                                                <button type="button" class="st-table-button" data-st-grade data-id="{{ $activity->id }}" data-title="{{ $activity->title }}" data-possible="{{ $activity->points_possible }}" data-obtained="{{ $activity->points_obtained }}">{{ $activity->points_obtained === null ? 'Calificar' : 'Editar nota' }}</button>
                                                            @endif
                                                            @if (!$activity->classroom_coursework_id)
                                                            <button type="button" class="st-table-button" data-st-edit-activity
                                                                data-id="{{ $activity->id }}"
                                                                data-title="{{ $activity->title }}"
                                                                data-description="{{ $activity->description }}"
                                                                data-type="{{ $activity->activity_type }}"
                                                                data-course="{{ $activity->course_id }}"
                                                                data-due="{{ $activity->due_datetime?->format('Y-m-d\TH:i') }}"
                                                                data-graded="{{ $activity->is_graded ? '1' : '0' }}"
                                                                data-possible="{{ $activity->points_possible }}"
                                                                data-obtained="{{ $activity->points_obtained }}"
                                                                data-status="{{ $activity->status }}">Editar</button>
                                                            <button type="button" class="st-table-button st-button-danger" data-st-delete-activity data-id="{{ $activity->id }}" data-title="{{ $activity->title }}">Eliminar</button>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endif
                </section>

                {{-- sección de calendario --}}
                <section class="page" id="calendario" aria-labelledby="calendar-heading">
                    <header class="sectionhead"><p class="eyebrow">Organización del tiempo</p><h1 id="calendar-heading">Calendario académico</h1><p>Eventos de la semana y sus prioridades.</p></header>
                    @if ($dashboard === null)
                        <div class="card empty-state"><h2>No hay semestre activo</h2><p>Marca un semestre como actual para consultar el calendario.</p></div>
                    @else
                        <div class="calendar-layout">
                            <section class="card">
                                <div class="calendar-header"><div><h2>Esta semana</h2><p class="calendar-range">{{ $calendar['week_start']->format('d/m/Y') }} al {{ $calendar['week_end']->format('d/m/Y') }}</p></div><span class="tag blue">{{ $calendar['events']->count() }} eventos</span></div>
                                <div class="calendar-days">
                                    @for ($dayIndex = 0; $dayIndex < 7; $dayIndex++)
                                        @php
                                            $day = $calendar['week_start']->copy()->addDays($dayIndex);
                                            $dayEvents = $calendar['events']->filter(fn ($event) => $event->event_datetime->isSameDay($day));
                                        @endphp
                                        <div class="day-column">
                                            <div class="dayname">{{ mb_strtoupper($day->locale('es')->isoFormat('ddd')) }}</div>
                                            <div class="day {{ $day->isToday() ? 'today' : '' }}">
                                                <strong class="day-number">{{ $day->format('d') }}</strong>
                                                @foreach ($dayEvents->take(2) as $event)
                                                    @php $eventPriority = $event->priorityRule->code ?? ''; @endphp
                                                    <div class="eventmark {{ $eventPriority === 'WARNING_YELLOW' ? 'yellow' : ($eventPriority === 'CRITICAL_RED' ? 'event-red' : '') }}" title="{{ $event->title }} · {{ $event->event_datetime->format('H:i') }}">{{ $event->title }}</div>
                                                @endforeach
                                                @if ($dayEvents->count() > 2)
                                                    <small class="more-events">+{{ $dayEvents->count() - 2 }} más</small>
                                                @endif
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                                <p class="notice">Vista de la semana actual: las fechas y eventos provienen de StudyTrack.</p>
                            </section>
                            <section class="card" aria-labelledby="week-agenda-heading">
                                <div class="card-head"><div><h2 id="week-agenda-heading">Agenda semanal</h2><p>Eventos registrados para esta semana.</p></div></div>
                                @forelse ($calendar['events'] as $event)
                                    <article class="agendarow">
                                        <div class="datebox"><b>{{ $event->event_datetime->format('d') }}</b><small>{{ $event->event_datetime->locale('es')->isoFormat('MMM') }}</small></div>
                                        <div class="agenda-info"><b>{{ $event->title }}</b><small>{{ $event->course->name ?? 'Sin curso' }} · {{ $event->event_datetime->format('H:i') }}</small><small class="agenda-priority">{{ $event->priorityRule->name ?? 'General' }}</small></div>
                                    </article>
                                @empty
                                    <p class="empty">No hay eventos del calendario esta semana.</p>
                                @endforelse
                            </section>
                        </div>
                    @endif
                </section>

                {{-- sección de analítica --}}
                <section class="page" id="analitica" aria-labelledby="analytics-heading">
                    <header class="sectionhead"><p class="eyebrow">Conoce tu avance</p><h1 id="analytics-heading">Mi analítica</h1><p>Cuatro perspectivas sobre tu situación académica.</p></header>
                    @if ($dashboard === null)
                        <div class="card empty-state"><h2>No hay semestre activo</h2><p>Marca un semestre como actual para consultar tu analítica.</p></div>
                    @else
                        <nav class="analytics-tabs" aria-label="Niveles de analítica">
                            <a href="#analitica-descriptiva">Descriptiva</a><a href="#analitica-diagnostica">Diagnóstica</a><a href="#analitica-predictiva">Predictiva</a><a href="#analitica-prescriptiva">Prescriptiva</a>
                        </nav>
                        <div class="analyticsgrid">
                            <article class="card analytics-card" id="analitica-descriptiva">
                                <span class="bubble">01</span><h2>Descriptiva · ¿Qué pasó?</h2><p>Resumen de tus actividades y resultados registrados.</p>
                                <dl class="analytics-numbers">
                                    <div><dt>Registradas</dt><dd>{{ $analytics['descriptive']['summary']['activities_total'] }}</dd></div>
                                    <div><dt>Pendientes</dt><dd>{{ $analytics['descriptive']['summary']['pending'] }}</dd></div>
                                    <div><dt>Vencidas</dt><dd>{{ $analytics['descriptive']['summary']['overdue'] }}</dd></div>
                                    <div><dt>Completadas</dt><dd>{{ $analytics['descriptive']['summary']['completed'] }}</dd></div>
                                    <div><dt>Calificadas</dt><dd>{{ $analytics['descriptive']['summary']['graded'] }}</dd></div>
                                </dl>
                                <h3 class="analytics-subtitle">Cursos</h3>
                                <ul class="analytic-list">
                                    @forelse ($analytics['descriptive']['courses'] as $course)
                                        <li><b>{{ $course['name'] }}</b><span>Promedio {{ number_format($course['current_average'], 2) }}% · Objetivo {{ number_format($course['target_grade'], 2) }}% · {{ $course['activities_total'] }} actividades</span></li>
                                    @empty
                                        <li>No hay datos de cursos para describir.</li>
                                    @endforelse
                                </ul>
                            </article>

                            <article class="card analytics-card" id="analitica-diagnostica">
                                <span class="bubble">02</span>
                                <h2>Diagnóstica · ¿Qué cambió?</h2>
                                <p>Historial de los cambios registrados en tus cursos, actividades y calificaciones.</p>
                                <ol class="analytic-list audit-history">
                                    @forelse ($analytics['diagnostic']['events'] as $event)
                                        @php
                                            $before = $event['old_data'] ?? [];
                                            $after = $event['new_data'] ?? [];
                                            $before = is_string($before) ? (json_decode($before, true) ?: []) : (array) $before;
                                            $after = is_string($after) ? (json_decode($after, true) ?: []) : (array) $after;
                                            $entity = $event['entity'];
                                            $action = $event['action'];
                                            $isCourse = in_array($entity, ['course', 'courses']);
                                            $isActivity = in_array($entity, ['activity', 'activities']);
                                            $name = $after['name'] ?? $after['title'] ?? $before['name'] ?? $before['title'] ?? null;
                                            if ($name === null && $isCourse && $dashboard !== null) {
                                                $matchedCourse = collect($dashboard['courses'])->firstWhere('id', $event['entity_id']);
                                                $name = $matchedCourse['name'] ?? null;
                                            }
                                            $priorities = [1 => 'Crítica', 2 => 'Media', 3 => 'Baja', 4 => 'Informativa'];
                                            $title = match (true) {
                                                $isCourse && $action === 'created' => 'Curso creado',
                                                $isActivity && $action === 'created' => 'Actividad creada',
                                                $isCourse && $action === 'recalculated' => 'Promedio actualizado',
                                                $action === 'updated' => 'Registro actualizado',
                                                $action === 'deleted' => 'Registro eliminado',
                                                default => 'Cambio registrado',
                                            };
                                            $fieldNames = [
                                                'name' => 'Nombre', 'title' => 'Título', 'code' => 'Código',
                                                'target_grade' => 'Nota objetivo', 'current_average' => 'Promedio',
                                                'points_possible' => 'Puntos posibles', 'points_obtained' => 'Puntos obtenidos',
                                                'status' => 'Estado', 'due_datetime' => 'Fecha límite',
                                            ];
                                        @endphp
                                        <li class="audit-entry">
                                            <b class="audit-title">{{ $title }}</b>
                                            <span class="audit-date">{{ $event['created_at'] }}</span>
                                            <p class="audit-detail">{{ $name ?? ($isCourse ? 'Curso #' . $event['entity_id'] : ($isActivity ? 'Actividad #' . $event['entity_id'] : ucfirst($entity) . ' #' . $event['entity_id'])) }}</p>
                                            @if ($isCourse && $action === 'created')
                                                @if (isset($after['code']))<p class="audit-detail">Código: {{ $after['code'] }}</p>@endif
                                                @if (isset($after['target_grade']))<p class="audit-detail">Nota objetivo: {{ number_format((float) $after['target_grade'], 2) }}</p>@endif
                                            @elseif ($isActivity && $action === 'created')
                                                @if (isset($after['points_possible']))<p class="audit-detail">Calificación: {{ $after['points_obtained'] ?? 'Sin calificar' }} / {{ $after['points_possible'] }}</p>@endif
                                                @if (isset($after['due_datetime']))<p class="audit-detail">Fecha límite: {{ $after['due_datetime'] }}</p>@endif
                                            @elseif ($isCourse && $action === 'recalculated')
                                                @if (isset($after['current_average']))
                                                    <div class="audit-average">
                                                        <span>{{ number_format((float) ($before['current_average'] ?? 0), 2) }}%</span>
                                                        <span>→</span>
                                                        <strong>{{ number_format((float) $after['current_average'], 2) }}%</strong>
                                                    </div>
                                                @endif
                                                @if (isset($before['priority_level_id'], $after['priority_level_id']) && $before['priority_level_id'] != $after['priority_level_id'])
                                                    <p class="audit-detail">Prioridad: {{ $priorities[$before['priority_level_id']] ?? 'Sin clasificar' }} → {{ $priorities[$after['priority_level_id']] ?? 'Sin clasificar' }}</p>
                                                @endif
                                            @elseif ($action === 'updated')
                                                @foreach ($fieldNames as $field => $label)
                                                    @if (array_key_exists($field, $after) && (!array_key_exists($field, $before) || $before[$field] != $after[$field]))
                                                        <p class="audit-detail">{{ $label }}: {{ $before[$field] ?? 'Sin dato' }} → {{ $after[$field] ?? 'Sin dato' }}</p>
                                                    @endif
                                                @endforeach
                                            @endif
                                        </li>
                                    @empty
                                        <li class="audit-entry">Todavía no hay cambios registrados en tu historial académico.</li>
                                    @endforelse
                                </ol>
                            </article>

                            <article class="card analytics-card" id="analitica-predictiva">
                                <span class="bubble">03</span><h2>Predictiva · ¿Qué podría pasar?</h2>
                                <p>{{ $analytics['predictive']['method'] }}</p><p class="analytics-note">{{ $analytics['predictive']['overdue_policy'] }}</p>
                                @php($eligibility = $analytics['predictive']['final_exam_eligibility'])
                                <p class="analytics-note">
                                    Derecho a examen final:
                                    Zona {{ $eligibility['components']['zona'] }} + Parcial 1 {{ $eligibility['components']['parcial_1'] }} + Parcial 2 {{ $eligibility['components']['parcial_2'] }} = máximo {{ $eligibility['maximum_points_before_final'] }} puntos antes del final.
                                    El requisito es {{ $eligibility['minimum_points'] }} puntos.
                                    @if ($eligibility['attainable'])
                                        El requisito es alcanzable.
                                    @else
                                        <strong>No es alcanzable con esta ponderación: faltan {{ $eligibility['shortfall'] }} puntos incluso con el máximo.</strong>
                                    @endif
                                </p>
                                <ul class="analytic-list">
                                    @forelse ($analytics['predictive']['courses'] as $course)
                                        <li>
                                            <b>{{ $course['course_name'] }}</b><span>Riesgo: {{ ['high' => 'Alto', 'medium' => 'Medio', 'low' => 'Bajo', 'critical' => 'Crítico'][$course['risk_level']] ?? ucfirst($course['risk_level']) }}</span>
                                            @if ($course['current_average'] !== null)<span>Promedio actual: {{ number_format($course['current_average'], 2) }}%</span>@endif
                                            @if ($course['required_average_on_remaining'] !== null)<span>Promedio requerido en lo restante: {{ number_format($course['required_average_on_remaining'], 2) }}%</span>@endif
                                            @if ($course['maximum_final_grade'] !== null)<span>Máximo final estimado: {{ number_format($course['maximum_final_grade'], 2) }}%</span>@endif
                                        </li>
                                    @empty
                                        <li>No hay cursos disponibles para estimar el riesgo.</li>
                                    @endforelse
                                </ul>
                            </article>

                            <article class="card analytics-card" id="analitica-prescriptiva">
                                <span class="bubble">04</span><h2>Prescriptiva · ¿Qué conviene hacer?</h2><p>Acciones sugeridas según tu situación académica.</p>
                                @forelse ($analytics['prescriptive']['actions'] as $recommendation)
                                    <div class="recommendation-actions"><h3>{{ $recommendation['course_name'] }}</h3><p>Riesgo: {{ ['high' => 'Alto', 'medium' => 'Medio', 'low' => 'Bajo', 'critical' => 'Crítico'][$recommendation['risk_level']] ?? ucfirst($recommendation['risk_level']) }}</p><ul class="analytic-list">@foreach ($recommendation['actions'] as $action)<li>{{ $action }}</li>@endforeach</ul></div>
                                @empty
                                    <p class="empty">No hay acciones sugeridas porque no hay cursos en el semestre.</p>
                                @endforelse
                            </article>
                        </div>
                    @endif
                </section>

            </main>
        </div>
    </div>

    <div class="st-modal-backdrop" id="st-modal-auth" hidden>
        <div class="st-dialog" role="dialog" aria-modal="true" aria-labelledby="st-auth-title">
            <div class="st-dialog-heading">
                <div><p class="eyebrow">Conexión con la API</p><h2 id="st-auth-title">Habilitar edición</h2></div>
                <button type="button" class="st-close" data-st-close aria-label="Cerrar">×</button>
            </div>
            <p class="st-dialog-description">Tu sesión web está activa, pero la API requiere un token Sanctum. Confirma una vez tu contraseña para crear semestres, cursos y actividades en esta pestaña.</p>
            <form id="st-auth-form" class="st-form">
                <label>Correo de tu sesión
                    <input id="st-auth-email" type="email" readonly autocomplete="username">
                </label>
                <label>Contraseña
                    <input id="st-auth-password" type="password" required autocomplete="current-password">
                </label>
                <p class="st-hint">La contraseña se envía a tu API local y no se guarda en el navegador.</p>
                <div class="st-form-error" role="alert" hidden></div>
                <div class="st-dialog-actions">
                    <button type="button" class="outline" data-st-close>Cancelar</button>
                    <button type="submit" class="primary">Conectar API</button>
                </div>
            </form>
        </div>
    </div>

    <div class="st-modal-backdrop" id="st-modal-semester" hidden>
        <div class="st-dialog" role="dialog" aria-modal="true" aria-labelledby="st-semester-title">
            <div class="st-dialog-heading">
                <div><p class="eyebrow">Organización académica</p><h2 id="st-semester-title">Nuevo semestre</h2></div>
                <button type="button" class="st-close" data-st-close aria-label="Cerrar">×</button>
            </div>
            <form id="st-semester-form" class="st-form">
                <label>Nombre del semestre
                    <input name="name" type="text" maxlength="100" required placeholder="Segundo semestre 2026">
                </label>
                <div class="st-form-grid">
                    <label>Fecha de inicio<input name="start_date" type="date" required></label>
                    <label>Fecha de finalización<input name="end_date" type="date" required></label>
                </div>
                <label class="st-checkbox"><input type="checkbox" name="is_current" checked> Establecer como semestre actual</label>
                <p class="st-hint">Si activas este semestre, el anterior dejará de ser el actual.</p>
                <div class="st-form-error" role="alert" hidden></div>
                <div class="st-dialog-actions">
                    <button type="button" class="outline" data-st-close>Cancelar</button>
                    <button type="submit" class="primary">Guardar semestre</button>
                </div>
            </form>
        </div>
    </div>

    <div class="st-modal-backdrop" id="st-modal-switch-semester" hidden>
        <div class="st-dialog" role="dialog" aria-modal="true" aria-labelledby="st-switch-title">
            <div class="st-dialog-heading">
                <div><p class="eyebrow">Tus semestres</p><h2 id="st-switch-title">Seleccionar semestre actual</h2></div>
                <button type="button" class="st-close" data-st-close aria-label="Cerrar">×</button>
            </div>
            <form id="st-switch-form" class="st-form">
                <label>Semestres registrados<select name="semester_id" id="st-existing-semesters" required></select></label>
                <p class="st-hint" id="st-switch-note">Selecciona uno para visualizar sus cursos y actividades.</p>
                <div class="st-form-error" role="alert" hidden></div>
                <div class="st-dialog-actions">
                    <button type="button" class="outline" data-st-close>Cancelar</button>
                    <button type="submit" class="primary">Activar semestre</button>
                </div>
            </form>
        </div>
    </div>

    <div class="st-modal-backdrop" id="st-modal-course" hidden>
        <div class="st-dialog" role="dialog" aria-modal="true" aria-labelledby="st-course-title">
            <div class="st-dialog-heading">
                <div><p class="eyebrow">Mis cursos</p><h2 id="st-course-title">Nuevo curso</h2></div>
                <button type="button" class="st-close" data-st-close aria-label="Cerrar">×</button>
            </div>
            <form id="st-course-form" class="st-form">
                <input type="hidden" name="editing_id" value="">
                <label>Semestre<select name="semester_id" id="st-course-semester" required></select></label>
                <label>Nombre del curso<input name="name" type="text" maxlength="150" required placeholder="Programación"></label>
                <div class="st-form-grid">
                    <label>Código<input name="code" type="text" maxlength="50" required placeholder="PROG-101"></label>
                    <label>Nota objetivo (0 a 100)<input name="target_grade" type="number" step="0.01" min="0" max="100" value="85" required></label>
                </div>
                <label>Color identificativo<input name="color_hex" type="color" value="#526531" required></label>
                <p class="st-hint">La nota actual se calculará a partir de las actividades calificadas, no se escribe aquí.</p>
                <div class="st-form-error" role="alert" hidden></div>
                <div class="st-dialog-actions">
                    <button type="button" class="outline" data-st-close>Cancelar</button>
                    <button type="submit" class="primary">Guardar curso</button>
                </div>
            </form>
        </div>
    </div>

    <div class="st-modal-backdrop" id="st-modal-activity" hidden>
        <div class="st-dialog" role="dialog" aria-modal="true" aria-labelledby="st-activity-title">
            <div class="st-dialog-heading">
                <div><p class="eyebrow">Planificación</p><h2 id="st-activity-title">Nueva actividad</h2></div>
                <button type="button" class="st-close" data-st-close aria-label="Cerrar">×</button>
            </div>
            <form id="st-activity-form" class="st-form">
                <input type="hidden" name="editing_id" value="">
                <label>Título<input name="title" type="text" maxlength="200" required placeholder="Parcial de programación"></label>
                <div class="st-form-grid">
                    <label>Tipo
                        <select name="activity_type" id="st-activity-type">
                            <option value="task">Tarea</option><option value="exam">Examen</option>
                            <option value="project">Proyecto</option><option value="reading">Lectura</option>
                            <option value="administrative">Trámite</option><option value="meeting">Reunión</option>
                        </select>
                    </label>
                    <label>Curso<select name="course_id" id="st-activity-course"></select></label>
                </div>
                <div class="st-form-grid">
                    <label>Fecha y hora límite<input name="due_datetime" type="datetime-local" required></label>
                    <label>Estado
                        <select name="status">
                            <option value="pending">Pendiente</option>
                            <option value="in_progress">En progreso</option>
                            <option value="completed">Completada</option>
                            <option value="overdue">Vencida</option>
                        </select>
                    </label>
                </div>
                <label>Descripción (opcional)<textarea name="description" rows="2" placeholder="Detalles de la actividad..."></textarea></label>
                <label class="st-checkbox" id="st-graded-label"><input name="is_graded" id="st-activity-graded" type="checkbox" checked> Actividad calificable</label>
                <div class="st-form-grid" id="st-grade-fields">
                    <label>Puntos posibles<input name="points_possible" id="st-points-possible" type="number" min="0.01" max="999.99" step="0.01" value="20" required></label>
                    <label>Puntos obtenidos (opcional)<input name="points_obtained" id="st-points-obtained" type="number" min="0" max="999.99" step="0.01" placeholder="Sin calificar"></label>
                </div>
                <div class="st-form-error" role="alert" hidden></div>
                <div class="st-dialog-actions">
                    <button type="button" class="outline" data-st-close>Cancelar</button>
                    <button type="submit" class="primary">Guardar actividad</button>
                </div>
            </form>
        </div>
    </div>

    <div class="st-modal-backdrop" id="st-modal-grade" hidden>
        <div class="st-dialog" role="dialog" aria-modal="true" aria-labelledby="st-grade-title">
            <div class="st-dialog-heading">
                <div><p class="eyebrow">Mis actividades</p><h2 id="st-grade-title">Registrar calificación</h2></div>
                <button type="button" class="st-close" data-st-close aria-label="Cerrar">×</button>
            </div>
            <p class="st-dialog-description" id="st-grade-description"></p>
            <form id="st-grade-form" class="st-form">
                <input name="activity_id" type="hidden">
                <label>Puntos obtenidos<input name="points_obtained" type="number" min="0" max="999.99" step="0.01" required></label>
                <div class="st-form-error" role="alert" hidden></div>
                <div class="st-dialog-actions">
                    <button type="button" class="outline" data-st-close>Cancelar</button>
                    <button type="submit" class="primary">Guardar nota</button>
                </div>
            </form>
        </div>
    </div>

    <div class="st-toast" id="st-toast" role="status" aria-live="polite" hidden></div>
</body>
</html>
