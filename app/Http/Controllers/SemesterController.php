<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\AcademicSemester;
use App\Http\Requests\StoreSemesterRequest;
use App\Http\Requests\UpdateSemesterRequest;
use Illuminate\Support\Facades\DB;

// Importar la clase AuthorizesRequests para usar la autorización de políticas
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class SemesterController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // busca todos los semestres del usuario autenticado
        $semesters = AcademicSemester::where(
            'user_id',
            $request->user()->id
        )->get();

        //mensaje de respuesta a la obtencion de semestres
        return response()->json([
            'data' => $semesters,
            'message' => 'Semestres obtenidos correctamente',
        ]);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSemesterRequest $request)
    {
        // esto es para crear un nuevo semestre para el usuario autenticado
        // Validar los datos de entrada usando StoreSemesterRequest
        $validatedData = $request->validated();
        
        // Usar una transacción para asegurar que solo un semestre puede ser actual a la vez
        $semester = DB::transaction(function () use ($request, $validatedData){

        // Si el semestre que se está creando es marcado como actual, primero desmarcar cualquier otro semestre actual del usuario
            if ($request->boolean('is_current')) {
                AcademicSemester::where('user_id', $request->user()->id)
                    ->update(['is_current' => false]);
            }

            // esto es para crear un nuevo semestre para el usuario autenticado
            return AcademicSemester::create([
                'user_id' => $request->user()->id,
                'name' => $validatedData['name'],
                'start_date' => $validatedData['start_date'],
                'end_date' => $validatedData['end_date'],
                'is_current' => $validatedData['is_current'] ?? false,
            ]);

        });

        return response()->json([
            'data' => $semester,
            'message' => 'Semestre creado correctamente',
        ], 201);
       
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, AcademicSemester $semester)
    { 

        // Esto es para asegurarnos de que el semestre pertenece al usuario autenticado
        $this->authorize('view', $semester);

        //mensaje de respuesta a la obtencion del semestre localizado
        return response()->json([
            'data' => $semester,
            'message' => 'Semestre obtenido correctamente',
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSemesterRequest $request, AcademicSemester $semester)
    {
        // esto es para asegurarnos de que el semestre pertenece al usuario autenticado
        $this->authorize('update', $semester);

        // Validar los datos de entrada usando UpdateSemesterRequest
        $validatedData = $request->validated();

        // Usar una transacción para asegurar que solo un semestre puede ser actual a la vez
        DB::transaction(function () use ($request, $semester, $validatedData) {

            // Si el semestre que se está actualizando es marcado como actual, primero desmarcar cualquier otro semestre actual del usuario
            if ($request->boolean('is_current')) {
                AcademicSemester::where('user_id', $request->user()->id)
                    ->where('id', '!=', $semester->id)
                    ->update(['is_current' => false]);
            }

            // Actualizar el semestre con los datos validados
            $semester->update($validatedData);
        });

        //mensaje de respuesta a la actualizacion del semestre localizado
        return response()->json([
            'data' => $semester,
            'message' => 'Semestre actualizado correctamente',
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, AcademicSemester $semester)
    {
        // Esto es para asegurarnos de que el semestre pertenece al usuario autenticado
        $this->authorize('delete', $semester);

        //eliminar el semestre
        $semester->delete();

        //mensaje de respuesta a eliminacion de semestre
        return response()->json([
            'data' => null,
            'message' => 'Semestre eliminado correctamente',
        ]);
    }

    public function current(Request $request)
    {
        // esto es para obtener el semestre actual del usuario autenticado 
        $semester = AcademicSemester::where(
            'user_id',
            $request->user()->id
        )
        ->where('is_current', true)
            ->first();
        
        //Mensaje de respuesta a la obtencion del semestre actual localizado
        return response()->json([
            'data' => $semester,
            'message' => 'Semestre actual obtenido correctamente',
        ]);
    }
}
