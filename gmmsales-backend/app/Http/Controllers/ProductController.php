<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/api/products',
        summary: 'Daftar semua produk',
        description: 'Mengambil seluruh daftar produk yang tersedia. Dapat diakses oleh semua user yang sudah login untuk keperluan dropdown saat mendaftarkan customer.',
        tags: ['Product'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', required: false, description: 'Cari produk berdasarkan nama', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Daftar produk berhasil diambil'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request)
    {
        $query = Product::query()->orderBy('nama_produk');

        if ($request->filled('search')) {
            $query->where('nama_produk', 'like', '%' . $request->search . '%');
        }

        $products = $query->get();

        return response()->json([
            'message' => 'Daftar produk berhasil diambil',
            'data' => $products->map(fn($p) => $this->formatProduct($p)),
        ], 200);
    }

    #[OA\Post(
        path: '/api/admin/products',
        summary: 'Tambah produk baru (Admin)',
        tags: ['Admin - Product'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nama_produk', 'harga'],
                properties: [
                    new OA\Property(property: 'nama_produk', type: 'string', example: '50 Mbps Internet'),
                    new OA\Property(property: 'harga', type: 'number', format: 'float', example: 270000),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Produk berhasil ditambahkan'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request)
    {
        $request->validate([
            'nama_produk' => 'required|string|max:255',
            'harga' => 'required|numeric|min:0',
        ], [
            'nama_produk.required' => 'Nama produk wajib diisi.',
            'nama_produk.max' => 'Nama produk maksimal 255 karakter.',
            'harga.required' => 'Harga wajib diisi.',
            'harga.numeric' => 'Harga harus berupa angka.',
            'harga.min' => 'Harga tidak boleh negatif.',
        ]);

        $product = Product::create([
            'nama_produk' => $request->nama_produk,
            'harga' => $request->harga,
        ]);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan',
            'data' => $this->formatProduct($product),
        ], 201);
    }

    #[OA\Put(
        path: '/api/admin/products/{id}',
        summary: 'Edit produk (Admin)',
        tags: ['Admin - Product'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'nama_produk', type: 'string'),
                    new OA\Property(property: 'harga', type: 'number', format: 'float'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Produk berhasil diupdate'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
            new OA\Response(response: 404, description: 'Produk tidak ditemukan'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $request->validate([
            'nama_produk' => 'sometimes|string|max:255',
            'harga' => 'sometimes|numeric|min:0',
        ], [
            'nama_produk.max' => 'Nama produk maksimal 255 karakter.',
            'harga.numeric' => 'Harga harus berupa angka.',
            'harga.min' => 'Harga tidak boleh negatif.',
        ]);

        $product->update($request->only(['nama_produk', 'harga']));

        return response()->json([
            'message' => 'Produk berhasil diupdate',
            'data' => $this->formatProduct($product),
        ], 200);
    }

    #[OA\Delete(
        path: '/api/admin/products/{id}',
        summary: 'Hapus produk (Admin)',
        tags: ['Admin - Product'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Produk berhasil dihapus'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Bukan role admin'),
            new OA\Response(response: 404, description: 'Produk tidak ditemukan'),
        ]
    )]
    public function destroy(string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'message' => 'Produk tidak ditemukan',
            ], 404);
        }

        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus',
        ], 200);
    }

    private function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'nama_produk' => $product->nama_produk,
            'harga' => (float) $product->harga,
            'created_at' => $product->created_at?->toDateTimeString(),
            'updated_at' => $product->updated_at?->toDateTimeString(),
        ];
    }
}