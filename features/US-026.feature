# language: es
@story_id:US-026 @origin:discovery_inicial @priority:2 @epic:EPIC-005
Característica: Descargar el archivo sellado con su prueba de inclusión
  Como Verificador Público
  quiero descargar exactamente el archivo que se selló
  para someterlo a mi propio peritaje o usarlo como prueba legal

  Antecedentes:
    Dado que soy un visitante sin sesión en el mapa público de "veeduria-smr"

  @complexity:medium
  Escenario: Descarga del archivo exacto y su prueba
    Dado que una evidencia publicada tiene la foto "obra-gaira.jpg"
    Cuando pulso "Descargar archivo original" en su tarjeta de la línea de tiempo
    Entonces descargo el mismo binario que se selló, sin recompresión ni marcas de agua
    Y el archivo no tiene metadatos EXIF
    Y descargo también su prueba de inclusión con las hojas, el camino de Merkle, la raíz y la transacción

  @complexity:low @negative
  Escenario: Una evidencia retirada no se puede descargar
    Dado que una evidencia fue retirada por la organización
    Cuando abro su tarjeta en la línea de tiempo
    Entonces no hay botón de descarga

  @complexity:low @negative
  Escenario: Una evidencia no publicada no se puede descargar
    Dado que una evidencia está oculta
    Cuando intento descargar su archivo por su dirección
    Entonces la descarga es rechazada
