# language: es
@story_id:US-008 @origin:discovery_inicial @priority:1 @epic:EPIC-002
Característica: Crear un reporte de evidencia con ubicación GPS
  Como Veedor de Campo
  quiero crear un reporte vinculado a un contrato SECOP capturando el GPS del teléfono
  para registrar una anomalía o avance de obra en terreno

  Antecedentes:
    Dado que estoy autenticado como el veedor "carlos@correo.co" de "Veeduría Ciudadana Santa Marta"
    Y mi organización vigila "Magdalena" (47)
    Y la obra del contrato "CO1.PCCNTR.1234567" en "Santa Marta" tiene ubicación oficial fijada
    Y el radio de geocerca vigente es 500 m

  @complexity:medium
  Escenario: Reporte exitoso cerca de la obra
    Dado que mi GPS indica una posición a 120 m de la obra con precisión de 15 m
    Cuando creo un reporte del contrato "CO1.PCCNTR.1234567" clasificado como "Retraso" con el comentario "Obra detenida hace 2 meses" y 2 fotos
    Entonces el reporte se envía con los archivos, sus hashes, mi latitud y longitud, la clasificación y el comentario
    Y veo el mensaje "Reporte recibido con éxito. Su evidencia ha sido encolada para sellado inmutable en la red Stellar."

  @complexity:high
  Escenario: El primer reporte fija la ubicación de una obra sin ubicación
    Dado que la obra del contrato "CO1.PCCNTR.2222222" no tiene ubicación en mi organización
    Y mi GPS indica 11.2408, -74.1990 con precisión de 10 m
    Cuando creo un reporte del contrato "CO1.PCCNTR.2222222" clasificado como "Avance" con 1 foto
    Entonces la ficha de obra de mi organización queda ubicada en 11.2408, -74.1990
    Y el contrato de SECOP no se modifica

  @complexity:high @negative
  Esquema del escenario: El primer reporte solo fija la ubicación cerca del municipio del contrato
    Dado que la obra del contrato "CO1.PCCNTR.2222222" de "Santa Marta" no tiene ubicación en mi organización
    Y la distancia al municipio para fijar una obra es 30 km
    Y mi GPS indica una posición a <distancia> km de la cabecera de "Santa Marta" con precisión de 10 m
    Cuando creo un reporte del contrato "CO1.PCCNTR.2222222" clasificado como "Avance" con 1 foto
    Entonces el reporte es aceptado
    Y la ubicación de la obra <resultado>

    Ejemplos:
      | distancia | resultado                     |
      | 29        | queda fijada en mi posición   |
      | 42.3      | queda por confirmar           |

  @complexity:medium @negative
  Escenario: El veedor sabe por qué la ubicación de la obra quedó por confirmar
    Dado que la obra del contrato "CO1.PCCNTR.2222222" de "Santa Marta" no tiene ubicación en mi organización
    Y mi GPS indica una posición a 42.3 km de la cabecera de "Santa Marta" con precisión de 10 m
    Cuando creo un reporte del contrato "CO1.PCCNTR.2222222" clasificado como "Avance" con 1 foto
    Entonces veo el mensaje "La ubicación de esta obra queda por confirmar: su reporte se tomó a 42.3 km de Santa Marta, y para fijar una obra hay que estar a menos de 30 km de su municipio. La veeduría la revisará."

  @complexity:high @negative
  Esquema del escenario: El primer reporte solo fija la ubicación con buena señal del GPS
    Dado que la obra del contrato "CO1.PCCNTR.2222222" de "Santa Marta" no tiene ubicación en mi organización
    Y mi GPS indica una posición cerca de la cabecera de "Santa Marta" con precisión de <precision> m
    Cuando creo un reporte del contrato "CO1.PCCNTR.2222222" clasificado como "Avance" con 1 foto
    Entonces el reporte es aceptado
    Y la ubicación de la obra <resultado>

    Ejemplos:
      | precision | resultado                                                                                                                                                     |
      | 20        | queda fijada en mi posición                                                                                                                                   |
      | 21        | queda por confirmar, y veo "La ubicación de esta obra queda por confirmar: la señal del GPS tenía una precisión de 21 m, y para fijar una obra se necesitan 20 m o menos. La veeduría la revisará." |

  @complexity:medium @edge
  Escenario: Un contrato departamental se mide contra la cabecera más cercana de su departamento
    Dado que la obra de un contrato de la Gobernación del Magdalena, sin municipio, no tiene ubicación en mi organización
    Y mi GPS indica una posición a 5 km de la cabecera de "Ciénaga" con precisión de 10 m
    Cuando creo un reporte de ese contrato
    Entonces la ubicación de la obra queda fijada en mi posición

  @complexity:medium @edge
  Escenario: Mientras la ubicación esté por confirmar, el siguiente reporte lo vuelve a intentar
    Dado que el primer reporte de la obra del contrato "CO1.PCCNTR.2222222" dejó su ubicación por confirmar
    Cuando otro veedor envía un reporte de esa obra desde cerca de "Santa Marta" con precisión de 10 m
    Entonces el reporte es aceptado sin geocerca
    Y la ubicación de la obra queda fijada en su posición

  @complexity:low @negative
  Escenario: La clasificación es obligatoria
    Dado que mi GPS indica una posición a 120 m de la obra con precisión de 15 m
    Cuando intento enviar un reporte del contrato "CO1.PCCNTR.1234567" sin clasificación y con 1 foto
    Entonces el reporte no se envía

  @complexity:low @negative
  Esquema del escenario: Solo se aceptan las clasificaciones definidas
    Cuando intento clasificar el reporte como "<clasificacion>"
    Entonces la clasificación es "<resultado>"

    Ejemplos:
      | clasificacion | resultado |
      | Avance        | aceptada  |
      | Retraso       | aceptada  |
      | Abandono      | aceptada  |
      | Anomalía      | rechazada |

  @complexity:low @negative
  Esquema del escenario: El comentario es opcional y tiene máximo 500 caracteres
    Dado que mi GPS indica una posición a 120 m de la obra con precisión de 15 m
    Cuando creo un reporte clasificado como "Avance" con 1 foto y un comentario de <largo> caracteres
    Entonces el reporte es "<resultado>"

    Ejemplos:
      | largo | resultado |
      | 0     | aceptado  |
      | 500   | aceptado  |
      | 501   | rechazado |

  @complexity:medium @negative
  Esquema del escenario: Precisión mínima del GPS de 50 m
    Dado que mi GPS indica una posición a 120 m de la obra con precisión de <precision> m
    Cuando intento enviar el reporte
    Entonces "<resultado>"

    Ejemplos:
      | precision | resultado                                                  |
      | 50        | el reporte se envía                                        |
      | 51        | la app me pide reintentar hasta obtener buena señal        |

  @complexity:low @negative
  Escenario: Permiso de GPS denegado
    Dado que negué el permiso de ubicación a la app
    Cuando toco "Nuevo Reporte" en el contrato "CO1.PCCNTR.1234567"
    Entonces no puedo crear el reporte
    Y veo el mensaje "GovTrace requiere acceso a su ubicación exacta para certificar criptográficamente que la evidencia fue tomada en el sitio de la obra. Por favor habilite el GPS."

  @complexity:medium @negative
  Escenario: Reporte fuera de la geocerca
    Dado que mi GPS indica una posición a 2.3 km de la obra con precisión de 10 m
    Cuando intento enviar un reporte del contrato "CO1.PCCNTR.1234567" clasificado como "Abandono" con 1 foto
    Entonces el reporte es rechazado
    Y veo el mensaje "Se encuentra a 2.3 km de la ubicación oficial de la obra. Para prevenir fraudes, debe acercarse a un radio de 500 metros del proyecto."

  @complexity:low @negative
  Escenario: No se puede reportar una obra fuera del territorio de la organización
    Cuando intento crear un reporte de un contrato de "Medellín" (05001)
    Entonces el reporte es rechazado

  @complexity:medium @negative
  Esquema del escenario: Se marca el reporte con hora de captura sospechosa
    Dado que el servidor recibe mi reporte a las "2026-09-27 10:00"
    Y la hora de captura del teléfono es "<captura>"
    Cuando el servidor procesa el reporte
    Entonces el reporte "<marca>"
    Y si queda marcado lo ven el Administrador de Organización y el Super Administrador

    # R-SEC-05: 5 minutos de tolerancia hacia el futuro (latencia y reloj del teléfono).
    Ejemplos:
      | captura           | marca              |
      | 2026-09-27 09:40  | no queda marcado   |
      | 2026-09-21 10:00  | no queda marcado   |
      | 2026-09-27 10:04  | no queda marcado   |
      | 2026-09-27 10:05  | no queda marcado   |
      | 2026-09-27 10:06  | queda marcado      |
      | 2026-09-27 12:00  | queda marcado      |
      | 2026-09-19 09:00  | queda marcado      |

  @complexity:medium @edge
  Escenario: En una ficha con varios contratos el reporte se vincula a la ficha completa
    Dado que la ficha de obra "Acueducto Gaira" agrupa los contratos "CO1.PCCNTR.1111111" y "CO1.PCCNTR.3333333"
    Cuando creo un reporte en la obra "Acueducto Gaira"
    Entonces el reporte queda vinculado a la ficha "Acueducto Gaira" con sus 2 contratos

  @complexity:high @edge
  Escenario: Dos veedores envían a la vez el primer reporte de una obra sin ubicación
    Dado que la obra del contrato "CO1.PCCNTR.2222222" no tiene ubicación en mi organización
    Y otro veedor de mi organización está a 80 m de mí
    Cuando ambos enviamos el primer reporte de esa obra al mismo tiempo
    Entonces solo la primera transacción fija la ubicación oficial
    Y la segunda se valida contra esa ubicación con la geocerca
    Y ambos reportes son aceptados

  @complexity:medium @edge
  Escenario: Una ubicación oficial errónea se corrige
    Dado que la ubicación oficial de la obra quedó fijada a 3 km del sitio real
    Y los veedores en el sitio real quedan fuera de la geocerca
    Cuando el Administrador de Organización corrige la ubicación de la obra
    Entonces los veedores en el sitio real pueden reportar

  @complexity:low
  Escenario: El botón de enviar dice qué falta
    Dado que elegí la obra y el GPS tiene mi ubicación
    Pero aún no elegí la clasificación ni adjunté archivos
    Cuando miro el botón "Enviar Reporte"
    Entonces junto a él leo "Para enviar falta:" con "decir qué vio en la obra" y "adjuntar al menos una foto o un PDF"

  # It. 47, "Encontrar la obra en campo" (discovery corto, 2026-10-10): esperar
  # una buena lectura del GPS y leerlo una sola vez por pantalla.

  @complexity:medium
  Escenario: La app espera una lectura de 50 m o menos y muestra la precisión mientras tanto
    Dado que mi GPS da primero una lectura de 2000 m, luego una de 120 m y luego una de 18 m
    Cuando abro "Nuevo reporte"
    Entonces veo "Buscando señal GPS: 2000 m. Se necesitan 50 m o menos."
    Y después "Buscando señal GPS: 120 m. Se necesitan 50 m o menos."
    Y con la lectura de 18 m la ubicación queda lista

  @complexity:low @negative
  Escenario: Sin una buena lectura en un minuto, la app explica qué hacer
    Dado que mi GPS no da una lectura de 50 m o menos durante 60 s
    Cuando espero en "Nuevo reporte"
    Entonces veo el mensaje "No se consiguió una buena señal del GPS en 1 minuto. Salga a un lugar abierto y revise que su celular tenga activada la ubicación precisa. Luego toque «Intentar de nuevo»."
    Y veo el botón "Intentar de nuevo"

  @complexity:medium @edge
  Esquema del escenario: El reporte usa la última lectura del GPS si tiene 30 segundos o menos
    Dado que mi última lectura del GPS fue de 15 m hace <edad> s
    Cuando armo el reporte
    Entonces el reporte <resultado>

    Ejemplos:
      | edad | resultado                                       |
      | 10   | usa esa lectura, con su hora como hora de captura |
      | 30   | usa esa lectura, con su hora como hora de captura |
      | 31   | espera una lectura nueva                        |
