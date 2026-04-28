<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class CustomerController extends Controller
{
    #[OA\Post(
        path: '/api/customers',
        summary: 'Daftarkan customer baru (Sales)',
        description: 'Sales mendaftarkan customer baru beserta foto kunjungan dan koordinat GPS.',
        tags: ['Customer'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['nama_customer', 'alamat', 'nomor_telepon', 'latitude', 'longitude', 'photos'],
                    properties: [
                        new OA\Property(property: 'nama_customer', type: 'string', example: 'Toko Jaya Abadi'),
                        new OA\Property(property: 'alamat', type: 'string', example: 'Jl. Merdeka No. 10'),
                        new OA\Property(property: 'nomor_telepon', type: 'string', example: '081234567890'),
                        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: -6.1751),
                        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: 106.865),
                        new OA\Property(
                            property: 'photos[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: '1-3 foto (JPG/PNG, max 5MB per file)'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Customer berhasil didaftarkan'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'nama_customer' => 'required|string|max:255',
            'alamat' => 'required|string',
            'nomor_telepon' => 'required|numeric|digits_between:10,15',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'photos' => 'required|array|min:1|max:3',
            'photos.*' => 'required|file|mimes:jpeg,jpg,png|max:5120',
        ]);

        $customer = Customer::create([
            'user_id' => $request->user()->id,
            'nama_customer' => $request->nama_customer,
            'alamat' => $request->alamat,
            'nomor_telepon' => $request->nomor_telepon,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'visited_at' => now(),
        ]);

        foreach ($request->file('photos') as $file) {
            $path = $file->store("customers/{$customer->id}", 'public');
            CustomerPhoto::create([
                'customer_id' => $customer->id,
                'photo_path' => $path,
            ]);
        }

        $customer->load('photos');

        return response()->json([
            'message' => 'Customer berhasil didaftarkan',
            'data' => $this->formatCustomer($customer),
        ], 201);
    }

    #[OA\Get(
        path: '/api/customers',
        summary: 'Daftar customer milik sales yang login',
        description: 'Mengambil list customer yang didaftarkan oleh sales yang sedang login. Mendukung pencarian dan pagination.',
        tags: ['Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Cari berdasarkan nama customer (parsial match)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Halaman pagination', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar customer berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Customer::with('photos')
            ->where('user_id', $request->user()->id);

        if ($request->filled('search')) {
            $query->where('nama_customer', 'like', '%' . $request->search . '%');
        }

        $customers = $query->latest()->paginate(10);

        return response()->json([
            'message' => 'Daftar customer berhasil diambil',
            'data' => $customers->map(fn($c) => $this->formatCustomer($c)),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
            ],
        ], 200);
    }

    #[OA\Get(
        path: '/api/customers/history',
        summary: 'Riwayat kunjungan customer milik sales yang login',
        description: 'List customer dengan filter rentang tanggal kunjungan.',
        tags: ['Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-04-01')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date', example: '2026-04-30')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Riwayat customer berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function history(Request $request)
    {
        $query = Customer::with('photos')
            ->where('user_id', $request->user()->id)
            ->orderBy('visited_at', 'desc');

        if ($request->has('start_date')) {
            $query->whereDate('visited_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('visited_at', '<=', $request->end_date);
        }

        $history = $query->paginate(10);

        return response()->json([
            'message' => 'Riwayat customer berhasil diambil',
            'data' => $history->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'nama_customer' => $customer->nama_customer,
                    'alamat' => $customer->alamat,
                    'foto_utama' => $customer->photos->first()?->photo_url ?? null,
                    'tanggal_pendaftaran' => $customer->visited_at,
                ];
            }),
            'meta' => [
                'current_page' => $history->currentPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
                'last_page' => $history->lastPage(),
            ]
        ], 200);
    }

    #[OA\Get(
        path: '/api/customers/{id}',
        summary: 'Detail customer milik sales yang login',
        description: 'Mengambil detail satu customer. Hanya bisa diakses oleh sales pemilik data.',
        tags: ['Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID customer', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail customer berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Customer bukan milik sales yang login'),
            new OA\Response(response: 404, description: 'Customer tidak ditemukan'),
        ]
    )]
    public function show(Request $request, string $id)
    {
        $customer = Customer::with('photos')->find($id);

        if (!$customer) {
            return response()->json([
                'message' => 'Customer tidak ditemukan',
            ], 404);
        }

        if ($customer->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke data customer ini',
            ], 403);
        }

        return response()->json([
            'message' => 'Detail customer berhasil diambil',
            'data' => $this->formatCustomer($customer),
        ], 200);
    }

    #[OA\Delete(
        path: '/api/customers/{id}',
        summary: 'Hapus customer (soft delete)',
        description: 'Sales menghapus customer miliknya sendiri. Foto akan dihapus dari storage.',
        tags: ['Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Customer berhasil dihapus'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan milik sales yang login'),
            new OA\Response(response: 404, description: 'Customer tidak ditemukan'),
        ]
    )]
    public function destroy(Request $request, $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'message' => 'Customer tidak ditemukan'
            ], 404);
        }

        if ($customer->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke data ini'
            ], 403);
        }

        foreach ($customer->photos as $photo) {
            Storage::delete($photo->photo_path);
            $photo->delete();
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer berhasil dihapus'
        ], 200);
    }

    private function formatCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'nama_customer' => $customer->nama_customer,
            'alamat' => $customer->alamat,
            'nomor_telepon' => $customer->nomor_telepon,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'visited_at' => $customer->visited_at?->toDateTimeString(),
            'created_at' => $customer->created_at->toDateTimeString(),
            'photos' => $customer->photos->map(fn($p) => [
                'id' => $p->id,
                'photo_url' => $p->photo_url,
            ])->values(),
        ];
    }
}