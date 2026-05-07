<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    #[OA\Put(
        path: '/api/profile',
        summary: 'Edit profil sendiri',
        description: 'User (admin atau sales) mengubah nomor telepon pribadi.',
        tags: ['Profile'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nomor_telepon'],
                properties: [
                    new OA\Property(property: 'nomor_telepon', type: 'string', example: '081234567890'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil berhasil diupdate'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Format nomor telepon tidak valid'),
        ]
    )]
    public function update(Request $request)
    {
        $request->validate([
            'nomor_telepon' => 'required|numeric|digits_between:10,15',
        ], [
            'nomor_telepon.required' => 'Nomor telepon wajib diisi.',
            'nomor_telepon.numeric' => 'Nomor telepon harus berupa angka.',
            'nomor_telepon.digits_between' => 'Nomor telepon harus 10-15 digit.',
        ]);

        $user = $request->user();
        $user->update([
            'nomor_telepon' => $request->nomor_telepon,
        ]);

        $user->load('wilayah');

        return response()->json([
            'message' => 'Profil berhasil diupdate',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'nomor_telepon' => $user->nomor_telepon,
                'wilayah_id' => $user->wilayah_id,
                'wilayah_nama' => $user->wilayah?->nama,
                'is_active' => $user->is_active,
                'photo_url' => $user->photo_url,
            ],
        ], 200);
    }

    #[OA\Post(
        path: '/api/profile/photo',
        summary: 'Upload foto profil sendiri',
        description: 'User (admin atau sales) mengupload foto profil pribadi. Foto lama akan dihapus jika ada.',
        tags: ['Profile'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['photo'],
                    properties: [
                        new OA\Property(
                            property: 'photo',
                            type: 'string',
                            format: 'binary',
                            description: 'File image (jpeg, jpg, png), max 5 MB'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Foto profil berhasil diupload'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'File tidak valid'),
        ]
    )]
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|file|mimes:jpeg,jpg,png|max:5120',
        ], [
            'photo.required' => 'Foto profil wajib diunggah.',
            'photo.file' => 'Upload harus berupa file.',
            'photo.mimes' => 'Foto harus berformat jpeg, jpg, atau png.',
            'photo.max' => 'Ukuran foto maksimal 5 MB.',
        ]);

        $user = $request->user();

        if ($user->photo_path && Storage::disk('public')->exists($user->photo_path)) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $extension = $request->file('photo')->getClientOriginalExtension();
        $path = $request->file('photo')->storeAs(
            "users/{$user->id}",
            "profile.{$extension}",
            'public'
        );

        $user->update(['photo_path' => $path]);

        return response()->json([
            'message' => 'Foto profil berhasil diupload',
            'data' => [
                'photo_url' => $user->fresh()->photo_url,
            ],
        ], 200);
    }
}