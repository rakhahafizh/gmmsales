<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class AdminSalesController extends Controller
{
    #[OA\Get(
        path: '/api/admin/sales',
        summary: 'Daftar semua sales (Admin)',
        tags: ['Admin - Sales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'wilayah_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar sales berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
        ]
    )]
    public function index(Request $request)
    {
        $query = User::with('wilayah')
            ->where('role', 'sales')
            ->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('username', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('wilayah_id')) {
            $query->where('wilayah_id', $request->wilayah_id);
        }

        $sales = $query->paginate(10);

        return response()->json([
            'message' => 'Daftar sales berhasil diambil',
            'data' => $sales->map(fn($s) => $this->formatSales($s)),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
                'last_page' => $sales->lastPage(),
            ],
        ], 200);
    }

    #[OA\Post(
        path: '/api/admin/sales',
        summary: 'Tambah sales baru (Admin)',
        tags: ['Admin - Sales'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'username', 'password', 'nomor_telepon', 'wilayah_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Budi Santoso'),
                    new OA\Property(property: 'username', type: 'string', example: 'budi'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123'),
                    new OA\Property(property: 'nomor_telepon', type: 'string', example: '081234567890'),
                    new OA\Property(property: 'wilayah_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Sales berhasil dibuat'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username',
            'password' => 'required|string|min:6',
            'nomor_telepon' => 'required|numeric|digits_between:10,15',
            'wilayah_id' => 'required|exists:wilayahs,id',
        ], [
            'name.required' => 'Nama wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 6 karakter.',
            'nomor_telepon.required' => 'Nomor telepon wajib diisi.',
            'nomor_telepon.numeric' => 'Nomor telepon harus berupa angka.',
            'nomor_telepon.digits_between' => 'Nomor telepon harus 10-15 digit.',
            'wilayah_id.required' => 'Wilayah wajib dipilih.',
            'wilayah_id.exists' => 'Wilayah tidak ditemukan.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => 'sales',
            'nomor_telepon' => $request->nomor_telepon,
            'wilayah_id' => $request->wilayah_id,
            'is_active' => false,
        ]);

        $user->load('wilayah');

        return response()->json([
            'message' => 'Sales berhasil dibuat',
            'data' => $this->formatSales($user),
        ], 201);
    }

    #[OA\Get(
        path: '/api/admin/sales/{id}',
        summary: 'Detail sales (Admin)',
        tags: ['Admin - Sales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail sales berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
            new OA\Response(response: 404, description: 'Sales tidak ditemukan'),
        ]
    )]
    public function show(string $id)
    {
        $user = User::with('wilayah')
            ->where('role', 'sales')
            ->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Sales tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'Detail sales berhasil diambil',
            'data' => $this->formatSales($user),
        ], 200);
    }

    #[OA\Put(
        path: '/api/admin/sales/{id}',
        summary: 'Edit data sales (Admin)',
        description: 'Admin mengubah data sales. Field is_active tidak dapat diubah secara manual karena bersifat auto-managed (set true saat sales submit customer, reset false tiap tengah malam).',
        tags: ['Admin - Sales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'nomor_telepon', type: 'string'),
                    new OA\Property(property: 'wilayah_id', type: 'integer'),
                    new OA\Property(property: 'password', type: 'string', description: 'Opsional, untuk reset password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Sales berhasil diupdate'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
            new OA\Response(response: 404, description: 'Sales tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $user = User::where('role', 'sales')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Sales tidak ditemukan',
            ], 404);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'nomor_telepon' => 'sometimes|numeric|digits_between:10,15',
            'wilayah_id' => 'sometimes|exists:wilayahs,id',
            'password' => 'sometimes|string|min:6',
        ], [
            'name.max' => 'Nama maksimal 255 karakter.',
            'nomor_telepon.numeric' => 'Nomor telepon harus berupa angka.',
            'nomor_telepon.digits_between' => 'Nomor telepon harus 10-15 digit.',
            'wilayah_id.exists' => 'Wilayah tidak ditemukan.',
            'password.min' => 'Password minimal 6 karakter.',
        ]);

        $updateData = $request->only(['name', 'nomor_telepon', 'wilayah_id']);

        $passwordReset = false;
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
            $passwordReset = true;
        }

        $user->update($updateData);
        $user->load('wilayah');

        $message = $passwordReset
            ? 'Password sales berhasil direset'
            : 'Data sales berhasil diupdate';

        return response()->json([
            'message' => $message,
            'data' => $this->formatSales($user),
        ], 200);
    }

    #[OA\Delete(
        path: '/api/admin/sales/{id}',
        summary: 'Hapus sales (soft delete)',
        tags: ['Admin - Sales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sales berhasil dihapus'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
            new OA\Response(response: 404, description: 'Sales tidak ditemukan'),
        ]
    )]
    public function destroy(string $id)
    {
        $user = User::where('role', 'sales')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Sales tidak ditemukan',
            ], 404);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Sales berhasil dihapus',
        ], 200);
    }

    private function formatSales(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'role' => $user->role,
            'nomor_telepon' => $user->nomor_telepon,
            'wilayah_id' => $user->wilayah_id,
            'wilayah_nama' => $user->wilayah?->nama,
            'is_active' => $user->is_active,
            'photo_url' => $user->photo_url,
            'created_at' => $user->created_at?->toDateTimeString(),
        ];
    }
}