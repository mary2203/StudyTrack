<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;



class AuthController extends Controller
{
    // metodo para registrar nuevo usuario
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        // Generar un token de acceso para el usuario registrado
        $token = $user->createToken('auth_token')->plainTextToken;

        // Retornar la respuesta con el token y los datos del usuario
        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
            'message' => 'Usuario Registrado Correctamente',
        ], 201);
    }
    
    // metodo para iniciar sesión
    public function login(LoginRequest $request)
    {
        // Buscar el usuario por correo electrónico
        $user = User::where('email', $request->email)->first();

        // Verificar si el usuario existe y si la contraseña es correcta
        if (! $user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas'],
            ]);
        }

        // Generar un token de acceso para el usuario autenticado
        $token = $user->createToken('auth_token')->plainTextToken;

        // Retornar la respuesta con el token y los datos del usuario
        return response()->json([
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
            'message' => 'Inicio de sesion exitoso',
        ]);
    }

    // metodo para cerrar sesión
    public function logout(Request $request)
    {
        // Revocar el token de acceso del usuario autenticado
        $request->user()->currentAccessToken()->delete();

        // Retornar la respuesta indicando que la sesión se cerró correctamente
        return response()->json([
            'data' => null,
            'message' => 'Sesión cerrada correctamente',
        ]);
    }

    // metodo para obtener los datos del usuario autenticado   
    public function me(Request $request)
    {
        // Retornar la respuesta con los datos del usuario autenticado
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }
}
