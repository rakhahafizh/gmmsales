<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    private const TARGET_HARIAN = 5;

    #[OA\Get(
        path: '/api/dashboard',
        summary: 'Dashboard progress harian (Sales)',
        description: 'Mengembalikan jumlah kunjungan sales hari ini, target harian, dan 3 kunjungan terbaru miliknya.',
        tags: ['Dashboard'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $kunjunganHariIni = Customer::where('user_id', $userId)
            ->whereDate('visited_at', today())
            ->count();

        $recentVisits = Customer::with('product')
            ->where('user_id', $userId)
            ->latest('visited_at')
            ->limit(3)
            ->get();

        return response()->json([
            'message' => 'Dashboard berhasil diambil',
            'data' => [
                'kunjungan_hari_ini' => $kunjunganHariIni,
                'target_harian' => self::TARGET_HARIAN,
                'recent_visits' => $recentVisits->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'nama_customer' => $customer->nama_customer,
                        'alamat' => $customer->alamat,
                        'produk' => $customer->product?->nama_produk,
                        'visited_at' => $customer->visited_at?->toDateTimeString(),
                    ];
                }),
            ],
        ], 200);
    }
}