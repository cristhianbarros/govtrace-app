# Estado al 2026-09-29: testnet oficial y lo que falta para la red principal

Una foto del cierre de la iteración 37a y del arranque de la 37b. La lista viva de la salida a producción es `docs/go-live.md`, y las decisiones están en `specs/PLAN.md` (D11, D13).

## 1. Testnet oficial

- **Contrato:** `CAKUYPROMNYKZCMCNI2N5RTWZE3JZ7RR4Q2W5FNVQNPANNMQPLJ4PLDY`. Es el entorno de pruebas previo a la red principal.
- **Cuentas:**
  - selladora `GCUCPDHRG3AVSYBBEHNB3A5QQ4NEFHAKLTGR74FQZJQQCEOMKPXD3NJM` (identidad `govtrace-37a-sealer`);
  - patrocinadora `GCOQRTRH6KYDSAGEGF4R7O4OUM5F5NZJD5ESVFVUALCZ7TOXSXR6S6KU` (identidad `govtrace-37a-sponsor`).
  - Se cambiaron junto con el contrato: el contrato solo acepta sellos de su propia selladora.
- **RPC:** el de la SDF (`https://soroban-testnet.stellar.org`), en `STELLAR_RPC_URL` y en `STELLAR_PUBLIC_RPC_URL`. La restricción por dominio y CORS se aplica en la infraestructura de la red principal.
- **`.env.testnet`** (fuera de git) apunta a ese contrato y a esas cuentas.
- **`tools/verify/contracts.json`** lista el contrato nuevo junto al anterior (`CABXHM74…`), cuyos sellos siguen siendo verificables.
- **Prueba de humo oficial (`make smoke-testnet`), en verde**, a 0,270728 XLM por sello:
  - `4a4729362dc8304985dc32554f38e081903641581f064492f7bc2e32adb2b2bc` (ledger 4934885)
  - `4bd1cd8113f211deb9a7a39ee1ee614949a513e3227ef7d9a134fff741d44483` (ledger 4934886)
- **Alerta de saldo de la patrocinadora:** se mantiene en 50 XLM.

## 2. Credenciales de Jenkins

Hay que actualizar tres, no una, porque las llaves cambiaron con el contrato:

| Credencial | Valor |
|---|---|
| `stellar-testnet-contract-id` | `CAKUYPROMNYKZCMCNI2N5RTWZE3JZ7RR4Q2W5FNVQNPANNMQPLJ4PLDY` |
| `stellar-testnet-sealer-secret` | la llave de `govtrace-37a-sealer` |
| `stellar-testnet-sponsor-secret` | la llave de `govtrace-37a-sponsor` |

Las llaves se sacan en la terminal local, sin pegarlas en ningún chat ni archivo del repositorio (D11):

```bash
docker compose --env-file .env.docker --profile stellar run --rm -T soroban stellar keys show govtrace-37a-sealer
docker compose --env-file .env.docker --profile stellar run --rm -T soroban stellar keys show govtrace-37a-sponsor
```

## 3. AWS KMS (D11, opción b)

**Nombres correctos**, verificados en el modelo del API de KMS que trae la CLI de AWS:

- **Tipo de llave:** `KeySpec=ECC_NIST_EDWARDS25519`, con `KeyUsage=SIGN_VERIFY`. `ECC_ED25519` no existe.
- **Firma:** `SigningAlgorithm=ED25519_SHA_512` con `MessageType=RAW`, sobre los 32 bytes del hash de la transacción. Es el Ed25519 puro que verifica Stellar.
- **No sirve `ED25519_PH_SHA_512`:** es Ed25519ph, y Stellar rechaza sus firmas.

**El emulador local no sirve** (probado el 2026-09-29): LocalStack 3.8, el gratuito, no crea llaves `ECC_NIST_EDWARDS25519` (error interno en `CreateKey`), y la versión actual exige una licencia (`LOCALSTACK_AUTH_TOKEN`). La prueba tiene que ser contra AWS. (La primera sonda no respondía por otra razón: el contenedor estaba en la red por defecto de Docker, que la VPN desvía; en la red del proyecto sí responde.)

**La prueba de concepto**, antes de la integración:

1. Crear la llave y derivar su dirección `G…` de la llave pública.
2. Fondear esa dirección en testnet con friendbot.
3. Armar una transacción de testnet, firmar su hash en KMS y verificar la firma localmente.
4. Enviarla y que la red la acepte.

**Lo que necesita:**

