# language: es
@story_id:US-007 @origin:discovery_inicial @priority:2 @epic:EPIC-009
Característica: Actualizar nombre de fantasía y logo de la veeduría
  Como Administrador de Organización
  quiero actualizar el nombre de fantasía y el logo de la veeduría
  para mantener la identidad visual de su observatorio

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"

  @complexity:low
  Escenario: Actualización exitosa del nombre y el logo
    Cuando cambio el nombre de fantasía a "Ojo Ciudadano SMR"
    Y cargo el logo "logo.png" de 512x512 px y 300 KB
    Y guardo los cambios
    Entonces el nombre de fantasía es "Ojo Ciudadano SMR"
    Y mis veedores ven de inmediato el nuevo nombre y logo

  @complexity:low @negative
  Escenario: Logo con formato no permitido
    Cuando cargo el logo "logo.gif" de 512x512 px y 300 KB
    Entonces el logo es rechazado
    Y veo el mensaje "El formato del archivo no es válido. Solo se permiten imágenes PNG, JPG o SVG."

  @complexity:low @negative
  Escenario: Logo que supera los 2 MB
    Cuando cargo el logo "logo.png" de 512x512 px y 2.5 MB
    Entonces el logo es rechazado
    Y veo el mensaje "El tamaño de la imagen supera el límite permitido de 2 MB."

  @complexity:low @negative
  Esquema del escenario: Logo por debajo de 128x128 píxeles
    Cuando cargo el logo "logo.png" de <dimensiones> px y 300 KB
    Entonces el logo es "<resultado>"

    Ejemplos:
      | dimensiones | resultado |
      | 127x128     | rechazado |
      | 128x127     | rechazado |
      | 128x128     | aceptado  |

  @complexity:low @negative
  Escenario: Mensaje al rechazar un logo pequeño
    Cuando cargo el logo "logo.png" de 100x100 px y 300 KB
    Entonces veo el mensaje "La imagen es demasiado pequeña. Las dimensiones mínimas requeridas son de al menos 128x128 píxeles."

  @complexity:medium @negative
  Escenario: Un logo SVG con contenido ejecutable se guarda limpio
    Cuando cargo el logo "logo.svg" que contiene un script y un manejador de evento "onload"
    Y guardo los cambios
    Entonces el logo guardado no contiene scripts ni manejadores de eventos

  @complexity:low @negative
  Esquema del escenario: El nombre de fantasía debe tener entre 3 y 100 caracteres
    Cuando cambio el nombre de fantasía a un texto de <largo> caracteres
    Y guardo los cambios
    Entonces el cambio es "<resultado>"

    Ejemplos:
      | largo | resultado |
      | 2     | rechazado |
      | 3     | aceptado  |
      | 100   | aceptado  |
      | 101   | rechazado |

  @complexity:low @negative
  Escenario: Mensaje cuando el nombre de fantasía está fuera de rango
    Cuando cambio el nombre de fantasía a "OC"
    Y guardo los cambios
    Entonces veo el mensaje "El nombre de fantasía debe tener entre 3 y 100 caracteres."

  @complexity:low @negative
  Escenario: El NIT no es editable desde la configuración de la veeduría
    Cuando abro la configuración de mi organización
    Entonces el NIT se muestra solo en modo lectura

  @complexity:low @negative
  Escenario: Un veedor no accede a la configuración de la organización
    Dado que estoy autenticado como el veedor "carlos@correo.co" de "Veeduría Ciudadana Santa Marta"
    Cuando intento abrir la configuración de la organización
    Entonces la acción es rechazada

  # It. 43h (V13 de docs/mapa-funcional.md, aprobado por el usuario el 2026-10-01): el contacto
  # público de la veeduría, un correo y, si quiere, un teléfono, en su sitio.

  @complexity:low @origin:mapa_funcional
  Escenario: La veeduría publica su correo y su teléfono de contacto
    Cuando escribo el correo de contacto "contacto@veeduria-smr.org" y el teléfono "+57 300 123 4567"
    Y guardo los cambios
    Entonces el sitio público de la veeduría muestra su correo y su teléfono de contacto
    Y el log de auditoría registra el contacto anterior y el nuevo

  @complexity:low @negative @origin:mapa_funcional
  Escenario: Un correo de contacto que no es válido se rechaza
    Cuando escribo el correo de contacto "contacto@"
    Y guardo los cambios
    Entonces veo el mensaje "Escriba un correo de contacto válido, como contacto@veeduria.org."

  @complexity:low @negative @origin:mapa_funcional
  Escenario: Un teléfono de contacto que no es válido se rechaza
    Cuando escribo el teléfono de contacto "300-ABC"
    Y guardo los cambios
    Entonces veo el mensaje "Escriba un teléfono de 7 a 15 dígitos. Puede empezar con + y llevar espacios o guiones."

