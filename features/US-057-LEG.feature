# language: es
@story_id:US-057-LEG @origin:proceso_actual @priority:1 @epic:EPIC-006
Característica: Declarar que no tengo impedimentos para ser veedor
  Como Veedor de Campo
  quiero declarar que no estoy en ninguno de los impedimentos que la ley fija para ser veedor
  para que mis reportes no queden viciados por un conflicto de interés

  Antecedentes:
    Dado que la organización "veeduria-smr" está activa

  @complexity:medium
  Escenario: El veedor declara sus impedimentos al activar su cuenta
    Dado que me invitaron como veedor de campo
    Cuando abro el enlace de mi invitación
    Entonces veo los impedimentos del artículo 19 de la Ley 850 de 2003
    Cuando marco "Declaro que no estoy en ninguno de estos casos" y creo mi contraseña
    Entonces mi cuenta queda activa y mi declaración registrada con su fecha

  @complexity:low @negative
  Escenario: Sin la declaración no se activa la cuenta de un veedor
    Dado que me invitaron como veedor de campo
    Cuando creo mi contraseña sin marcar la declaración
    Entonces veo "Para ser veedor, declare que no está en ninguno de estos casos."
    Y mi invitación sigue pendiente

  @complexity:low
  Escenario: El Administrador activa su cuenta sin declarar impedimentos de veedor
    Dado que me asignaron como Administrador inicial
    Cuando abro el enlace de mi invitación
    Entonces no se me pide la declaración

  @complexity:medium
  Escenario: Un veedor que ya tenía cuenta declara antes de su próximo reporte
    Dado que soy un veedor activo que no ha declarado sus impedimentos
    Cuando abro "Nuevo Reporte"
    Entonces se me lleva a la declaración
    Cuando declaro que no estoy en ninguno de los casos
    Entonces llego a "Nuevo Reporte"

  @complexity:medium @negative
  Escenario: Sin declarar no se recibe un reporte
    Dado que soy un veedor activo que no ha declarado sus impedimentos
    Cuando envío un reporte
    Entonces se rechaza con "Antes de reportar, declare que no tiene impedimentos para ser veedor (Ley 850 de 2003, art. 19)."
    Y el reporte no se descarta de un celular sin conexión

  @complexity:low
  Escenario: La declaración queda en el log de auditoría
    Cuando un veedor declara que no tiene impedimentos
    Entonces el log de auditoría registra "observer.impediments_declared" con el veedor y la fecha

  @complexity:low
  Escenario: El Administrador ve qué veedores declararon
    Dado que un veedor declaró y otro no
    Cuando el Administrador abre "Veedores"
    Entonces ve quién declaró no tener impedimentos y cuándo, y quién aún no
