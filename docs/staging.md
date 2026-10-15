# Staging en AWS

Una copia de GovTrace en internet, apuntando a la **testnet** de Stellar, para probar con celulares de verdad: cámara y GPS (que el navegador solo da por HTTPS), correo real y SECOP II de punta a punta. Iteración 42b; las decisiones están en D14 de `specs/PLAN.md`.

| Qué | Valor |
|---|---|
| Dirección | `https://govtrace.duckdns.org`; cada organización en `https://<slug>.govtrace.duckdns.org` |
| Red de Stellar | Testnet: el contrato y las cuentas de `.env.staging.example` |
| Máquina | EC2 t4g.small (ARM, 2 GB + 4 GB de swap), Ubuntu 24.04, 30 GB, en el proyecto de AWS (us-east-2) |
| Correo | El SMTP de Gmail, desde `govtrace.app@gmail.com` |
| Certificado | Let's Encrypt, comodín, validado por DNS en DuckDNS |
| Secretos | SSM Parameter Store, en `/govtrace/staging/` (SecureString) |
| Infraestructura | CloudFormation: `deploy/aws/staging.yml` |

**Costo:** unos 17,5 USD al mes, que pagan los créditos de AWS: ~12 de la máquina, ~3,65 de la IP fija, ~2,40 del disco y centavos de S3. DuckDNS y Gmail son gratis.

## La primera vez

Necesitas el AWS CLI 2.32 o más nuevo y la sesión del proyecto:

```
aws login --profile govtrace-staging
```

**1. La infraestructura.** Crea la pila de CloudFormation: los dos buckets, el rol de la máquina, el grupo de seguridad (solo 80 y 443), la máquina y su IP fija. Además genera los secretos que nadie escribe (`APP_KEY`, `DB_PASSWORD`, `SEALING_PSEUDONYM_KEY`) y copia las llaves de testnet de `.env.testnet`, si lo tienes.

```
make staging-provision
```

Se puede repetir: actualiza la pila y nunca reemplaza un secreto que ya existe. La máquina tarda unos minutos en instalar Docker la primera vez.

**2. Tus secretos.** Cada uno se pide sin mostrarse y va directo a SSM:

```
make staging-secret NAME=DUCKDNS_TOKEN     # el token de duckdns.org
make staging-secret NAME=MAIL_PASSWORD     # la contraseña de aplicación de Gmail, sin espacios
make staging-secret NAME=ALERT_WEBHOOK_URL # opcional: las alertas a Discord o Slack
```

Si no tienes `.env.testnet`, también `STELLAR_SEALER_SECRET` y `STELLAR_SPONSOR_SECRET`.

**3. El despliegue.**

```
make staging-deploy REF=main
```

Corre en la máquina por SSM, sin SSH:
1. trae la rama `REF` de GitHub;
2. carga la plantilla pública y los secretos de SSM, solo en el entorno del proceso;
3. apunta DuckDNS a la IP fija de la máquina;
4. pide el certificado comodín, si no hay uno con más de 30 días;
5. corre `deploy/deploy.sh`: construye la imagen, migra, levanta todo y comprueba `/up` por HTTPS.

La primera vez tarda unos 15 minutos, casi todos para construir la imagen.

**4. El primer Super Administrador.** Desde una sesión en la máquina (ver "Entrar a la máquina"):

```
sudo docker exec -it -u workspace govtrace-app-1 php artisan admin:create ana@correo.co
```

La contraseña se pide o se genera, y nunca va en la línea de comandos.

## Desplegar otra versión

```
make staging-deploy REF=main
```

Con `REF=<rama>` se prueba una rama antes de fusionarla. Un secreto nuevo de `make staging-secret` toma efecto en el siguiente despliegue.

### Lo que se ve mientras se despliega (it. 42c)

Durante 10 a 30 segundos, mientras se reemplaza el contenedor de la app, nginx responde **503** con una página en español: "Estamos actualizando GovTrace. Vuelva a intentar en un minuto". La página se recarga sola cada 20 s. A la app del veedor le llega en JSON, y un reporte que estaba enviando **queda guardado en el celular** y se envía solo después.

**Si una migración rompe la versión anterior** (borra o renombra una columna que el código viejo usa), despliega con el modo mantenimiento:

```
make staging-deploy REF=main MAINTENANCE=1
```

Apaga la app, el worker y el calendario **antes** de migrar, y durante la migración se ve la misma página. `php artisan down` no sirve para esto: la marca vive dentro del contenedor de la app, y el despliegue lo reemplaza.

