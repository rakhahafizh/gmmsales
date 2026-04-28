<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class AdminCustomerController extends Controller
{
    /**
     * US-10: Admin melihat semua customer dari seluruh sales.
     * GET /api/admin/customers?start_date=&end_date=&sales_id=&page=
     */
    public function index(Request $request)
    {
        $query = Customer::with(['photos', 'user'])
            ->latest('visited_at');

        // Filter berdasarkan rentang tanggal kunjungan
        if ($request->filled('start_date')) {
            $query->whereDate('visited_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('visited_at', '<=', $request->end_date);
        }

        // Filter berdasarkan sales tertentu
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

    /**
     * US-11: Admin melihat detail lengkap satu customer.
     * GET /api/admin/customers/{id}
     */
    public function show(Request $request, string $id)
    {
        $customer = Customer::with(['photos', 'user'])->find($id);

        // 404 jika customer tidak ditemukan
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

    /**
     * Helper: format data customer untuk tampilan list (lebih ringkas).
     */
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
            'sales' => [
                'id' => $customer->user?->id,
                'name' => $customer->user?->name,
                'username' => $customer->user?->username,
            ],
            'foto_utama' => $customer->photos->first()?->photo_url,
            'total_foto' => $customer->photos->count(),
        ];
    }

    /**
     * Helper: format data customer untuk tampilan detail (lengkap).
     */
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
            'sales' => [
                'id' => $customer->user?->id,
                'name' => $customer->user?->name,
                'username' => $customer->user?->username,
            ],
            'photos' => $customer->photos->map(fn($p) => [
                'id' => $p->id,
                'photo_url' => $p->photo_url,
            ])->values(),
        ];
    }
}