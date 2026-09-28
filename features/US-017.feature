# language: es
@story_id:US-017 @origin:discovery_inicial @priority:1 @epic:EPIC-004
Característica: Datos del contrato en la vista pública de la obra
  Como Verificador Público
  quiero ver los datos clave del contrato oficial en la vista de la obra
  para contrastar la magnitud de los fondos públicos con la evidencia ciudadana

  Antecedentes:
    Dado que soy un visitante sin sesión en el mapa público de "veeduria-smr"
    Y la obra "Pavimentación Calle 30" tiene el contrato "CO1.PCCNTR.1234567" de la entidad "Alcaldía de Santa Marta" con el contratista "Constructora Caribe S.A.S.", valor 1250000000 y plazo de 8 meses

  @complexity:low
  Escenario: Tarjeta del contrato en la vista de la obra
    Cuando abro la vista de la obra "Pavimentación Calle 30"
    Entonces veo la entidad "Alcaldía de Santa Marta", el contratista "Constructora Caribe S.A.S.", el valor "$1.250.000.000" y el plazo "8 meses"
    Y veo el botón "Ver original en SECOP" que abre la página oficial del proceso en una pestaña nueva

  @complexity:low @negative
  Escenario: Los datos se muestran tal como vienen de SECOP II
    Dado que SECOP II trae el contratista escrito "Constructora Caribe SAS."
    Cuando abro la vista de la obra "Pavimentación Calle 30"
    Entonces veo el contratista exactamente como "Constructora Caribe SAS."

  @complexity:low
  Escenario: La vista no exige inicio de sesión
    Cuando abro la vista de la obra sin haber iniciado sesión
    Entonces veo la tarjeta del contrato

  @complexity:medium @edge
  Escenario: Contrato anulado en SECOP que ya tenía evidencias
    Dado que el contrato "CO1.PCCNTR.1234567" pasó a "cancelled" y la obra tiene 2 evidencias publicadas
    Cuando abro la vista de la obra "Pavimentación Calle 30"
    Entonces la página sigue activa con sus 2 evidencias
    Y la tarjeta del contrato muestra el aviso "⚠️ Contrato Anulado/Retirado en SECOP"