- Un perfil de AWS configurado localmente, de un usuario o rol limitado a KMS. Permisos: `kms:CreateKey`, `kms:GetPublicKey`, `kms:Sign` y `kms:ScheduleKeyDeletion` (para borrar la llave al terminar).
- La región.
- La confirmación del costo: cada llave cobra un mes y cada firma aparte, poco para la prueba.

## 4. Lo que queda pendiente

### Lo que depende de ti

Bloquea lo demás:

1. **Jenkins:** las tres credenciales de la sección 2. No depende de nada más.
2. **AWS:** el perfil, la región y el visto bueno del costo (sección 3). Desbloquea la prueba de concepto de KMS.
3. **Proveedor de RPC** (QuickNode o Validation Cloud), con dos endpoints de la red principal (D13):
   - uno privado para el servidor (`STELLAR_RPC_URL`, y `STELLAR_MAINNET_RPC_URL` para el despliegue);
   - uno de solo lectura, restringido al dominio de GovTrace y con CORS para el validador del navegador (`STELLAR_PUBLIC_RPC_URL`).
4. **La tesorería fondeada** desde el exchange corporativo, con el presupuesto operativo central (D13):
   - 28 XLM para el contrato;
   - 100 XLM del saldo inicial de la patrocinadora (`SPONSOR_STARTING_XLM`);
   - 1,5 XLM de la selladora;
   - un margen para comisiones y la reserva de la propia tesorería.
5. **La infraestructura de producción:**
   - el gestor de secretos y el entorno desde `.env.production.example`, con `APP_DEBUG=false`;
   - correo SMTP real y `ALERT_WEBHOOK_URL`, si se usa Slack o Discord;
   - el volumen de las copias en otro disco u otra máquina;
   - el bucket de la réplica fuera del sitio, en otra cuenta o región, con versionado y una regla que guarde 30 días las versiones anteriores;
   - el monitor externo (Gatus, `ops/monitoring`) apuntando a producción.

### Una decisión abierta

**¿La patrocinadora también firma desde KMS?** D11 (b) nombra solo la llave de la selladora. Pero la patrocinadora es la que tiene fondos: la hot wallet, unos 100 XLM. Su firma del *fee bump* es el mismo Ed25519 sobre el hash de una transacción, así que la misma integración le sirve.

- **Recomendación: sí, las dos en KMS.** Es el mismo código, y así ninguna llave con fondos vive en el entorno del servidor.
- **El costo:** una llave más al mes.

### Lo que hago yo, en orden, cuando lo anterior esté

1. **La prueba de concepto de KMS** en testnet (sección 3). Necesita el paso 2.
2. **La integración con KMS** detrás de la interfaz `SealingNetwork`, con sus tests y la prueba de humo en testnet firmando desde KMS. Necesita que la prueba de concepto pase.
3. **El despliegue en la red principal:** `make network-deploy NETWORK=mainnet`.
   - Necesita los pasos 3 y 4, y las direcciones de la selladora y la patrocinadora (de KMS, o del gestor de secretos).
   - La llave de la tesorería entra solo por el entorno: lo puedes correr tú.
4. **El contrato de la red principal en `tools/verify/contracts.json`**, con `rpc` en `null`: quien verifica desde su equipo usa `--rpc <su RPC>`.
5. **En producción:** las migraciones, `tenants:migrate`, `DivipolaSeeder`, el worker y el calendario.
6. **Un reporte hasta "Sellada"** en la red principal.
7. **La restauración de prueba en producción**, con sus datos reales, anotada en `docs/restore.md`: menos de 1 h de datos perdidos y menos de 4 h de recuperación (R-BCK-05).
8. **El cierre:** `docs/go-live.md` completo, la 37b marcada como cumplida en `specs/PLAN.md` y R-CFG-01, R-BCK-02 y R-BCK-05 en ✅ en `specs/AUDIT.md`.

### Recurrente, después de la salida

- **La vigencia del contrato cada ~180 días:** `make network-extend NETWORK=mainnet`, con la llave de la tesorería (~27 XLM). La aplicación avisa 30 días antes.
- **La recarga de la patrocinadora** desde la tesorería, cuando baje de 50 XLM.

### Deuda técnica aceptada

La aceptaste el 2026-09-29 y no bloquea la salida. Está en la tabla de `specs/PLAN.md`:

- los mensajes por defecto de Laravel en inglés;
- el calendario en UTC;
- repetir `make setup` sobre un stack que ya corre;
- el nombre del veedor tomado de su correo;
- la DIVIPOLA sin sembrar en desarrollo.
