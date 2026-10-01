# language: es
@story_id:US-060-MON @origin:mapa_funcional @priority:2 @epic:EPIC-004
Característica: El resumen diario de las evidencias por revisar
  Como Administrador de Organización
  quiero recibir una vez al día un correo con las evidencias que esperan mi revisión
  para no tener que entrar a buscarlas

  Antecedentes:
    Dado que la organización "Veeduría Ciudadana Santa Marta" está activa y tiene a la Administradora "Ana Pérez"

  @complexity:low
  Escenario: El resumen diario llega con las evidencias por revisar
    Dado que hay 3 evidencias selladas que siguen ocultas
    Cuando son las 07:00 en Colombia
    Entonces "Ana Pérez" recibe un correo con "3 evidencias de sus veedores esperan su revisión"
    Y el correo tiene un botón a su Bandeja

  @complexity:low @negative
  Escenario: Sin evidencias por revisar no hay resumen
    Dado que no hay evidencias selladas que sigan ocultas
    Cuando son las 07:00 en Colombia
    Entonces "Ana Pérez" no recibe ningún correo

  @complexity:low @edge
  Escenario: Solo cuentan las evidencias que ya se pueden publicar
    Dado que hay 1 evidencia sellada y oculta, 1 todavía en cola de sellado y 1 publicada
    Cuando son las 07:00 en Colombia
    Entonces "Ana Pérez" recibe un correo con "1 evidencia de sus veedores espera su revisión"

  @complexity:low @negative
  Escenario: El resumen no llega a quien no puede revisar
    Dado que hay 2 evidencias selladas que siguen ocultas
    Y la organización tiene un Administrador desactivado, uno que no activó su cuenta y un veedor
    Cuando son las 07:00 en Colombia
    Entonces solo "Ana Pérez" recibe el correo

  @complexity:low @negative
  Escenario: Una organización suspendida no recibe resumen
    Dado que hay 2 evidencias selladas que siguen ocultas
    Y la organización está suspendida
    Cuando son las 07:00 en Colombia
    Entonces nadie recibe el correo
