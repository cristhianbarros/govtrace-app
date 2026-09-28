# language: es
@story_id:US-046-INT @origin:analisis_completitud @priority:2 @epic:EPIC-005
Característica: Script de verificación independiente
  Como Verificador Público
  quiero un script de verificación en el repositorio abierto que compruebe un archivo con su prueba solo contra Polygon
  para verificar aunque GovTrace no esté disponible

  Antecedentes:
    Dado que descargué "obra-gaira.jpg" y su prueba de inclusión
    Y GovTrace no está disponible

  @complexity:medium
  Escenario: Verificación de un archivo auténtico
    Cuando ejecuto el script con "obra-gaira.jpg" y su prueba
    Entonces el script calcula el hash, recompone la raíz y la encuentra en el Smart Contract
    Y informa que el archivo es auténtico

  @complexity:medium @negative
  Escenario: Verificación de un archivo alterado
    Cuando ejecuto el script con una copia alterada de "obra-gaira.jpg" y su prueba
    Entonces informa que el archivo no coincide con el sello

  @complexity:medium @negative
  Escenario: El script no depende de GovTrace
    Cuando ejecuto el script con "obra-gaira.jpg" y su prueba
    Entonces no se hace ninguna petición a servidores de GovTrace

  @complexity:medium @edge
  Escenario: Sello de una versión anterior del Smart Contract
    Dado que "obra-gaira.jpg" se selló en la versión 1 del Smart Contract
    Cuando ejecuto el script con el archivo y su prueba
    Entonces el script consulta todas las direcciones históricas del contrato
    Y informa que el archivo es auténtico
