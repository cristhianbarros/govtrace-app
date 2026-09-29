# Respaldos y restauración

Runbook de GovTrace para perder lo menos posible y volver a funcionar rápido (R-BCK-01 a R-BCK-05, iteración 35).

| Regla | Qué pide | Cómo se cumple |
|---|---|---|
| R-BCK-01 | Perder a lo sumo **1 hora** de datos | Una copia al arrancar y otra cada hora en punto |
| R-BCK-02 | Volver a funcionar en menos de **4 horas** | Este procedimiento, medido abajo. La restauración de prueba falla si pasa de 4 h (`RESTORE_RTO_SECONDS`) |
| R-BCK-03 | Respaldar también los archivos de evidencia | Cada copia trae el bucket completo |
| R-BCK-04 | Guardar las copias **30 días** | La copia más vieja se borra al hacer la siguiente |
| R-BCK-05 | Una restauración de prueba antes de salir a producción | `make restore-drill`, y en cada ejecución del pipeline (`make backup-check`) |

## Qué se respalda

El servicio `backup` de `docker-compose.yml` hace una copia al arrancar y otra cada hora en punto. Cada copia queda en el volumen `backup_data`, en `/backups/<fecha UTC>/`:

```
/backups/20260929T120000Z/
├── postgres/
│   ├── govtrace.dump                 # la base central: organizaciones, contratos, log de auditoría…
│   └── tenant<id>.dump               # una por organización: reportes, evidencias, sellos, veedores
├── evidencias/<id>/reports/<n>/…     # los archivos, espejo del bucket
├── SHA256SUMS                        # de cada volcado
└── manifest.json                     # cuándo se tomó, qué bases y cuántos archivos trae
```

- Los volcados están en el formato de `pg_restore` y se restauran base por base. Así se puede recuperar una sola organización.
- Los archivos que no cambiaron desde la copia anterior se enlazan (`cp -al`): 24 copias al día no ocupan 24 veces el bucket. Los archivos de evidencia no cambian nunca, así que cada copia solo ocupa lo nuevo.
- La base de los tests (`testing`) no se respalda (`BACKUP_EXCLUDE_DATABASES`).
- El servicio está sano mientras la última copia tenga menos de 2 horas. Si una copia falla, lo dice en su log (`make logs S=backup`) y se reintenta en la hora siguiente.

**En producción, el volumen `backup_data` debe estar en otro disco o en otra máquina.** Una copia en el mismo disco que la base no sobrevive a ese disco. Lo mismo vale para el bucket, que tiene que estar fuera del servidor. Las variables `BACKUP_*` de `.env.docker.example` apuntan el servicio al bucket real.

### Réplica fuera del sitio (it. 37a)

Con `BACKUP_OFFSITE_S3_URL`, cada copia se replica, al terminar, a otro bucket, en otra cuenta o en otra región:

```
s3://<bucket>/copias/<fecha UTC>/     # los volcados de esa copia, su SHA256SUMS y su manifest.json
s3://<bucket>/evidencias/             # un espejo de los archivos de evidencia
```

- Los volcados de cada copia se borran allá a los 30 días, por su fecha (R-BCK-04).
- Los archivos van a un espejo, porque no cambian nunca: subirlos todos cada hora no tendría sentido.
- **Ese bucket necesita versionado, y una regla que guarde 30 días las versiones anteriores.** Así, un archivo borrado por error se puede recuperar durante 30 días, aunque el espejo ya lo haya quitado.
- Con la réplica configurada, el servicio `backup` deja de estar sano si la última tiene más de 2 horas.
- `make backup-check` la prueba con un segundo bucket de LocalStack: replica, borra la de hace 30 días y restaura desde allá.

## Comandos

| Comando | Qué hace |
|---|---|
| `make backup-now` | Una copia ahora, igual a la de cada hora |
| `make backup-list` | Las copias guardadas y lo que trae cada una |
| `make restore-drill` | La restauración de prueba: una copia nueva, restaurada en un PostgreSQL vacío y en un bucket de prueba, verificando cada evidencia |
| `make backup-check` | Lo anterior, y además: la retención de 30 días, los archivos enlazados, la réplica fuera del sitio y su restauración, y que la restauración de prueba falla ante un archivo alterado, una base que falta o una recuperación de más de 4 h |

La restauración de prueba no toca lo que está en uso: restaura en el servicio descartable `restore-pg` (perfil `restore`) y en un bucket `<bucket>-restore-drill` que borra al terminar. Para restaurar una copia en particular:

```bash
docker compose --env-file .env.docker --profile restore up -d --wait restore-pg
docker compose --env-file .env.docker exec -e RESTORE_PGHOST=restore-pg backup restore-drill.sh /backups/<copia>
docker compose --env-file .env.docker --profile restore rm -sf restore-pg
```

