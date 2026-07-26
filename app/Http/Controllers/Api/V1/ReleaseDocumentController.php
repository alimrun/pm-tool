<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ReleaseDocumentRequest;
use App\Http\Resources\V1\ReleaseDocumentResource;
use App\Models\Release;
use App\Models\ReleaseDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Release attachments.
 *
 * Files live on the private `local` disk and are only ever served through
 * `download()`, which runs behind the API's authentication. There is no public
 * URL to leak, so losing a document reference does not lose the document.
 */
class ReleaseDocumentController extends ApiController
{
    public function index(Release $release): JsonResponse
    {
        return $this->ok(
            ReleaseDocumentResource::collection($release->documents()->with('uploader')->get())
        );
    }

    /** Upload authorization (contributors, not viewers) lives in the request. */
    public function store(ReleaseDocumentRequest $request, Release $release): JsonResponse
    {
        $file = $request->file('document');
        $path = $file->store("releases/{$release->id}", 'local');

        $document = $release->documents()->create([
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return $this->created(
            new ReleaseDocumentResource($document->load('uploader')),
            'Document uploaded.'
        );
    }

    /** Streams the file itself — the one endpoint that does not return JSON. */
    public function download(Release $release, ReleaseDocument $document): StreamedResponse
    {
        abort_unless($document->release_id === $release->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Release $release, ReleaseDocument $document): JsonResponse
    {
        abort_unless($document->release_id === $release->id, 404);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return $this->message('Document deleted.');
    }
}
