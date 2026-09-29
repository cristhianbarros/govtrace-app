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
    Dado que mi reporte está sellado con la raíz "9f2c…a1" en la transacción "abc…01" del ledger 61234567 el "2026-09-27 10:15:32"
    Cuando abro el Recibo de Inmutabilidad del reporte
    Entonces veo la raíz de Merkle, la transacción, el ledger y la fecha y hora exacta del ledger
    Y veo el botón "Ver en Stellar Expert"

  @complexity:low @negative
  Escenario: La evidencia aún no está sellada
    Dado que mi reporte está "En Cola"
    Cuando abro el Recibo de Inmutabilidad del reporte
    Entonces veo el mensaje "⏳ Su evidencia está en proceso de sellado en la red Stellar. Este proceso toma unos minutos. El recibo criptográfico aparecerá aquí en breve."

  @complexity:medium @edge
  Escenario: Evidencia reenviada tras una transacción que no se incluyó
    Dado que la transacción "abc…01" de mi reporte no entró en ningún ledger y el sellado se reenvió en "def…02"
    Cuando abro el Recibo de Inmutabilidad del reporte
    Entonces veo solo la transacción "def…02" y su ledger
    Y la transacción "abc…01" no se muestra
