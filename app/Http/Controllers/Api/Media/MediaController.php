<?php

namespace App\Http\Controllers\Api\Media;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class MediaController extends Controller
{
    /**
     * Upload media file (image, audio, etc.)
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'type' => 'required|string|in:profile,message,audio',
            'match_id' => 'required_if:type,message,audio|nullable|exists:user_matches,id',
        ]);

        try {
            $file = $request->file('file');
            $type = $request->input('type');
            $user = Auth::user();

            // Generate a unique filename
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

            // Determine storage path based on type
            $path = "user/{$user->id}/{$type}";

            // Process file based on type
            switch ($type) {
                case 'profile':
                    // For profile photos, resize and optimize
                    $image = Image::make($file)
                        ->fit(1024, 1024, function ($constraint) {
                            $constraint->upsize();
                        })
                        ->encode('jpg', 80);

                    // Store the processed image
                    Storage::disk('public')->put("{$path}/{$filename}", $image);

                    // Create thumbnails
                    $thumbnail = Image::make($file)
                        ->fit(256, 256)
                        ->encode('jpg', 80);
                    Storage::disk('public')->put("{$path}/thumbnail_{$filename}", $thumbnail);

                    break;

                case 'message':
                    // For message images, resize to reasonable size
                    $image = Image::make($file)
                        ->resize(1200, null, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        })
                        ->encode('jpg', 85);

                    Storage::disk('public')->put("{$path}/{$filename}", $image);
                    break;

                case 'audio':
                    // For audio, store as-is
                    Storage::disk('public')->putFileAs($path, $file, $filename);
                    break;
            }

            // Get the public URL
            $url = Storage::disk('public')->url("{$path}/{$filename}");

            // Get additional metadata
            $meta = [];
            if (in_array($type, ['profile', 'message'])) {
                $imageSize = getimagesize($file);
                $meta = [
                    'width' => $imageSize[0],
                    'height' => $imageSize[1],
                    'mime' => $imageSize['mime'],
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id' => pathinfo($filename, PATHINFO_FILENAME),
                    'url' => $url,
                    'type' => $type,
                    'filename' => $filename,
                    'original_name' => $file->getClientOriginalName(),
                    'meta' => $meta,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload media file',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete uploaded media
     */
    public function delete(string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Find files with this ID (might be multiple variations)
            $files = Storage::disk('public')->files("user/{$user->id}");
            $deleted = false;

            foreach ($files as $file) {
                if (Str::contains($file, $id)) {
                    Storage::disk('public')->delete($file);
                    $deleted = true;
                }
            }

            if (!$deleted) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'File deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete media file',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
