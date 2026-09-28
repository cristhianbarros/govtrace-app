# language: es
@story_id:US-032 @origin:discovery_inicial @priority:1 @epic:EPIC-001
Característica: Solo contratos de tipo Obra
  Como Sistema
  quiero traer solo los contratos de tipo "Obra"
  para descartar contratos irrelevantes

  @complexity:low @negative
  Esquema del escenario: Filtro por el tipo de contrato oficial de SECOP II
    Dado que SECOP II entrega un contrato con tipo de contrato "<tipo>"
    Cuando se ejecuta la sincronización
    Entonces el contrato "<resultado>"

    Ejemplos:
      | tipo                     | resultado    |
      | Obra                     | se conserva  |
      | Consultoría              | se descarta  |
      | Prestación de servicios  | se descarta  |
      | Suministros              | se descarta  |
      | Compraventa              | se descarta  |