## Restaurar después de un incidente

Anota la hora a la que empiezas: con ella se mide R-BCK-02.

1. **Detén lo que escribe.** Primero la aplicación, la cola, el calendario y el propio servicio de respaldo, que no debe copiar una base a medio restaurar:
   ```bash
   docker compose --env-file .env.docker stop proxy app worker scheduler backup
   ```
2. **Elige la copia.** La más reciente es la última de `make backup-list`: el servicio solo deja con nombre las copias completas. Si el disco de las copias también se perdió, tráela de fuera del sitio con `fetch-offsite.sh <copia> /backups/<copia>`, dentro del mismo contenedor. Levanta el contenedor de respaldo sin que haga una copia nueva:
   ```bash
   docker compose --env-file .env.docker run --rm --entrypoint bash backup
   cd /backups/<copia>/postgres && sha256sum -c ../SHA256SUMS      # los volcados, intactos
   ```
3. **Restaura cada base**, dentro de ese contenedor. `--clean --create` borra la base y la vuelve a crear desde la copia:
   ```bash
   for f in /backups/<copia>/postgres/*.dump; do
       pg_restore -h pgsql --clean --if-exists --create -d postgres "$f"
   done
   ```
   En un servidor nuevo, sin bases, el mismo comando las crea. Los pasos 2 y 3 se probaron así, sobre un servidor que ya tenía las bases.
4. **Restaura los archivos**, en el mismo contenedor. Sin `--delete`: un archivo más nuevo que la copia no molesta.
   ```bash
   aws ${BACKUP_S3_ENDPOINT:+--endpoint-url $BACKUP_S3_ENDPOINT} s3 sync /backups/<copia>/evidencias "s3://$BACKUP_S3_BUCKET"
   ```
5. **Pon el esquema al día**, por si la copia es anterior a una migración: `make up` y después `make migrate`.
6. **Comprueba** que `http://<dominio>/up` responde 200, entra al panel global y abre el mapa de una organización. Para verificar todas las evidencias contra sus archivos, corre `restore-drill.sh` sobre la misma copia.

### Qué se pierde, y qué no

- Se pierde lo que llegó después de la copia, a lo sumo una hora: reportes, decisiones editoriales, cambios de configuración.
- **Los sellos no se pierden.** Un reporte que se selló después de la copia sigue en Stellar, aunque su fila ya no esté. Su veedor lo puede volver a enviar desde su teléfono.
- Un sello que estaba "Transmitiendo" en la copia se retoma solo: la cola vive en la base central. Si la red ya lo había incluido, el reenvío recibe "Hash ya registrado" y toma el sello que la red tiene (US-021).
- Los archivos borrados por la retención de una organización dada de baja (US-003b) no vuelven a la base de datos. La restauración de prueba no los busca.

## Restauración de prueba ejecutada

**2026-09-29**, en el entorno de desarrollo (Docker), con los datos que tenía en ese momento: 7 bases (la central y 6 de organizaciones, una de ellas registrada con una evidencia) y 7 archivos.

Se usó la copia que el servicio tomó al arrancar, y se simuló el incidente 14 minutos después:

```
Copia: 20260929T113352Z (tomada 2026-09-29T11:33:52Z). Incidente simulado: 2026-09-29T11:48:19Z.
PASS  7 bases restauradas en 1 s
PASS  7 archivos restaurados en un bucket de prueba en 2 s
PASS  1 organizaciones con su base; 1 evidencias con su archivo y el mismo SHA-256 (0 s)
Datos perdidos: 14 min 27 s (desde la copia; R-BCK-01: menos de 1 h)
Recuperación: 3 s (R-BCK-02: menos de 4 h)
```

| | Medido | Límite |
|---|---|---|
| Datos perdidos | 14 min 27 s. En el peor caso, justo antes de la copia siguiente, serían 60 min. | 1 h (R-BCK-01) |
| Recuperación: restaurar las bases y los archivos, y verificarlos | 3 s | 4 h (R-BCK-02) |

Qué enseñó el simulacro:
- **La copia era anterior a una migración.** La base de desarrollo no tenía todavía la de la it. 33, y la primera verificación falló al buscar esa columna. Por eso el paso 5 del procedimiento es `make migrate`, y la verificación ahora lee las dos versiones del esquema.
- **La restauración de prueba falla en voz alta.** Ante un archivo que no coincide con su SHA-256, una base de organización que falta o una base central ilegible, termina con `FAIL`: nunca da por buena una copia en silencio. Lo prueba `make backup-check`.

**Antes de salir a producción** (R-BCK-05) hay que repetir la restauración de prueba en el servidor de producción, con sus datos reales, y anotarla aquí. El tiempo de 3 s crece con el volumen de datos, y a eso se suma levantar un servidor nuevo si el incidente se lo llevó.
