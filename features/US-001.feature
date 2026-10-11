# language: es
@story_id:US-001 @origin:discovery_inicial @priority:1 @epic:EPIC-009
Característica: Alta de una organización con validación legal y subdominio
  Como Super Administrador
  quiero dar de alta una nueva organización validando su NIT y nombre y asignándole su subdominio
  para habilitar su acceso de forma controlada y prevenir suplantaciones o spam

  Antecedentes:
    Dado que estoy autenticado como Super Administrador en el panel global
    Y no existe ninguna organización con el NIT "900123456-8"
    Y el subdominio "veeduria-smr" está libre

  @complexity:medium
  Escenario: Alta exitosa de una organización
    Cuando registro la organización "Veeduría Ciudadana Santa Marta" con NIT "900123456-8" y subdominio "veeduria-smr"
    Entonces la organización queda creada con estado "Activa"
    Y el subdominio "veeduria-smr" responde peticiones
    Y no queda pendiente ningún paso de aprobación

  @complexity:low @negative
  Escenario: No se puede registrar un NIT ya existente
    Dado que ya existe una organización con NIT "900123456-8"
    Cuando registro la organización "Otra Veeduría" con NIT "900123456-8" y subdominio "otra-veeduria"
    Entonces el alta es rechazada
    Y veo el mensaje "Ya existe una organización registrada con el NIT ingresado."

  @complexity:medium @negative
  Esquema del escenario: El NIT debe traer un dígito de verificación válido según la DIAN
    Cuando registro la organización "Veeduría Ciudadana Santa Marta" con NIT "<nit>" y subdominio "veeduria-smr"
    Entonces el alta es rechazada
    Y veo el mensaje "El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN."

    Ejemplos:
      | nit          |
      | 900123456    |
      | 900123456-3  |
      | 90012A456-8  |

  @complexity:low @negative
  Escenario: No se puede usar un subdominio ya asignado
    Dado que la organización "Veeduría Ciénaga" ya tiene el subdominio "veeduria-smr"
    Cuando registro la organización "Veeduría Ciudadana Santa Marta" con NIT "900123456-8" y subdominio "veeduria-smr"
    Entonces el alta es rechazada
    Y veo el mensaje "El subdominio especificado ya no está disponible. Por favor elija otro."

  @complexity:low @negative
  Esquema del escenario: El subdominio solo admite minúsculas, números y guiones, con mínimo 3 caracteres
    Cuando registro la organización "Veeduría Ciudadana Santa Marta" con NIT "900123456-8" y subdominio "<subdominio>"
    Entonces el alta es rechazada
    Y veo el mensaje "El subdominio solo puede contener letras minúsculas, números y guiones, sin espacios ni caracteres especiales."

    Ejemplos:
      | subdominio    |
      | Veeduria-SMR  |
      | veeduria smr  |
      | veeduría      |
      | ve            |

  @complexity:low @negative
  Esquema del escenario: El subdominio no puede ser una palabra reservada
    Cuando registro la organización "Veeduría Ciudadana Santa Marta" con NIT "900123456-8" y subdominio "<reservada>"
    Entonces el alta es rechazada
    Y veo el mensaje "El subdominio utiliza una palabra reservada del sistema y no puede ser utilizado."

    Ejemplos:
      | reservada |
      | api       |
      | admin     |
      | app       |
      | www       |
      | auth      |
      | assets    |

  @complexity:low @negative
  Esquema del escenario: El nombre de la organización debe tener entre 3 y 150 caracteres
    Cuando registro una organización cuyo nombre tiene <largo> caracteres con NIT "900123456-8" y subdominio "veeduria-smr"
    Entonces el alta es "<resultado>"

    Ejemplos:
      | largo | resultado |
      | 0     | rechazada |
      | 2     | rechazada |
      | 3     | aceptada  |
      | 150   | aceptada  |
      | 151   | rechazada |

  @complexity:low @negative
  Escenario: Solo el Super Administrador asigna el subdominio de una organización
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando intento cambiar el subdominio de mi organización a "smr-veeduria"
    Entonces la acción es rechazada
    Y el subdominio sigue siendo "veeduria-smr"

  # It. 44d (docs/proceso-actual.md A5): una veeduría que forman unos ciudadanos se inscribe en la
  # personería o en la cámara de comercio (Ley 850 de 2003, art. 3) y puede no tener NIT.

  @complexity:medium @origin:proceso_actual
  Escenario: Alta de una veeduría sin NIT, con su inscripción
    Dado que estoy autenticado como Super Administrador en el panel global
    Cuando registro la organización "Veeduría del Parque Los Trupillos" sin NIT, con la inscripción "Resolución 012 de 2026" ante "Personería de Santa Marta" y el subdominio "trupillos"
    Entonces la organización queda activa en "trupillos.govtrace.localhost"
    Y el log de auditoría registra su inscripción

  @complexity:low @negative @origin:proceso_actual
  Escenario: Una organización necesita su NIT o su inscripción
    Cuando registro una organización sin NIT y sin inscripción
    Entonces veo "Ingrese el NIT de la organización, o el número de la resolución o el acta de su inscripción y la entidad que la registró."

  @complexity:low @negative @origin:proceso_actual
  Escenario: La inscripción necesita el número y la entidad
    Cuando registro una organización con el número de su resolución pero sin la entidad que la registró, o al revés
    Entonces veo "Para identificar la inscripción hacen falta los dos datos: el número de la resolución o el acta, y la entidad que la registró."

  @complexity:low @negative @origin:proceso_actual
  Escenario: La entidad de registro es una personería o una cámara de comercio
    Cuando registro una organización inscrita ante "Notaría Tercera de Santa Marta"
    Entonces veo "La entidad de registro debe ser una personería o una cámara de comercio. Por ejemplo: Personería de Santa Marta."

  @complexity:low @negative @origin:proceso_actual
  Escenario: No se puede registrar una inscripción que ya tiene otra organización
    Dado que existe una organización inscrita con "Resolución 012 de 2026" ante "Personería de Santa Marta"
    Cuando registro otra con "resolución 012 de 2026" ante "personeria de santa marta"
    Entonces veo "Ya existe una organización registrada con esa inscripción."

  @complexity:low @origin:proceso_actual
  Escenario: Una organización puede tener su NIT y su inscripción
    Cuando registro una organización con NIT "900123456-8" y con su inscripción
    Entonces quedan los dos, y el panel los muestra

  @complexity:low
  Escenario: Al dar de alta una organización se consulta el RUES por su NIT
    Dado que en los datos abiertos del RUES el NIT "900123456-8" es de "VEEDURIA CIUDADANA SANTA MARTA", activa
    Cuando escribo ese NIT en la nueva organización y pido consultarlo en el RUES
    Entonces veo su razón social, su cámara y el estado de su matrícula
    Y al registrarla, el log de auditoría guarda lo que dijo el RUES

  @complexity:medium
  Escenario: El subdominio sale del nombre de la veeduría
    Cuando registro la organización "Veeduría Ciudadana Santa Marta" sin escribir su subdominio
    Entonces su subdominio es "veeduria-ciudadana-santa-marta"
    Y el nombre se limpia con Str::slug(): sin tildes ni eñe, en minúsculas, con guiones donde había espacios o símbolos

  @complexity:medium
  Escenario: Si el Super Administrador escribe un subdominio, se respeta
    Dado que ya existe una organización con el subdominio "smr"
    Cuando registro otra organización escribiendo el subdominio "smr"
    Entonces veo "El subdominio especificado ya no está disponible. Por favor elija otro."
    Y no se le agrega ningún número: lo escrito se respeta o se rechaza

  @complexity:medium @edge
  Escenario: Dos veedurías con el mismo nombre: la segunda toma -2, y la tercera -3
    Dado que ya existe "Veeduría Ciudadana de Ciénaga" con su subdominio calculado
    Cuando registro otra con ese mismo nombre sin escribir su subdominio
    Entonces su subdominio es "veeduria-ciudadana-de-cienaga-2"

  @complexity:low @edge
  Escenario: Un nombre largo se corta a 63 caracteres, el límite del DNS, sin guion al final
    Cuando registro una organización cuyo nombre da más de 63 caracteres
    Entonces su subdominio tiene 63 caracteres o menos y no termina en guion

  @complexity:low @negative
  Esquema del escenario: Un nombre cuyo subdominio sería una palabra reservada o muy corto se rechaza, con el mensaje de siempre
    Cuando registro una organización llamada "<nombre>" sin escribir su subdominio
    Entonces veo "<mensaje>"
    Y no se registra nada

    Ejemplos:
      | nombre  | mensaje                                                                                                      |
      | API     | El subdominio utiliza una palabra reservada del sistema y no puede ser utilizado.                            |
      | ¿¿¿ ??? | El subdominio solo puede contener letras minúsculas, números y guiones, sin espacios ni caracteres especiales. |

  @complexity:medium
  Escenario: El formulario propone el subdominio mientras se escribe el nombre
    Cuando escribo el nombre "Veeduría Ciudadana Santa Marta" en "Nueva organización"
    Entonces tras una pausa el campo "Subdominio" dice "veeduria-ciudadana-santa-marta"
    Y debajo dice "La dirección será veeduria-ciudadana-santa-marta" seguido del dominio de las veedurías

  @complexity:medium
  Escenario: Un subdominio ajustado a mano no lo pisa el nombre
    Dado que cambié el subdominio propuesto por "smr"
    Cuando sigo escribiendo el nombre
    Entonces el subdominio sigue siendo "smr"
    Y puedo tocar "Usar la propuesta" para volver al que sale del nombre

