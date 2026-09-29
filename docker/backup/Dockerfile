# Respaldos (it. 35, R-BCK-01..05): las herramientas de PostgreSQL 16 — la
# misma versión que el servicio pgsql — y la CLI de AWS para el bucket de
# evidencias (LocalStack en desarrollo, S3 en producción).
FROM postgres:16-alpine

RUN apk add --no-cache aws-cli bash coreutils findutils

COPY docker/backup/entrypoint.sh docker/backup/backup.sh docker/backup/restore-drill.sh docker/backup/fetch-offsite.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh /usr/local/bin/backup.sh /usr/local/bin/restore-drill.sh /usr/local/bin/fetch-offsite.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
