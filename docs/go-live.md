# Lista de salida a producción

La salida de GovTrace a la red principal de Stellar. Lo que se preparó sin datos de producción está en la **iteración 37a**; lo que espera a producción, en la **37b** (`specs/PLAN.md`).

✅ listo · ⬜ pendiente, con su iteración

Lo que depende del operador (quién opera GovTrace, el dominio, la cuenta de AWS, el proveedor de RPC, la tesorería en XLM y lo legal), paso a paso: [`docs/preparacion-red-principal.md`](preparacion-red-principal.md).

## 1. Red principal de Stellar (R-CFG-01, D13)

- ✅ **El despliegue:** `make network-deploy NETWORK=mainnet` hace el mismo camino que se probó de punta a punta en testnet (`make network-deploy-check`).
  - La tesorería, ya fondeada, crea la selladora y la patrocinadora, despliega el contrato y extiende su vigencia.
  - Se niega sin `CONFIRM_MAINNET=yes` y con un RPC que no sea de la red principal.
  - Solo escribe valores públicos, en `.env.mainnet.deploy`.
- ✅ **Varias evidencias a la vez** (it. 39): la selladora sella por turnos, una transacción por ledger: unos 10 a 12 sellos por minuto en la red principal. Una ráfaga se sella en orden, sin gastar intentos. Probado contra la red local con 7 evidencias de dos organizaciones y con `make demo` (9 de golpe).
- ✅ **La aplicación no arranca en la red principal** si falta `STELLAR_PUBLIC_RPC_URL` o si es el mismo endpoint que `STELLAR_RPC_URL`.
- ⬜ **Cuenta del proveedor de RPC** (QuickNode o Validation Cloud), con dos endpoints (37b):
  - uno privado para el servidor, que va en `STELLAR_RPC_URL` y, para el despliegue, en `STELLAR_MAINNET_RPC_URL`;
  - uno de solo lectura, restringido al dominio de GovTrace y con CORS para el validador del navegador, que va en `STELLAR_PUBLIC_RPC_URL`.
- ⬜ **Las llaves de la selladora y la patrocinadora**, generadas en el gestor de secretos. Al despliegue solo entran sus direcciones (D11) (37b).
- ⬜ **La tesorería fondeada**, con el presupuesto operativo central y desde un exchange corporativo (D13) (37b):
  - 28 XLM para el contrato (subir el código, desplegar la instancia, extender su vigencia);
  - más el saldo inicial de la patrocinadora (`SPONSOR_STARTING_XLM`, 100 por defecto) y 1,5 XLM de la selladora;
  - después, unos 27 XLM cada ~180 días para volver a extender la vigencia.
- ⬜ **El despliegue** (37b):
  ```bash
  STELLAR_TREASURY_SECRET=… STELLAR_SEALER_ADDRESS=G… STELLAR_SPONSOR_ADDRESS=G… \
  STELLAR_MAINNET_RPC_URL=https://… CONFIRM_MAINNET=yes make network-deploy NETWORK=mainnet
  ```
  Los valores de `.env.mainnet.deploy` pasan al entorno de producción.
- ⬜ **El contrato en `tools/verify/contracts.json`**, en "Public Global Stellar Network ; September 2015", para el verificador independiente (R-MNT-01) (37b).
  - Ahí `rpc` se deja en `null`: el endpoint del navegador está restringido al dominio, así que quien verifica desde su equipo usa `--rpc <su RPC>`.
- ⬜ **Un reporte hasta "Sellada" en la red principal** (37b).
- ⬜ **La vigencia del contrato, cada ~180 días:** `make network-extend NETWORK=mainnet`, con la llave de la tesorería. La it. 32 avisa 30 días antes.

## 2. Firma remota (D11, opción b)

- ✅ **Elegido: AWS KMS** (2026-09-29).
- ⬜ **La integración con AWS KMS** (37b). La llave de la selladora no sale de KMS; va detrás de la misma interfaz `SealingNetwork`. La llave es `ECC_NIST_EDWARDS25519` con `SIGN_VERIFY`, y se firma con `ED25519_SHA_512` y `MessageType=RAW` sobre el hash de la transacción.

## 3. La aplicación

