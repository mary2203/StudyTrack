<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Course;
use App\Models\AcademicSemester;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CourseController extends Controller
{

    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $semesterId = $request->query('semester_id');

        if ($semesterId) {
            $semester = AcademicSemester::where('id', $semesterId)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        } else {
            $semester = AcademicSemester::where('user_id', $request->user()->id)
                ->where('is_current', true)
                ->firstOrFail();
        }

        $courses = Course::with('priorityRule')
            ->where('semester_id', $semester->id)
            ->get();

        return CourseResource::collection($courses);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseRequest $request)
    {
        //
        $validatedData = $request->validated();

        $semester = AcademicSemester::where('id', $validatedData['semester_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        
        $course = Course::create($validatedData);

        $course->load('priorityRule');

        return response()->json([
            'data' => new CourseResource($course),
            'message' => 'Curso creado exitosamente.',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course)
    {
        //
        $this->authorize('view', $course);

        $course->load('priorityRule');

        return response()->json([
            'data' => new CourseResource($course),
            'message' => 'Curso obtenido exitosamente.',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseRequest $request, Course $course)
    {
        //
        $this->authorize('update', $course);

        $validatedData = $request->validated();

        if (isset($validatedData['semester_id'])){
            AcademicSemester::where('id', $validatedData['semester_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
        }

        $course->update($validatedData);
        $course->load('priorityRule');

        return response()->json([
            'data' => new CourseResource($course),
            'message' => 'Curso actualizado exitosamente.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course)
    {
        //
        $this->authorize('delete', $course);

        $course->delete();

        return response()->json([
            'message' => 'Curso eliminado exitosamente.',
        ]);
    }
}
