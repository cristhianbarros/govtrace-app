# language: es
@story_id:US-070 @origin:usuario @priority:1 @epic:EPIC-003
Característica: Sincronizar el SECOP desde la veeduría
  Como Administrador de una veeduría
  quiero pedir la sincronización con el SECOP II de mi territorio sin esperar a la noche
  para reportar un contrato que se publicó hoy

  Antecedentes:
    Dado que la veeduría "veeduria-smr" vigila Santa Marta

  @complexity:medium
  Escenario: La veeduría pide la sincronización de su territorio sin esperar a la noche
    Cuando el Administrador pulsa "Sincronizar ahora" en "Contratos"
    Entonces se sincroniza solo el territorio de su veeduría
    Y ve "Buscamos los contratos de su territorio en el SECOP II. En unos minutos verá el resultado aquí."

  @complexity:low @negative
  Escenario: Una sincronización cada 5 minutos por veeduría
    Dado que el Administrador pidió una sincronización hace 2 minutos
    Cuando la pide otra vez
    Entonces ve "Ya pidió una sincronización hace menos de 5 minutos. Espere su resultado."
    Y otra veeduría puede pedir la suya

  @complexity:medium
  Escenario: La pantalla dice cómo le fue a la última sincronización
    Dado que la sincronización de la noche le trajo 3 contratos nuevos y 12 actualizados
    Cuando el Administrador abre "Contratos"
    Entonces ve cuándo terminó, "3 contratos nuevos" y "12 actualizados"
    Y si hay una en curso, ve "Sincronizando…"

  @complexity:low @negative
  Escenario: Solo el Administrador sincroniza su veeduría
    Cuando un veedor pide la sincronización o su resultado
    Entonces no puede
