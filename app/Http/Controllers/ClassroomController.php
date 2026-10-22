<?php

namespace App\Http\Controllers;

use App\Models\AcademicSemester;
use App\Models\ClassroomConnection;
use App\Models\ClassroomCourseImport;
use App\Models\Course;
use App\Services\GoogleClassroomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ClassroomController extends Controller
{
    public function connect(Request $request, GoogleClassroomService $classroom): RedirectResponse
    {
        try {
            $state = Str::random(40);
            $request->session()->put('classroom_oauth_state', $state);

            return redirect()->away($classroom->authorizationUrl($state));
        } catch (RuntimeException $exception) {
            return to_route('dashboard')->withErrors(['classroom' => $exception->getMessage()]);
        }
    }

    public function callback(Request $request, GoogleClassroomService $classroom): RedirectResponse
    {
        $expectedState = $request->session()->pull('classroom_oauth_state');
        if (!$expectedState || !hash_equals($expectedState, (string) $request->query('state'))) {
            return to_route('dashboard')->withErrors(['classroom' => 'La autorización de Google expiró o no es válida. Inténtalo de nuevo.']);
        }
        if ($request->filled('error')) {
            return to_route('dashboard')->withErrors(['classroom' => 'No se autorizó el acceso a Google Classroom.']);
        }
        if (!$request->filled('code')) {
            return to_route('dashboard')->withErrors(['classroom' => 'Google no devolvió el código de autorización.']);
        }

        try {
            $user = $request->user();
            $connection = ClassroomConnection::firstWhere('user_id', $user->id);
            $credentials = $classroom->connect(
                (string) $request->query('code'),
                $connection?->refresh_token,
            );

            ClassroomConnection::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'google_email' => $credentials['email'],
                    'refresh_token' => $credentials['refresh_token'],
                ],
            );
            $count = $classroom->sync($user);

            return to_route('dashboard')->with('classroom_status', "Cuenta conectada. Se sincronizaron {$count} actividades.");
        } catch (RuntimeException $exception) {
            return to_route('dashboard')->withErrors(['classroom' => $exception->getMessage()]);
        }
    }

    public function sync(Request $request, GoogleClassroomService $classroom): RedirectResponse
    {
        try {
            $count = $classroom->sync($request->user());

            return to_route('dashboard')->with('classroom_status', "Sincronización completada: {$count} actividades actualizadas.");
        } catch (RuntimeException $exception) {
            return to_route('dashboard')->withErrors(['classroom' => $exception->getMessage()]);
        }
    }

    public function disconnect(Request $request, GoogleClassroomService $classroom): RedirectResponse
    {
        $connection = ClassroomConnection::where('user_id', $request->user()->id)->first();
        if ($connection) {
            try {
                $classroom->revoke($connection);
            } catch (RuntimeException $exception) {
                return to_route('dashboard')->withErrors(['classroom' => $exception->getMessage()]);
            }
            $connection->delete();
        }

        return to_route('dashboard')->with('classroom_status', 'Se desconectó Google Classroom. Los datos ya importados no se eliminaron.');
    }

    public function resolveImport(Request $request, ClassroomCourseImport $import): RedirectResponse
    {
        abort_unless($import->user_id === $request->user()->id && $import->status === 'pending', 404);

        $data = $request->validate([
            'semester_id' => ['nullable', 'integer', 'required_without:create_semester', 'exists:academic_semesters,id'],
            'create_semester' => ['nullable', 'boolean'],
            'semester_name' => ['required_if:create_semester,1', 'nullable', 'string', 'max:100'],
            'start_date' => ['required_if:create_semester,1', 'nullable', 'date'],
            'end_date' => ['required_if:create_semester,1', 'nullable', 'date', 'after_or_equal:start_date'],
        ]);

        [$course, $semester] = DB::transaction(function () use ($request, $data, $import) {
            if (!empty($data['create_semester'])) {
                $semester = AcademicSemester::create([
                    'user_id' => $request->user()->id,
                    'name' => $data['semester_name'],
                    'start_date' => $data['start_date'],
                    'end_date' => $data['end_date'],
                    'is_current' => false,
                ]);
            } else {
                $semester = AcademicSemester::where('user_id', $request->user()->id)
                    ->find($data['semester_id']);

                if (!$semester) {
                    throw ValidationException::withMessages([
                        'semester_id' => 'Selecciona uno de tus semestres.',
                    ]);
                }
            }

            $course = Course::create([
                'semester_id' => $semester->id,
                'name' => $import->name,
                'code' => $import->code,
                'classroom_course_id' => $import->google_course_id,
            ]);
            $import->update(['course_id' => $course->id, 'status' => 'imported']);

            return [$course, $semester];
        });

        return to_route('dashboard')->with('classroom_status', "Se agregó «{$course->name}» al semestre {$semester->name}.");
    }

    public function ignoreImport(Request $request, ClassroomCourseImport $import): RedirectResponse
    {
        abort_unless($import->user_id === $request->user()->id && $import->status === 'pending', 404);
        $import->update(['status' => 'ignored']);

        return to_route('dashboard')->with('classroom_status', 'El curso se omitió. Seguirá disponible para importarlo más adelante.');
    }
}
