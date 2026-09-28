# language: es
@story_id:US-012 @origin:discovery_inicial @priority:1 @epic:EPIC-009
Característica: Configurar el territorio de vigilancia de la organización
  Como Administrador de Organización
  quiero configurar las ciudades y/o departamentos que vigila mi organización
  para que el mapa y las obras disponibles correspondan a nuestro territorio

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"

  @complexity:medium
  Escenario: Configuración de varios territorios
    Cuando busco "Magd" en el selector de territorios
    Y selecciono el departamento "Magdalena" (DIVIPOLA 47)
    Y selecciono el municipio "Medellín" (DIVIPOLA 05001)
    Y guardo el territorio
    Entonces el territorio de mi organización es "Magdalena" y "Medellín"
    Y el mapa y la sincronización SECOP quedan restringidos a esos territorios

  @complexity:low @negative
  Escenario: No se puede guardar un territorio vacío
    Cuando guardo el territorio sin seleccionar ningún departamento ni municipio
    Entonces el cambio es rechazado
    Y veo el mensaje "Debe seleccionar al menos un departamento o municipio para delimitar el territorio de vigilancia."

  @complexity:low @negative
  Escenario: No se acepta un código geográfico que no esté en la tabla DIVIPOLA
    Cuando intento guardar el territorio con el código "99999"
    Entonces el cambio es rechazado

  @complexity:medium @edge
  Escenario: Quitar una ciudad no borra lo que ya se registró en ella
    Dado que mi territorio incluye "Santa Marta" (47001) y "Ciénaga" (47189)
    Y en "Ciénaga" hay 4 evidencias selladas, 2 de ellas publicadas
    Cuando quito "Ciénaga" de mi territorio y guardo
    Entonces las 4 evidencias siguen en la base de datos y en la blockchain
    Y dejan de aparecer en el mapa de mis veedores
    Pero las 2 evidencias publicadas siguen visibles en el mapa público
    Y la sincronización SECOP deja de traer nuevos contratos de "Ciénaga"
