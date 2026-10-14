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
    Y cada tarjeta muestra fecha y hora, clasificación, comentario, miniaturas y el botón "Comprobar que es original"

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
    Entonces la tarjeta dice "Ubicación aproximada, a unos 100 m del lugar." sin mostrar coordenadas numéricas, que parecerían más precisas de lo que son

  @complexity:medium @edge
  Escenario: Una evidencia retirada queda como lápida
    Dado que la organización retiró una de las 3 evidencias publicadas
    Cuando hago clic en el pin de "Pavimentación Calle 30"
    Entonces sigo viendo 3 tarjetas
    Y la retirada oculta sus fotos y su comentario y muestra "🚫 Evidencia retirada por la organización por incumplimiento de políticas."
    Y su sello criptográfico sigue disponible

  @complexity:low
  Escenario: La obra muestra su estado y por qué
    Dado que la evidencia publicada más reciente de la obra es de "Abandono"
    Cuando abro la vista de la obra
    Entonces veo su estado "En riesgo" con su signo
    Y la razón en palabras: "El reporte publicado más reciente, del <fecha>, es de abandono."

  # It. 60: al tocar un pin, una tarjeta con la obra, sin salir del mapa.
  @complexity:medium
  Escenario: Al tocar un pin se abre la tarjeta de la obra
    Dado que la obra "Pavimentación Calle 30" tiene fotos publicadas
    Cuando toco el pin de "Pavimentación Calle 30"
    Entonces sigo en el mapa, y veo su tarjeta con su estado y por qué, su nombre, su municipio y su valor
    Y la fecha de su evidencia publicada más reciente y hasta 3 miniaturas de sus fotos publicadas
    Y los botones "Ver la obra" e "Informar a la veeduría"

  @complexity:low
  Escenario: Una obra sin evidencias publicadas lo dice en su tarjeta
    Dado que la obra "Acueducto Gaira" tiene ubicación y ninguna evidencia publicada
    Cuando toco su pin
    Entonces su tarjeta dice "Sin evidencias publicadas todavía." y no tiene miniaturas

  @complexity:low
  Escenario: Una miniatura lleva a esa evidencia en la página de la obra
    Cuando toco una miniatura de la tarjeta de "Pavimentación Calle 30"
    Entonces llego a la página de la obra, en esa evidencia

  @complexity:medium
  Escenario: La tarjeta se usa con el teclado
    Cuando llego al pin de "Pavimentación Calle 30" con el teclado y oprimo Enter
    Entonces se abre su tarjeta y el foco pasa a ella
    Cuando oprimo Esc
    Entonces la tarjeta se cierra y el foco vuelve al pin

  @complexity:medium @negative
  Escenario: Las miniaturas son livianas y solo de lo publicado
    Cuando el Administrador publica una evidencia con una foto de 1920 px
    Entonces su miniatura tiene un lado mayor de 320 px, y el archivo sellado no cambia
    Y la miniatura de una evidencia oculta o rechazada no se puede ver

  @complexity:medium @negative
  Escenario: La miniatura se va con la foto
    Dado que una evidencia publicada tiene su miniatura
    Cuando el Administrador la retira, o se borran los archivos de la organización tras su baja
    Entonces su miniatura se borra y ya no se puede ver

  @complexity:low
  Escenario: Las evidencias ya publicadas reciben su miniatura
    Dado que hay fotos publicadas antes de que existieran las miniaturas
    Cuando se corre el comando de las miniaturas
    Entonces cada foto publicada tiene la suya, y correrlo otra vez no hace nada

  @complexity:low @edge
  Escenario: Sin miniatura, la tarjeta muestra la foto completa
    Dado que no se pudo generar la miniatura de una foto publicada
    Entonces la evidencia sigue publicada, y su tarjeta y su página muestran la foto completa
