# language: es
@story_id:US-003a @origin:discovery_inicial @priority:2 @epic:EPIC-009
Característica: Suspender y reactivar una organización
  Como Super Administrador
  quiero suspender o reactivar una organización activa
  para bloquear o restablecer temporalmente su acceso ante impagos o revisiones de seguridad

  Antecedentes:
    Dado que estoy autenticado como Super Administrador en el panel global
    Y existe la organización activa "Veeduría Ciudadana Santa Marta" con subdominio "veeduria-smr"

  @complexity:low
  Escenario: Suspensión de una organización activa
    Cuando suspendo la organización "Veeduría Ciudadana Santa Marta"
    Entonces su estado es "Suspendida"
    Y sus usuarios no pueden acceder a su panel
    Y la organización no acepta nuevos reportes

  @complexity:low
  Escenario: Reactivación inmediata de una organización suspendida
    Dado que la organización "Veeduría Ciudadana Santa Marta" está suspendida
    Cuando reactivo la organización "Veeduría Ciudadana Santa Marta"
    Entonces su estado es "Activa"
    Y sus usuarios pueden volver a acceder de inmediato

  @complexity:low
  Escenario: El mapa público de una organización suspendida sigue disponible con aviso
    Dado que la organización "Veeduría Ciudadana Santa Marta" está suspendida
    Y tiene 3 evidencias publicadas
    Cuando un visitante abre el mapa público de "veeduria-smr"
    Entonces ve las 3 evidencias publicadas y puede verificarlas
    Y ve el aviso "⚠️ Esta organización se encuentra suspendida temporalmente. Sus evidencias publicadas siguen disponibles solo para consulta."

  @complexity:low @negative
  Escenario: No se puede suspender una organización ya suspendida
    Dado que la organización "Veeduría Ciudadana Santa Marta" está suspendida
    Cuando suspendo la organización "Veeduría Ciudadana Santa Marta"
    Entonces la acción es rechazada
    Y veo el mensaje "La organización seleccionada ya se encuentra en estado suspendido."

  @complexity:medium @edge
  Escenario: Un veedor en campo intenta enviar un reporte con su organización suspendida
    Dado que el veedor "carlos@veeduria-smr.org" tiene 2 reportes pendientes en su teléfono
    Y la organización "Veeduría Ciudadana Santa Marta" está suspendida
    Cuando la app intenta sincronizar los reportes
    Entonces el servidor responde con acceso prohibido
    Y la app muestra "La organización veedora ha sido temporalmente suspendida. Contacte a soporte"
    Y los 2 reportes siguen guardados en el teléfono

  @complexity:medium @edge
  Escenario: Los reportes pendientes se envían si la organización se reactiva dentro de los 7 días
    Dado que el veedor "carlos@veeduria-smr.org" tiene un reporte pendiente capturado hace 3 días
    Y su organización fue suspendida y luego reactivada
    Cuando la app sincroniza los reportes pendientes
    Entonces el reporte es aceptado por el servidor
