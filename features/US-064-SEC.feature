# language: es
@story_id:US-064-SEC @origin:pregunta_del_usuario @priority:1 @epic:EPIC-004
Característica: Identificadores públicos que no se pueden recorrer
  Como veeduría que publica en GovTrace
  quiero que las URL y el API no lleven el número consecutivo de mis registros
  para no revelar cuántos tengo ni dejar que alguien recorra lo público en orden

  Antecedentes:
    Dado que "Veeduría Santa Marta" tiene una obra con un reporte sellado y publicado

  @complexity:low
  Escenario: La página pública de una obra se abre con su identificador público
    Cuando un ciudadano abre la obra desde el mapa
    Entonces la URL lleva un identificador de 26 caracteres, no un número
    Y la foto, la descarga, la prueba y el recibo de su evidencia también

  @complexity:medium
  Escenario: Un enlace viejo, con número, ya no abre nada
    Dado que alguien guardó el enlace de una obra, de un recibo o de una invitación con su número
    Cuando lo abre
    Entonces ve que no existe, sin importar si ese registro existe o es público
    Y solo el enlace con su identificador público abre

  @complexity:medium
  Escenario: Ninguna ruta de la app lleva el número de un registro
    Cuando se recorren todas las rutas de la app
    Entonces ninguna recibe el número de una obra, un reporte, una evidencia, un informe ciudadano, un usuario o una solicitud de alta
    Y no queda ninguna ruta para los enlaces viejos
    Y las rutas internas responden como no encontrado si reciben un número

  @complexity:low
  Escenario: El mapa, la línea de tiempo y los datos abiertos exponen el identificador público
    Cuando un ciudadano consulta el mapa, la línea de tiempo de la obra o los datos abiertos
    Entonces cada obra, reporte y evidencia lleva su identificador público y ninguno lleva su número

  @complexity:medium
  Escenario: La invitación lleva el identificador público del usuario
    Dado que un veedor o un Super Administrador recibe su invitación por correo
    Cuando abre el enlace
    Entonces llega a crear su contraseña, con su token

  @complexity:high
  Escenario: Un reporte guardado sin conexión antes del cambio se envía igual
    Dado que un veedor guardó un reporte en su celular, sin conexión, antes del cambio
    Cuando vuelve la conexión y se envía
    Entonces se recibe para la misma obra, que el reporte nombra por su contrato de SECOP

  @complexity:high
  Escenario: Lo ya sellado se sigue verificando
    Dado que un reporte se selló antes del cambio
    Cuando se sella otro reporte de la misma obra después del cambio
    Entonces los dos tienen la misma referencia de la obra en Stellar
    Y su prueba de inclusión no lleva ningún número
