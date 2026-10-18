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
  Escenario: Una foto de 50 megapíxeles se prepara sin agotar la memoria del celular
    Cuando adjunto una foto de 8160x6120 px tomada con el celular en vertical
    Entonces la app la decodifica ya reducida, a 1530x2040 px, sin pasar por su tamaño completo
    Y la foto queda derecha, con lado mayor de 1920 px
    Y si el navegador no puede decodificarla reducida, la decodifica entera, como antes

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

  # 2026-10-04, pedido por el usuario: el aviso se nota, y el recuadro amarillo no viaja en la foto.
  @complexity:low
  Escenario: Un rostro que el detector no vio se difumina con un toque
    Cuando reviso una foto antes de adjuntarla
    Entonces encima de la foto leo, destacado, "¿Ve a alguien sin difuminar? Tóquelo en la foto."
    Y leo "El recuadro amarillo solo marca la zona: no aparece en la foto que se envía."

  # It. 46f: una grilla de 4 × 4 ventanas, además de la foto entera.
  @complexity:medium @edge
  Escenario: Un rostro lejano también se difumina
    Dado que adjunto una foto de 1920 px de ancho donde, lejos, se ven personas
    Y sus rostros miden unos 50 px de ancho, también en el centro de la foto
    Cuando reviso la foto antes de adjuntarla
    Entonces la app los encontró y ya están difuminados

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
    Entonces veo "Tomar foto", que abre la cámara trasera
    Y veo "Elegir de la galería o un PDF"

  # It. 62c (2026-10-18, reportado por el usuario): con la app de la cámara al frente, el
  # celular se quedaba sin memoria, cerraba GovTrace y, al volver, se reiniciaba sin la foto.
  @complexity:medium
  Escenario: La foto se toma dentro de GovTrace, sin salir de la app
    Cuando toco "Tomar foto"
    Entonces la cámara trasera se abre dentro de GovTrace, sin abrir la app de la cámara del teléfono
    Y al tocar "Tomar la foto" la cámara se apaga y la foto pasa a revisión, como una de la galería
    Y el botón "atrás" del teléfono cierra la cámara sin salir del formulario
    Y si el navegador no da la cámara, puedo abrir la cámara del teléfono, como antes

  # It. 62d (2026-10-18, reportado por el usuario): la revisión salía debajo, fuera de la pantalla.
  @complexity:low
  Escenario: La foto para revisar queda a la vista al tomarla
    Cuando tomo una foto o la elijo de la galería
    Entonces la pantalla se mueve sola hasta "Revise la foto antes de adjuntarla", con lo que encontró el detector
    Y si algo no se pudo adjuntar, hasta el aviso que dice por qué

  # It. 62f (2026-10-18, pedido por el usuario): entre "Tomar la foto" y la revisión pasaba casi un segundo sin ver nada.
  @complexity:medium
  Escenario: La foto tomada pasa a revisión sin esperas de más
    Cuando toco "Tomar la foto"
    Entonces la cámara se cierra al instante y veo la foto con "Revisando la foto: buscando rostros para difuminarlos…"
    Y la foto pasa a revisión tal como la vio la cámara, sin convertirla antes en un archivo
    Y el detector de rostros empezó a cargar al abrir la cámara, mientras encuadraba

