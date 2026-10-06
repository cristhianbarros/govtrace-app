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

  # Iteración 46j — lo que califica la Contraloría (docs/viabilidad-legal.md, R2)
  @complexity:low
  Escenario: El supervisor según SECOP II
    Dado que SECOP II da el nombre del supervisor del contrato
    Cuando descargo el expediente
    Entonces el expediente y la denuncia lo dicen, y si SECOP II no lo da dicen "Sin dato en SECOP II"

  @complexity:low @negative
  Escenario: El documento del supervisor no se guarda
    Dado que SECOP II publica el nombre y el documento del supervisor
    Cuando GovTrace sincroniza el contrato
    Entonces guarda el nombre, nunca el documento

  @complexity:medium
  Escenario: El origen de los recursos
    Dado que SECOP II desglosa el valor del contrato en sus fuentes
    Cuando descargo el expediente
    Entonces dice el orden de la entidad y cada fuente con dinero, en pesos
    Y si SECOP II no desglosa nada, dice "Sin dato en SECOP II"

  @complexity:medium
  Escenario: Con recursos de la Nación, la Contraloría General
    Dado que el contrato tiene recursos del Presupuesto General de la Nación
    Cuando descargo el expediente
    Entonces la denuncia orienta hacia la Contraloría General de la República
    Y aclara que es una orientación de GovTrace: la competencia la define la Contraloría
    Y el derecho de petición no lleva esa sección

  @complexity:medium
  Escenario: Con recursos propios del territorio, su contraloría
    Dado que el contrato tiene recursos propios del territorio
    Cuando descargo el expediente
    Entonces la denuncia orienta hacia la contraloría de ese territorio, sin perjuicio del control prevalente de la General

  @complexity:medium
  Escenario: Sin el origen de los recursos, la denuncia no lo inventa
    Dado que SECOP II no informa el origen de los recursos
    Cuando descargo el expediente
    Entonces la denuncia lo dice y remite a preguntar a la entidad o a presentarla ante la Contraloría General

  @complexity:medium @negative
  Escenario: El lugar de la obra, aproximado
    Cuando descargo el expediente
    Entonces dice el municipio y el departamento, el punto aproximado a unos 100 m con el enlace a la obra en el mapa, y un espacio para la dirección
    Y nunca el punto exacto

  @complexity:low
  Escenario: Las normas incumplidas las escribe la veeduría
    Cuando descargo la denuncia
    Entonces deja líneas en blanco para las normas o cláusulas que se consideran incumplidas, con un ejemplo
    Y GovTrace no sugiere ninguna como hallazgo
