import './bootstrap';

function initStudyTrackNavigation() {
    const dashboard = document.querySelector('[data-studytrack-dashboard]');
    if (!dashboard) return;

    const titles = {
        inicio: 'Inicio',
        cursos: 'Mis cursos',
        actividades: 'Actividades',
        calendario: 'Calendario',
        analitica: 'Mi analítica',
    };

    function activeSectionFromHash() {
        const hash = window.location.hash.slice(1);
        if (hash.startsWith('analitica-')) return 'analitica';
        return Object.hasOwn(titles, hash) ? hash : 'inicio';
    }

    function updateNavigation({ scroll = false } = {}) {
        const page = activeSectionFromHash();

        dashboard.querySelectorAll('.page').forEach((section) => {
            section.classList.toggle('active', section.id === page);
        });

        dashboard.querySelectorAll('[data-page]').forEach((link) => {
            const active = link.dataset.page === page;
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });

        const breadcrumb = document.getElementById('breadcrumb-title');
        if (breadcrumb) breadcrumb.textContent = titles[page];

        const hash = window.location.hash.slice(1);
        if (hash.startsWith('analitica-')) {
            requestAnimationFrame(() => {
                document.getElementById(hash)?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            });
        } else if (scroll) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    dashboard.querySelectorAll('a[data-page]').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.location.hash === `#${link.dataset.page}`) {
                updateNavigation({ scroll: true });
            }
        });
    });

    window.addEventListener('hashchange', () => updateNavigation({ scroll: true }));
    updateNavigation();
}

