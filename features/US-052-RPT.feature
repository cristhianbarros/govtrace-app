# language: es
@story_id:US-052-RPT @origin:analisis_completitud @priority:3 @epic:EPIC-005
Característica: Datos abiertos de evidencias publicadas
  Como Verificador Público
  quiero descargar en CSV o JSON las evidencias publicadas y sus sellos
  para auditar y reutilizar la información de forma independiente

  Antecedentes:
    Dado que soy un visitante sin sesión en el mapa público de "veeduria-smr"
    Y la organización tiene 10 evidencias publicadas, 3 ocultas y 1 retirada

  @complexity:medium
  Esquema del escenario: Descarga de datos abiertos
    Cuando descargo los datos abiertos en formato "<formato>"
    Entonces obtengo 10 registros
    Y cada registro tiene obra, contrato, municipio, fecha, clasificación, coordenadas aproximadas, raíz de Merkle, TxID, bloque, comentario y seudónimo del veedor

    Ejemplos:
      | formato |
      | CSV     |
      | JSON    |

  @complexity:medium @negative
  Escenario: Los datos abiertos protegen la privacidad del veedor
    Cuando descargo los datos abiertos en formato "JSON"
    Entonces ninguna coordenada tiene más precisión que unos 100 m
    Y ningún registro contiene el nombre, el correo ni el ID real del veedor

  @complexity:low @negative
  Escenario: Las evidencias no publicadas no se incluyen
    Cuando descargo los datos abiertos en formato "CSV"
    Entonces no aparece ninguna de las 3 evidencias ocultas ni la retirada
