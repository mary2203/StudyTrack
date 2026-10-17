<?php

namespace App\Policies;

use App\Models\AcademicSemester;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SemesterPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AcademicSemester $academicSemester): bool
    {
        // Permitir que cualquier usuario autenticado vea un semestre si pertenece al usuario
        return $user->id === $academicSemester->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AcademicSemester $academicSemester): bool
    {
        // Permitir que cualquier usuario autenticado actualice un semestre si pertenece al usuario
        return $user->id === $academicSemester->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AcademicSemester $academicSemester): bool
    {
        // Permitir que cualquier usuario autenticado elimine un semestre si pertenece al usuario
        return $user->id === $academicSemester->user_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AcademicSemester $academicSemester): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AcademicSemester $academicSemester): bool
    {
        return false;
    }
}
