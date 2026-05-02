<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AdminSalesController extends Controller
{
    /**
     * US-12: Admin menambah akun sales baru.
     * POST /api/admin/sales
     */
    #[OA\Post(
        path: '/api/admin/sales',
        summary: 'Tambah akun sales baru (Admin)',
        description: 'Admin membuat akun sales baru. Role otomatis sales, is_active default true, password di-hash.',
        tags: ['Admin - Sales'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'username', 'password', 'nomor_telepon', 'wilayah_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Eko Saputra'),
                    new OA\Property(property: 'username', type: 'string', example: 'eko'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                    new OA\Property(property: 'nomor_telepon', type: 'string', example: '081234567890'),
                    new OA\Property(property: 'wilayah_id', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Sales berhasil ditambahkan'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
            new OA\Response(response: 422, description: 'Validation error (username sudah dipakai, dll)'),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:50|unique:users,username',
            'password' => 'required|string|min:8',
            'nomor_telepon' => 'required|numeric|digits_between:10,15',
            'wilayah_id' => 'required|exists:wilayahs,id',
        ]);

        $sales = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'nomor_telepon' => $request->nomor_telepon,
            'wilayah_id' => $request->wilayah_id,
            'role' => 'sales',
            'is_active' => true,
        ]);

        $sales->load('wilayah');

        return response()->json([
            'message' => 'Sales berhasil ditambahkan',
            'data' => $this->formatSales($sales),
        ], 201);
    }

    /**
     * US-13: Admin edit data sales.
     * PUT /api/admin/sales/{id}
     */
    #[OA\Put(
        path: '/api/admin/sales/{id}',
        summary: 'Edit data sales (Admin)',
        description: 'Admin mengubah data sales: name, nomor_telepon, wilayah_id, is_active, dan password (opsional). Username dan role tidak bisa diubah. Admin tidak bisa nonaktifkan diri sendiri.',
        tags: ['Admin - Sales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Budi Santoso Updated'),
                    new OA\Property(property: 'nomor_telepon', type: 'string', example: '081234567899'),
                    new OA\Property(property: 'wilayah_id', type: 'integer', example: 2),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'password', type: 'string', format: 'password', description: 'Opsional, untuk reset password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Sales berhasil diupdate'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan admin / Admin nonaktifkan diri sendiri'),
            new OA\Response(response: 404, description: 'Sales tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $sales = User::find($id);

        if (!$sales) {
            return response()->json([
                'message' => 'Sales tidak ditemukan',
            ], 404);
        }

        // Cegah admin nonaktifkan akun sendiri
        if (
            $request->has('is_active')
            && !$request->is_active
            && $sales->id === $request->user()->id
        ) {
            return response()->json([
                'message' => 'Anda tidak dapat menonaktifkan akun sendiri',
            ], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'nomor_telepon' => 'sometimes|numeric|digits_between:10,15',
            'wilayah_id' => 'sometimes|exists:wilayahs,id',
            'is_active' => 'sometimes|boolean',
            'password' => 'sometimes|string|min:8',
        ]);

        // Update field yang dikirim aja (selain password & username/role yang dilarang)
        $sales->fill($request->only([
            'name',
            'nomor_telepon',
            'wilayah_id',
            'is_active',
        ]));

        // Hash password kalo dikirim
        if ($request->filled('password')) {
            $sales->password = Hash::make($request->password);
        }

        $passwordWasReset = $request->filled('password');

        $sales->save();
        $sales->load('wilayah');

        // Pesan lebih spesifik tergantung apa yang diubah
        if ($passwordWasReset && $request->has('is_active')) {
            $message = 'Password dan status aktif sales berhasil diupdate';
        } elseif ($passwordWasReset) {
            $message = 'Password sales berhasil direset';
        } elseif ($request->has('is_active') && !$sales->is_active) {
            $message = 'Akun sales berhasil dinonaktifkan';
        } elseif ($request->has('is_active') && $sales->is_active) {
            $message = 'Akun sales berhasil diaktifkan';
        } else {
            $message = 'Data sales berhasil diupdate';
        }

        return response()->json([
            'message' => $message,
            'data' => $this->formatSales($sales),
        ], 200);
    }

    /**
     * US-14: Admin cari/filter sales.
     * GET /api/admin/sales?search=&wok_id=&is_active=
     */
    #[OA\Get(
        path: '/api/admin/sales',
        summary: 'Daftar sales dengan filter (Admin)',
        description: 'Admin melihat list sales dengan dukungan search (nama/username), filter wilayah, dan filter status aktif.',
        tags: ['Admin - Sales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Cari nama atau username', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'wok_id', in: 'query', required: false, description: 'Filter wilayah ID', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, description: 'Filter status aktif (true/false)', schema: new OA\Schema(type: 'boolean')),
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
            ->withCount('customers')
            ->where('role', 'sales');

        // Filter search by nama atau username
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('username', 'like', '%' . $search . '%');
            });
        }

        // Filter wilayah
        if ($request->filled('wok_id')) {
            $query->where('wilayah_id', $request->wok_id);
        }

        // Filter status aktif
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sales = $query->latest()->paginate(10);

        return response()->json([
            'message' => 'Daftar sales berhasil diambil',
            'data' => $sales->map(fn($s) => $this->formatSalesList($s)),
            'meta' => [
                'current_page' => $sales->currentPage(),
                'per_page' => $sales->perPage(),
                'total' => $sales->total(),
                'last_page' => $sales->lastPage(),
            ],
        ], 200);
    }

    /**
     * US-15: Admin lihat detail sales + 3 customer terbaru.
     * GET /api/admin/sales/{id}
     */
    #[OA\Get(
        path: '/api/admin/sales/{id}',
        summary: 'Detail sales beserta 3 customer terbaru (Admin)',
        description: 'Admin lihat detail sales lengkap. Untuk semua customer milik sales ini, gunakan GET /api/admin/customers?sales_id={id}.',
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
        $sales = User::with(['wilayah'])
            ->withCount('customers')
            ->find($id);

        if (!$sales) {
            return response()->json([
                'message' => 'Sales tidak ditemukan',
            ], 404);
        }

        // Ambil 3 customer terbaru dari sales ini
        $recentCustomers = $sales->customers()
            ->with('photos')
            ->latest('visited_at')
            ->take(3)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'nama_customer' => $c->nama_customer,
                'alamat' => $c->alamat,
                'visited_at' => $c->visited_at?->toDateTimeString(),
                'foto_utama' => $c->photos->first()?->photo_url,
            ]);

        return response()->json([
            'message' => 'Detail sales berhasil diambil',
            'data' => [
                ...$this->formatSales($sales),
                'total_customer' => $sales->customers_count,
                'recent_customers' => $recentCustomers,
            ],
        ], 200);
    }

    /**
     * US-17: Admin hapus akun sales (soft delete).
     * DELETE /api/admin/sales/{id}
     */
    #[OA\Delete(
        path: '/api/admin/sales/{id}',
        summary: 'Hapus akun sales (Admin) - soft delete',
        description: 'Admin menghapus sales secara soft delete. Customer milik sales tersebut tetap ada di database. Admin tidak bisa hapus akun sendiri.',
        tags: ['Admin - Sales'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Sales berhasil dihapus'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan admin / Admin hapus diri sendiri'),
            new OA\Response(response: 404, description: 'Sales tidak ditemukan'),
        ]
    )]
    public function destroy(Request $request, string $id)
    {
        $sales = User::find($id);

        if (!$sales) {
            return response()->json([
                'message' => 'Sales tidak ditemukan',
            ], 404);
        }

        // Cegah admin hapus akun sendiri
        if ($sales->id === $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak dapat menghapus akun sendiri',
            ], 403);
        }

        $sales->delete(); // Soft delete (set deleted_at)

        return response()->json([
            'message' => 'Sales berhasil dihapus',
        ], 200);
    }

    /**
     * Helper: format sales detail (untuk show & response store/update).
     */
    private function formatSales(User $sales): array
    {
        return [
            'id' => $sales->id,
            'name' => $sales->name,
            'username' => $sales->username,
            'nomor_telepon' => $sales->nomor_telepon,
            'role' => $sales->role,
            'is_active' => $sales->is_active,
            'photo_url' => $sales->photo_url,
            'wilayah' => $sales->wilayah ? [
                'id' => $sales->wilayah->id,
                'nama' => $sales->wilayah->nama,
            ] : null,
            'created_at' => $sales->created_at->toDateTimeString(),
        ];
    }

    /**
     * Helper: format sales list (lebih ringkas, dengan total_customer).
     */
    private function formatSalesList(User $sales): array
    {
        return [
            'id' => $sales->id,
            'name' => $sales->name,
            'username' => $sales->username,
            'nomor_telepon' => $sales->nomor_telepon,
            'is_active' => $sales->is_active,
            'photo_url' => $sales->photo_url,
            'wilayah' => $sales->wilayah?->nama,
            'total_customer' => $sales->customers_count ?? 0,
        ];
    }
}