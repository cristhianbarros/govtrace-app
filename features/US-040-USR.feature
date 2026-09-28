# language: es
@story_id:US-040-USR @origin:analisis_completitud @priority:3 @epic:EPIC-006
Característica: Reenviar o revocar una invitación pendiente
  Como Administrador de Organización
  quiero reenviar o revocar una invitación pendiente
  para corregir invitaciones enviadas por error o no atendidas

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Y "carlos@correo.co" tiene una invitación pendiente

  @complexity:low
  Escenario: Reenviar una invitación
    Cuando reenvío la invitación de "carlos@correo.co"
    Entonces se envía un nuevo enlace con la vigencia configurada
    Y el log de auditoría registra el reenvío

  @complexity:low @negative
  Escenario: Un enlace revocado no permite activar la cuenta
    Dado que revoqué la invitación de "carlos@correo.co"
    Cuando "carlos@correo.co" abre el enlace de la invitación
    Entonces no puede crear su contraseña
    Y ve el mensaje "El enlace de invitación ha expirado o no es válido. Solicite una nueva invitación al administrador."
    Y el log de auditoría registra la revocación