## El certificado

- Let's Encrypt valida que el dominio es nuestro con un registro TXT en DuckDNS. DuckDNS guarda **un solo TXT**, y un certificado de `govtrace.duckdns.org` y `*.govtrace.duckdns.org` pide dos a la vez. Por eso `deploy/issue-certificate.sh` lo pide en dos pasos: primero solo el nombre principal, y después los dos, cuando Let's Encrypt ya validó el primero y solo pide el TXT del comodín.
- Un temporizador de systemd lo revisa cada semana (`govtrace-certificate.timer`) y lo renueva cuando le quedan menos de 30 días; nginx lo recarga sin cortar el servicio.
- `make staging-aws-check` prueba todo esto contra Pebble, la CA de prueba de Let's Encrypt, con un DuckDNS de mentira que también guarda un solo TXT.

## Entrar a la máquina

No tiene SSH: se entra por **Session Manager** de AWS. Una vez, instala el plugin:

```
curl -fsSL "https://s3.amazonaws.com/session-manager-downloads/plugin/latest/ubuntu_64bit/session-manager-plugin.deb" -o /tmp/session-manager-plugin.deb
sudo dpkg -i /tmp/session-manager-plugin.deb
```

Y entra con el ID de la máquina, que muestra `make staging-provision`:

```
aws ssm start-session --profile govtrace-staging --region us-east-2 --target <InstanceId>
```

Adentro, el código está en `/opt/govtrace`, y los contenedores se ven con `sudo docker ps`. Por ejemplo:
- los logs: `sudo docker logs govtrace-app-1`;
- los enlaces de los correos que salieron: `sudo docker exec -u workspace govtrace-app-1 php artisan invitations:latest`, si el correo está en `MAIL_MAILER=log`.

## Borrar todo

```
aws cloudformation delete-stack --profile govtrace-staging --region us-east-2 --stack-name govtrace-staging
```

Borra la máquina (con su base de datos), la IP, el rol y el grupo de seguridad. **Los dos buckets quedan**, porque guardan evidencias y respaldos: si de verdad sobran, se vacían y se borran a mano. Los secretos de SSM también quedan.

## El horario, lo que cuesta y la seguridad (it. 61)

### Staging duerme de noche

**Se apaga a las 21:00 y se enciende a las 8:00, hora de Colombia, todos los días.** Lo hace EventBridge Scheduler, sin Lambda, con un rol que solo puede encender y apagar esta máquina. Para cambiarlo:

```
STAGING_STOP_AT="cron(0 23 * * ? *)" make staging-provision          # apagarla a las 23:00
STAGING_START_AT="cron(0 8 ? * MON-FRI *)" make staging-provision    # encenderla solo de lunes a viernes
STAGING_SLEEP=no make staging-provision                              # encendida siempre
```

Para encenderla fuera de horario (el `InstanceId` sale al final de `make staging-provision`):

```
aws ec2 start-instances --profile govtrace-staging --region us-east-2 --instance-ids <InstanceId>
```

**Lo que pasa mientras duerme:**
- **El sitio no responde:** no hay página de mantenimiento, porque la máquina está apagada. Un veedor no puede elegir la obra, porque la lista viene del servidor (V20). Un reporte que ya tenía armado queda en la bandeja de salida de su celular y se envía cuando la máquina vuelve.
- **Las tareas de la noche no corren**, porque el calendario no recupera las que se perdió:
  - la sincronización con SECOP es a las 02:00. **En staging, ponla a las 08:30** desde el panel del Super Administrador, en *Parámetros → Hora de sincronización SECOP*;
  - las purgas de retención (01:00 a 01:30) y el resumen de quién sigue qué obra (03:30) quedan para la noche siguiente en que esté encendida. El resumen también se recalcula en cada despliegue;
  - no hay copias de respaldo por hora de noche, pero tampoco hay cambios que respaldar.
- **El certificado no se pierde:** su temporizador semanal corre al encender si le tocaba de noche (`Persistent=true`).
- **Un efecto bueno:** reiniciar cada día aplica las actualizaciones de seguridad de Ubuntu que necesitan un reinicio.
- **Al apagarse,** Docker detiene los contenedores y PostgreSQL recibe la orden de cerrar. Si no alcanzara, se recupera solo al arrancar con su registro de transacciones.

### Lo que cuesta

Aproximado, con los precios de lista de us-east-2 que se conocían al escribir esto; las cifras reales están en *Billing → Cost Explorer*:

