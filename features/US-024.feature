# language: es
@story_id:US-024 @origin:discovery_inicial @priority:2 @epic:EPIC-005
Característica: Validador público de integridad
  Como Verificador Público
  quiero que mi navegador calcule el hash de un archivo y lo compare con Polygon
  para obtener un veredicto que no dependa de la base de datos de GovTrace

  Antecedentes:
    Dado que soy un visitante sin sesión en el validador de "veeduria-smr"
    Y la foto "obra-gaira.jpg" está sellada en el bloque 61234567 el "2026-09-27"

  @complexity:high
  Escenario: Modo libre con un archivo auténtico
    Cuando arrastro "obra-gaira.jpg" al validador libre
    Entonces el hash se calcula en mi navegador sin enviar el archivo al servidor
    Y el navegador recompone la raíz con la prueba de inclusión y la compara con el Smart Contract
    Y veo el banner verde "✅ Archivo Auténtico e Inmutable. Sellado el 2026-09-27 en el bloque #61234567."
    Y veo un enlace a la transacción en Polygonscan

  @complexity:high
  Escenario: Modo contextual con un archivo alterado
    Dado que estoy en la tarjeta de la evidencia de "obra-gaira.jpg" en la línea de tiempo
    Cuando pulso "Verificar Sello Blockchain" y arrastro una copia de "obra-gaira.jpg" con un píxel modificado
    Entonces veo el banner rojo "❌ Archivo Alterado o Falso. Las huellas criptográficas no coinciden con la blockchain."

  @complexity:medium @negative
  Escenario: El modo libre nunca dice "Alterado"
    Cuando arrastro al validador libre una copia de "obra-gaira.jpg" con un píxel modificado
    Entonces veo el mensaje "⚠️ Archivo no encontrado. No hay registro de este documento en GovTrace."
    Y no veo el banner rojo de archivo alterado

  @complexity:high
  Escenario: Modo con prueba adjunta, sin consultar a GovTrace
    Dado que descargué "obra-gaira.jpg" junto con su prueba de inclusión
    Cuando aporto al validador el archivo y su prueba de inclusión
    Entonces la verificación se hace solo contra Polygon sin consultar el API de GovTrace
    Y veo el banner verde de archivo auténtico

  @complexity:low @negative
  Esquema del escenario: Formatos aceptados
    Cuando arrastro al validador el archivo "<archivo>"
    Entonces el archivo es "<resultado>"

    Ejemplos:
      | archivo       | resultado  |
      | foto.jpg      | procesado  |
      | foto.png      | procesado  |
      | acta.pdf      | procesado  |
      | video.mp4     | rechazado  |

  @complexity:low @negative
  Escenario: Archivo de más de 10 MB
    Cuando arrastro al validador un archivo de 12 MB
    Entonces el navegador no calcula el hash
    Y veo el mensaje "El archivo supera el tamaño máximo de 10MB."

  @complexity:medium @negative
  Escenario: Falla de conexión con Polygon
    Dado que el nodo RPC de Polygon no responde
    Cuando arrastro "obra-gaira.jpg" al validador libre
    Entonces veo el mensaje "⏳ Error de conexión con la red Polygon. No se pudo verificar la inmutabilidad en este momento. Intente más tarde."

  @complexity:low
  Escenario: El validador no exige registro
    Cuando uso el validador sin haber iniciado sesión
    Entonces puedo verificar archivos normalmente

  @complexity:high @edge
  Escenario: Sellos de una versión anterior del Smart Contract siguen verificables
    Dado que "acta-2026.pdf" se selló en la versión 1 del Smart Contract
    Y hoy está vigente la versión 2
    Cuando arrastro "acta-2026.pdf" al validador libre
    Entonces veo el banner verde de archivo auténtico

  @complexity:medium @edge
  Escenario: Evidencia sellada que la organización no ha publicado
    Dado que "reporte-oculto.jpg" se selló pero su evidencia está oculta
    Cuando arrastro "reporte-oculto.jpg" al validador libre
    Entonces veo "✅ Archivo Auténtico. (Nota: esta evidencia existe en la blockchain pero la organización aún no la ha publicado)."

  @complexity:medium @edge
  Escenario: Copia de una evidencia retirada después de descargarla
    Dado que descargué "obra-gaira.jpg" y después la organización retiró esa evidencia
    Cuando arrastro "obra-gaira.jpg" al validador libre
    Entonces veo "✅ Archivo Auténtico. (Nota: Esta evidencia fue retirada de la galería pública por la organización, pero su registro criptográfico permanece inalterable)."
