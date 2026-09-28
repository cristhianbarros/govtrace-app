# language: es
@story_id:US-025 @origin:discovery_inicial @priority:2 @epic:EPIC-005
Característica: Recibo de Inmutabilidad público
  Como Verificador Público
  quiero ver el Recibo de Inmutabilidad junto a cada evidencia publicada
  para auditar el registro en un explorador de bloques independiente

  @complexity:low
  Escenario: Recibo público de una evidencia publicada
    Dado que soy un visitante sin sesión en el mapa público de "veeduria-smr"
    Y una evidencia publicada está sellada en la transacción "0xabc…01" del bloque 61234567
    Cuando abro su Recibo de Inmutabilidad
    Entonces veo la raíz de Merkle, la transacción, el bloque y la fecha y hora exacta del bloque
    Y veo el botón "Ver en Polygonscan"

  @complexity:medium @edge
  Escenario: Evidencia re-sellada tras una reorganización
    Dado que una evidencia publicada fue re-sellada en "0xdef…02" tras una reorganización
    Cuando abro su Recibo de Inmutabilidad
    Entonces veo solo la transacción "0xdef…02"
