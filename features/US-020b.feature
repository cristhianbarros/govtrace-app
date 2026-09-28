# language: es
@story_id:US-020b @origin:discovery_inicial @priority:1 @epic:EPIC-003
Característica: Sellado de cada reporte en Stellar con comisión patrocinada
  Como Sistema
  quiero encolar el reporte validado y registrar su raíz de Merkle en el Smart Contract de Soroban
  para garantizar la inmutabilidad sin fricción para el usuario

  # Pivote a Stellar (2026-09-28): la cuenta selladora firma y la
  # patrocinadora paga con fee bump (D5); un ledger cerrado es definitivo,
  # así que no hay confirmaciones extra ni reorganizaciones (R-BLK-06).
  Antecedentes:
    Dado que un reporte con 3 fotos pasó la verificación de hashes del servidor

  @complexity:high
  Escenario: Sellado de una sola raíz de Merkle por reporte
    Cuando el servidor procesa el reporte
    Entonces construye un árbol de Merkle con 4 hojas: el SHA-256 de cada foto y el hash del JSON de metadatos
    Y el JSON de metadatos contiene latitud, longitud, comentario, timestamp y el seudónimo del veedor, no su ID
    Y guarda la prueba de inclusión de cada archivo
    Y envía una sola transacción con la raíz y la referencia de la obra

  @complexity:medium
  Escenario: Recorrido de estados hasta "Sellada"
    Cuando el reporte se encola y el servidor envía la transacción
    Entonces la evidencia pasa por "Recibida", "En Cola" y "Transmitiendo"
    Y pasa a "Sellada" cuando la red incluye la transacción en un ledger cerrado

  @complexity:medium @negative
  Escenario: Mientras la red no cierre un ledger con la transacción, la evidencia no está sellada
    Dado que la transacción del reporte fue enviada y la red todavía no la incluye en un ledger cerrado
    Entonces la evidencia sigue en "Transmitiendo"

  @complexity:high @negative
  Escenario: El servidor ignora la raíz enviada por el teléfono
    Dado que el teléfono envió una raíz de Merkle distinta a la que corresponde a los hashes verificados
    Cuando el servidor procesa el reporte
    Entonces sella la raíz que él mismo calculó

  @complexity:medium @negative
  Escenario: El veedor nunca ve billeteras ni comisiones
    Cuando el veedor crea y envía el reporte
    Entonces en ningún momento se le pide una cuenta de Stellar, una billetera, una firma ni un pago en XLM

  @complexity:medium @negative
  Escenario: Firma la cuenta selladora y paga la cuenta patrocinadora
    Cuando el servidor envía la orden de sellado
    Entonces la invocación la firma la cuenta selladora
    Y la comisión la paga la cuenta patrocinadora con un fee bump
    Y ninguna llave secreta está en el repositorio ni en .env.example

  @complexity:high @edge
  Escenario: La cuenta patrocinadora se queda sin saldo
    Dado que la cuenta patrocinadora no tiene XLM para la comisión
    Cuando se intenta sellar el reporte
    Entonces el envío falla y los trabajos de sellado quedan en pausa
    Y el Super Administrador recibe una alerta crítica por email para fondear la cuenta patrocinadora
