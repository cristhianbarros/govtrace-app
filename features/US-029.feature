# language: es
@story_id:US-029 @origin:discovery_inicial @priority:2 @epic:EPIC-004
Característica: Línea de tiempo de evidencias publicadas de una obra
  Como Verificador Público
  quiero ver la línea de tiempo de evidencias publicadas de una obra
  para comprobar su evolución o estancamiento

  Antecedentes:
    Dado que soy un visitante sin sesión en el mapa público de "veeduria-smr"
    Y la obra "Pavimentación Calle 30" tiene 3 evidencias publicadas y 2 ocultas

  @complexity:medium
  Escenario: Línea de tiempo cargada al hacer clic en el pin
    Cuando hago clic en el pin de "Pavimentación Calle 30"
    Entonces se cargan en ese momento los datos del contrato y la línea de tiempo
    Y veo las 3 evidencias publicadas ordenadas por fecha
    Y cada tarjeta muestra fecha y hora, clasificación, comentario, miniaturas y el botón "Verificar Sello Blockchain"

  @complexity:low
  Escenario: Visor de fotos
    Cuando hago clic en el pin de "Pavimentación Calle 30"
    Y toco la miniatura de una foto
    Entonces se abre el visor con la foto

  @complexity:low @negative
  Escenario: Las evidencias ocultas no aparecen
    Cuando hago clic en el pin de "Pavimentación Calle 30"
    Entonces no veo ninguna de las 2 evidencias ocultas

  @complexity:low @negative
  Escenario: Las coordenadas de cada evidencia se muestran aproximadas
    Dado que una evidencia se capturó en 11.240812, -74.199034
    Cuando veo su tarjeta en la línea de tiempo
    Entonces si se muestran coordenadas tienen una precisión de unos 100 m y no las exactas

  @complexity:medium @edge
  Escenario: Una evidencia retirada queda como lápida
    Dado que la organización retiró una de las 3 evidencias publicadas
    Cuando hago clic en el pin de "Pavimentación Calle 30"
    Entonces sigo viendo 3 tarjetas
    Y la retirada oculta sus fotos y su comentario y muestra "🚫 Evidencia retirada por la organización por incumplimiento de políticas."
    Y su sello criptográfico sigue disponible
