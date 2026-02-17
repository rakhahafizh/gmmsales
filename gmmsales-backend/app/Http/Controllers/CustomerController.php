<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    /**
     * US-4: Mendaftarkan customer baru beserta foto & GPS.
     * POST /api/customers
     */
    public function store(Request $request)
    {
        // Validasi input
        $request->validate([
            'nama_customer'  => 'required|string|max:255',
            'alamat'         => 'required|string',
            'nomor_telepon'  => 'required|numeric|digits_between:10,15',
            'latitude'       => 'required|numeric|between:-90,90',
            'longitude'      => 'required|numeric|between:-180,180',
            'photos'         => 'required|array|min:1|max:3',
            'photos.*'       => 'required|file|mimes:jpeg,jpg,png|max:5120', // max 5 MB per file
        ]);

        // Simpan data customer
        $customer = Customer::create([
            'user_id'       => $request->user()->id,
            'nama_customer' => $request->nama_customer,
            'alamat'        => $request->alamat,
            'nomor_telepon' => $request->nomor_telepon,
            'latitude'      => $request->latitude,
            'longitude'     => $request->longitude,
            'visited_at'    => now(), // waktu kunjungan = waktu request dikirim
        ]);

        // Upload foto (1-3 file) ke storage
        foreach ($request->file('photos') as $file) {
            $path = $file->store("customers/{$customer->id}", 'public');
            CustomerPhoto::create([
                'customer_id' => $customer->id,
                'photo_path'  => $path,
            ]);
        }

        // Load relasi foto untuk response
        $customer->load('photos');

        return response()->json([
            'message' => 'Customer berhasil didaftarkan',
            'data'    => $this->formatCustomer($customer),
        ], 201);
    }

    /**
     * US-5: Melihat daftar customer milik sales yang login.
     * GET /api/customers?search=...&page=...
     */
    public function index(Request $request)
    {
        $query = Customer::with('photos')
            ->where('user_id', $request->user()->id);

        // Filter pencarian berdasarkan nama_customer
        if ($request->filled('search')) {
            $query->where('nama_customer', 'like', '%' . $request->search . '%');
        }

        $customers = $query->latest()->paginate(10);

        return response()->json([
            'message' => 'Daftar customer berhasil diambil',
            'data'    => $customers->map(fn($c) => $this->formatCustomer($c)),
            'meta'    => [
                'current_page' => $customers->currentPage(),
                'per_page'     => $customers->perPage(),
                'total'        => $customers->total(),
                'last_page'    => $customers->lastPage(),
            ],
        ], 200);
    }

    /**
     * US-6: Melihat detail satu customer (hanya milik sales yang login).
     * GET /api/customers/{id}
     */
    public function show(Request $request, string $id)
    {
        $customer = Customer::with('photos')->find($id);

        // 404 jika customer tidak ditemukan
        if (!$customer) {
            return response()->json([
                'message' => 'Customer tidak ditemukan',
            ], 404);
        }

        // 403 jika customer bukan milik sales yang sedang login
        if ($customer->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke data customer ini',
            ], 403);
        }

        return response()->json([
            'message' => 'Detail customer berhasil diambil',
            'data'    => $this->formatCustomer($customer),
        ], 200);
    }

    /**
     * Helper: format data customer menjadi struktur JSON yang konsisten.
     */
    private function formatCustomer(Customer $customer): array
    {
        return [
            'id'             => $customer->id,
            'nama_customer'  => $customer->nama_customer,
            'alamat'         => $customer->alamat,
            'nomor_telepon'  => $customer->nomor_telepon,
            'latitude'       => $customer->latitude,
            'longitude'      => $customer->longitude,
            'visited_at'     => $customer->visited_at?->toDateTimeString(),
            'created_at'     => $customer->created_at->toDateTimeString(),
            'photos'         => $customer->photos->map(fn($p) => [
                'id'        => $p->id,
                'photo_url' => $p->photo_url,
            ])->values(),
        ];
    }
}
