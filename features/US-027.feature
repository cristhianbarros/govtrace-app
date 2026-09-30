# language: es
@story_id:US-027 @origin:discovery_inicial @priority:2 @epic:EPIC-004
Característica: Pines del mapa por color de estado
  Como Verificador Público
  quiero ver las obras como pines que cambian de color según su estado
  para detectar de un vistazo los proyectos en riesgo o abandono

  Antecedentes:
    Dado que soy un visitante sin sesión en el mapa público de "veeduria-smr"

  @complexity:medium
  Esquema del escenario: Color del pin según el estado de la obra
    Dado que una obra anclada <situacion>
    Cuando abro el mapa
    Entonces su pin es de color "<color>"

    Ejemplos:
      | situacion                                                              | color    |
      | está en ejecución, en plazo y sin evidencias publicadas                | verde    |
      | está en plazo y su evidencia publicada más reciente es "Retraso"       | amarillo |
      | está en plazo y su evidencia publicada más reciente es "Abandono"      | rojo     |
      | venció su plazo y SECOP la sigue mostrando "En ejecución"              | rojo     |
      | fue liquidada hace 3 meses y no tiene evidencias publicadas            | verde    |
      | fue liquidada hace 3 meses y su evidencia publicada más reciente es "Retraso" | amarillo |

  @complexity:medium @negative
  Escenario: El color refleja la evidencia publicada más reciente
    Dado que una obra en plazo tenía como evidencia más reciente un "Abandono"
    Cuando se publica una evidencia más reciente clasificada como "Avance"
    Entonces su pin deja de ser rojo

  @complexity:medium @negative
  Escenario: Las evidencias no publicadas no cambian el color
    Dado que una obra en plazo tiene su pin verde
    Cuando un veedor envía un reporte de "Abandono" que sigue oculto
    Entonces su pin sigue verde

  @complexity:medium @negative
  Escenario: Una ficha con varios contratos toma el peor estado
    Dado que la ficha "Acueducto Gaira" agrupa un contrato en plazo y otro vencido que sigue "En ejecución"
    Cuando abro el mapa
    Entonces el pin de "Acueducto Gaira" es rojo

  @complexity:low @negative
  Escenario: Las obras sin ubicación no tienen pin
    Dado que una obra de mi territorio no tiene ubicación oficial
    Cuando abro el mapa
    Entonces esa obra no aparece como pin

  @complexity:low @negative
  Escenario: El mapa de una organización no muestra obras de otra
    Dado que "Veeduría Ciénaga" tiene una obra con evidencias publicadas que "veeduria-smr" no vigila
    Cuando abro el mapa de "veeduria-smr"
    Entonces no veo el pin de esa obra

  @complexity:low @negative
  Escenario: La carga inicial del mapa es liviana
    Cuando abro el mapa
    Entonces la carga inicial solo trae id, latitud, longitud y color de cada pin

  @complexity:low
  Escenario: Los estados del mapa se explican junto al mapa, con ícono y palabra
    Cuando abro el mapa público de la organización
    Entonces arriba del mapa veo "✓ Normal", "! Alerta" y "✕ En riesgo", cada uno con cuántas obras tiene
    Y cada pin lleva el mismo signo que su estado, además de su color
    Y al tocar un estado, el mapa muestra solo sus obras

  @complexity:low
  Escenario: Llegar al mapa de una veeduría desde el Inicio
    Dado que "Veeduría Ciudadana Santa Marta" vigila Magdalena
    Cuando abro el Inicio de GovTrace
    Entonces veo "¿Cómo funciona?" en tres pasos
    Y veo "Veeduría Ciudadana Santa Marta", que vigila Magdalena, con el enlace "Ver su mapa de obras"
    Y una veeduría dada de baja no aparece
