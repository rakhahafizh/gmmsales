<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'GMMSales API Documentation',
    description: 'Dokumentasi API untuk aplikasi GMMSales - sistem pencatatan kunjungan customer oleh sales lapangan dengan dokumentasi GPS dan foto.',
    contact: new OA\Contact(name: 'Rakha Hafizh', email: 'rakhahafizh@example.com')
)]
#[OA\Server(url: 'http://127.0.0.1:8000', description: 'GMMSales Local Development Server')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: "Masukkan token Sanctum tanpa prefix 'Bearer ' (cukup tokennya saja)"
)]
#[OA\Tag(name: 'Auth', description: 'Endpoint untuk autentikasi user (login & logout)')]
#[OA\Tag(name: 'Customer', description: 'Endpoint untuk sales mengelola data customer yang didaftarkan')]
#[OA\Tag(name: 'Admin - Customer', description: 'Endpoint khusus admin untuk monitoring seluruh customer dari semua sales')]
#[OA\Tag(name: 'Admin - Sales', description: 'Endpoint khusus admin untuk mengelola akun sales (CRUD)')]
#[OA\Tag(name: 'Profile', description: 'Endpoint untuk mengelola profil user (foto profil)')]
class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/login',
        summary: 'Login user',
        description: 'Autentikasi user dengan username & password. Mengembalikan token Sanctum jika berhasil. Akun yang is_active=false tidak dapat login.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'admin'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login berhasil'),
            new OA\Response(response: 401, description: 'Username atau password salah'),
            new OA\Response(response: 403, description: 'Akun dinonaktifkan'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Username atau password salah',
            ], 401);
        }

        // BARU di Iterasi 5: cek apakah akun masih aktif
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Akun Anda sudah dinonaktifkan, silakan hubungi admin',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role,
                    'wilayah' => $user->wilayah?->nama,
                    'photo_url' => $user->photo_url,
                ],
                'token' => $token,
            ],
        ], 200);
    }

    #[OA\Post(
        path: '/api/logout',
        summary: 'Logout user',
        description: 'Hapus token aktif user yang sedang login.',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logout berhasil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil',
        ], 200);
    }
}