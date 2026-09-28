# language: es
@story_id:US-049-RPT @origin:analisis_completitud @priority:2 @epic:EPIC-004
Característica: Resumen del territorio para el administrador
  Como Administrador de Organización
  quiero ver un resumen de mi territorio
  para entender el estado de nuestra veeduría de un vistazo

  @complexity:low
  Escenario: Resumen de la organización
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Y mi organización tiene 10 obras verdes, 4 amarillas y 3 rojas, 25 evidencias de "Avance", 8 de "Retraso" y 3 de "Abandono", y 6 veedores activos
    Cuando abro el resumen del territorio
    Entonces veo 10 verdes, 4 amarillas y 3 rojas
    Y veo las evidencias por clasificación y por mes
    Y veo 6 veedores activos

  @complexity:low @negative
  Escenario: El resumen solo incluye datos de la propia organización
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Y "Veeduría Ciénaga" tiene 12 evidencias
    Cuando abro el resumen del territorio
    Entonces ninguna cifra incluye datos de "Veeduría Ciénaga"
