# language: es
@story_id:US-021 @origin:discovery_inicial @priority:2 @epic:EPIC-003
Característica: Reintentos del sellado con retraso exponencial
  Como Sistema
  quiero reintentar el sellado con retraso creciente ante fallas
  para que ningún reporte quede sin sello

  @complexity:medium
  Escenario: Reintentos con retraso creciente
    Dado que la red de Stellar (su nodo RPC) falla al sellar una evidencia
    Cuando el sistema reintenta
    Entonces los reintentos se programan con retraso creciente: 1 min, 5 min, 15 min y 1 h, hasta 5 intentos

  @complexity:medium @negative
  Escenario: Falla definitiva tras el quinto intento
    Dado que una evidencia falló 4 intentos de sellado
    Cuando falla el quinto intento
    Entonces la evidencia queda en "Falla de Sellado"
    Y no se hace un sexto intento automático
    Y el veedor no ve ningún error

  @complexity:low @negative
  Escenario: Banner para el Administrador de Organización
    Dado que "Veeduría Ciudadana Santa Marta" tiene 3 evidencias en "Falla de Sellado"
    Cuando su Administrador abre su panel
    Entonces ve un banner rojo con "Alerta: 3 evidencias no pudieron ser selladas en blockchain. Se requiere intervención del soporte técnico."

  @complexity:medium @edge
  Escenario: La red de Stellar no confirma la transacción
    Dado que la red de Stellar recibió la transacción de un sello
    Y pasan 5 minutos sin que un ledger cerrado la incluya
    Cuando se revisa la transacción
    Entonces el sellado vuelve a la cola y reintenta con la misma política de reintentos

  @complexity:high @edge
  Escenario: Varias evidencias a la vez esperan su turno sin gastar intentos
    Dado que llegan 7 evidencias a la vez de "Veeduría Ciudadana Santa Marta" y de "Veeduría Ciénaga"
    Y la red de Stellar admite una sola transacción pendiente de la cuenta selladora
    Cuando el sistema las sella
    Entonces cada una espera su turno y llega a "Sellada"
    Y ninguna gasta un intento ni queda en "Falla de Sellado"

  @complexity:medium @edge
  Escenario: Evidencia estancada más de 2 horas en cola
    Dado que una evidencia de "Veeduría Ciudadana Santa Marta" lleva 2 horas y 5 minutos "En Cola"
    Cuando se revisa la cola de sellado
    Entonces el Super Administrador y el Administrador de "Veeduría Ciudadana Santa Marta" reciben "⚠️ Alerta de Sistema: Hay evidencias con más de 2 horas estancadas en la cola de sellado. Revisa el estado de la red o del proveedor RPC."
