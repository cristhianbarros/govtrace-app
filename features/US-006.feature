# language: es
@story_id:US-006 @origin:discovery_inicial @priority:2 @epic:EPIC-006
Característica: Desactivar a un veedor de campo
  Como Administrador de Organización
  quiero desactivar el acceso a un veedor de campo
  para revocar sus permisos si deja la organización o comete infracciones

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Y "carlos@correo.co" es un veedor activo con una sesión abierta en su teléfono

  @complexity:low
  Escenario: Desactivación con revocación inmediata de sesiones
    Cuando desactivo al veedor "carlos@correo.co"
    Entonces su estado es "Inactivo"
    Y su sesión abierta deja de ser válida de inmediato

  @complexity:low @edge
  Escenario: Los reportes previos del veedor desactivado se conservan
    Dado que "carlos@correo.co" tiene 6 reportes sellados
    Cuando desactivo al veedor "carlos@correo.co"
    Entonces los 6 reportes siguen en la base de datos y en la blockchain con su autoría

  @complexity:medium @edge
  Escenario: El veedor desactivado intenta sincronizar su cola local
    Dado que "carlos@correo.co" tiene 1 reporte pendiente en su teléfono
    Y desactivo al veedor "carlos@correo.co"
    Cuando su app intenta sincronizar el reporte
    Entonces el servidor responde con acceso prohibido y no acepta el reporte
    Y la app muestra "Su cuenta ha sido desactivada. No es posible sincronizar nuevos reportes."
