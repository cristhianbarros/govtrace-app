# language: es
@story_id:US-044-MON @origin:analisis_completitud @priority:2 @epic:EPIC-009
Característica: Alertas de monitoreo externo de la plataforma
  Como Super Administrador
  quiero recibir alertas de una herramienta de monitoreo externa sobre caídas de la aplicación
  para enterarme de las fallas técnicas antes que los usuarios

  @complexity:medium
  Escenario: Caída de más de 5 minutos
    Dado que la aplicación deja de responder
    Cuando pasan 6 minutos sin respuesta
    Entonces el Super Administrador recibe una alerta por Email
    Y el Super Administrador recibe una alerta por Webhook

  @complexity:low @negative
  Escenario: Una interrupción de menos de 5 minutos no genera alerta
    Dado que la aplicación deja de responder
    Cuando vuelve a responder a los 3 minutos
    Entonces no se envía ninguna alerta
