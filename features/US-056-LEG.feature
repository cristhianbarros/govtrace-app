# language: es
@story_id:US-056-LEG @origin:proceso_actual @priority:1 @epic:EPIC-008
Característica: El expediente de una obra, con el derecho de petición y la denuncia pre-llenados
  Como Administrador de Organización
  quiero descargar desde una obra un expediente con sus evidencias, sus sellos y las plantillas pre-llenadas
  para llevar la evidencia de GovTrace al proceso formal del control social

  Antecedentes:
    Dado que soy el Administrador de "veeduria-smr"
    Y la obra "Pavimentación Calle 30" tiene dos evidencias publicadas

  @complexity:medium
  Escenario: Descargar el expediente de una obra
    Cuando pulso "Descargar expediente" en la obra
    Entonces descargo un ZIP con "01-expediente.pdf", "02-derecho-de-peticion.pdf", "03-denuncia-contraloria.pdf" y "LEAME.txt"
    Y en "evidencias/" cada archivo publicado, con su prueba de inclusión

  @complexity:high
  Escenario: Los archivos del expediente son los que se sellaron
    Cuando descargo el expediente
    Entonces cada archivo tiene el SHA-256 con que se selló
    Y su prueba de inclusión es la misma de la descarga pública

  @complexity:high
  Escenario: Cada archivo se cita como Prueba Pericial Criptográfica
    Cuando descargo el expediente
    Entonces bajo "Prueba Pericial Criptográfica" cada archivo lleva su SHA-256, la raíz de Merkle, la transacción y el ledger de Stellar, y la fecha del sello
    Y dice cómo verificarlo sin GovTrace, con la Ley 527 de 1999

  @complexity:medium
  Escenario: El derecho de petición llega pre-llenado
    Cuando descargo el expediente
    Entonces el derecho de petición va dirigido a la entidad contratante y cita el contrato, la veeduría, la Ley 1755 de 2015 y la Ley 850 de 2003
    Y pide los informes de supervisión o interventoría y el estado de la ejecución
    Y deja en blanco el nombre, la identificación y la firma de quien lo presenta

  @complexity:medium
  Escenario: La denuncia ante la Contraloría llega pre-llenada
    Cuando descargo el expediente
    Entonces la denuncia cita la Ley 1757 de 2015, los hechos de la obra y la Prueba Pericial Criptográfica
    Y dice por qué canales radicarla y cuándo es competente la contraloría territorial

  @complexity:medium @negative
  Escenario: Solo van las evidencias publicadas
    Dado que la obra tiene además una evidencia oculta, una rechazada y una retirada
    Cuando descargo el expediente
    Entonces solo están las dos evidencias publicadas

  @complexity:low
  Escenario: Una obra sin evidencias publicadas también tiene expediente
    Dado que una obra no tiene evidencias publicadas
    Cuando descargo su expediente
    Entonces el expediente dice que aún no hay evidencias publicadas
    Y trae el derecho de petición para pedir información

  @complexity:low
  Escenario: Cada descarga del expediente queda registrada
    Cuando descargo el expediente
    Entonces el log de auditoría registra "dossier.downloaded" con la obra, el número de archivos y quién lo descargó

  @complexity:low @negative
  Escenario: Solo el Administrador descarga el expediente
    Cuando un veedor pide el expediente de una obra
    Entonces se le niega

  @complexity:low
  Escenario: Las plantillas identifican a la veeduría
    Dado que la veeduría no tiene NIT y está inscrita con "Resolución 012 de 2026" ante "Personería de Santa Marta"
    Cuando descargo el expediente
    Entonces el derecho de petición y la denuncia la identifican por su inscripción
