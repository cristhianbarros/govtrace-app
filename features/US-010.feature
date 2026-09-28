# language: es
@story_id:US-010 @origin:discovery_inicial @priority:2 @epic:EPIC-002
Característica: Seguimiento de mis reportes
  Como Veedor de Campo
  quiero consultar mis reportes enviados, su estado y su hash
  para hacer seguimiento a lo que envié

  Antecedentes:
    Dado que estoy autenticado como el veedor "carlos@correo.co" de "Veeduría Ciudadana Santa Marta"

  @complexity:low
  Escenario: Estado técnico y estado editorial por separado
    Dado que tengo un reporte sellado y publicado
    Cuando abro "Mis Reportes"
    Entonces veo el reporte con estado técnico "Sellado" y estado editorial "Publicado" por separado

  @complexity:medium
  Esquema del escenario: Correspondencia del estado técnico visible
    Dado que tengo un reporte cuyo estado interno de sellado es "<interno>"
    Cuando abro "Mis Reportes"
    Entonces veo el estado técnico "<visible>"

    Ejemplos:
      | interno           | visible  |
      | Recibida          | En Cola  |
      | En Cola           | En Cola  |
      | Transmitiendo     | Sellando |
      | Sellada           | Sellado  |
      | Falla de Sellado  | En Cola  |

  @complexity:low
  Escenario: Evidencia aún no revisada
    Dado que tengo un reporte en estado interno "Oculto"
    Cuando abro "Mis Reportes"
    Entonces veo el estado editorial "En Revisión"

  @complexity:low
  Escenario: Evidencia rechazada con motivo
    Dado que el administrador rechazó mi reporte con el motivo "La foto no corresponde a la obra"
    Cuando abro "Mis Reportes"
    Entonces veo el estado editorial "Rechazada" con el motivo "La foto no corresponde a la obra"

  @complexity:low @negative
  Escenario: El veedor nunca ve errores de sellado
    Dado que tengo un reporte en "Falla de Sellado"
    Cuando abro "Mis Reportes"
    Entonces no veo ningún mensaje de error de sellado

  @complexity:low @negative
  Escenario: Solo veo mis propios reportes
    Dado que "laura@correo.co" tiene 3 reportes en mi organización
    Cuando abro "Mis Reportes"
    Entonces no veo ningún reporte de "laura@correo.co"
