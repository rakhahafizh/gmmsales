<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class AdminCustomerController extends Controller
{
    #[OA\Get(
        path: '/api/admin/customers',
        summary: 'Daftar semua customer dari seluruh sales (Admin)',
        description: 'Admin melihat list semua customer beserta info sales yang mendaftarkan. Mendukung filter tanggal, sales_id, dan pencarian berdasarkan nama customer.',
        tags: ['Admin - Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Cari berdasarkan nama customer', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'start_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'sales_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar customer berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Customer::with([
            'photos',
            'user' => function ($q) {
                $q->withTrashed()->with('wilayah');
            }
        ])->latest('visited_at');

        if ($request->filled('search')) {
            $query->where('nama_customer', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('start_date')) {
            $query->whereDate('visited_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('visited_at', '<=', $request->end_date);
        }

        if ($request->filled('sales_id')) {
            $query->where('user_id', $request->sales_id);
        }

        $customers = $query->paginate(10);

        return response()->json([
            'message' => 'Daftar customer berhasil diambil',
            'data' => $customers->map(fn($c) => $this->formatCustomerList($c)),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
            ],
        ], 200);
    }

    #[OA\Get(
        path: '/api/admin/customers/{id}',
        summary: 'Detail customer (Admin)',
        tags: ['Admin - Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Detail customer berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
            new OA\Response(response: 404, description: 'Customer tidak ditemukan'),
        ]
    )]
    public function show(Request $request, string $id)
    {
        $customer = Customer::with([
            'photos',
            'user' => function ($q) {
                $q->withTrashed()->with('wilayah');
            }
        ])->find($id);

        if (!$customer) {
            return response()->json([
                'message' => 'Customer tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'Detail customer berhasil diambil',
            'data' => $this->formatCustomerDetail($customer),
        ], 200);
    }

    #[OA\Put(
        path: '/api/admin/customers/{id}',
        summary: 'Edit data customer milik sales manapun (Admin)',
        description: 'Admin mengubah data customer secara penuh termasuk koordinat GPS dan foto. Jika field photos dikirim, foto lama akan diganti dengan yang baru (replace all).',
        tags: ['Admin - Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'nama_customer', type: 'string'),
                        new OA\Property(property: 'alamat', type: 'string'),
                        new OA\Property(property: 'nomor_telepon', type: 'string'),
                        new OA\Property(property: 'latitude', type: 'number', format: 'float'),
                        new OA\Property(property: 'longitude', type: 'number', format: 'float'),
                        new OA\Property(
                            property: 'photos[]',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                            description: 'Opsional. Jika dikirim, foto lama dihapus dan diganti dengan yang baru'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Customer berhasil diupdate'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
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

        $request->validate([
            'nama_customer' => 'sometimes|string|max:255',
            'alamat' => 'sometimes|string',
            'nomor_telepon' => 'sometimes|numeric|digits_between:10,15',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'photos' => 'sometimes|array|min:1|max:3',
            'photos.*' => 'file|mimes:jpeg,jpg,png|max:5120',
        ], [
            'nama_customer.max' => 'Nama customer maksimal 255 karakter.',
            'nomor_telepon.numeric' => 'Nomor telepon harus berupa angka.',
            'nomor_telepon.digits_between' => 'Nomor telepon harus 10-15 digit.',
            'latitude.between' => 'Latitude harus di antara -90 dan 90.',
            'longitude.between' => 'Longitude harus di antara -180 dan 180.',
            'photos.max' => 'Maksimal 3 foto.',
            'photos.*.mimes' => 'Foto harus berformat jpeg, jpg, atau png.',
            'photos.*.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        $customer->fill($request->only([
            'nama_customer',
            'alamat',
            'nomor_telepon',
            'latitude',
            'longitude',
        ]));
        $customer->save();

        if ($request->hasFile('photos')) {
            foreach ($customer->photos as $oldPhoto) {
                Storage::disk('public')->delete($oldPhoto->photo_path);
                $oldPhoto->delete();
            }

            foreach ($request->file('photos') as $file) {
                $path = $file->store("customers/{$customer->id}", 'public');
                CustomerPhoto::create([
                    'customer_id' => $customer->id,
                    'photo_path' => $path,
                ]);
            }
        }

        $customer->load('photos', 'user.wilayah');

        return response()->json([
            'message' => 'Data customer berhasil diupdate',
            'data' => $this->formatCustomerDetail($customer),
        ], 200);
    }

    #[OA\Delete(
        path: '/api/admin/customers/{id}',
        summary: 'Hapus customer (Admin) - soft delete',
        description: 'Admin menghapus customer secara soft delete. Seluruh foto akan dihapus dari storage.',
        tags: ['Admin - Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Customer berhasil dihapus'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
            new OA\Response(response: 404, description: 'Customer tidak ditemukan'),
        ]
    )]
    public function destroy(string $id)
    {
        $customer = Customer::with('photos')->find($id);

        if (!$customer) {
            return response()->json([
                'message' => 'Customer tidak ditemukan',
            ], 404);
        }

        foreach ($customer->photos as $photo) {
            Storage::disk('public')->delete($photo->photo_path);
            $photo->delete();
        }

        $customer->delete();

        return response()->json([
            'message' => 'Customer berhasil dihapus',
        ], 200);
    }

    private function formatCustomerList(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'nama_customer' => $customer->nama_customer,
            'alamat' => $customer->alamat,
            'nomor_telepon' => $customer->nomor_telepon,
            'latitude' => $customer->latitude,
            'longitude' => $customer->longitude,
            'visited_at' => $customer->visited_at?->toDateTimeString(),
            'sales' => $this->formatSales($customer->user),
            'foto_utama' => $customer->photos->first()?->photo_url,
            'total_foto' => $customer->photos->count(),
        ];
    }

    private function formatCustomerDetail(Customer $customer): array
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
            'sales' => $this->formatSales($customer->user),
            'photos' => $customer->photos->map(fn($p) => [
                'id' => $p->id,
                'photo_url' => $p->photo_url,
            ])->values(),
        ];
    }

    private function formatSales($user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'wilayah' => $user->wilayah?->nama,
            'is_deleted' => $user->trashed(),
        ];
    }
}