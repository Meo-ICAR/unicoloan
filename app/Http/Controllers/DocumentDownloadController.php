<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentDownloadController extends Controller
{
    /**
     * Scarica il file allegato a un Documento.
     *
     * La rotta e' protetta dal middleware 'auth': in precedenza il download era
     * pubblico e permetteva di enumerare gli allegati (anche di reclami e SOS)
     * conoscendone l'id.
     */
    public function __invoke(Document $document): BinaryFileResponse
    {
        abort_unless(\checkPiano('documents'), 403);

        $media = $document->getFirstMedia('documents');

        abort_if($media === null, 404);

        return response()->download($media->getPath(), $media->file_name);
    }
}
