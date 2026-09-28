# language: es
@story_id:US-023 @origin:discovery_inicial @priority:2 @epic:EPIC-003
Característica: Recibo de Inmutabilidad para el veedor
  Como Veedor de Campo
  quiero ver el Recibo de Inmutabilidad de mi evidencia confirmada
  para tener la prueba independiente de que mi reporte no puede ser alterado

  Antecedentes:
    Dado que estoy autenticado como el veedor "carlos@correo.co" de "Veeduría Ciudadana Santa Marta"

  @complexity:low
  Escenario: Recibo de una evidencia sellada
    Dado que mi reporte está sellado con la raíz "0x9f2c…a1" en la transacción "0xabc…01" del bloque 61234567 el "2026-09-27 10:15:32"
    Cuando abro el Recibo de Inmutabilidad del reporte
    Entonces veo la raíz de Merkle, la transacción, el bloque y la fecha y hora exacta del bloque
    Y veo el botón "Ver en Polygonscan"

  @complexity:low @negative
  Escenario: La evidencia aún no está sellada
    Dado que mi reporte está "En Cola"
    Cuando abro el Recibo de Inmutabilidad del reporte
    Entonces veo el mensaje "⏳ Su evidencia está en proceso de sellado en la red Polygon. Este proceso toma unos minutos. El recibo criptográfico aparecerá aquí en breve."

  @complexity:medium @edge
  Escenario: Evidencia re-sellada tras una reorganización
    Dado que mi reporte se selló en la transacción "0xabc…01" y fue re-sellado en "0xdef…02" tras una reorganización
    Cuando abro el Recibo de Inmutabilidad del reporte
    Entonces veo solo la transacción "0xdef…02" y su bloque
    Y la transacción "0xabc…01" no se muestra
