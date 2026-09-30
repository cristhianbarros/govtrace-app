<?php

namespace App\Application\Dossier;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * US-056-LEG: el ZIP del expediente — los tres PDF, el LEAME y, en
 * evidencias/, cada archivo publicado tal como se selló, byte a byte, con su
 * prueba de inclusión: la misma de la descarga pública (US-026), con la que
 * cualquiera lo comprueba sin GovTrace.
 */
class DossierArchive
{
    public function __construct(private readonly DossierDocuments $documents) {}

    /**
     * @param  array<string, mixed>  $dossier  from WorksiteDossier
     * @return array{path: string, files: int} a temporary file the caller deletes, and how many evidence files it carries
     */
    public function build(array $dossier): array
    {
        $path = tempnam(sys_get_temp_dir(), 'expediente');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo del expediente.');
        }

        foreach (DossierDocuments::FILES as $document => $name) {
            $zip->addFromString($name, $this->documents->pdf($document, $dossier));
        }
        $zip->addFromString('LEAME.txt', $this->documents->readme($dossier));

        // Cada original pasa por un archivo temporal: el ZIP los lee al cerrarse, sin tenerlos todos en memoria.
        $copies = [];
        foreach ($dossier['evidences'] as $evidence) {
            foreach ($evidence['files'] as $file) {
                $copies[] = $copy = tempnam(sys_get_temp_dir(), 'evidencia');
                file_put_contents($copy, Storage::disk('evidencias')->readStream($file['storage_path']));
                $zip->addFile($copy, $file['path']);
                $zip->addFromString($file['proof_path'], json_encode($file['proof'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        $zip->close();
        array_map(unlink(...), $copies);

        return ['path' => $path, 'files' => count($copies)];
    }
}
