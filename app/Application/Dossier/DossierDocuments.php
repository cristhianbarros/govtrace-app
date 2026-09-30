<?php

namespace App\Application\Dossier;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * US-056-LEG: los documentos del expediente — el expediente mismo y las dos
 * plantillas pre-llenadas, el derecho de petición a la entidad contratante
 * (Ley 1755 de 2015) y la denuncia ante la Contraloría (Ley 1757 de 2015,
 * art. 69) —, en PDF. GovTrace no los radica: la veeduría los completa, los
 * firma y los presenta.
 */
class DossierDocuments
{
    /** The document and its name in the ZIP. */
    public const FILES = [
        'expediente' => '01-expediente.pdf',
        'peticion' => '02-derecho-de-peticion.pdf',
        'denuncia' => '03-denuncia-contraloria.pdf',
    ];

    /** @param  array<string, mixed>  $dossier  from WorksiteDossier */
    public function html(string $document, array $dossier): string
    {
        return view("dossier.{$document}", $dossier)->render();
    }

    /** @param  array<string, mixed>  $dossier */
    public function pdf(string $document, array $dossier): string
    {
        return Pdf::loadHTML($this->html($document, $dossier))->setPaper('letter')->output();
    }

    /** @param  array<string, mixed>  $dossier */
    public function readme(array $dossier): string
    {
        return view('dossier.leame', $dossier)->render();
    }
}
