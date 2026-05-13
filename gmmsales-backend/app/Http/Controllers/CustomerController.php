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
        description: 'Sales mendaftarkan customer baru beserta foto kunjungan, koordinat GPS, dan produk yang ditawarkan. Akan otomatis menandai sales sebagai aktif hari ini.',
        tags: ['Customer'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['nama_customer', 'alamat', 'nomor_telepon', 'latitude', 'longitude', 'product_id', 'photos'],
                    properties: [
                        new OA\Property(property: 'nama_customer', type: 'string', example: 'Toko Jaya Abadi'),
                        new OA\Property(property: 'alamat', type: 'string', example: 'Jl. Merdeka No. 10'),
                        new OA\Property(property: 'nomor_telepon', type: 'string', example: '081234567890'),
                        new OA\Property(property: 'latitude', type: 'number', format: 'float', example: -6.1751),
                        new OA\Property(property: 'longitude', type: 'number', format: 'float', example: 106.865),
                        new OA\Property(property: 'product_id', type: 'integer', example: 3),
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
            'product_id' => 'required|exists:products,id',
            'photos' => 'required|array|min:1|max:3',
            'photos.*' => 'required|file|mimes:jpeg,jpg,png|max:5120',
        ], [
            'product_id.required' => 'Produk wajib dipilih.',
            'product_id.exists' => 'Produk tidak ditemukan.',
        ]);

        $user = $request->user();

        $customer = Customer::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
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

        if (!$user->is_active) {
            $user->update(['is_active' => true]);
        }

        $customer->load('photos', 'product');

        return response()->json([
            'message' => 'Customer berhasil didaftarkan',
            'data' => $this->formatCustomer($customer),
        ], 201);
    }

    #[OA\Get(
        path: '/api/customers',
        summary: 'Daftar customer milik sales yang login',
        tags: ['Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar customer berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Customer::with(['photos', 'product'])
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
        tags: ['Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Riwayat customer berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function history(Request $request)
    {
        $query = Customer::with(['photos', 'product'])
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
                    'produk' => $customer->product?->nama_produk,
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
        tags: ['Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
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
        $customer = Customer::with(['photos', 'product'])->find($id);

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

    #[OA\Put(
        path: '/api/customers/{id}',
        summary: 'Edit data customer sendiri (Sales)',
        description: 'Sales mengubah data tekstual customer miliknya termasuk pilihan produk. GPS dan foto tidak dapat diubah.',
        tags: ['Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama_customer', type: 'string', example: 'Toko Jaya Abadi Updated'),
                    new OA\Property(property: 'alamat', type: 'string', example: 'Jl. Merdeka No. 10A'),
                    new OA\Property(property: 'nomor_telepon', type: 'string', example: '081234567899'),
                    new OA\Property(property: 'product_id', type: 'integer', example: 5),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Customer berhasil diupdate'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan milik sales yang login'),
            new OA\Response(response: 404, description: 'Customer tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $customer = Customer::find($id);

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

        $request->validate([
            'nama_customer' => 'sometimes|string|max:255',
            'alamat' => 'sometimes|string',
            'nomor_telepon' => 'sometimes|numeric|digits_between:10,15',
            'product_id' => 'sometimes|exists:products,id',
        ], [
            'nama_customer.string' => 'Nama customer harus berupa teks.',
            'nama_customer.max' => 'Nama customer maksimal 255 karakter.',
            'alamat.string' => 'Alamat harus berupa teks.',
            'nomor_telepon.numeric' => 'Nomor telepon harus berupa angka.',
            'nomor_telepon.digits_between' => 'Nomor telepon harus 10-15 digit.',
            'product_id.exists' => 'Produk tidak ditemukan.',
        ]);

        $customer->fill($request->only([
            'nama_customer',
            'alamat',
            'nomor_telepon',
            'product_id',
        ]));

        $customer->save();
        $customer->load('photos', 'product');

        return response()->json([
            'message' => 'Data customer berhasil diupdate',
            'data' => $this->formatCustomer($customer),
        ], 200);
    }

    #[OA\Delete(
        path: '/api/customers/{id}',
        summary: 'Hapus customer (soft delete)',
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
            Storage::disk('public')->delete($photo->photo_path);
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
            'product' => $customer->product ? [
                'id' => $customer->product->id,
                'nama_produk' => $customer->product->nama_produk,
                'harga' => (float) $customer->product->harga,
            ] : null,
            'photos' => $customer->photos->map(fn($p) => [
                'id' => $p->id,
                'photo_url' => $p->photo_url,
            ])->values(),
        ];
    }
}