# language: es
@story_id:US-015 @origin:discovery_inicial @priority:1 @epic:EPIC-001
Característica: Listado de contratos del territorio para el administrador
  Como Administrador de Organización
  quiero ver un listado de los contratos sincronizados en mi territorio
  para planificar a qué obras enviar a mis veedores

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Y mi territorio es el departamento "Magdalena" (47)

  @complexity:medium
  Escenario: Tabla paginada ordenada por fecha de firma
    Dado que hay 45 contratos de obra en "Magdalena"
    Cuando abro el módulo "Contratos"
    Entonces veo 20 contratos en la primera página ordenados por fecha de firma descendente
    Y cada fila muestra Número de Proceso, Objeto, Contratista, Valor Total, Estado y Fecha de Firma

  @complexity:low
  Escenario: Invertir el orden por valor
    Dado que hay 45 contratos de obra en "Magdalena"
    Cuando abro el módulo "Contratos" y ordeno por "Valor"
    Y vuelvo a ordenar por "Valor"
    Entonces el orden por valor se invierte

  @complexity:low
  Escenario: El objeto largo se trunca con tooltip
    Dado que un contrato tiene un objeto de 120 caracteres
    Cuando abro el módulo "Contratos"
    Entonces veo sus primeros 50 caracteres
    Y al pasar sobre él veo el texto completo

  @complexity:medium @negative
  Escenario: Un departamento incluye la Gobernación y todos sus municipios, y nada de fuera
    Dado que hay contratos de la Gobernación del Magdalena, de "Santa Marta" (47001), de "Ciénaga" (47189) y de "Medellín" (05001)
    Cuando abro el módulo "Contratos"
    Entonces veo los contratos de la Gobernación del Magdalena, de "Santa Marta" y de "Ciénaga"
    Pero no veo los de "Medellín"

  @complexity:low @edge
  Escenario: Territorio aún sin contratos
    Dado que no hay contratos de obra sincronizados en "Magdalena"
    Cuando abro el módulo "Contratos"
    Entonces veo el mensaje "Aún no hay contratos de obra sincronizados para su territorio. La actualización desde SECOP II se ejecuta automáticamente cada madrugada."

  @complexity:low
  Escenario: Buscar un contrato del territorio
    Dado que mi territorio tiene 742 contratos sincronizados
    Cuando busco "trupillos"
    Entonces veo solo los contratos cuyo objeto, contratista, número de proceso o id de SECOP lo contienen
    Y si ninguno coincide, veo "Ningún contrato del territorio coincide con «trupillos»."
