# language: es
@story_id:US-036 @origin:discovery_inicial @priority:1 @epic:EPIC-004
Característica: Bandeja de revisión y publicación de evidencias
  Como Administrador de Organización
  quiero revisar las evidencias ocultas y publicarlas
  para decidir qué se muestra en nuestro mapa público

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Y hay 4 evidencias selladas en estado "Oculto"

  @complexity:low
  Escenario: Toda evidencia sellada nace oculta
    Cuando se sella una nueva evidencia de mi organización
    Entonces su estado editorial es "Oculto"
    Y no aparece en el mapa público

  @complexity:low
  Escenario: Publicar una evidencia
    Cuando abro la bandeja de entrada y publico una evidencia
    Entonces veo el mensaje "Evidencia publicada. Ya es visible en el mapa."
    Y la evidencia aparece en el mapa y en la línea de tiempo

  @complexity:low @negative
  Escenario: No existe publicación masiva
    Cuando abro la bandeja de entrada
    Entonces solo puedo publicar las evidencias una por una

  @complexity:low
  Escenario: Rechazar una evidencia con motivo
    Cuando rechazo una evidencia con el motivo "La foto no corresponde a la obra"
    Entonces su estado es "Rechazado"
    Y nunca se hace pública ni deja lápida
    Y su veedor ve "Rechazada" con el motivo "La foto no corresponde a la obra"

  @complexity:low @negative
  Escenario: Rechazar exige un motivo
    Cuando intento rechazar una evidencia sin escribir motivo
    Entonces el rechazo no se realiza

  @complexity:low @negative
  Escenario: Solo el Administrador de la organización publica
    Dado que estoy autenticado como el veedor "carlos@correo.co" de "Veeduría Ciudadana Santa Marta"
    Cuando intento publicar una evidencia
    Entonces la acción es rechazada

  @complexity:low @negative
  Escenario: Un Administrador no publica evidencias de otra organización
    Cuando intento publicar una evidencia de "Veeduría Ciénaga"
    Entonces la acción es rechazada

  @complexity:medium @edge
  Escenario: Evidencia marcada por hora sospechosa
    Dado que una de las evidencias ocultas quedó marcada por hora de captura sospechosa
    Cuando abro la bandeja de entrada
    Entonces veo la marca en esa evidencia
    Y puedo publicarla o rechazarla igual que las demás

  @complexity:low
  Escenario: Cada evidencia de la bandeja dice de qué obra es y quién la envió
    Cuando abro la bandeja de entrada
    Entonces cada evidencia muestra el nombre de su obra y su municipio
    Y el nombre del veedor que la envió

  @complexity:medium
  Escenario: La evidencia que fijó la ubicación de la obra llega marcada
    Dado que la obra "Pavimentación Calle 30" no tenía ubicación oficial
    Y el primer reporte desde ella fijó su ubicación
    Cuando abro la bandeja de entrada
    Entonces esa evidencia dice "📍 Este reporte fijó la ubicación oficial de la obra."
    Y muestra ese punto en un mapa pequeño
    Y ofrece "Corregir ubicación", que abre la corrección de esa obra

  @complexity:low @privacy
  Escenario: Cada evidencia de la bandeja dice a qué distancia de la obra se tomó
    Dado que una evidencia se tomó a 120 m de la ubicación oficial de su obra
    Cuando abro la bandeja de entrada
    Entonces esa evidencia dice "Tomada a 120 m de la obra."
    Y no muestra las coordenadas del veedor

  @complexity:low
  Escenario: Rechazar la evidencia que fijó la ubicación no la cambia
    Dado que en la bandeja está la evidencia que fijó la ubicación de la obra
    Cuando elijo "Rechazar"
    Entonces la confirmación avisa "Este reporte fijó la ubicación oficial de la obra. Rechazarlo no la cambia: si el lugar está mal, corríjalo en Obras."
    Y ofrece "Corregir ubicación"
    Y al confirmar el rechazo, la ubicación oficial de la obra sigue igual