- ✅ **`.env.production.example`**, la plantilla sin secretos. `make secrets-check` revisa que siga así, y un test que no pierda lo que la hace segura (it. 41): HTTPS, sin depuración, sesiones seguras, logs diarios, español.
- ✅ **Lista para ir detrás de TLS** (it. 41): confía solo en el proxy de la red privada (`TRUSTED_PROXIES`), CSP y `Permissions-Policy` en toda respuesta, HSTS por HTTPS, y límites de abuso (`REPORTS_PER_VEEDOR_PER_HOUR`, `PUBLIC_REQUESTS_PER_MINUTE`, `OPEN_DATA_PER_MINUTE`).
- ✅ **El stack de producción** (it. 42a): `docker-compose.prod.yml` con la imagen inmutable, nginx con TLS y el certificado comodín, S3 de verdad, sin montar el código; lo levanta `deploy/deploy.sh` con los secretos por el entorno. Probado entero en local con `make staging-check`, que corre en el pipeline.
- ⬜ **En AWS** (it. 42b): la instancia con su rol, los buckets, SES, Route 53, el certificado de Let's Encrypt (DNS en Route 53) y los secretos en SSM Parameter Store.
- ⬜ **El entorno de producción, desde esa plantilla.** Los secretos, desde el gestor: `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD`, `EVIDENCE_AWS_SECRET_ACCESS_KEY`, `SEALING_PSEUDONYM_KEY` (fija para siempre) y las llaves de Stellar. `APP_DEBUG=false`.
- ⬜ **El primer Super Administrador:** el panel no lo crea. Desde la consola del servidor, `php artisan admin:create <correo> --name="…"` (la contraseña se pregunta sin eco, o se genera y se muestra una vez; nunca va en la línea de comandos). En Docker, `make admin EMAIL=<correo>`.
- ⬜ **Migraciones y DIVIPOLA:** `php artisan migrate --force`, `php artisan tenants:migrate --force` y `php artisan db:seed --class=DivipolaSeeder`.
- ⬜ **El worker de la cola y el calendario corriendo.** Tras cada despliegue, `php artisan queue:restart`: el worker guarda en memoria el código con que arrancó, y sin reiniciarlo seguiría sellando con el anterior. Las horas del calendario son las de Colombia (`schedule_timezone`, it. 45a).
- ⬜ **Al menos dos Super Administradores activos** (it. 46a, US-063-USR). Después del primero, el segundo se invita desde el panel, en **Super Administradores**. Mientras haya uno solo, el panel lo avisa en todas sus pantallas. `make admin` sigue siendo la vía para recuperar el acceso si todos lo pierden.
- ⬜ **Correo SMTP real** para las alertas, y **`ALERT_WEBHOOK_URL`** (Slack o Discord). Con el webhook, las alertas críticas llegan aunque ningún Super Administrador pueda leer su correo (it. 46a).
- ⬜ **La política de tratamiento de datos** (it. 44e, Ley 1581 de 2012):
  - un abogado revisa el texto de `/privacidad` (`resources/js/Pages/Public/Privacy.vue`) y decide quién es el responsable del tratamiento (D-V2-10);
  - sus datos van en `PRIVACY_CONTROLLER_*`, y `PRIVACY_HOSTING` dice dónde están los servidores;
  - mientras falte uno, la página se ve como borrador;
  - si el responsable está obligado, inscribir las bases de datos en el Registro Nacional de Bases de Datos de la SIC.

## 4. Respaldos (R-BCK-01..05)

- ✅ **El servicio `backup`:** una copia cada hora, guardada 30 días, con la restauración de prueba (`make restore-drill`).
- ✅ **La réplica fuera del sitio** (`BACKUP_OFFSITE_S3_URL`), probada en `make backup-check`: se replica, se borra allá a los 30 días y se restaura desde allá.
- ⬜ **El volumen de las copias en otro disco u otra máquina** (37b).
- ⬜ **El bucket de la réplica** en otra cuenta o región, con versionado y una regla que guarde 30 días las versiones anteriores (37b).
- ⬜ **La restauración de prueba en producción**, con sus datos reales, anotada en `docs/restore.md` (R-BCK-05) (37b).

## 5. Monitoreo y CI

- ⬜ **El monitor externo (Gatus, `ops/monitoring`)** apuntando a producción, con correo y webhook (US-044-MON).
- ⬜ **Las credenciales de Jenkins** para la prueba de humo en testnet, con el contrato oficial `CAKUYPROMNYKZCMCNI2N5RTWZE3JZ7RR4Q2W5FNVQNPANNMQPLJ4PLDY`: `stellar-testnet-contract-id`, `stellar-testnet-sealer-secret` y `stellar-testnet-sponsor-secret`. Las llaves salen de las identidades `govtrace-37a-sealer` y `govtrace-37a-sponsor`.
- ✅ **El pipeline:** todas sus etapas en verde en un entorno de cero (it. 35 y 36), y la auditoría de dependencias (`make audit`, it. 41).
