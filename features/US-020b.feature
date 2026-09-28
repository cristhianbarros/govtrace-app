# language: es
@story_id:US-020b @origin:discovery_inicial @priority:1 @epic:EPIC-003
Característica: Sellado de cada reporte en Polygon vía el relayer gestionado
  Como Sistema
  quiero encolar el reporte validado y registrar su raíz de Merkle vía el relayer gestionado
  para garantizar la inmutabilidad sin fricción para el usuario

  Antecedentes:
    Dado que un reporte con 3 fotos pasó la verificación de hashes del servidor

  @complexity:high
  Escenario: Sellado de una sola raíz de Merkle por reporte
    Cuando el servidor procesa el reporte
    Entonces construye un árbol de Merkle con 4 hojas: el SHA-256 de cada foto y el hash del JSON de metadatos
    Y el JSON de metadatos contiene latitud, longitud, comentario, timestamp y el seudónimo del veedor, no su ID
    Y guarda la prueba de inclusión de cada archivo
    Y envía una sola transacción con la raíz

  @complexity:medium
  Escenario: Recorrido de estados hasta "Sellada"
    Cuando el reporte se encola y el relayer envía la transacción
    Entonces la evidencia pasa por "Recibida", "En Cola" y "Transmitiendo"
    Y pasa a "Sellada" cuando la transacción tiene 3 bloques de confirmación

  @complexity:medium @negative
  Escenario: Con menos de 3 confirmaciones la evidencia no está sellada
    Dado que la transacción del reporte tiene 2 bloques de confirmación
    Entonces la evidencia sigue en "Transmitiendo"

  @complexity:high @negative
  Escenario: El servidor ignora la raíz enviada por el teléfono
    Dado que el teléfono envió una raíz de Merkle distinta a la que corresponde a los hashes verificados
    Cuando el servidor procesa el reporte
    Entonces sella la raíz que él mismo calculó

  @complexity:medium @negative
  Escenario: El veedor nunca ve billeteras ni gas
    Cuando el veedor crea y envía el reporte
    Entonces en ningún momento se le pide una billetera, una firma ni un pago de gas

  @complexity:medium @negative
  Escenario: La firma la hace el relayer gestionado, no el servidor
    Cuando el servidor envía la orden de sellado
    Entonces la transacción la firma el servicio de relayer gestionado
    Y el servidor no tiene la llave privada del Relayer en su configuración

  @complexity:high @edge
  Escenario: El Relayer se queda sin saldo
    Dado que la billetera del Relayer no tiene saldo para el gas
    Cuando se intenta sellar el reporte
    Entonces la estimación de gas falla y los trabajos de sellado quedan en pausa
    Y el Super Administrador recibe una alerta crítica por email para fondear la billetera

  @complexity:high @edge
  Escenario: Reorganización de la cadena
    Dado que la evidencia estaba "Sellada" con la transacción "0xabc…01"
    Y una reorganización de Polygon dejó huérfano ese bloque
    Cuando corre la auditoría nocturna que cruza la base de datos con la blockchain
    Entonces la evidencia vuelve a "En Cola" para resellarse automáticamente
