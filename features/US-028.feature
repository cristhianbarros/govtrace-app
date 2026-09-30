# language: es
@story_id:US-028 @origin:discovery_inicial @priority:3 @epic:EPIC-004
Característica: Filtros del mapa público
  Como Verificador Público
  quiero filtrar los pines del mapa
  para enfocar mi análisis

  Antecedentes:
    Dado que soy un visitante sin sesión en el mapa público de "veeduria-smr"

  @complexity:medium
  Escenario: Filtrar por estado, fechas, presupuesto y municipio
    Cuando filtro por estado "En Riesgo", evidencias de septiembre de 2026, presupuesto mayor a 1000000000 y municipio "Santa Marta"
    Y aplico los filtros
    Entonces solo veo pines de obras que cumplen los 4 filtros

  @complexity:low @negative
  Escenario: No se puede aplicar sin elegir ningún filtro
    Cuando no selecciono ningún valor de filtro
    Entonces el botón "Aplicar" está deshabilitado

  @complexity:low @negative
  Escenario: Ninguna obra coincide
    Cuando filtro por municipio "Santa Marta" y presupuesto mayor a 900000000000
    Y aplico los filtros
    Entonces veo el mensaje "No se encontraron obras o evidencias que coincidan con estos filtros en este territorio."

  @complexity:low
  Escenario: Buscar una obra por su nombre
    Cuando elijo ver las obras como "Lista" y escribo "cienaga"
    Entonces veo solo las obras cuyo nombre lo contiene, aunque lleve tilde
    Y cada una con su estado en palabras y su municipio