function initStudyTrackForms() {
    const dashboard = document.querySelector('[data-studytrack-dashboard]');
    if (!dashboard) return;

    sessionStorage.removeItem('studytrack_api_token');
    const toast = document.getElementById('st-toast');

    let currentDialog = null;
    let lastFocusedElement = null;
    let toastTimeout;

    function notify(message) {
        if (!toast) return;
        toast.textContent = message;
        toast.hidden = false;
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(() => { toast.hidden = true; }, 4200);
    }

    function showFormError(form, message) {
        const errorElement = form?.querySelector('.st-form-error');
        if (!errorElement) return;
        errorElement.textContent = message;
        errorElement.hidden = false;
    }

    function clearFormError(form) {
        const errorElement = form?.querySelector('.st-form-error');
        if (!errorElement) return;
        errorElement.hidden = true;
        errorElement.textContent = '';
    }

    function showDialog(name) {
        const dialog = document.getElementById(`st-modal-${name}`);
        if (!dialog) return;
        if (currentDialog) currentDialog.hidden = true;
        lastFocusedElement = lastFocusedElement || document.activeElement;
        currentDialog = dialog;
        dialog.hidden = false;
        document.body.classList.add('st-dialog-open');
        clearFormError(dialog.querySelector('form'));
        requestAnimationFrame(() => dialog.querySelector('input:not([type="hidden"]), select, button')?.focus());
    }

    function closeDialog() {
        if (!currentDialog) return;
        currentDialog.hidden = true;
        currentDialog = null;
        document.body.classList.remove('st-dialog-open');
        lastFocusedElement?.focus?.();
        lastFocusedElement = null;
    }

    async function parseResponse(response) {
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const messages = data.errors && typeof data.errors === 'object'
                ? Object.values(data.errors).flat().filter(Boolean)
                : [];
            const message = messages.length > 0
                ? messages.join(' ')
                : (data.message || `La API respondió con error ${response.status}.`);
            const error = new Error(message);
            error.status = response.status;
            throw error;
        }
        return data;
    }

    async function apiRequest(path, { method = 'GET', body } = {}) {
        const headers = { Accept: 'application/json' };
        if (body !== undefined) headers['Content-Type'] = 'application/json';
        if (!['GET', 'HEAD', 'OPTIONS'].includes(method.toUpperCase())) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const response = await fetch(path, {
            method,
            headers,
            credentials: 'same-origin',
            ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
        });
        return parseResponse(response);
    }

    async function requireApi(action) {
        try {
            await action();
        } catch (error) {
            if (error.status === 401) {
                notify('Tu sesión venció. Inicia sesión de nuevo para continuar.');
            } else {
                notify(error.message || 'No se pudo consultar la API.');
            }
        }
    }

    function fillSelect(select, records, placeholder = 'No hay registros') {
        select.replaceChildren();
        if (!records.length) {
            const emptyOption = new Option(placeholder, '');
            select.add(emptyOption);
            return;
        }
        for (const record of records) {
            const option = new Option(record.name || `Registro ${record.id}`, record.id);
            select.add(option);
        }
    }

    async function loadSemesters() {
        const result = await apiRequest('/dashboard-data/semesters');
        return Array.isArray(result.data) ? result.data : [];
    }

    async function loadCourses(semesterId) {
        const result = await apiRequest(`/api/courses?semester_id=${encodeURIComponent(semesterId)}`);
        return Array.isArray(result.data) ? result.data : [];
    }

    async function openRequestedDialog(name) {
        if (name === 'semester') {
            document.getElementById('st-semester-form').reset();
            showDialog('semester');
            return;
        }

        if (name === 'switch-semester' || name === 'course') {
            const semesters = await loadSemesters();
            const select = name === 'course'
                ? document.getElementById('st-course-semester')
                : document.getElementById('st-existing-semesters');
            fillSelect(select, semesters, 'Primero crea un semestre');

            const current = semesters.find((semester) => (semester.is_current === true || semester.is_current === 1 || semester.is_current === '1'));
            if (current) select.value = String(current.id);

            if (name === 'course' && semesters.length === 0) {
                notify('Primero necesitas crear un semestre.');
                showDialog('semester');
                return;
            }
            if (name === 'switch-semester' && semesters.length === 0) {
                notify('No hay semestres guardados. Crea uno primero.');
                showDialog('semester');
                return;
            }
            if (name === 'course') {
                const form = document.getElementById('st-course-form');
                form.reset();
                form.elements.editing_id.value = '';
                document.getElementById('st-course-title').textContent = 'Nuevo curso';
                form.querySelector('[type="submit"]').textContent = 'Guardar curso';
            }
            if (current && name === 'course') select.value = String(current.id);
            showDialog(name);
            return;
        }

        if (name === 'activity') {
            const semesters = await loadSemesters();
            const active = semesters.find((semester) => (semester.is_current === true || semester.is_current === 1 || semester.is_current === '1'));
            if (!active) {
                notify('Primero activa un semestre desde el botón «Semestres».');
                showDialog(semesters.length ? 'switch-semester' : 'semester');
                if (semesters.length) {
                    fillSelect(document.getElementById('st-existing-semesters'), semesters);
                }
                return;
            }

            const courses = await loadCourses(active.id);
            const select = document.getElementById('st-activity-course');
            fillSelect(select, courses, 'No hay cursos en el semestre');
            if (!courses.length) {
                notify('Primero registra un curso en el semestre actual.');
                await openRequestedDialog('course');
                return;
            }
            const form = document.getElementById('st-activity-form');
            form.reset();
            form.elements.editing_id.value = '';
            form.elements.status.value = 'pending';
            document.getElementById('st-activity-title').textContent = 'Nueva actividad';
            form.querySelector('[type="submit"]').textContent = 'Guardar actividad';
            fillSelect(select, courses);
            updateActivityGradeFields();
            showDialog('activity');
        }
    }

    function updateActivityGradeFields() {
        const type = document.getElementById('st-activity-type').value;
        const graded = document.getElementById('st-activity-graded');
        const course = document.getElementById('st-activity-course');
        const general = type === 'administrative' || type === 'meeting';
        const gradedLabel = document.getElementById('st-graded-label');
        const gradeFields = document.getElementById('st-grade-fields');
        const possible = document.getElementById('st-points-possible');

        graded.disabled = general;
        if (general) graded.checked = false;
        gradedLabel.hidden = general;
        course.disabled = general;
        course.required = !general;
        gradeFields.hidden = !graded.checked || general;
        possible.required = graded.checked && !general;
    }

    async function submitForm(form, operation) {
        clearFormError(form);
        const button = form.querySelector('button[type="submit"]');
        if (button) button.disabled = true;
        try {
            await operation();
        } catch (error) {
            if (error.status === 401) {
                showFormError(form, 'Tu sesión venció. Inicia sesión de nuevo para continuar.');
            } else {
                showFormError(form, error.message || 'No se pudieron guardar los datos.');
            }
        } finally {
            if (button) button.disabled = false;
        }
    }

    function refreshDashboard(section = 'inicio') {
        window.location.hash = section;
        window.location.reload();
    }

    document.querySelectorAll('[data-st-open]').forEach((button) => {
        button.addEventListener('click', () => {
            requireApi(() => openRequestedDialog(button.dataset.stOpen));
        });
    });

    document.querySelectorAll('[data-st-close]').forEach((button) => {
        button.addEventListener('click', closeDialog);
    });

    document.querySelectorAll('.st-modal-backdrop').forEach((overlay) => {
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) closeDialog();
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && currentDialog) closeDialog();
        if (event.key !== 'Tab' || !currentDialog) return;
        const items = [...currentDialog.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled)')]
            .filter((el) => el.getClientRects().length > 0);
        if (!items.length) return;
        const first = items[0];
        const last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    document.getElementById('st-activity-type')?.addEventListener('change', updateActivityGradeFields);
    document.getElementById('st-activity-graded')?.addEventListener('change', updateActivityGradeFields);

    document.getElementById('st-semester-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        await submitForm(form, async () => {
            const fields = new FormData(form);
            const startDate = String(fields.get('start_date'));
            const endDate = String(fields.get('end_date'));
            if (endDate <= startDate) throw new Error('La fecha final debe ser posterior a la inicial.');
            await apiRequest('/dashboard-data/semesters', {
                method: 'POST',
                body: {
                    name: String(fields.get('name')).trim(),
                    start_date: startDate,
                    end_date: endDate,
                    is_current: form.elements.is_current.checked,
                },
            });
            refreshDashboard('inicio');
        });
    });

    document.getElementById('st-switch-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        await submitForm(form, async () => {
            const semesterId = String(form.elements.semester_id.value || '');
            if (!semesterId) throw new Error('Selecciona un semestre válido.');
            await apiRequest(`/dashboard-data/semesters/${encodeURIComponent(semesterId)}`, {
                method: 'PATCH',
                body: { is_current: true },
            });
            refreshDashboard('inicio');
        });
    });

    document.getElementById('st-course-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        await submitForm(form, async () => {
            const fields = new FormData(form);
            const editingId = String(fields.get('editing_id') || '');
            await apiRequest(editingId ? `/api/courses/${encodeURIComponent(editingId)}` : '/api/courses', {
                method: editingId ? 'PATCH' : 'POST',
                body: {
                    semester_id: Number(fields.get('semester_id')),
                    name: String(fields.get('name')).trim(),
                    code: String(fields.get('code')).trim(),
                    color_hex: String(fields.get('color_hex')),
                    target_grade: Number(fields.get('target_grade')),
                },
            });
            refreshDashboard('cursos');
        });
    });

    document.getElementById('st-activity-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        await submitForm(form, async () => {
            const fields = new FormData(form);
            const type = String(fields.get('activity_type'));
            const general = type === 'administrative' || type === 'meeting';
            const graded = !general && form.elements.is_graded.checked;
            const due = String(fields.get('due_datetime'));
            const dateTime = due.includes('T') ? `${due.replace('T', ' ')}:00` : due;
            const body = {
                title: String(fields.get('title')).trim(),
                activity_type: type,
                due_datetime: dateTime,
                is_graded: graded,
                status: String(fields.get('status') || 'pending'),
            };
            const description = String(fields.get('description') || '').trim();
            if (description) body.description = description;
            if (!general) {
                body.course_id = Number(fields.get('course_id'));
                if (!body.course_id) throw new Error('Selecciona un curso.');
            } else {
                body.course_id = null;
            }
            if (graded) {
                const max = Number(fields.get('points_possible'));
                if (!Number.isFinite(max) || max <= 0) throw new Error('Los puntos posibles deben ser mayores a cero.');
                body.points_possible = max;
                const score = String(fields.get('points_obtained') || '').trim();
                if (score !== '') {
                    if (Number(score) < 0 || Number(score) > max) throw new Error('Los puntos obtenidos deben estar entre cero y los posibles.');
                    body.points_obtained = Number(score);
                } else {
                    body.points_obtained = null;
                }
            }
            const editingId = String(fields.get('editing_id') || '');
            await apiRequest(editingId ? `/api/activities/${encodeURIComponent(editingId)}` : '/api/activities', {
                method: editingId ? 'PATCH' : 'POST',
                body,
            });
            refreshDashboard('actividades');
        });
    });

    const activityFilterForm = document.querySelector('.activity-filters');
    const activitySearch = document.getElementById('activity_search');
    const activityStatus = document.getElementById('activity_status');
    let activitySearchTimeout;
    let activityRequestController;

    async function updateActivityResults(url, historyMode = 'replace') {
        activityRequestController?.abort();
        activityRequestController = new AbortController();
        const requestController = activityRequestController;
        const currentCard = document.querySelector('.activity-list-card');
        currentCard?.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: requestController.signal,
            });
            if (!response.ok) throw new Error(`No se pudieron consultar las actividades (${response.status}).`);

            const document = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updatedCard = document.querySelector('.activity-list-card');
            if (!updatedCard) throw new Error('No se pudo actualizar la lista de actividades.');

            currentCard?.replaceWith(updatedCard);
            bindActivityRowActions();

            const nextUrl = new URL(url, window.location.origin);
            nextUrl.hash = 'actividades';
            if (historyMode) window.history[`${historyMode}State`]({}, '', nextUrl);
        } catch (error) {
            if (error.name !== 'AbortError') notify(error.message || 'No se pudieron consultar las actividades.');
        } finally {
            if (requestController === activityRequestController) {
                document.querySelector('.activity-list-card')?.removeAttribute('aria-busy');
            }
        }
    }

    function applyActivityFilters() {
        const url = new URL(activityFilterForm.action, window.location.origin);
        new FormData(activityFilterForm).forEach((value, key) => {
            if (String(value).trim() !== '') url.searchParams.set(key, String(value));
        });
        updateActivityResults(url);
    }

    activityFilterForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        applyActivityFilters();
    });

    activitySearch?.addEventListener('input', () => {
        clearTimeout(activitySearchTimeout);
        activitySearchTimeout = setTimeout(() => activityFilterForm?.requestSubmit(), 300);
    });

    activityStatus?.addEventListener('change', () => {
        clearTimeout(activitySearchTimeout);
        activityFilterForm?.requestSubmit();
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('.activity-pagination a');
        if (!link) return;
        event.preventDefault();
        updateActivityResults(link.href, 'push');
    });

    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href);
        if (activitySearch) activitySearch.value = url.searchParams.get('activity_search') || '';
        if (activityStatus) activityStatus.value = url.searchParams.get('activity_status') || '';
        updateActivityResults(url, null);
    });

    document.getElementById('st-clear-filters')?.addEventListener('click', (event) => {
        event.preventDefault();
        if (activitySearch) activitySearch.value = '';
        if (activityStatus) activityStatus.value = '';
        clearTimeout(activitySearchTimeout);
        applyActivityFilters();
    });

    async function resolveCourseId(button) {
        if (button.dataset.id) return button.dataset.id;
        const semesters = await loadSemesters();
        const active = semesters.find((item) => item.is_current === true || item.is_current === 1 || item.is_current === '1');
        if (!active) throw new Error('No hay semestre actual para localizar el curso.');
        const courses = await loadCourses(active.id);
        const matches = courses.filter((course) =>
            String(course.name || '') === String(button.dataset.name || '') &&
            String(course.code || '') === String(button.dataset.code || '')
        );
        if (matches.length !== 1) throw new Error('No pude identificar este curso de forma única; confirma el ID con el equipo de backend.');
        return String(matches[0].id);
    }

    document.querySelectorAll('[data-st-edit-course]').forEach((button) => {
        button.addEventListener('click', () => requireApi(async () => {
            const id = await resolveCourseId(button);
            const semesters = await loadSemesters();
            const select = document.getElementById('st-course-semester');
            fillSelect(select, semesters, 'No hay semestres');
            const result = await apiRequest(`/api/courses/${encodeURIComponent(id)}`);
            const course = result.data || {};
            const form = document.getElementById('st-course-form');
            form.reset();
            form.elements.editing_id.value = id;
            form.elements.semester_id.value = String(course.semester_id || semesters.find((item) => item.is_current)?.id || '');
            form.elements.name.value = course.name ?? button.dataset.name ?? '';
            form.elements.code.value = course.code ?? button.dataset.code ?? '';
            form.elements.target_grade.value = course.target_grade ?? button.dataset.grade ?? 85;
            const hex = course.color_hex ?? button.dataset.color ?? '#526531';
            form.elements.color_hex.value = /^#[0-9a-f]{6}$/i.test(hex) ? hex : '#526531';
            document.getElementById('st-course-title').textContent = 'Editar curso';
            form.querySelector('[type="submit"]').textContent = 'Guardar cambios';
            showDialog('course');
        }));
    });

    document.querySelectorAll('[data-st-delete-course]').forEach((button) => {
        button.addEventListener('click', () => requireApi(async () => {
            if (!window.confirm(`¿Eliminar el curso «${button.dataset.name}»? Las actividades asociadas pueden verse afectadas. Esta acción no se podrá deshacer desde la interfaz.`)) return;
            const id = await resolveCourseId(button);
            button.disabled = true;
            try {
                await apiRequest(`/api/courses/${encodeURIComponent(id)}`, { method: 'DELETE' });
                refreshDashboard('cursos');
            } finally {
                button.disabled = false;
            }
        }));
    });

    function bindActivityRowActions() {
        document.querySelectorAll('[data-st-edit-activity]').forEach((button) => {
            button.addEventListener('click', () => requireApi(async () => {
                const semesters = await loadSemesters();
                const current = semesters.find((item) => item.is_current === true || item.is_current === 1 || item.is_current === '1');
                const courses = current ? await loadCourses(current.id) : [];
                const form = document.getElementById('st-activity-form');
                form.reset();
                fillSelect(document.getElementById('st-activity-course'), courses, 'Sin curso');
                form.elements.editing_id.value = button.dataset.id;
                form.elements.title.value = button.dataset.title || '';
                form.elements.description.value = button.dataset.description || '';
                form.elements.activity_type.value = button.dataset.type || 'task';
                form.elements.course_id.value = button.dataset.course || '';
                form.elements.due_datetime.value = button.dataset.due || '';
                form.elements.status.value = button.dataset.status || 'pending';
                form.elements.is_graded.checked = button.dataset.graded === '1';
                form.elements.points_possible.value = button.dataset.possible || '20';
                form.elements.points_obtained.value = button.dataset.obtained || '';
                updateActivityGradeFields();
                document.getElementById('st-activity-title').textContent = 'Editar actividad';
                form.querySelector('[type="submit"]').textContent = 'Guardar cambios';
                showDialog('activity');
            }));
        });

        document.querySelectorAll('[data-st-delete-activity]').forEach((button) => {
            button.addEventListener('click', () => requireApi(async () => {
                if (!window.confirm(`¿Eliminar la actividad «${button.dataset.title}»? Se retirará de la planificación.`)) return;
                button.disabled = true;
                try {
                    await apiRequest(`/api/activities/${encodeURIComponent(button.dataset.id)}`, { method: 'DELETE' });
                    refreshDashboard('actividades');
                } finally {
                    button.disabled = false;
                }
            }));
        });

        document.querySelectorAll('[data-st-status]').forEach((select) => {
            select.addEventListener('change', () => {
                const oldValue = select.dataset.current;
                const newValue = select.value;
                if (oldValue === newValue) return;
                select.disabled = true;
                requireApi(async () => {
                    try {
                        await apiRequest(`/api/activities/${encodeURIComponent(select.dataset.id)}`, {
                            method: 'PATCH',
                            body: { status: newValue },
                        });
                        refreshDashboard('actividades');
                    } catch (error) {
                        select.value = oldValue;
                        select.disabled = false;
                        throw error;
                    }
                }).then(() => {
                    select.disabled = false;
                });
            });
        });

        document.querySelectorAll('[data-st-grade]').forEach((button) => {
            button.addEventListener('click', () => {
                requireApi(async () => {
                    const form = document.getElementById('st-grade-form');
                    form.elements.activity_id.value = button.dataset.id;
                    const points = Number(button.dataset.possible);
                    const input = form.elements.points_obtained;
                    input.max = String(points);
                    input.value = button.dataset.obtained || '';
                    document.getElementById('st-grade-description').textContent =
                        `${button.dataset.title} · Nota máxima: ${points} puntos`;
                    showDialog('grade');
                });
            });
        });
    }

    bindActivityRowActions();

    document.getElementById('st-grade-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        await submitForm(form, async () => {
            const id = String(form.elements.activity_id.value);
            const score = Number(form.elements.points_obtained.value);
            const max = Number(form.elements.points_obtained.max);
            if (score < 0 || score > max) throw new Error(`La nota debe estar entre 0 y ${max}.`);
            await apiRequest(`/api/activities/${encodeURIComponent(id)}/grade`, {
                method: 'PATCH',
                body: { points_obtained: score },
            });
            refreshDashboard('actividades');
        });
    });
}

function bootStudyTrack() {
    initStudyTrackNavigation();
    initStudyTrackForms();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootStudyTrack);
} else {
    bootStudyTrack();
}
