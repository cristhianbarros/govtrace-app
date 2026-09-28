# language: es
@story_id:US-022 @origin:discovery_inicial @priority:3 @epic:EPIC-003
Característica: Alerta de saldo bajo del Relayer
  Como Super Administrador
  quiero vigilar el saldo de la billetera del Relayer y recibir alertas bajo un umbral
  para recargar fondos antes de que se detenga el sellado

  Antecedentes:
    Dado que el umbral de saldo configurado es 5 POL
    Y la billetera del Relayer es "0x71C…9A2"

  @complexity:low
  Escenario: Consulta periódica del saldo
    Cuando pasan 15 minutos desde la última consulta
    Entonces el sistema consulta el saldo del Relayer a través del nodo RPC

  @complexity:low @negative
  Esquema del escenario: Alerta cuando el saldo cae bajo el umbral
    Dado que el saldo del Relayer es <saldo> POL
    Cuando se consulta el saldo
    Entonces la alerta "<resultado>"

    Ejemplos:
      | saldo | resultado    |
      | 5.0   | no se envía  |
      | 4.9   | se envía     |

  @complexity:low @negative
  Escenario: Contenido y canales de la alerta
    Dado que el saldo del Relayer es 3.2 POL
    Cuando se consulta el saldo
    Entonces el Super Administrador recibe por Email y por Webhook "🚨 URGENTE: El Relayer de GovTrace tiene saldo crítico (3.2 POL). Recargue la wallet 0x71C…9A2 inmediatamente para evitar el bloqueo en la cola de sellado."
