# language: es
@story_id:US-025 @origin:discovery_inicial @priority:2 @epic:EPIC-005
Característica: Recibo de Inmutabilidad público
  Como Verificador Público
  quiero ver el Recibo de Inmutabilidad junto a cada evidencia publicada
  para auditar el registro en un explorador de bloques independiente

  @complexity:low
  Escenario: Recibo público de una evidencia publicada
    Dado que soy un visitante sin sesión en el mapa público de "veeduria-smr"
    Y una evidencia publicada está sellada en la transacción "abc…01" del ledger 61234567
    Cuando abro su Recibo de Inmutabilidad
    Entonces veo la raíz de Merkle, la transacción, el ledger y la fecha y hora exacta del ledger
    Y veo el botón "Ver en Stellar Expert"

  @complexity:medium @edge
  Escenario: Evidencia reenviada tras una transacción que no se incluyó
    Dado que una evidencia publicada se reenvió en "def…02" porque su primera transacción no entró en ningún ledger
    Cuando abro su Recibo de Inmutabilidad
    Entonces veo solo la transacción "def…02"
