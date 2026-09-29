# language: es
@story_id:US-004 @origin:discovery_inicial @priority:3 @epic:EPIC-009
Característica: Reporte de comisiones de sellado por organización
  Como Super Administrador
  quiero visualizar las comisiones de sellado en Stellar agrupadas por mes y por organización
  para controlar la rentabilidad y los costos de infraestructura del SaaS

  # Pivote a Stellar (2026-09-28): cada sello cuesta la comisión en XLM que
  # la red le cobró a la cuenta patrocinadora (fee bump, D5). El costo se
  # estima en pesos colombianos con el precio de XLM de un API externo.
  Antecedentes:
    Dado que estoy autenticado como Super Administrador en el panel global
    Y en septiembre de 2026 la organización "Veeduría Ciudadana Santa Marta" selló 40 evidencias con comisiones por 9,7 XLM en total

  @complexity:medium
  Escenario: Reporte mensual por organización con costo en pesos
    Dado que el API de precios informa que 1 XLM equivale a 1.200 COP
    Cuando abro el reporte de costos
    Entonces veo una fila de septiembre de 2026 para "Veeduría Ciudadana Santa Marta"
    Y la fila muestra 40 evidencias selladas, 9,7 XLM de comisiones y un costo estimado de $11.640

  @complexity:low @edge
  Escenario: El API de precios no responde
    Dado que el último precio conocido fue 1.150 COP por XLM el "26/09/2026 10:00"
    Y el API de precios no responde
    Cuando abro el reporte de costos
    Entonces el costo estimado se calcula con 1.150 COP por XLM
    Y el reporte indica que el precio es del "26/09/2026 10:00"

  @complexity:low @negative
  Esquema del escenario: Solo el Super Administrador ve el reporte de costos
    Dado que estoy autenticado como "<rol>" de "Veeduría Ciudadana Santa Marta"
    Cuando intento abrir el reporte de costos
    Entonces la acción es rechazada

    Ejemplos:
      | rol                           |
      | Administrador de Organización |
      | Veedor de Campo               |
