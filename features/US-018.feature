# language: es
@story_id:US-018 @origin:discovery_inicial @priority:3 @epic:EPIC-002
Característica: Reportes sin conexión con envío automático
  Como Veedor de Campo
  quiero que la app guarde mis reportes sin señal y los envíe al recuperar la conexión
  para no perder datos

  Antecedentes:
    Dado que estoy autenticado como el veedor "carlos@correo.co" de "Veeduría Ciudadana Santa Marta"

  @complexity:medium
  Escenario: Guardado sin conexión
    Dado que mi teléfono no tiene conexión
    Y ya tengo 1 reporte en la bandeja de salida
    Cuando envío un reporte válido
    Entonces el reporte queda guardado en el teléfono
    Y veo el mensaje "📵 Sin conexión. Reporte guardado en el dispositivo. Se enviará automáticamente cuando recupere la señal."
    Y la bandeja de salida de "Mis Reportes" indica "⏳ 2 reportes esperando conexión"

  @complexity:medium
  Escenario: Envío automático al recuperar la señal
    Dado que tengo 2 reportes en la bandeja de salida
    Cuando el teléfono recupera la conexión
    Entonces los 2 reportes se suben en segundo plano
    Y aparecen en "Mis Reportes" con estado "En Cola"
    Y la bandeja de salida queda vacía

  @complexity:high @edge
  Escenario: Ubicación y hora se congelan al capturar
    Dado que tomé una foto sin conexión el "2026-09-25 09:00" a 100 m de la obra
    Y el "2026-09-26 18:00" sincronizo desde un lugar a 30 km de la obra
    Cuando el servidor recibe el reporte
    Entonces la ubicación y la hora del reporte son las de la captura
    Y la geocerca se valida con la ubicación de la captura
    Y el reporte es aceptado

  @complexity:low @negative
  Esquema del escenario: Límite de almacenamiento local
    Dado que tengo <pendientes> reportes pendientes que ocupan <peso>
    Cuando intento guardar un nuevo reporte sin conexión
    Entonces el nuevo reporte "<resultado>"

    Ejemplos:
      | pendientes | peso  | resultado              |
      | 9          | 30 MB | se guarda              |
      | 10         | 30 MB | no se guarda           |
      | 4          | 50 MB | no se guarda           |

  @complexity:low @negative
  Escenario: Mensaje de almacenamiento lleno
    Dado que tengo 10 reportes pendientes
    Cuando intento guardar un nuevo reporte sin conexión
    Entonces veo el mensaje "⚠️ Almacenamiento local lleno. Conéctese a internet para sincronizar los reportes pendientes antes de crear uno nuevo."

  @complexity:medium @negative
  Esquema del escenario: Vigencia de 7 días con aviso 24 horas antes
    Dado que tengo un reporte pendiente capturado hace <tiempo>
    Cuando la app revisa la bandeja de salida
    Entonces "<resultado>"

    Ejemplos:
      | tiempo            | resultado                                    |
      | 5 días            | el reporte sigue pendiente sin aviso         |
      | 6 días            | la app me avisa que el reporte está por vencer |
      | 7 días y 1 minuto | el reporte se descarta                       |

  @complexity:low @negative
  Escenario: Aviso de vencimiento
    Dado que tengo un reporte pendiente capturado hace 6 días
    Cuando la app revisa la bandeja de salida
    Entonces veo el mensaje "⚠️ Tu reporte pendiente de sincronización expirará en 24 horas. Conéctate a una red para enviarlo antes de que se descarte."

  @complexity:low @negative
  Escenario: Falla al reenviar
    Dado que tengo 1 reporte pendiente y el teléfono recupera la conexión
    Cuando el servidor no responde al subirlo
    Entonces el reporte sigue en la bandeja de salida
    Y veo el mensaje "🔄 Error al sincronizar con el servidor. Se reintentará en unos minutos."

  @complexity:low @edge
  Escenario: Cerrar sesión con reportes pendientes
    Dado que tengo 2 reportes en la bandeja de salida
    Cuando intento cerrar sesión
    Entonces la app muestra "🚨 Tienes reportes sin enviar. Si cierras sesión ahora, se borrarán permanentemente del teléfono. ¿Deseas continuar?"
    Y la sesión no se cierra hasta que confirme

  @complexity:high @edge
  Escenario: El contrato se anula mientras el reporte esperaba en la cola
    Dado que tomé una foto el lunes cuando el contrato "CO1.PCCNTR.1234567" estaba "En ejecución"
    Y el martes SECOP anula el contrato
    Cuando el miércoles el reporte llega al servidor
    Entonces el reporte es aceptado y encolado para sellado
    Y entra con estado "Oculto" para que el administrador decida
