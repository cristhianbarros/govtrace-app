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
