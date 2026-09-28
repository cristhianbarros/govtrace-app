# language: es
@story_id:US-019 @origin:discovery_inicial @priority:3 @epic:EPIC-002
Característica: Sugerencia de obras cercanas
  Como Veedor de Campo
  quiero que la app me sugiera primero las obras cercanas
  para encontrar rápido la obra y evitar errores de asignación

  Antecedentes:
    Dado que estoy autenticado como el veedor "carlos@correo.co" de "Veeduría Ciudadana Santa Marta"
    Y mi organización vigila "Magdalena" (47)

  @complexity:medium
  Escenario: Hasta 5 obras dentro de 500 m ordenadas por distancia
    Dado que hay 7 obras ancladas a 50, 120, 200, 310, 420, 480 y 650 m de mí
    Cuando abro la sugerencia de obras cercanas
    Entonces veo 5 obras en este orden: 50, 120, 200, 310 y 420 m

  @complexity:medium @negative
  Esquema del escenario: Solo se sugieren obras seleccionables de mi territorio y ancladas
    Dado que a 100 m de mí hay una obra <situacion>
    Cuando abro la sugerencia de obras cercanas
    Entonces esa obra "<resultado>"

    Ejemplos:
      | situacion                                   | resultado        |
      | en ejecución y anclada                      | aparece          |
      | liquidada hace 13 meses                     | no aparece       |
      | anulada                                     | no aparece       |
      | sin ubicación oficial                       | no aparece       |
      | de otro territorio no vigilado              | no aparece       |

  @complexity:low @negative
  Escenario: Ninguna obra cercana
    Dado que no hay obras ancladas a menos de 500 m de mí
    Cuando abro la sugerencia de obras cercanas
    Entonces veo el mensaje "📍 No se encontraron obras a menos de 500m. Utilice el buscador para encontrarla por nombre o contrato."
