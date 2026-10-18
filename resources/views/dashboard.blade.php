<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>StudyTrack | {{ $user->name }}</title>
</head>
<body>
    <aside>
        <header>
            <h1><a href="#inicio">StudyTrack</a></h1>
            <p>Plataforma de seguimiento académico</p>
        </header>

        <nav aria-label="Navegación principal">
            <h2>Mi espacio</h2>
            <ul>
                <li><a href="#inicio">Inicio</a></li>
                <li><a href="#cursos">Mis cursos</a></li>
                <li><a href="#actividades">Actividades</a></li>
                <li><a href="#calendario">Calendario</a></li>
                <li><a href="#analitica">Mi analítica</a></li>
            </ul>
        </nav>

        <section aria-labelledby="account-heading">
            <h2 id="account-heading">Cuenta</h2>
            <p>{{ $user->name }}</p>
            @if ($dashboard !== null)
                <p>Semestre activo: {{ $dashboard['semester']['name'] }}</p>
            @else
                <p>No hay semestre activo</p>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Cerrar sesión</button>
            </form>
        </section>
    </aside>

    <div>
        <header>
            <p><a href="#inicio">Mi espacio</a> / <span id="breadcrumb-title">StudyTrack</span></p>
            <p>Sesión iniciada como {{ $user->email }}</p>
            <p>Recomendaciones sin leer: {{ $dashboard['recommendations_unread'] ?? $recommendations->count() }}</p>
        </header>

        <main>
            @if ($dashboard === null)
                <section id="inicio" aria-labelledby="welcome-heading">
                    <h1 id="welcome-heading">Tu semestre, bajo control</h1>
                    <p>No tienes un semestre actual. Crea uno o márcalo como actual para consultar cursos, actividades y analítica.</p>
                </section>
            @else
                <section id="inicio" aria-labelledby="welcome-heading">
                    <header>
                        <h1 id="welcome-heading">Tu semestre, bajo control</h1>
                        <p>Consulta tus cursos, organiza tus pendientes y conoce tus prioridades.</p>
                        <p>
                            {{ $dashboard['semester']['name'] }}:
                            {{ $dashboard['semester']['start_date'] }} a {{ $dashboard['semester']['end_date'] }}
                        </p>
                        <p><a href="#calendario">Ver calendario</a></p>
                    </header>

                    <section aria-labelledby="overview-heading">
                        <h2 id="overview-heading">Resumen del semestre</h2>
                        <dl>
                            <dt>Cursos registrados</dt>
                            <dd>{{ count($dashboard['courses']) }}</dd>
                            <dt>Actividades</dt>
                            <dd>{{ $dashboard['summary']['total'] }}</dd>
                            <dt>Cursos al día</dt>
                            <dd>{{ collect($dashboard['courses'])->where('priority.code', 'SAFE_GREEN')->count() }}</dd>
                            <dt>Cursos por revisar</dt>
                            <dd>{{ collect($dashboard['courses'])->whereIn('priority.code', ['WARNING_YELLOW', 'CRITICAL_RED'])->count() }}</dd>
                            <dt>Actividades pendientes</dt>
                            <dd>{{ $dashboard['summary']['pending'] }}</dd>
                            <dt>Actividades vencidas</dt>
                            <dd>{{ $dashboard['summary']['overdue'] }}</dd>
                            <dt>Actividades completadas</dt>
                            <dd>{{ $dashboard['summary']['completed'] }}</dd>
                            <dt>Vencen en los próximos 7 días</dt>
                            <dd>{{ $dashboard['summary']['due_next_7_days'] }}</dd>
                            <dt>Progreso total</dt>
                            <dd>{{ $dashboard['summary']['progress_percent'] }}%</dd>
                        </dl>
                    </section>

                    <section aria-labelledby="course-status-heading">
                        <h2 id="course-status-heading">Estado de tus cursos</h2>
                        <p>Promedios y prioridades calculados por el sistema.</p>
                        <p><a href="#cursos">Ver todos los cursos</a></p>

                        @forelse ($dashboard['courses'] as $course)
                            <article>
                                <h3>{{ $course['name'] }}@if ($course['code']) ({{ $course['code'] }})@endif</h3>
                                <p>Promedio: {{ number_format($course['current_average'], 2) }}%</p>
                                <p>Nota objetivo: {{ number_format($course['target_grade'], 2) }}%</p>
                                <p>Prioridad: {{ $course['priority']['name'] ?? 'Sin clasificar' }}</p>
                                <p>Pendientes: {{ $course['pending'] }} · Vencidas: {{ $course['overdue'] }} · Completadas: {{ $course['completed'] }}</p>
                                @if ($course['next_due'])
                                    <p>Próxima actividad: {{ $course['next_due']['title'] }} ({{ $course['next_due']['due_datetime'] }})</p>
                                @endif
                            </article>
                        @empty
                            <p>Aún no hay cursos registrados.</p>
                        @endforelse
                    </section>

                    <section aria-labelledby="recommendations-heading">
                        <h2 id="recommendations-heading">Recomendaciones</h2>
                        <p>Orientación basada en tus resultados y actividades.</p>
                        @forelse ($recommendations as $recommendation)
                            <article>
                                <h3>{{ $recommendation->title }}</h3>
                                <p>{{ $recommendation->message }}</p>
                                <p>Curso: {{ $recommendation->course->name ?? 'General' }}</p>
                                <p>Urgencia: {{ $recommendation->urgency_level }}</p>
                            </article>
                        @empty
                            <p>No tienes recomendaciones pendientes.</p>
                        @endforelse
                    </section>

                    <section aria-labelledby="upcoming-heading">
                        <h2 id="upcoming-heading">Próximas actividades</h2>
                        @forelse ($dashboard['upcoming'] as $activity)
                            <article>
                                <h3>{{ $activity['title'] }}</h3>
                                <p>Curso: {{ $activity['course']['name'] ?? 'Sin curso' }}</p>
                                <p>Tipo: {{ $activity['activity_type'] }}</p>
                                <p>Fecha límite: {{ $activity['due_datetime'] }}</p>
                            </article>
                        @empty
                            <p>No tienes actividades próximas.</p>
                        @endforelse
                    </section>
                </section>

                <section id="cursos" aria-labelledby="courses-heading">
                    <h1 id="courses-heading">Mis cursos</h1>
                    <p>Cursos del semestre actual y su prioridad académica.</p>
                    @forelse ($dashboard['courses'] as $course)
                        <article>
                            <h2>{{ $course['name'] }}</h2>
                            @if ($course['code'])
                                <p>Código: {{ $course['code'] }}</p>
                            @endif
                            <p>Estado: {{ $course['priority']['name'] ?? 'Sin clasificar' }}</p>
                            <p>Promedio: {{ number_format($course['current_average'], 2) }} de 100</p>
                            <p>Nota objetivo: {{ number_format($course['target_grade'], 2) }}</p>
                            <p>Actividades: {{ $course['pending'] }} pendientes, {{ $course['overdue'] }} vencidas y {{ $course['completed'] }} completadas.</p>
                            <p><a href="{{ route('dashboard', ['activity_search' => $course['name']]) }}#actividades">Ver actividades</a></p>
                        </article>
                    @empty
                        <p>Aún no hay cursos en el semestre actual.</p>
                    @endforelse
                </section>

                <section id="actividades" aria-labelledby="activities-heading">
                    <h1 id="activities-heading">Mis actividades</h1>
                    <p>Consulta tus entregas, fechas, cursos y estados.</p>

                    <form method="GET" action="{{ route('dashboard') }}#actividades">
                        <div>
                            <label for="activity_search">Buscar por actividad o curso</label>
                            <input
                                id="activity_search"
                                name="activity_search"
                                type="search"
                                value="{{ $activity_search }}"
                                placeholder="Buscar una actividad"
                            >
                        </div>
                        <div>
                            <label for="activity_status">Estado</label>
                            <select id="activity_status" name="activity_status">
                                <option value="">Todos los estados</option>
                                @foreach (['pending' => 'Pendiente', 'in_progress' => 'En progreso', 'completed' => 'Completada', 'overdue' => 'Vencida'] as $value => $label)
                                    <option value="{{ $value }}" @selected($activity_status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit">Filtrar actividades</button>
                        <a href="{{ route('dashboard') }}#actividades">Limpiar filtros</a>
                    </form>

                    @if ($activities->isEmpty())
                        <p>No se encontraron actividades con los filtros actuales.</p>
                    @else
                        <table>
                            <caption>Actividades del semestre actual</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Actividad</th>
                                    <th scope="col">Curso</th>
                                    <th scope="col">Fecha límite</th>
                                    <th scope="col">Estado</th>
                                    <th scope="col">Calificación</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activities as $activity)
                                    <tr>
                                        <th scope="row">{{ $activity->title }}</th>
                                        <td>{{ $activity->course->name ?? 'Sin curso' }}</td>
                                        <td>{{ $activity->due_datetime->format('d/m/Y H:i') }}</td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $activity->status)) }}</td>
                                        <td>
                                            @if ($activity->is_graded && $activity->points_obtained !== null)
                                                {{ $activity->points_obtained }} / {{ $activity->points_possible }}
                                            @elseif ($activity->is_graded)
                                                Sin calificar
                                            @else
                                                No calificable
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>

                <section id="calendario" aria-labelledby="calendar-heading">
                    <h1 id="calendar-heading">Calendario académico</h1>
                    <p>Actividades programadas del {{ $calendar['week_start']->format('d/m/Y') }} al {{ $calendar['week_end']->format('d/m/Y') }}.</p>

                    @if ($calendar['events']->isEmpty())
                        <p>No hay eventos del calendario esta semana.</p>
                    @else
                        <table>
                            <caption>Eventos de esta semana</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Fecha y hora</th>
                                    <th scope="col">Evento</th>
                                    <th scope="col">Curso</th>
                                    <th scope="col">Prioridad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($calendar['events'] as $event)
                                    <tr>
                                        <td>{{ $event->event_datetime->format('d/m/Y H:i') }}</td>
                                        <th scope="row">{{ $event->title }}</th>
                                        <td>{{ $event->course->name ?? 'Sin curso' }}</td>
                                        <td>{{ $event->priorityRule->name ?? 'General' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <h2>Esta semana</h2>
                    <ol>
                        @forelse ($calendar['events'] as $event)
                            <li>
                                {{ $event->event_datetime->format('d/m/Y') }}:
                                {{ $event->title }} — {{ $event->course->name ?? 'Sin curso' }}
                            </li>
                        @empty
                            <li>No hay actividades programadas esta semana.</li>
                        @endforelse
                    </ol>
                    <p>Los eventos se sincronizan con las actividades del usuario para evitar duplicados.</p>
                </section>

                <section id="analitica" aria-labelledby="analytics-heading">
                    <h1 id="analytics-heading">Mi analítica</h1>
                    <p>Consulta los cuatro niveles de análisis del semestre actual.</p>

                    <nav aria-label="Niveles de analítica">
                        <ul>
                            <li><a href="#analitica-descriptiva">Descriptiva</a></li>
                            <li><a href="#analitica-diagnostica">Diagnóstica</a></li>
                            <li><a href="#analitica-predictiva">Predictiva</a></li>
                            <li><a href="#analitica-prescriptiva">Prescriptiva</a></li>
                        </ul>
                    </nav>

                    <article id="analitica-descriptiva">
                        <h2>Descriptiva · ¿Qué pasó?</h2>
                        <p>Resumen de actividades y resultados registrados.</p>
                        <dl>
                            <dt>Actividades registradas</dt>
                            <dd>{{ $analytics['descriptive']['summary']['activities_total'] }}</dd>
                            <dt>Pendientes</dt>
                            <dd>{{ $analytics['descriptive']['summary']['pending'] }}</dd>
                            <dt>Vencidas</dt>
                            <dd>{{ $analytics['descriptive']['summary']['overdue'] }}</dd>
                            <dt>Completadas</dt>
                            <dd>{{ $analytics['descriptive']['summary']['completed'] }}</dd>
                            <dt>Con calificación</dt>
                            <dd>{{ $analytics['descriptive']['summary']['graded'] }}</dd>
                        </dl>
                        <ul>
                            @forelse ($analytics['descriptive']['courses'] as $course)
                                <li>{{ $course['name'] }}: promedio {{ number_format($course['current_average'], 2) }}%, objetivo {{ number_format($course['target_grade'], 2) }}%, {{ $course['activities_total'] }} actividades.</li>
                            @empty
                                <li>No hay datos de cursos para describir.</li>
                            @endforelse
                        </ul>
                    </article>

                    <article id="analitica-diagnostica">
                        <h2>Diagnóstica · ¿Por qué?</h2>
                        <p>Eventos recientes registrados en la bitácora de tu cuenta.</p>
                        <ol>
                            @forelse ($analytics['diagnostic']['events'] as $event)
                                <li>
                                    {{ $event['created_at'] }}:
                                    {{ $event['entity'] }} #{{ $event['entity_id'] }} — {{ $event['action'] }}.
                                    @if ($event['old_data'] || $event['new_data'])
                                        <details>
                                            <summary>Ver cambios</summary>
                                            <p>Antes: {{ json_encode($event['old_data'], JSON_UNESCAPED_UNICODE) }}</p>
                                            <p>Después: {{ json_encode($event['new_data'], JSON_UNESCAPED_UNICODE) }}</p>
                                        </details>
                                    @endif
                                </li>
                            @empty
                                <li>Aún no hay eventos en tu bitácora.</li>
                            @endforelse
                        </ol>
                    </article>

                    <article id="analitica-predictiva">
                        <h2>Predictiva · ¿Qué podría pasar?</h2>
                        <p>{{ $analytics['predictive']['method'] }}</p>
                        <p>{{ $analytics['predictive']['overdue_policy'] }}</p>
                        <ul>
                            @forelse ($analytics['predictive']['courses'] as $course)
                                <li>
                                    {{ $course['course_name'] }}:
                                    riesgo {{ str_replace('_', ' ', $course['risk_level']) }}.
                                    @if ($course['current_average'] !== null)
                                        Promedio actual {{ number_format($course['current_average'], 2) }}%.
                                    @endif
                                    @if ($course['required_average_on_remaining'] !== null)
                                        Promedio requerido en lo restante {{ number_format($course['required_average_on_remaining'], 2) }}%.
                                    @endif
                                    @if ($course['maximum_final_grade'] !== null)
                                        Máximo final estimado {{ number_format($course['maximum_final_grade'], 2) }}%.
                                    @endif
                                </li>
                            @empty
                                <li>No hay cursos disponibles para estimar el riesgo.</li>
                            @endforelse
                        </ul>
                    </article>

                    <article id="analitica-prescriptiva">
                        <h2>Prescriptiva · ¿Qué conviene hacer?</h2>
                        <p>Acciones sugeridas según tu situación académica.</p>
                        @forelse ($analytics['prescriptive']['actions'] as $recommendation)
                            <section>
                                <h3>{{ $recommendation['course_name'] }} · Riesgo {{ str_replace('_', ' ', $recommendation['risk_level']) }}</h3>
                                <ul>
                                    @foreach ($recommendation['actions'] as $action)
                                        <li>{{ $action }}</li>
                                    @endforeach
                                </ul>
                            </section>
                        @empty
                            <p>No hay acciones sugeridas porque no hay cursos en el semestre.</p>
                        @endforelse
                    </article>
                </section>
            @endif

            @if ($dashboard === null)
                <section id="cursos" aria-labelledby="courses-heading">
                    <h1 id="courses-heading">Mis cursos</h1>
                    <p>Marca un semestre como actual para consultar tus cursos.</p>
                </section>

                <section id="actividades" aria-labelledby="activities-heading">
                    <h1 id="activities-heading">Mis actividades</h1>
                    <p>Marca un semestre como actual para consultar tus actividades.</p>
                </section>

                <section id="calendario" aria-labelledby="calendar-heading">
                    <h1 id="calendar-heading">Calendario académico</h1>
                    <p>Marca un semestre como actual para consultar el calendario.</p>
                </section>

                <section id="analitica" aria-labelledby="analytics-heading">
                    <h1 id="analytics-heading">Mi analítica</h1>
                    <p>Marca un semestre como actual para consultar los cuatro niveles de analítica.</p>
                </section>
            @endif
        </main>
    </div>
</body>
</html>
