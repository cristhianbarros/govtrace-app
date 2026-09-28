# language: es
@story_id:US-034 @origin:discovery_inicial @priority:1 @epic:EPIC-004
Característica: Cálculo diario de obras en riesgo por fecha vencida
  Como Sistema
  quiero marcar cada día como "en riesgo" las obras cuyo contrato venció y sigue en ejecución
  para colorear su pin en el mapa

  @complexity:medium @negative
  Esquema del escenario: Regla de obra en riesgo
    Dado que hoy es "2026-09-27"
    Y el contrato de una obra tiene fecha de terminación "<fecha_fin>" y estado SECOP "<estado>"
    Cuando se ejecuta el cálculo diario
    Entonces la obra "<resultado>"

    # R-SEC-07: en ejecución según SECOP II son "En ejecución" y "Modificado".
    Ejemplos:
      | fecha_fin  | estado        | resultado                |
      | 2026-09-26 | En ejecución  | queda "En riesgo"        |
      | 2026-09-28 | En ejecución  | no queda "En riesgo"     |
      | 2026-09-26 | terminado     | no queda "En riesgo"     |
      | 2026-09-26 | Modificado    | queda "En riesgo"        |
      | 2026-09-26 | Suspendido    | no queda "En riesgo"     |

  @complexity:medium @negative
  Escenario: El estado calculado vive en la ficha de obra, no en el contrato
    Dado que una obra quedó "En riesgo"
    Entonces el estado se guarda en la ficha de obra de cada organización
    Y el contrato de SECOP no se modifica
