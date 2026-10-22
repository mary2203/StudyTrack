<?php

namespace App\Http\Controllers;

use App\Services\WebDashboardService;
use App\Models\AcademicSemester;
use App\Models\ClassroomConnection;
use App\Models\ClassroomCourseImport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebDashboardController extends Controller
{
    public function index(Request $request, WebDashboardService $dashboard): View
    {
        $filters = $request->validate([
            'activity_search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'activity_status' => ['sometimes', 'nullable', 'in:pending,in_progress,completed,overdue'],
        ]);

        return view('dashboard', $dashboard->forUser(
            $request->user(),
            $filters['activity_search'] ?? null,
            $filters['activity_status'] ?? null,
        ) + [
            'user' => $request->user(),
            'classroomConnection' => ClassroomConnection::where('user_id', $request->user()->id)->first(),
            'pendingClassroomImports' => ClassroomCourseImport::where('user_id', $request->user()->id)
                ->where('status', 'pending')
                ->orderBy('name')
                ->get(),
            'semesters' => AcademicSemester::where('user_id', $request->user()->id)
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }
}
