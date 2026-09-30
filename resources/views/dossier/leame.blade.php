EXPEDIENTE DE LA OBRA: {!! $worksite['name'] !!}
Veeduría ciudadana: {!! $organization['name'] !!}
Generado el {!! $generated_at !!} con GovTrace ({!! $organization['url'] !!})

QUÉ TRAE
  01-expediente.pdf            La obra, sus contratos en SECOP II, sus evidencias publicadas y la prueba de cada archivo.
  02-derecho-de-peticion.pdf   Plantilla para pedir información a la entidad contratante (Ley 1755 de 2015).
  03-denuncia-contraloria.pdf  Plantilla para denunciar ante la Contraloría (Ley 1757 de 2015, art. 69).
  evidencias/                  Cada archivo publicado, tal como se selló, con su prueba de inclusión (.prueba.json).

ANTES DE PRESENTAR UNA PLANTILLA
  Complete los espacios en blanco, revise los hechos y fírmela.
  GovTrace no la radica: la presenta la veeduría.

CÓMO COMPROBAR UN ARCHIVO SIN GOVTRACE
  1. Calcule su SHA-256 (por ejemplo: sha256sum archivo.jpg). Debe ser el que aparece abajo y en el expediente.
  2. Con su .prueba.json, el programa independiente recalcula la raíz de Merkle y la busca en la red Stellar:
     {!! $verifier_url !!}
  3. O suéltelo en el validador de la veeduría: {!! $validator_url !!}

ARCHIVOS
@forelse ($evidences as $evidence)
@foreach ($evidence['files'] as $file)
  {!! $file['path'] !!}
    SHA-256: {!! $file['sha256'] !!}
    Transacción en Stellar: {!! $evidence['tx_hash'] !!} (ledger {!! $evidence['ledger'] !!})
@endforeach
@empty
  Aún no hay evidencias publicadas de esta obra.
@endforelse
