# language: es
@story_id:US-003b @origin:discovery_inicial @priority:3 @epic:EPIC-009
Característica: Baja definitiva de una organización
  Como Super Administrador
  quiero dar de baja de forma definitiva a una organización
  para retirar del sistema a entidades inoperantes aplicando políticas de retención de datos

  Antecedentes:
    Dado que estoy autenticado como Super Administrador en el panel global
    Y existe la organización "Veeduría Ciudadana Santa Marta" con 5 evidencias selladas en Polygon

  @complexity:medium
  Escenario: Baja lógica tras doble confirmación
    Cuando solicito dar de baja la organización "Veeduría Ciudadana Santa Marta"
    Y confirmo la baja por primera vez
    Y confirmo la baja por segunda vez
    Entonces la organización queda marcada como dada de baja sin borrar sus datos físicamente
    Y se revocan todos los accesos de sus usuarios

  @complexity:low @negative
  Escenario: La baja no se ejecuta sin la doble confirmación
    Cuando solicito dar de baja la organización "Veeduría Ciudadana Santa Marta"
    Y confirmo la baja por primera vez
    Pero cancelo la segunda confirmación
    Entonces la organización sigue activa

  @complexity:low
  Escenario: Tras la baja, el mapa sale de línea pero las evidencias siguen verificables
    Dado que la organización "Veeduría Ciudadana Santa Marta" fue dada de baja
    Cuando un visitante abre el mapa público de "veeduria-smr"
    Entonces el mapa no está disponible
    Pero si arrastra al validador libre un archivo sellado por esa organización ve que es "Auténtico"

  @complexity:medium @edge
  Escenario: La baja no altera los registros en la blockchain
    Cuando doy de baja la organización "Veeduría Ciudadana Santa Marta" con doble confirmación
    Entonces las 5 raíces selladas en Polygon permanecen sin cambios

  @complexity:medium @edge
  Esquema del escenario: Retención de 5 años de los archivos tras la baja
    Dado que la organización "Veeduría Ciudadana Santa Marta" fue dada de baja hace <tiempo>
    Cuando se aplica la política de retención
    Entonces sus archivos de evidencia están "<archivos>"
    Y sus sellos en Polygon y sus pruebas de inclusión se conservan

    Ejemplos:
      | tiempo            | archivos   |
      | 4 años y 11 meses | conservados |
      | 5 años y 1 día    | borrados    |
