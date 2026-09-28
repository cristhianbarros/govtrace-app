# language: es
@story_id:US-009 @origin:discovery_inicial @priority:1 @epic:EPIC-002
Característica: Adjuntar fotos o PDF con privacidad y hash en el teléfono
  Como Veedor de Campo
  quiero adjuntar fotografías o documentos PDF con metadatos automáticos
  para respaldar visualmente la veeduría

  Antecedentes:
    Dado que estoy creando un reporte válido en la app

  @complexity:high
  Escenario: Fotos optimizadas, sin EXIF y con hash calculado en el teléfono
    Cuando adjunto 2 fotos de 4000x3000 px en formato HEIC con coordenadas GPS en su EXIF
    Entonces cada foto se convierte a JPEG con lado mayor de 1920 px y calidad 80 %
    Y ninguna foto conserva metadatos EXIF
    Y se calcula el SHA-256 de cada foto ya optimizada antes de enviarla

  @complexity:medium
  Escenario: El servidor verifica que el hash coincide antes de encolar el sellado
    Dado que adjunté 1 foto y la app calculó su SHA-256
    Cuando el servidor recibe el reporte y el hash que recalcula coincide
    Entonces la evidencia se encola para el sellado

  @complexity:low @negative
  Esquema del escenario: Cantidad y combinación de archivos
    Cuando adjunto <archivos>
    Entonces el reporte es "<resultado>"

    Ejemplos:
      | archivos             | resultado |
      | 1 foto               | aceptado  |
      | 5 fotos              | aceptado  |
      | 6 fotos              | rechazado |
      | 1 PDF                | aceptado  |
      | 2 PDF                | rechazado |
      | 2 fotos y 1 PDF      | rechazado |
      | ningún archivo       | rechazado |

  @complexity:low @negative
  Escenario: La app no permite mezclar fotos y PDF
    Dado que ya adjunté 2 fotos
    Cuando intento adjuntar un PDF
    Entonces la app no permite agregarlo

  @complexity:low @negative
  Esquema del escenario: Peso máximo de 10 MB por archivo
    Cuando adjunto un PDF de <peso>
    Entonces el archivo es "<resultado>"

    Ejemplos:
      | peso    | resultado |
      | 10 MB   | aceptado  |
      | 10.1 MB | rechazado |

  @complexity:low @negative
  Escenario: No se aceptan videos
    Cuando intento adjuntar un video de 20 segundos
    Entonces el archivo es rechazado

  @complexity:medium @negative
  Escenario: Los metadatos del PDF se limpian antes del hash
    Cuando adjunto un PDF con autor "Carlos Gómez", software "Word" y fecha de creación
    Entonces el PDF que se sube no conserva autor, software ni fechas
    Y el SHA-256 se calcula sobre el PDF ya limpio

  @complexity:low @edge
  Escenario: Las fotos no se difuminan
    Cuando adjunto una foto donde se ven rostros y la placa de un vehículo
    Entonces la foto se sube sin difuminar
    Y queda a decisión del Administrador de Organización al revisarla

  @complexity:medium @negative
  Escenario: El hash del servidor no coincide con el del teléfono
    Dado que adjunté 1 foto y la app calculó su SHA-256
    Cuando el archivo llega al servidor alterado en un solo byte
    Entonces la evidencia no se encola para el sellado
    Y veo el mensaje "Alerta de seguridad: El archivo fue alterado o corrompido durante la transmisión (el hash del servidor no coincide con el de su celular). Por favor, intente de nuevo."
