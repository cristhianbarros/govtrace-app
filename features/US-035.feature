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

  @complexity:low @negative
  Escenario: Cada corrección queda en el log de auditoría
    Cuando corrijo la ubicación de "Acueducto Gaira" a 11.2408, -74.1990 y guardo
    Entonces el log registra mi ID, la fecha y hora, las coordenadas anteriores 11.2000, -74.2300 y las nuevas 11.2408, -74.1990

  @complexity:medium @negative
  Escenario: La corrección no afecta a otra organización
    Dado que "Veeduría Ciénaga" también vigila "Acueducto Gaira" con su propia ubicación 11.2000, -74.2300
    Cuando corrijo la ubicación de "Acueducto Gaira" a 11.2408, -74.1990 y guardo
    Entonces la ubicación de "Acueducto Gaira" en "Veeduría Ciénaga" sigue siendo 11.2000, -74.2300
