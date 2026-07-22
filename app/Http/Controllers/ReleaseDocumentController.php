<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseDocumentRequest;
use App\Models\Release;
use App\Models\ReleaseDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReleaseDocumentController extends Controller
{
    public function store(ReleaseDocumentRequest $request, Release $release): RedirectResponse
    {
        $file = $request->file('document');
        $path = $file->store("releases/{$release->id}", 'local');

        $release->documents()->create([
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Release $release, ReleaseDocument $document): StreamedResponse
    {
        abort_unless($document->release_id === $release->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Release $release, ReleaseDocument $document): RedirectResponse
    {
        abort_unless($document->release_id === $release->id, 404);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }
}
