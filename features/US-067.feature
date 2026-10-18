# language: es
@story_id:US-067 @origin:usuario @priority:1 @epic:EPIC-009
Característica: Saber dónde estoy y volver a GovTrace desde cualquier veeduría
  Como persona que pasa del Inicio de GovTrace al sitio de una veeduría, o al revés
  quiero ver en cada pantalla dónde estoy y poder volver a cada nivel con un toque
  para no quedarme atrapado en una veeduría sin saber cómo regresar a GovTrace o a mi panel

  Antecedentes:
    Dado que "Veeduría BAF Medellín" tiene su sitio en su subdominio

  @complexity:low
  Escenario: En el sitio de una veeduría, la ruta lleva de vuelta a GovTrace
    Cuando abro el mapa de "Veeduría BAF Medellín"
    Entonces veo arriba la ruta "GovTrace › Veeduría BAF Medellín"
    Y "GovTrace" me lleva al Inicio de GovTrace, en su propio dominio
    Y "Veeduría BAF Medellín" no es un enlace, porque es donde estoy

  @complexity:low
  Escenario: En una pantalla del sitio, la ruta suma la pantalla
    Cuando abro la página de una obra de "Veeduría BAF Medellín"
    Entonces veo la ruta "GovTrace › Veeduría BAF Medellín › Obra", con la obra al final y sin enlace
    Y "Veeduría BAF Medellín" me lleva a su mapa

  @complexity:low
  Escenario: En el panel de la veeduría, la ruta lleva a su sitio y a GovTrace
    Dado que soy la Administradora de "Veeduría BAF Medellín"
    Cuando abro "Contratos" en su panel
    Entonces veo la ruta "GovTrace › Veeduría BAF Medellín › Panel › Contratos"
    Y "Panel" me lleva a la Bandeja

  @complexity:low
  Escenario: En el panel global, la ruta lleva al Inicio de GovTrace
    Dado que soy Super Administrador
    Cuando abro "Sellado" en el panel global
    Entonces veo la ruta "GovTrace › Panel global › Sellado"
    Y "Panel global" me lleva a Organizaciones

  @complexity:low
  Escenario: En el celular, la ruta se resume en un enlace a la pantalla anterior
    Dado que uso un celular
    Cuando abro la página de una obra de "Veeduría BAF Medellín"
    Entonces veo un solo enlace, "← Veeduría BAF Medellín", en lugar de la ruta completa
    Y en la Bandeja del panel veo "← Veeduría BAF Medellín", porque "Panel" es la misma Bandeja

  # It. 62e (2026-10-18, pedido por el usuario): antes, Nuevo reporte no tenía ruta, para que un toque por error no
  # sacara al veedor a mitad de camino. Ahora la tiene, y salir con un reporte empezado pregunta antes.
  @complexity:low
  Escenario: En Nuevo reporte, la ruta aparece, y salir con un reporte empezado pregunta antes
    Dado que soy veedor de "Veeduría BAF Medellín"
    Cuando abro "Nuevo reporte"
    Entonces veo "GovTrace › Veeduría BAF Medellín › Nuevo Reporte", y en "Mis Reportes", "GovTrace › Veeduría BAF Medellín › Mis Reportes"
    Y si ya elegí la obra y toco la ruta o una pestaña, la app pregunta "¿Salir sin enviar el reporte? Se perderá lo que lleva."
    Y si digo que no, sigo en el reporte, con lo que llevaba

  @complexity:low
  Escenario: Desde Organizaciones, el Super Administrador abre el sitio de cada veeduría
    Dado que soy Super Administrador
    Cuando abro "Organizaciones"
    Entonces cada veeduría tiene "Ver su sitio", que abre su sitio en su subdominio
    Pero una veeduría dada de baja no lo tiene, porque su sitio está fuera de línea

  @complexity:low
  Escenario: En el Inicio de GovTrace, el menú de la cuenta lleva al panel global
    Dado que soy Super Administrador y tengo la sesión abierta
    Cuando abro el Inicio de GovTrace
    Entonces el menú de mi cuenta ofrece "Ir al panel global"

  @complexity:low
  Escenario: En el acceso de una veeduría, el Super Administrador sabe dónde está el suyo
    Cuando abro "Iniciar sesión" en el sitio de "Veeduría BAF Medellín"
    Entonces leo "¿Es Super Administrador de GovTrace? Su acceso está en el Inicio de GovTrace."
    Y su enlace me lleva al acceso de GovTrace

  @complexity:medium
  Escenario: El Super Administrador vuelve a su panel desde el sitio de una veeduría
    Dado que soy Super Administrador y tengo la sesión abierta en GovTrace
    Cuando abro el sitio de "Veeduría BAF Medellín" con "Ver su sitio"
    Y toco "GovTrace" en la ruta
    Entonces llego al Inicio de GovTrace con mi sesión
    Y desde el menú de mi cuenta, "Ir al panel global", vuelvo a Organizaciones
