# language: es
@story_id:US-004 @origin:discovery_inicial @priority:3 @epic:EPIC-009
Característica: Reporte de consumo de gas por organización
  Como Super Administrador
  quiero visualizar el consumo de transacciones y gas en Polygon agrupado por organización
  para controlar la rentabilidad y los costos de infraestructura del SaaS

  Antecedentes:
    Dado que estoy autenticado como Super Administrador en el panel global
    Y en septiembre de 2026 la organización "Veeduría Ciudadana Santa Marta" selló 40 evidencias con un gas total de 2.4 POL

  @complexity:medium
  Escenario: Reporte mensual por organización con costo en USD
    Dado que el API de precios informa que 1 POL equivale a 0.50 USD
    Cuando abro el reporte de costos
    Entonces veo una fila de septiembre de 2026 para "Veeduría Ciudadana Santa Marta"
    Y la fila muestra 40 evidencias selladas, 2.4 POL de gas total y un costo estimado de 1.20 USD

  @complexity:low @edge
  Escenario: El API de precios no responde
    Dado que el último precio conocido fue 0.48 USD por POL el "2026-09-26 10:00"
    Y el API de precios no responde
    Cuando abro el reporte de costos
    Entonces el costo estimado se calcula con 0.48 USD por POL
    Y el reporte indica que el precio es del "2026-09-26 10:00"

  @complexity:low @negative
  Esquema del escenario: Solo el Super Administrador ve el reporte de gas
    Dado que estoy autenticado como "<rol>" de "Veeduría Ciudadana Santa Marta"
    Cuando intento abrir el reporte de costos de gas
    Entonces la acción es rechazada

    Ejemplos:
      | rol                           |
      | Administrador de Organización |
      | Veedor de Campo               |
