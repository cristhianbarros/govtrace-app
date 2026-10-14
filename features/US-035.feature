# language: es
@story_id:US-035 @origin:discovery_inicial @priority:1 @epic:EPIC-004
Característica: Corregir la ubicación oficial de una obra
  Como Administrador de Organización
  quiero corregir la ubicación oficial de una obra dejando registro del cambio
  para que una ubicación errónea no bloquee los reportes de mis veedores

  Antecedentes:
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Y la obra "Acueducto Gaira" tiene ubicación oficial 11.2000, -74.2300

  @complexity:medium
  Esquema del escenario: Corrección de la ubicación
    Cuando corrijo la ubicación de "Acueducto Gaira" <forma> a 11.2408, -74.1990
    Y guardo
    Entonces veo el mensaje "La ubicación oficial de la obra ha sido ajustada. La nueva geocerca de 500m ya está activa para los veedores."
    Y la geocerca de mis veedores se calcula desde 11.2408, -74.1990

    Ejemplos:
      | forma                               |
      | arrastrando el pin                  |
      | escribiendo latitud y longitud      |

  @complexity:medium
  Escenario: Confirmar la ubicación que el primer reporte dejó por confirmar
    Dado que el primer reporte de la obra "Pavimentación Calle 30" dejó su ubicación por confirmar en 11.6000, -74.2000
    Cuando toco "Confirmar esta ubicación" en esa evidencia de la Bandeja
    Entonces la ubicación oficial de la obra queda en 11.6000, -74.2000
    Y el log registra mi ID, la fecha y hora, sin coordenadas anteriores, y las nuevas 11.6000, -74.2000

  @complexity:medium
  Escenario: La Bandeja muestra dónde quedó la ubicación corregida
    Dado que el primer reporte de "Acueducto Gaira" fijó su ubicación en 11.2000, -74.2300
    Cuando corrijo la ubicación de "Acueducto Gaira" a 11.2408, -74.1990 y guardo
    Y abro mi Bandeja
    Entonces la tarjeta de ese reporte muestra dos puntos: donde se tomó, en 11.2000, -74.2300, y la ubicación oficial hoy, en 11.2408, -74.1990
    Y dice a cuántos metros de donde se tomó el reporte quedó la ubicación oficial

  @complexity:low @negative
  Escenario: Cada corrección queda en el log de auditoría
    Cuando corrijo la ubicación de "Acueducto Gaira" a 11.2408, -74.1990 y guardo
    Entonces el log registra mi ID, la fecha y hora, las coordenadas anteriores 11.2000, -74.2300 y las nuevas 11.2408, -74.1990

  @complexity:medium @negative
  Escenario: La corrección no afecta a otra organización
    Dado que "Veeduría Ciénaga" también vigila "Acueducto Gaira" con su propia ubicación 11.2000, -74.2300
    Cuando corrijo la ubicación de "Acueducto Gaira" a 11.2408, -74.1990 y guardo
    Entonces la ubicación de "Acueducto Gaira" en "Veeduría Ciénaga" sigue siendo 11.2000, -74.2300

  # It. 58: la ubicación que propuso un ciudadano en su informe (US-059-LEG).
  @complexity:medium
  Escenario: Confirmar la ubicación que propuso un ciudadano
    Dado que un ciudadano propuso en su informe que la obra "Construcción de la vía a Minca" queda en 11.1427, -74.1173
    Cuando toco "Confirmar esta ubicación" en ese informe de "Informes ciudadanos"
    Entonces la ubicación oficial de la obra queda en 11.1427, -74.1173, y la obra aparece en el mapa
    Y el log registra mi ID, la fecha y hora, sin coordenadas anteriores, y las nuevas 11.1427, -74.1173

  @complexity:medium @negative
  Escenario: Con varias ubicaciones propuestas, confirmar una cierra las demás
    Dado que dos ciudadanos propusieron ubicaciones distintas para la obra "Construcción de la vía a Minca"
    Cuando abro "Informes ciudadanos"
    Entonces el mapa de cada informe muestra también la otra propuesta
    Cuando confirmo una
    Entonces el otro informe dice "La obra ya tiene ubicación", y confirmarlo responde "La obra ya tiene ubicación. Si está mal, corríjala en Obras."
