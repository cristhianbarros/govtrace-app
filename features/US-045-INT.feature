# language: es
@story_id:US-045-INT @origin:analisis_completitud @priority:2 @epic:EPIC-004
Característica: Agrupar varios contratos en una ficha de obra
  Como Administrador de Organización
  quiero agrupar varios contratos de tipo Obra en una misma ficha
  para que una obra física con varias fases se vea y se reporte como una sola

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Y existen los contratos de obra "CO1.PCCNTR.1111111" y "CO1.PCCNTR.3333333" en "Santa Marta"

  @complexity:medium
  Escenario: Agrupación de dos contratos
    Cuando agrupo "CO1.PCCNTR.1111111" y "CO1.PCCNTR.3333333" en la ficha "Acueducto Gaira"
    Entonces la vista pública de "Acueducto Gaira" muestra los 2 contratos
    Y los reportes de la obra quedan vinculados a la ficha completa

  @complexity:medium @negative
  Escenario: El pin toma el peor estado de los contratos agrupados
    Dado que "CO1.PCCNTR.1111111" está en plazo y "CO1.PCCNTR.3333333" venció y sigue "En ejecución"
    Cuando agrupo ambos en la ficha "Acueducto Gaira"
    Entonces el pin de "Acueducto Gaira" es rojo

  @complexity:low @negative
  Escenario: Solo se agrupan contratos del territorio de la organización
    Dado que existe el contrato de obra "CO1.PCCNTR.5555555" en "Medellín" (05001)
    Cuando intento agruparlo con "CO1.PCCNTR.1111111"
    Entonces la acción es rechazada

  @complexity:medium
  Escenario: Las obras de la veeduría llegan de a 20 y Ver 20 más trae las siguientes
    Dado que la organización tiene 45 obras
    Cuando abro "Obras"
    Entonces veo 20 obras y "45 obras"
    Cuando toco "Ver 20 más"
    Entonces veo 40 obras

  @complexity:low
  Escenario: Busco una obra por su nombre o por el objeto de sus contratos
    Dado que la organización tiene las obras "Acueducto Gaira" y "Cancha de Bonda"
    Cuando escribo "acueducto" en "Buscar una obra"
    Entonces veo solo "Acueducto Gaira"
    Y si ninguna coincide, veo "Ninguna obra con esa búsqueda."

  @complexity:medium @edge
  Escenario: Corregir ubicación desde la Bandeja abre la obra aunque no esté en la primera página
    Dado que la obra "Cancha de Bonda" es la número 30 de la lista
    Cuando toco "Corregir ubicación" en una evidencia de esa obra, en la Bandeja
    Entonces veo la obra "Cancha de Bonda" lista para corregir, con "Mostrando la obra que va a corregir."
    Y puedo tocar "Ver todas las obras"
