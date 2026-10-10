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

  # It. 47, "Encontrar la obra en campo" (discovery corto, 2026-10-10): la lista
  # de obras del municipio del veedor, con filtros, orden y tope (V18, V19).

  @complexity:medium
  Escenario: Al abrir Nuevo reporte veo las obras de mi municipio
    Dado que mi GPS indica una posición en "Santa Marta" con precisión de 30 m
    Y hay obras reportables en "Santa Marta" y en "Ciénaga"
    Cuando abro "Nuevo reporte"
    Entonces veo la lista de obras de "Santa Marta"
    Y el municipio elegido es "Santa Marta"

  @complexity:low
  Escenario: Puedo cambiar el municipio de la lista
    Dado que veo la lista de obras de "Santa Marta"
    Cuando elijo el municipio "Ciénaga"
    Entonces veo la lista de obras de "Ciénaga"

  @complexity:medium @negative
  Esquema del escenario: Sin municipio por el GPS, la lista abre con el primero del territorio
    Dado que <gps>
    Cuando abro "Nuevo reporte"
    Entonces veo la lista de obras de "Santa Marta"
    Y veo el mensaje "No pudimos saber en qué municipio está. Le mostramos las obras de Santa Marta: elija el suyo."

    # El primero por código DIVIPOLA: en Magdalena (47), Santa Marta (47001).
    Ejemplos:
      | gps                                                                      |
      | negué el permiso de ubicación a la app                                   |
      | mi GPS indica una posición con precisión de 6 km                         |
      | mi GPS indica una posición a 45 km de la cabecera más cercana del territorio |

  @complexity:medium
  Escenario: Primero las de plazo vencido, luego las que vencen más pronto
    Dado que en "Santa Marta" hay estas obras reportables:
      | obra        | situacion                  |
      | Parque A    | vence en 90 días           |
      | Colegio B   | plazo vencido hace 10 días |
      | Vía C       | vence en 20 días           |
      | Acueducto D | plazo vencido hace 40 días |
      | Cancha E    | terminada hace 2 meses     |
      | Sede F      | sin fecha de fin           |
    Cuando abro "Nuevo reporte"
    Entonces veo las obras en este orden: "Colegio B", "Acueducto D", "Vía C", "Parque A", "Sede F" y "Cancha E"

  @complexity:medium @edge
  Escenario: Las de plazo vencido hace más de 12 meses van después de las que están en ejecución
    Dado que la ventana para reportar un contrato terminado es de 12 meses
    Y que en "Santa Marta" hay estas obras reportables:
      | obra      | situacion                           |
      | Parque A  | vence en 90 días                    |
      | Muelle G  | plazo vencido hace 8 años y 2 meses |
      | Colegio B | plazo vencido hace 10 días          |
      | Puente H  | plazo vencido hace 14 meses         |
      | Sede F    | sin fecha de fin                    |
      | Cancha E  | terminada hace 2 meses              |
    Cuando abro "Nuevo reporte"
    Entonces veo las obras en este orden: "Colegio B", "Parque A", "Sede F", "Puente H", "Muelle G" y "Cancha E"
    Y "Muelle G" dice "Plazo vencido hace más de 8 años · SECOP no la ha cerrado"

  @complexity:medium
  Escenario: Veo 20 obras y Ver 20 más trae las siguientes
    Dado que en "Santa Marta" hay 45 obras reportables
    Cuando abro "Nuevo reporte"
    Entonces veo 20 obras
    Cuando toco "Ver 20 más"
    Entonces veo 40 obras
    Cuando toco "Ver 20 más"
    Entonces veo 45 obras
    Y no veo "Ver 20 más"

  @complexity:medium
  Esquema del escenario: Filtro las obras por su situación
    Dado que en "Santa Marta" hay estas obras reportables:
      | obra        | situacion                  |
      | Parque A    | vence en 90 días           |
      | Colegio B   | plazo vencido hace 10 días |
      | Acueducto D | plazo vencido hace 40 días |
      | Cancha E    | terminada hace 2 meses     |
      | Sede F      | sin fecha de fin           |
    Cuando filtro la situación por "<situacion>"
    Entonces veo solo <obras>

    Ejemplos:
      | situacion           | obras                                       |
      | Plazo vencido       | "Colegio B" y "Acueducto D"                 |
      | En ejecución        | "Parque A" y "Sede F"                       |
      | Terminada hace poco | "Cancha E"                                  |
      | Todas               | las cinco                                   |

  @complexity:low
  Escenario: Filtro las obras por su tipo
    Dado que en "Santa Marta" hay las obras "Construcción del acueducto veredal" y "Pavimentación Calle 30"
    Cuando filtro el tipo de obra por "Agua y saneamiento"
    Entonces veo solo "Construcción del acueducto veredal"

  @complexity:low
  Escenario: Filtro las obras por la entidad que contrató
    Dado que en "Santa Marta" hay obras de "Distrito de Santa Marta" y de "Gobernación del Magdalena"
    Cuando filtro la entidad por "Gobernación del Magdalena"
    Entonces solo veo obras de "Gobernación del Magdalena"

  @complexity:medium
  Esquema del escenario: El tipo de obra sale de las palabras del objeto y, si no, del código UNSPSC
    Dado que SECOP II publica un contrato de obra con el objeto "<objeto>" y el código "<codigo>"
    Cuando se sincroniza
    Entonces su tipo de obra es "<tipo>"

    Ejemplos:
      | objeto                                                 | codigo      | tipo                 |
      | Construcción de redes de acueducto y alcantarillado    | V1.72101500 | Agua y saneamiento   |
      | Mejoramientos de vivienda en la zona rural             | V1.72101500 | Vivienda             |
      | Mantenimiento de la cancha de fútbol Pan de Azúcar     | V1.72151500 | Deporte y recreación |
      | Adecuación de la sede de la institución educativa      | V1.72121400 | Educación            |
      | Ampliación del centro de salud del corregimiento       | V1.72121400 | Salud                |
      | Construcción del parque lineal de la quebrada          | V1.72141100 | Espacio público      |
      | Pavimentación de la vía al colegio                     | V1.72141100 | Vías y puentes       |
      | Obras de estabilización del talud                      | V1.72141100 | Vías y puentes       |
      | Obras complementarias del proyecto habitacional        | V1.72111000 | Vivienda             |
      | Suministro e instalación de elementos de señalización  | V1.81101500 | Otras                |

  @complexity:low @edge
  Escenario: La búsqueda no distingue tildes ni mayúsculas
    Dado que existe el contrato "Construcción de la VÍA a Minca" en "Santa Marta"
    Cuando escribo "via a minca" en "Buscar Obra"
    Entonces veo "Construcción de la VÍA a Minca" en los resultados

  @complexity:low
  Escenario: La búsqueda también encuentra por la entidad
    Dado que existe un contrato de "Empresa de Desarrollo Urbano" en "Santa Marta"
    Cuando escribo "desarrollo urbano" en "Buscar Obra"
    Entonces veo ese contrato en los resultados

  @complexity:medium @negative
  Escenario: Sin resultados en mi municipio, puedo buscar en todo el territorio
    Dado que veo la lista de obras de "Santa Marta"
    Y existe el contrato "Muelle del puerto de Ciénaga" solo en "Ciénaga"
    Cuando escribo "Muelle del puerto" en "Buscar Obra"
    Entonces veo el mensaje "No se encontraron obras en Santa Marta con esa búsqueda."
    Cuando toco "Buscar en todo el territorio"
    Entonces veo "Muelle del puerto de Ciénaga" en los resultados, con su municipio "Ciénaga"

  @complexity:low @edge
  Escenario: Una obra sin ubicación aparece con Sin ubicación todavía
    Dado que la obra de "Pavimentación Calle 30" no tiene ubicación
    Cuando abro "Nuevo reporte"
    Entonces veo "Pavimentación Calle 30" con la marca "Sin ubicación todavía"

  @complexity:low @privacy
  Escenario: Mi ubicación no viaja en la URL al pedir las obras de mi municipio
    Cuando abro "Nuevo reporte"
    Entonces la app envía mi ubicación en el cuerpo de la petición
    Y el servidor no acepta pedir las obras con la ubicación en la URL
    Y el servidor no guarda mi ubicación
