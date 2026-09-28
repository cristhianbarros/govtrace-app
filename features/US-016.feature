# language: es
@story_id:US-016 @origin:discovery_inicial @priority:1 @epic:EPIC-002
Característica: Buscar la obra por palabra clave
  Como Veedor de Campo
  quiero buscar contratos de mi territorio por nombre de la obra, contratista o número de proceso
  para seleccionar la obra exacta al subir mi evidencia

  Antecedentes:
    Dado que estoy autenticado como el veedor "carlos@correo.co" de "Veeduría Ciudadana Santa Marta"
    Y mi organización vigila "Magdalena" (47)
    Y existe el contrato "Pavimentación Calle 30" en "Santa Marta" en estado "En ejecución"

  @complexity:low
  Escenario: Búsqueda y selección de una obra
    Cuando escribo "Pavi" en "Buscar Obra"
    Y pasan 300 ms sin que escriba más
    Entonces veo "Pavimentación Calle 30" en los resultados
    Y al seleccionarla puedo continuar con el reporte

  @complexity:low @negative
  Escenario: La búsqueda exige al menos 3 caracteres
    Cuando escribo "Pa" en "Buscar Obra"
    Entonces no se ejecuta ninguna búsqueda

  @complexity:medium @negative
  Esquema del escenario: Qué contratos se pueden seleccionar según su estado
    Dado que existe el contrato "Parque Bastidas" en "Santa Marta" en estado "<estado>" <cierre>
    Cuando busco "Parque Bastidas"
    Entonces el contrato "<resultado>"

    # R-SEC-07: estados tal como los publica SECOP II, sin distinguir mayúsculas.
    Ejemplos:
      | estado            | cierre                  | resultado  |
      | En ejecución      |                         | aparece    |
      | Modificado        |                         | aparece    |
      | Aprobado          |                         | aparece    |
      | cedido            |                         | aparece    |
      | Suspendido        |                         | aparece    |
      | terminado         | terminado hace 11 meses | aparece    |
      | Cerrado           | cerrado hace 12 meses   | aparece    |
      | terminado         | terminado hace 13 meses | no aparece |
      | Cancelado         |                         | no aparece |
      | Borrador          |                         | no aparece |
      | enviado Proveedor |                         | no aparece |
      | En aprobación     |                         | no aparece |
      | MODIFICADO        |                         | aparece    |

  @complexity:medium @negative
  Escenario: Solo aparecen contratos del territorio de la organización
    Dado que existe el contrato "Pavimentación Calle 30" en "Medellín" (05001)
    Cuando busco "Pavimentación Calle 30"
    Entonces solo veo el contrato de "Santa Marta"

  @complexity:low @negative
  Escenario: Búsqueda sin resultados
    Cuando busco "Estadio Olímpico"
    Entonces veo el mensaje "No se encontraron obras activas. Verifique el número de proceso, nombre del contratista o palabras clave de la obra."
