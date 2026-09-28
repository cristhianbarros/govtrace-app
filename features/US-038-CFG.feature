# language: es
@story_id:US-038-CFG @origin:analisis_completitud @priority:2 @epic:EPIC-009
Característica: Parámetros operativos configurables por el Super Administrador
  Como Super Administrador
  quiero ajustar desde el panel global los parámetros operativos
  para adaptar la operación sin desplegar código

  Antecedentes:
    Dado que estoy autenticado como Super Administrador en el panel global

  @complexity:medium
  Esquema del escenario: Ajuste de un parámetro configurable
    Cuando cambio el parámetro "<parametro>" de "<anterior>" a "<nuevo>"
    Entonces el sistema usa "<nuevo>" sin un nuevo despliegue
    Y el log de auditoría registra el valor anterior "<anterior>" y el nuevo "<nuevo>"

    Ejemplos:
      | parametro                           | anterior | nuevo    |
      | radio de geocerca                   | 500 m    | 300 m    |
      | ventana de Terminados y Liquidados  | 12 meses | 6 meses  |
      | vigencia de invitaciones            | 48 h     | 72 h     |
      | umbral de saldo de la patrocinadora | 50 XLM   | 80 XLM   |
      | hora de sincronización              | 02:00    | 03:30    |

  @complexity:low @negative
  Esquema del escenario: Los parámetros fijos no se pueden configurar
    Cuando busco el parámetro "<parametro>" en el panel de configuración
    Entonces no está disponible para edición

    Ejemplos:
      | parametro                        |
      | precisión mínima del GPS         |
      | archivos por reporte             |
      | vigencia de reportes sin conexión |

  @complexity:low @negative
  Escenario: Un Administrador de Organización no puede cambiar parámetros globales
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando intento cambiar el radio de geocerca a "300 m"
    Entonces la acción es rechazada

  @complexity:high @edge
  Escenario: Un reporte capturado antes del cambio se valida con el radio vigente al capturar
    Dado que el radio de geocerca era "500 m" cuando un veedor tomó una foto a 400 m de la obra sin conexión
    Y luego cambio el radio de geocerca a "300 m"
    Cuando el reporte llega al servidor
    Entonces el reporte es aceptado porque se valida con el radio de "500 m"
