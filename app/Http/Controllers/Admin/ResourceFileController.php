<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ResourceFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResourceFileController extends Controller {

    public function download(ResourceFile $file): StreamedResponse {
        if (!$file->exists()) {
            abort(404, 'File not found');
        }

        if (!$file->resource->canUserAccess(auth()->user())) {
            abort(403, 'Access denied');
        }

        // Log download
        $file->resource->incrementDownloads(auth()->user());

        // Log activity
        activity()
                ->performedOn($file->resource)
                ->causedBy(auth()->user())
                ->withProperties(['file_name' => $file->original_name])
                ->log("Downloaded file: {$file->original_name}");

        return Storage::disk('resources')->download(
                        $file->file_path,
                        $file->original_name
                );
    }

    public function preview(ResourceFile $file): Response {
        if (!$file->exists()) {
            abort(404, 'File not found');
        }

        if (!$file->isPreviewable()) {
            abort(400, 'File cannot be previewed');
        }

        if (!$file->resource->canUserAccess(auth()->user())) {
            abort(403, 'Access denied');
        }

        // Log view
        $file->resource->incrementViews(auth()->user());

        $content = Storage::disk('resources')->get($file->file_path);

        return response($content)
                        ->header('Content-Type', $file->file_type)
                        ->header('Content-Disposition', 'inline; filename="' . $file->original_name . '"');
    }
}
