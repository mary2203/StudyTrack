<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | StudyTrack</title>
</head>
<body>
    <header>
        <h1>StudyTrack</h1>
        <p>Hola, {{ $user->name }}.</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Cerrar sesión</button>
        </form>
    </header>

    <main>
        <h2>Dashboard</h2>

        @if ($dashboard === null)
            <p>No tienes un semestre actual. Crea uno o márcalo como actual para ver tu resumen.</p>
        @else
            <section aria-labelledby="semester-heading">
                <h3 id="semester-heading">{{ $dashboard['semester']['name'] }}</h3>
                <p>Del {{ $dashboard['semester']['start_date'] }} al {{ $dashboard['semester']['end_date'] }}</p>
            </section>

            <section aria-labelledby="summary-heading">
                <h3 id="summary-heading">Resumen de actividades</h3>
                <dl>
                    <dt>Total</dt>
                    <dd>{{ $dashboard['summary']['total'] }}</dd>
                    <dt>Pendientes</dt>
                    <dd>{{ $dashboard['summary']['pending'] }}</dd>
                    <dt>Vencidas</dt>
                    <dd>{{ $dashboard['summary']['overdue'] }}</dd>
                    <dt>Completadas</dt>
                    <dd>{{ $dashboard['summary']['completed'] }}</dd>
                    <dt>Vencen en los próximos 7 días</dt>
                    <dd>{{ $dashboard['summary']['due_next_7_days'] }}</dd>
                    <dt>Progreso</dt>
                    <dd>{{ $dashboard['summary']['progress_percent'] }}%</dd>
                </dl>
            </section>

            <section aria-labelledby="courses-heading">
                <h3 id="courses-heading">Cursos</h3>
                @forelse ($dashboard['courses'] as $course)
                    <article>
                        <h4>{{ $course['name'] }}@if ($course['code']) ({{ $course['code'] }})@endif</h4>
                        <p>Promedio: {{ number_format($course['current_average'], 2) }}%</p>
                        <p>Nota objetivo: {{ number_format($course['target_grade'], 2) }}%</p>
                        @if ($course['priority'])
                            <p>Estado: {{ $course['priority']['name'] }}</p>
                        @endif
                        <p>Pendientes: {{ $course['pending'] }} | Vencidas: {{ $course['overdue'] }} | Completadas: {{ $course['completed'] }}</p>
                        @if ($course['next_due'])
                            <p>Próxima actividad: {{ $course['next_due']['title'] }} ({{ $course['next_due']['due_datetime'] }})</p>
                        @endif
                    </article>
                @empty
                    <p>Aún no hay cursos registrados en este semestre.</p>
                @endforelse
            </section>

            <section aria-labelledby="upcoming-heading">
                <h3 id="upcoming-heading">Próximas actividades</h3>
                @forelse ($dashboard['upcoming'] as $activity)
                    <article>
                        <h4>{{ $activity['title'] }}</h4>
                        <p>Tipo: {{ $activity['activity_type'] }}</p>
                        <p>Fecha límite: {{ $activity['due_datetime'] }}</p>
                        <p>Curso: {{ $activity['course']['name'] ?? 'Sin curso' }}</p>
                    </article>
                @empty
                    <p>No tienes actividades próximas.</p>
                @endforelse
            </section>

            <p>Recomendaciones sin leer: {{ $dashboard['recommendations_unread'] }}</p>
        @endif
    </main>
</body>
</html>
