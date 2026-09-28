# language: es
@story_id:US-013 @origin:discovery_inicial @priority:1 @epic:EPIC-001
Característica: Sincronización programada de contratos SECOP II por territorio
  Como Sistema
  quiero consultar la API de SECOP II solo en los territorios de las organizaciones activas
  para mantener los contratos de interés actualizados sin almacenamiento innecesario

  Antecedentes:
    Dado que la organización activa "Veeduría Ciudadana Santa Marta" vigila "Magdalena" (47)
    Y la organización activa "Veeduría Paisa" vigila "Medellín" (05001)
    Y ninguna organización activa vigila "Bogotá" (11001)

  @complexity:medium
  Escenario: Corrida nocturna por los territorios configurados
    Cuando se ejecuta la sincronización programada de las 02:00
    Entonces se consulta SECOP II solo para "Magdalena" y "Medellín"
    Y los contratos traídos se guardan una sola vez en la base de datos central

  @complexity:low @negative
  Escenario: No se consultan territorios sin organizaciones activas
    Cuando se ejecuta la sincronización programada de las 02:00
    Entonces no se consulta ni se guarda ningún contrato de "Bogotá"

  @complexity:medium @edge
  Escenario: Un territorio que se queda sin organizaciones deja de actualizarse
    Dado que "Veeduría Paisa" fue suspendida y era la única que vigilaba "Medellín"
    Y hay 12 contratos de "Medellín" guardados
    Cuando se ejecuta la sincronización programada de las 02:00
    Entonces los 12 contratos de "Medellín" se conservan
    Y no se actualizan

  @complexity:medium @negative
  Esquema del escenario: Emparejamiento normalizado del municipio con la tabla DIVIPOLA
    Dado que SECOP II entrega un contrato del municipio escrito "<municipio_secop>"
    Cuando se ejecuta la sincronización
    Entonces el contrato "<resultado>"

    Ejemplos:
      | municipio_secop    | resultado                                                      |
      | SANTA MARTA        | se asigna a Santa Marta (47001)                                |
      | Ciénaga            | se asigna a Ciénaga (47189)                                    |
      | Cienaga            | se asigna a Ciénaga (47189)                                    |
      | Villa Inexistente  | se descarta y se reporta en el panel de salud de sincronización |

  @complexity:low @negative
  Escenario: Los datos de un contrato no se pueden editar manualmente
    Dado que estoy autenticado como Super Administrador en el panel global
    Cuando intento modificar el valor de un contrato sincronizado
    Entonces la acción es rechazada
    Y el contrato conserva el valor que trae SECOP II

  @complexity:medium @edge
  Escenario: Falla de la API de SECOP II
    Dado que la API de SECOP II responde con error 504
    Cuando se ejecuta la sincronización programada de las 02:00
    Entonces la tarea registra el fallo
    Y programa un reintento automático con retraso exponencial

  @complexity:medium @edge
  Esquema del escenario: Sincronización inmediata al activar una organización o cambiar su territorio
    Dado que son las 10:00
    Cuando <evento>
    Entonces se encola de inmediato una sincronización de los contratos de ese territorio sin esperar a las 02:00

    Ejemplos:
      | evento                                                     |
      | el Super Administrador da de alta una organización         |
      | el Super Administrador reactiva una organización suspendida |
      | un Administrador de Organización cambia su territorio      |
