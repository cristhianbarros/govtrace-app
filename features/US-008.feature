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
    Y veo el mensaje "Reporte recibido con éxito. Su evidencia ha sido encolada para sellado inmutable en la red Polygon."

  @complexity:high
  Escenario: El primer reporte fija la ubicación de una obra sin ubicación
    Dado que la obra del contrato "CO1.PCCNTR.2222222" no tiene ubicación en mi organización
    Y mi GPS indica 11.2408, -74.1990 con precisión de 10 m
    Cuando creo un reporte del contrato "CO1.PCCNTR.2222222" clasificado como "Avance" con 1 foto
    Entonces la ficha de obra de mi organización queda ubicada en 11.2408, -74.1990
    Y el contrato de SECOP no se modifica

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
