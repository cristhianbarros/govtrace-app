# language: es
@story_id:US-022 @origin:discovery_inicial @priority:3 @epic:EPIC-003
Característica: Alerta de saldo bajo de la cuenta patrocinadora
  Como Super Administrador
  quiero vigilar el saldo en XLM de la cuenta patrocinadora y recibir alertas bajo un umbral
  para recargarla desde la tesorería antes de que se detenga el sellado

  # Pivote a Stellar (2026-09-28): la cuenta patrocinadora paga las
  # comisiones con fee bump (D5) y es una hot wallet que recarga la
  # tesorería (D11). El umbral es el parámetro de D12 (US-038-CFG). La
  # tesorería también paga la vigencia del contrato (D12): se avisa antes de
  # que venza, para que la extienda a tiempo.
  Antecedentes:
    Dado que el umbral de saldo configurado es 50 XLM
    Y la cuenta patrocinadora es "GCMN776RZKGVGGLLL2KB5YW322QOAA2TAHINWC6322RE2VBX4UWRSNGF"

  @complexity:low
  Escenario: Consulta periódica del saldo
    Cuando pasan 15 minutos desde la última consulta
    Entonces el sistema consulta el saldo de la cuenta patrocinadora a través del RPC de Stellar

  @complexity:low @negative
  Esquema del escenario: Alerta cuando el saldo cae bajo el umbral
    Dado que el saldo de la cuenta patrocinadora es <saldo> XLM
    Cuando se consulta el saldo
    Entonces la alerta "<resultado>"

    Ejemplos:
      | saldo | resultado    |
      | 50    | no se envía  |
      | 49,9  | se envía     |

  @complexity:low @negative
  Escenario: Contenido y canales de la alerta
    Dado que el saldo de la cuenta patrocinadora es 32,5 XLM
    Cuando se consulta el saldo
    Entonces el Super Administrador recibe por Email y por Webhook "🚨 URGENTE: La cuenta patrocinadora de GovTrace tiene saldo crítico (32,5 XLM). Recargue la cuenta GCMN776RZKGVGGLLL2KB5YW322QOAA2TAHINWC6322RE2VBX4UWRSNGF inmediatamente para evitar el bloqueo en la cola de sellado."

  @complexity:low @edge
  Escenario: Aviso antes de que venza la vigencia del contrato
    Dado que hoy es "2026-09-29"
    Y la vigencia del código del contrato de sellado vence en 20 días
    Y la de su instancia vence en 150 días
    Cuando se revisa la vigencia del contrato
    Entonces el Super Administrador recibe por Email y por Webhook "⏳ La vigencia del código del contrato de sellado vence en 20 días (19/10/2026). Extiéndala desde la cuenta de tesorería antes de esa fecha: si vence, el siguiente sello la restaura con cargo a la cuenta patrocinadora."
    Y no recibe ningún aviso por la instancia
