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

  # It. 46e (R-PRIV-05 reescrita): los rostros se difuminan en el celular, antes de la huella.
  @complexity:high
  Escenario: Los rostros de una foto se difuminan en el celular antes de calcular su huella
    Cuando adjunto una foto donde se ve el rostro de una persona
    Entonces la app me dice que encontró 1 rostro y que lo difuminó
    Y la foto que se envía tiene ese rostro difuminado
    Y el SHA-256 se calcula sobre la foto ya difuminada

  @complexity:low
  Escenario: Una foto sin rostros se envía sin difuminar nada
    Cuando adjunto una foto de la obra donde no se ve a nadie
    Entonces la app me dice que no encontró rostros
    Y me invita a difuminar a mano una persona o una placa, si la ve

  @complexity:medium
  Escenario: Difumino a mano lo que el detector no vio
    Dado que adjunté una foto donde se ve una placa de un vehículo
    Cuando toco la foto sobre la placa
    Entonces esa zona queda difuminada en la foto que se envía

  @complexity:medium @edge
  Escenario: Quito un recuadro que no es un rostro, y la Bandeja lo marca
    Dado que el detector propuso un rostro donde no lo hay
    Cuando marco "No es un rostro"
    Entonces esa zona no se difumina
    Y el Administrador de Organización ve en la Bandeja que quité un difuminado propuesto

  @complexity:medium @edge
  Escenario: Si el detector no carga, la foto se revisa a mano
    Dado que el detector de rostros no pudo cargar en el teléfono
    Cuando adjunto una foto
    Entonces la app me pide revisar la foto y difuminar a mano a las personas que se vean
    Y puedo enviar el reporte

  @complexity:low
  Escenario: La Bandeja dice cuántas zonas se difuminaron
    Dado que el veedor envió una foto con 2 rostros difuminados y 1 zona difuminada a mano
    Cuando el Administrador de Organización la revisa en la Bandeja
    Entonces ve "3 zonas difuminadas en el celular"

  @complexity:low
  Escenario: El servidor guarda la foto tal como llegó del celular, ya difuminada
    Cuando el servidor recibe la foto
    Entonces la guarda byte a byte, sin volver a codificarla

  @complexity:medium @negative
  Escenario: El hash del servidor no coincide con el del teléfono
    Dado que adjunté 1 foto y la app calculó su SHA-256
    Cuando el archivo llega al servidor alterado en un solo byte
    Entonces la evidencia no se encola para el sellado
    Y veo el mensaje "Alerta de seguridad: El archivo fue alterado o corrompido durante la transmisión (el hash del servidor no coincide con el de su celular). Por favor, intente de nuevo."

  @complexity:low
  Escenario: Tomar la foto con la cámara o elegirla de la galería
    Cuando voy a adjuntar la evidencia de un reporte
    Entonces veo "Tomar foto", que abre la cámara trasera del teléfono
    Y veo "Elegir de la galería o un PDF"
