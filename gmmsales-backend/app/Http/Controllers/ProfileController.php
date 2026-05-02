<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class ProfileController extends Controller
{
    /**
     * US-16: User upload foto profil sendiri.
     * POST /api/profile/photo
     *
     * Endpoint ini bisa diakses semua user (admin & sales).
     */
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

        // Hapus foto lama kalo ada
        if ($user->photo_path && Storage::disk('public')->exists($user->photo_path)) {
            Storage::disk('public')->delete($user->photo_path);
        }

        // Simpan foto baru: storage/app/public/users/{user_id}/profile.{ext}
        $extension = $request->file('photo')->getClientOriginalExtension();
        $path = $request->file('photo')->storeAs(
            "users/{$user->id}",
            "profile.{$extension}",
            'public'
        );

        // Update field photo_path di DB
        $user->update(['photo_path' => $path]);

        return response()->json([
            'message' => 'Foto profil berhasil diupload',
            'data' => [
                'photo_url' => $user->fresh()->photo_url,
            ],
        ], 200);
    }
}