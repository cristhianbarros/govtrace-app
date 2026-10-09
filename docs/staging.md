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

## Lo que hay que saber

- **Testnet se reinicia** cada tanto, y con ella desaparecen el contrato y los sellos. Si pasa, corre `make testnet-setup`, actualiza `.env.staging.example` con el contrato y las cuentas nuevas, y carga las llaves nuevas con `make staging-secret`.
- **Es una prueba, no evidencia definitiva:** por el reinicio de testnet, y porque todavía no hay operador ni revisión legal (`docs/viabilidad-legal.md`, L1 a L3).
- **Gmail** envía unos 500 correos al día, y DuckDNS es un servicio gratuito que a veces se cae. Alcanza para un piloto, no para producción: allí van un dominio propio, SES con DKIM y KMS (37b).
- **Los respaldos** van cada hora al bucket `govtrace-staging-respaldos`, con versionado, pero en la misma región: el proyecto de AWS no permite otra (D14).
- **Los secretos** se ven en la consola de AWS, en *Systems Manager → Parameter Store*, solo con los permisos de la cuenta.
