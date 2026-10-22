# language: es
@story_id:US-071 @origin:usuario @priority:1 @epic:EPIC-004
Característica: Cuántas personas ven cada obra
  Como Administrador de una veeduría
  quiero saber cuántas personas ven la página de cada obra
  para saber qué obras le importan a la gente y si compartirlas sirve

  Antecedentes:
    Dado que la veeduría "veeduria-smr" vigila la obra "Pavimentación Calle 30"

  @complexity:low
  Escenario: Quien abre la página de una obra cuenta como una persona que la vio
    Cuando una persona abre la página de la obra
    Entonces la obra suma 1 persona hoy

  @complexity:medium
  Escenario: La misma persona cuenta una vez al día
    Dado que una persona ya abrió hoy la página de la obra
    Cuando la vuelve a abrir el mismo día
    Entonces la obra sigue con 1 persona hoy
    Cuando la abre al día siguiente
    Entonces la obra suma 2 en total

  @complexity:medium
  Escenario: El día es el de Colombia
    Cuando una persona abre la página a las 23:30 y otra vez a las 00:30, hora de Colombia
    Entonces cuenta en los dos días

  @complexity:low
  Escenario: Dos personas distintas cuentan como dos
    Cuando dos personas, con distinta conexión o distinto navegador, abren la página de la obra el mismo día
    Entonces la obra suma 2 personas hoy

  @complexity:high
  Escenario: GovTrace no guarda la IP de quien ve una obra
    Cuando una persona abre la página de la obra
    Entonces GovTrace guarda una huella calculada con la clave del día, sin su IP ni su navegador
    Y la misma persona deja otra huella en otra obra, y otra al día siguiente

  @complexity:high
  Escenario: Al día siguiente solo queda el número
    Dado que ayer 3 personas vieron la página de la obra
    Cuando se cierra el día, a las 00:05
    Entonces se borran las huellas de ayer y la clave de ayer
    Y la obra conserva que ayer la vieron 3 personas

  @complexity:medium
  Escenario: Las huellas y la clave del día no van a los respaldos
    Dado que hoy una persona vio la página de la obra
    Cuando se hace la copia de cada hora
    Entonces la copia trae las tablas de las visitas sin sus huellas ni la clave del día
    Y trae el número de personas de cada día

  @complexity:low @negative
  Escenario: Un robot o la vista previa de un enlace no cuenta
    Cuando WhatsApp, Facebook o un buscador piden la página de la obra
    Entonces la obra no suma a nadie

  @complexity:low @negative
  Escenario: Una obra que no existe no cuenta
    Cuando el navegador avisa de la visita a una obra que no existe
    Entonces responde 404 y no se guarda nada

  @complexity:medium
  Escenario: La veeduría ve cuántas personas vieron cada obra
    Dado que la obra la vieron 3 personas hoy, 12 en los últimos 30 días y 140 en total
    Cuando el Administrador abre "Obras"
    Entonces la obra dice "Personas que vieron su página: 3 hoy · 12 en los últimos 30 días · 140 en total"

  @complexity:low
  Escenario: El público ve cuántas personas han visto la obra
    Dado que 140 personas han visto la obra
    Cuando un ciudadano abre su página
    Entonces ve "140 personas han visto esta obra"

  @complexity:low
  Escenario: Con menos de 10 personas, la página pública no dice el número
    Dado que 9 personas han visto la obra
    Entonces su página pública no dice cuántas
    Y la veeduría sí lo ve en "Obras"

  @complexity:low
  Escenario: La política de datos dice cómo se cuentan las visitas
    Cuando alguien abre la política de tratamiento de datos
    Entonces lee que la IP y el navegador sirven para contar cuántas personas ven una obra, con una huella que cambia cada día y se borra al día siguiente
