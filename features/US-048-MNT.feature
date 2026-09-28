# language: es
@story_id:US-048-MNT @origin:analisis_completitud @priority:2 @epic:EPIC-001
Característica: Archivado de contratos antiguos sin evidencias
  Como Sistema
  quiero archivar fuera de la base principal los contratos sin evidencias cerrados hace más de 5 años
  para contener el crecimiento de la base central

  @complexity:medium
  Esquema del escenario: Qué contratos se archivan en la corrida mensual
    Dado que existe un contrato <situacion>
    Cuando se ejecuta el archivado mensual
    Entonces el contrato "<resultado>"

    Ejemplos:
      | situacion                                         | resultado                       |
      | sin evidencias, cerrado hace 5 años y 1 mes       | se mueve al archivo             |
      | sin evidencias, cerrado hace 4 años               | sigue en la base principal      |
      | con 2 evidencias, cerrado hace 8 años             | sigue en la base principal      |

  @complexity:high @edge
  Escenario: Un contrato archivado vuelve si llega una evidencia
    Dado que el contrato "CO1.PCCNTR.7654321" está archivado
    Cuando llega un reporte sin conexión vinculado a ese contrato
    Entonces el contrato vuelve a la base principal
    Y el reporte se procesa normalmente
