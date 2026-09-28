# language: es
@story_id:US-037 @origin:discovery_inicial @priority:1 @epic:EPIC-004
Característica: Retirar una evidencia publicada dejando una lápida
  Como Administrador de Organización
  quiero retirar una evidencia publicada dejando una lápida visible
  para cumplir nuestras políticas sin borrar el rastro

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Y hay una evidencia publicada de la obra "Pavimentación Calle 30"

  @complexity:medium
  Escenario: Retiro con motivo
    Cuando retiro la evidencia con el motivo "Aparece un menor de edad identificable"
    Entonces su tarjeta pública oculta fotos y comentario y muestra "🚫 Evidencia retirada por la organización por incumplimiento de políticas."
    Y su sello criptográfico sigue disponible para auditoría externa
    Y el log registra el motivo, mi ID y la fecha y hora

  @complexity:low @negative
  Escenario: El motivo es obligatorio
    Cuando intento retirar la evidencia sin escribir motivo
    Entonces el retiro no se realiza

  @complexity:low @negative
  Escenario: El retiro no se puede deshacer
    Dado que retiré la evidencia
    Cuando intento volver a publicarla
    Entonces la acción es rechazada

  @complexity:low @negative
  Escenario: Retirar no borra la evidencia
    Cuando retiro la evidencia con el motivo "Solicitud del afectado"
    Entonces la evidencia sigue existiendo en la base de datos con estado "Retirado"

  @complexity:low @negative
  Escenario: Una evidencia nunca publicada no se retira sino que se rechaza
    Dado que hay una evidencia en estado "Oculto"
    Cuando la busco para retirarla
    Entonces solo tengo la opción de rechazarla desde la bandeja

  @complexity:low @negative
  Escenario: El Administrador no puede borrar ni alterar una evidencia sellada
    Cuando intento borrar la evidencia o reemplazar su archivo
    Entonces la acción es rechazada
    Y el archivo y su sello permanecen sin cambios
