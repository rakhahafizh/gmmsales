<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AdminDashboardController extends Controller
{
    #[OA\Get(
        path: '/api/admin/dashboard',
        summary: 'Dashboard statistik tim sales (Admin)',
        description: 'Mengembalikan statistik jumlah sales yang sudah submit hari ini, total sales aktif, dan 3 kunjungan terbaru hari ini.',
        tags: ['Admin - Dashboard'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
        ]
    )]
    public function index(Request $request)
    {
        $totalSales = User::where('role', 'sales')
            ->whereNull('deleted_at')
            ->count();

        $salesSudahSubmitHariIni = User::where('role', 'sales')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->count();

        $recentVisits = Customer::with(['user', 'product'])
            ->whereDate('visited_at', today())
            ->latest('visited_at')
            ->limit(3)
            ->get();

        return response()->json([
            'message' => 'Dashboard admin berhasil diambil',
            'data' => [
                'sales_submit_hari_ini' => $salesSudahSubmitHariIni,
                'total_sales_aktif' => $totalSales,
                'recent_visits' => $recentVisits->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'nama_customer' => $customer->nama_customer,
                        'alamat' => $customer->alamat,
                        'produk' => $customer->product?->nama_produk,
                        'sales' => [
                            'id' => $customer->user?->id,
                            'name' => $customer->user?->name,
                        ],
                        'visited_at' => $customer->visited_at?->toDateTimeString(),
                    ];
                }),
            ],
        ], 200);
    }
}