| Qué | Encendida siempre | Con el horario |
|---|---|---|
| La máquina (t4g.small, ~0,0168 USD por hora) | ~12,3 USD al mes | ~6,6 USD al mes (13 horas al día) |
| El disco (30 GB gp3) | ~2,4 USD | ~2,4 USD: se paga apagada |
| La IP fija (IPv4 pública, ~0,005 USD por hora) | ~3,6 USD | ~3,6 USD: se paga apagada |
| S3 (evidencias y respaldos) | centavos | centavos |
| **Total** | **~18 USD al mes** | **~13 USD al mes** |

Mientras haya créditos, los créditos pagan esto.

**El presupuesto y los gastos fuera de lo normal.** Con tu correo, la pila crea:
- un presupuesto de 20 USD al mes **sin contar los créditos** (con ellos, el costo parece cero y la alerta no avisaría nada), que avisa al 50, al 80 y al 100 % de lo gastado, y cuando el pronóstico del mes pasa del 100 %;
- una alerta de gastos fuera de lo normal por servicio, una vez al día, desde 5 USD. Sirve también de alarma de seguridad: una máquina que alguien levantó con unas llaves robadas se nota primero en la cuenta.

```
STAGING_ALERT_EMAIL=tu@correo.co make staging-provision
STAGING_ALERT_EMAIL=tu@correo.co STAGING_BUDGET_USD=30 make staging-provision   # otro presupuesto
```

Ninguna de las dos cuesta: un presupuesto sin acciones y la detección de anomalías son gratuitos.

**Las etiquetas:** todo lleva `Project=govtrace` y `Environment=staging`. Para ver los costos por etiqueta, actívalas una vez en *Billing → Cost allocation tags*.

### La seguridad

**Lo que la pila ya cumple**, y `make staging-aws-check` comprueba en cada PR:
- solo los puertos 80 y 443 abiertos; sin SSH, se entra por SSM;
- IMDSv2 obligatorio;
- el disco y los buckets cifrados; los buckets privados, que sobreviven a la pila y **solo aceptan HTTPS**;
- el rol de la máquina, sin comodines: solo sus secretos y sus dos buckets;
- **la máquina no se puede terminar por error** (`DisableApiTermination`), porque su disco guarda la base de datos;
- el horario solo puede encender y apagar esta máquina;
- el presupuesto no cuenta los créditos.

**Para reemplazar la máquina a propósito** (otra AMI, por ejemplo), primero se quita la protección:

```
aws ec2 modify-instance-attribute --profile govtrace-staging --region us-east-2 --instance-id <InstanceId> --no-disable-api-termination
```

**Lo que te toca a ti, una vez y gratis:**
- **MFA** en el usuario con el que entras a la consola de AWS;
- **el cifrado por defecto de los discos nuevos** de la cuenta: `aws ec2 enable-ebs-encryption-by-default --profile govtrace-staging --region us-east-2`.

**Lo que no se activó, porque cuesta:** GuardDuty (30 días gratis y después unos dólares al mes), AWS Config y un registro de CloudTrail en S3. Para staging alcanza el historial de eventos de CloudTrail, que guarda 90 días sin costo. En producción se deciden con la 37b.

## Lo que hay que saber

- **El GPS del celular en 2000 m:** Chrome tiene, en Android, solo la ubicación aproximada, y la app pide 50 m o menos. En *Ajustes → Aplicaciones → Chrome → Permisos → Ubicación*, activa **"Usar ubicación precisa"** y cierra Chrome del todo antes de volver a intentarlo. En un iPhone: *Ajustes → Privacidad y seguridad → Localización → Safari (o Chrome) → Ubicación exacta*.
- **Testnet se reinicia** cada tanto, y con ella desaparecen el contrato y los sellos. Si pasa, corre `make testnet-setup`, actualiza `.env.staging.example` con el contrato y las cuentas nuevas, y carga las llaves nuevas con `make staging-secret`.
- **Es una prueba, no evidencia definitiva:** por el reinicio de testnet, y porque todavía no hay operador ni revisión legal (`docs/viabilidad-legal.md`, L1 a L3).
- **Gmail** envía unos 500 correos al día, y DuckDNS es un servicio gratuito que a veces se cae. Alcanza para un piloto, no para producción: allí van un dominio propio, SES con DKIM y KMS (37b).
- **Los respaldos** van cada hora al bucket `govtrace-staging-respaldos`, con versionado, pero en la misma región: el proyecto de AWS no permite otra (D14).
- **Los secretos** se ven en la consola de AWS, en *Systems Manager → Parameter Store*, solo con los permisos de la cuenta.
