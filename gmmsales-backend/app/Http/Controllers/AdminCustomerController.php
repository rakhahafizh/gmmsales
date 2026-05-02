<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminCustomerController extends Controller
{
    #[OA\Get(
        path: '/api/admin/customers',
        summary: 'Daftar semua customer dari seluruh sales (Admin)',
        description: 'Admin melihat list semua customer beserta info sales yang mendaftarkan. Mendukung filter tanggal dan sales_id.',
        tags: ['Admin - Customer'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: false, description: 'Filter tanggal mulai (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-04-01')),
            new OA\Parameter(name: 'end_date', in: 'query', required: false, description: 'Filter tanggal akhir (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-04-30')),
            new OA\Parameter(name: 'sales_id', in: 'query', required: false, description: 'Filter berdasarkan ID sales tertentu', schema: new OA\Schema(type: 'integer', example: 3)),
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Halaman pagination', schema: new OA\Schema(type: 'integer', default: 1)),
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
                // Include sales yang udah soft-deleted biar audit trail tetep utuh
                $q->withTrashed()->with('wilayah');
            }
        ])->latest('visited_at');

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
        description: 'Admin melihat detail lengkap satu customer beserta info sales (termasuk yang sudah dihapus, ditandai dengan is_deleted=true) dan seluruh foto kunjungan.',
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

    /**
     * Helper: format info sales termasuk wilayah & flag is_deleted.
     */
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