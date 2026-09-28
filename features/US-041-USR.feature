# language: es
@story_id:US-041-USR @origin:analisis_completitud @priority:2 @epic:EPIC-006
Característica: Reactivar a un veedor desactivado
  Como Administrador de Organización
  quiero reactivar a un veedor desactivado
  para reincorporarlo al equipo sin crear una cuenta nueva

  @complexity:low
  Escenario: Reactivación de un veedor
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Y "carlos@correo.co" es un veedor desactivado
    Cuando reactivo al veedor "carlos@correo.co"
    Entonces "carlos@correo.co" puede volver a iniciar sesión con su contraseña
    Y el log de auditoría registra la reactivación

  @complexity:low @negative
  Escenario: Un veedor no puede reactivar cuentas
    Dado que estoy autenticado como el veedor "laura@correo.co" de "Veeduría Ciudadana Santa Marta"
    Cuando intento reactivar al veedor "carlos@correo.co"
    Entonces la acción es rechazada
