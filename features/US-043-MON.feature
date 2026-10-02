# language: es
@story_id:US-043-MON @origin:analisis_completitud @priority:2 @epic:EPIC-009
Característica: Consulta del log de auditoría
  Como Super Administrador o Administrador de Organización
  quiero consultar el log de auditoría según mi alcance
  para saber quién hizo qué, cuándo y qué cambió

  Antecedentes:
    Dado que el log tiene 3 entradas de "Veeduría Ciudadana Santa Marta" y 2 de "Veeduría Ciénaga"

  @complexity:low
  Escenario: El Super Administrador ve todo el log
    Dado que estoy autenticado como Super Administrador en el panel global
    Cuando abro el log de auditoría
    Entonces veo las 5 entradas
    Y cada entrada muestra quién, cuándo, la acción, el valor anterior y el nuevo

  @complexity:low
  Escenario: El Administrador de Organización ve solo lo de su organización
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando abro el log de auditoría
    Entonces veo solo las 3 entradas de mi organización

  @complexity:low @negative
  Escenario: Un Administrador de Organización no accede a entradas de otra organización
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando intento abrir una entrada del log de "Veeduría Ciénaga"
    Entonces la acción es rechazada

  # It. 46d: filtros y frases.
  @complexity:medium
  Escenario: El log se filtra por fecha, por quién lo hizo, por tipo de acción y, en el panel global, por organización
    Dado que estoy autenticado como Super Administrador en el panel global
    Cuando filtro el log por fechas, por quién lo hizo, por un tipo de acción y por una organización
    Entonces veo solo las entradas que cumplen todos los filtros a la vez
    Y la paginación conserva los filtros
    Y "Quitar filtros" vuelve a mostrarlo todo

  @complexity:low @negative
  Escenario: El Administrador de Organización no filtra por organización ni ve otra
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando pido el log filtrado por "Veeduría Ciénaga"
    Entonces sigo viendo solo las entradas de mi organización

  @complexity:low @negative
  Escenario: Un filtro inválido se rechaza con un mensaje
    Dado que estoy autenticado como Super Administrador en el panel global
    Cuando filtro por una fecha que no existe o por un tipo de acción desconocido
    Entonces el filtro se rechaza y dice cuál es el problema

  @complexity:low
  Escenario: Cada entrada se lee como una frase, con el detalle desplegable
    Dado que "Ana Directora" desactivó a "luis@govtrace.org"
    Cuando abro el log de auditoría
    Entonces la entrada dice "Ana Directora desactivó a un Super Administrador (luis@govtrace.org)"
    Y el valor anterior y el nuevo se despliegan aparte

  @complexity:low
  Escenario: Toda acción que se registra tiene su etiqueta y su tipo
    Cuando se recorren las acciones que el código registra
    Entonces cada una tiene una etiqueta en palabras y pertenece a un tipo de acción
