# language: es
@story_id:US-033 @origin:discovery_inicial @priority:1 @epic:EPIC-001
Característica: Sincronización incremental sin duplicados
  Como Sistema
  quiero sincronizar solo los contratos nuevos o modificados
  para no duplicar registros ni saturar la base de datos

  @complexity:low
  Escenario: Un contrato nuevo se inserta
    Dado que no existe ningún contrato con id_contrato "CO1.PCCNTR.1234567"
    Cuando la sincronización recibe el contrato "CO1.PCCNTR.1234567"
    Entonces existe exactamente 1 contrato con id_contrato "CO1.PCCNTR.1234567"

  @complexity:low
  Escenario: Un contrato modificado se actualiza sin duplicarse
    Dado que existe el contrato "CO1.PCCNTR.1234567" con valor 1.000.000.000 COP
    Cuando la sincronización recibe el contrato "CO1.PCCNTR.1234567" con valor 1.200.000.000 COP
    Entonces existe exactamente 1 contrato con id_contrato "CO1.PCCNTR.1234567"
    Y su valor es 1.200.000.000 COP

  @complexity:low @negative
  Escenario: La base de datos impide duplicar el identificador
    Cuando se intenta guardar dos veces el contrato "CO1.PCCNTR.1234567" como registros distintos
    Entonces la restricción de unicidad impide el segundo registro

  @complexity:medium @edge
  Esquema del escenario: Un contrato anulado en SECOP nunca se borra
    Dado que existe el contrato "CO1.PCCNTR.1234567" <situacion>
    Cuando SECOP II lo reporta como "Anulado"
    Entonces su estado interno pasa a "cancelled"
    Y el contrato sigue existiendo en la base de datos

    Ejemplos:
      | situacion                  |
      | con 3 evidencias selladas  |
      | sin evidencias ni sellos   |
