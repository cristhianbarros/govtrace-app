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
