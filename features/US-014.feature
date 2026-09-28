# language: es
@story_id:US-014 @origin:discovery_inicial @priority:2 @epic:EPIC-001
Característica: Panel de salud de la sincronización SECOP II
  Como Super Administrador
  quiero visualizar un panel de salud de la sincronización
  para monitorear la estabilidad de la integración

  Antecedentes:
    Dado que estoy autenticado como Super Administrador en el panel global

  @complexity:low
  Escenario: Métricas de la última ejecución exitosa
    Dado que la última sincronización corrió de 02:00 a 02:07 y procesó 150 contratos: 20 nuevos y 130 actualizados, y descartó 3 por no emparejar con DIVIPOLA
    Cuando abro el panel de salud de la sincronización
    Entonces veo inicio "02:00", fin "02:07" y estado "Success"
    Y veo 150 contratos procesados con el desglose de nuevos y actualizados por organización
    Y veo 3 contratos descartados por no emparejar con DIVIPOLA

  @complexity:low @negative
  Escenario: La última sincronización falló por timeout
    Dado que la última sincronización falló con "HTTP 504 Gateway Timeout" y el próximo reintento es en 30 minutos
    Cuando abro el panel de salud de la sincronización
    Entonces veo un indicador rojo
    Y veo el mensaje "Falla de sincronización con SECOP II: El servicio remoto no respondió (Error HTTP 504 Gateway Timeout). Reintento programado en 30 minutos."

  @complexity:low @negative
  Escenario: Un Administrador de Organización no accede al panel de salud
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando intento abrir el panel de salud de la sincronización
    Entonces la acción es rechazada
