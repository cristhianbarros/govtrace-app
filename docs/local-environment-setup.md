# Entorno local: recorrer toda la interfaz

Cómo levantar GovTrace en tu equipo y recorrer el flujo completo: crear una organización, invitar a un veedor, ver una obra en el mapa, tomar evidencia, sellarla y verificarla. No necesita cuentas externas ni gasta XLM: usa una red Stellar propia (*standalone*).

Solo hacen falta Docker y `make`. `make help` lista todos los comandos.

- **Para una demostración en vivo**, un solo comando: [`make demo`](#demostración-en-vivo-un-solo-comando).
- **Para recorrerlo a mano desde cero**, el [paso a paso](#paso-a-paso-a-mano).

## Las direcciones

| Qué | Dirección |
|---|---|
| Panel global (Super Administrador) | `http://govtrace.localhost:8080` |
| Una organización, por su subdominio | `http://<subdominio>.govtrace.localhost:8080` |
| El puerto | `HTTP_PORT` en `.env.docker` (8080 por defecto) |

`*.localhost` se resuelve solo a `127.0.0.1`: no hay que editar `/etc/hosts`.

## Demostración en vivo: un solo comando

```bash
make demo
```

Desde un equipo con solo Docker y `make`, en la primera corrida (construir las imágenes y compilar el frontend) tarda unos minutos; después, lo que tarde en sellar los reportes. Hace:

1. **La aplicación:** `make setup` si es la primera vez (con las migraciones y la DIVIPOLA); si no, solo arranca los servicios y migra.
2. **La red Stellar local y el contrato de sellado,** si no están. Si el contrato de `.env` ya responde en la red, se deja.
3. **La organización de demostración** (`php artisan demo:prepare`):
   - `veeduria-demo`, con su Administrador y dos veedores, todos con contraseña conocida;
   - 7 contratos de Magdalena y 6 obras (una sin ubicación, una que agrupa dos contratos);
   - 9 reportes con foto, enviados de golpe, que pasan por el mismo camino que los de un veedor y se sellan **de verdad** en la red local, uno por ledger;
   - 6 publicados (se ven en el mapa) y 3 esperando en la bandeja del Administrador, para publicarlos en vivo.

Al final imprime las direcciones y las contraseñas:

| Quién | Dirección | Correo | Contraseña |
|---|---|---|---|
| Super Administrador | `http://govtrace.localhost:8080` | `admin@demo.govtrace.test` | `Demo#2026Global` |
| Administrador de la organización | `http://veeduria-demo.govtrace.localhost:8080` | `admin@veeduria-demo.org` | `Demo#2026Admin` |
| Veedor | ídem | `ana.torres@correo.co` | `Demo#2026Veedor` |
| Veedor | ídem | `luis.mendoza@correo.co` | `Demo#2026Veedor` |

Se puede repetir: cada corrida borra la organización de demostración y la deja como nueva. Solo toca la que está en el subdominio `veeduria-demo`; una organización creada a mano no se toca. **No corre en producción.**

Los datos son de mentira: los contratos `CO1.PCCNTR.91…` no existen en SECOP y las fotos son imágenes de demostración rotuladas como tales.

### Un guion para la demostración

Recorre los cuatro perfiles con la interfaz de las it. 40 y 43. Todo se hace con clics, sin escribir direcciones, salvo la primera.

1. **El Inicio** (`http://govtrace.localhost:8080`):
   - "¿Cómo funciona?" en tres pasos;
   - el directorio de veedurías;
   - en *Veeduría Ciudadana Santa Marta (demo)*, **Ver su mapa de obras**.
2. **El ciudadano, sin cuenta:**
   - **El mapa:** arriba, los tres estados con su signo y cuántas obras tiene cada uno (✓ Normal, ! Alerta, ✕ En riesgo); tocar **✕ En riesgo** deja solo esas.
   - **La lista:** **Lista** muestra las obras como lista; escribir `colegio` en *Buscar una obra por su nombre*.
   - **Una obra:** su estado y **por qué**, en palabras. En una foto, **Comprobar que es original** abre su recibo en la red.
   - **Al pie del mapa:** **Estadísticas del territorio** y **Validar un archivo**; el validador enlaza también al programa independiente.
3. **El Administrador:**
   - **Entrar** (arriba a la derecha): `admin@veeduria-demo.org`.
   - **La Bandeja:** la pestaña cuenta **3** por revisar, y cada evidencia dice su obra, su municipio y quién la envió.
   - **Publicar una:** después, en el menú de la cuenta, **Ver el sitio público** para verla en el mapa.
   - **Rechazar otra** con un motivo, que después verá el veedor.
   - **En el computador:** la barra lateral agrupa todas las pantallas. **En el celular:** tres pestañas y **Más**.
   - **Veedores:** invitar un correo; su enlace, con `make invites`.
4. **El veedor** (`ana.torres@correo.co`):
   - **Nuevo Reporte:** **📍 Obras cercanas**, o buscar `Calle 30`.
   - **¿Qué vio en la obra?:** cada opción con su explicación.
   - **La foto:** **Tomar foto**, o **Elegir de la galería o un PDF**.
   - **Enviar:** mientras falte algo, junto al botón se lee **Para enviar falta:**.
   - **Mis Reportes:** *Sello digital* y *Publicación*, por separado; **Ver recibo**.
   - **El menú de la cuenta:** **Cambiar contraseña** y **Salir**, que siempre pregunta antes.
5. **El Super Administrador** (`admin@demo.govtrace.test`, en `http://govtrace.localhost:8080/login` o en *Acceso para administradores* al pie del Inicio):
   - **Organizaciones:** cada una con su Administrador y el estado de su invitación. Crear una en **+ Nueva organización**, con su Administrador: aparece **Invitación pendiente**, con **Reenviar invitación** y **Revocar invitación**.
   - **SECOP:** **Sincronizar ahora**.
   - **Sellado, Uso, Parámetros y Auditoría.**

Las capturas de todas las pantallas, en celular y en computador: `make ux-check` las deja en `storage/framework/testing/ux/shots/`.

### Lo que hay que saber antes de una demostración

- **Varios reportes a la vez se sellan por turnos.** Stellar admite una sola transacción pendiente de la cuenta selladora: cada sello espera a que el anterior entre en un ledger (en la red local, cerca de un segundo; en testnet y la red principal, unos 5). Ninguno gasta intentos por esperar su turno (it. 39). `make demo` envía sus 9 reportes de golpe, como los que un celular guarda sin señal.
- **Cámara, ubicación e instalar la app.** El navegador solo las da en un contexto seguro. En el mismo equipo, `*.localhost` lo es; desde un celular en tu red (`http://<tu IP>:8080`), no. Sin cámara, puedes subir una imagen desde el equipo. Por la misma razón, "Instalar la app en este celular" solo aparece por HTTPS (staging) o en el mismo equipo.
- **Los correos no se envían:** salen al archivo `storage/logs/mail.log` (`MAIL_MAILER=log`). `make invites` muestra los enlaces de los últimos correos; `make invites LIMIT=10` muestra más.
- **SECOP.** Al registrar una organización se lanza la sincronización con SECOP II en el worker. Con internet, llegan contratos reales de Magdalena, que se suman a los de demostración; sin internet, la sincronización falla y se reintenta sin afectar la demostración.
- **Si algo no sella,** `make demo` lo dice al terminar: casi siempre es el worker (`make ps`) o la red Stellar (`make stellar-up`).

## Paso a paso, a mano

Para partir de una organización vacía en lugar de la de demostración.

### 1. Levantar todo

```bash
make setup          # la primera vez: construye, instala, migra, siembra la DIVIPOLA y compila el frontend
make up             # las siguientes: solo arranca los servicios
make stellar-up     # la red Stellar local, con RPC y friendbot
make contract-deploy
make restart        # para que la app y el worker lean el contrato nuevo del .env
make ps             # app, proxy, pgsql, worker, scheduler y stellar, "healthy"
```

- `make contract-deploy` crea las cuentas de desarrollo (selladora, patrocinadora y tesorería, fondeadas por friendbot), despliega el contrato y escribe su ID y las llaves en `.env`. Ese archivo no va a git.
- Comprobación: `make contract-smoke` sella, rechaza a una cuenta externa y rechaza un duplicado, con transacciones reales.
- El sellado lo hace el **worker** de la cola. Si `make ps` no lo muestra sano, ningún reporte pasará de «En Cola».
- Si `make setup` ya se había corrido antes de que existiera la siembra de la DIVIPOLA, sémbrala: `make artisan CMD="db:seed --class=DivipolaSeeder"`.

### 2. El Super Administrador

En una base nueva **no hay ningún Super Administrador**, y el panel no lo crea (crea organizaciones, no a sí mismo). Se crea una vez desde la consola:

```bash
make admin EMAIL=ana@correo.co NAME="Ana Directora"
```

Pregunta la contraseña sin mostrarla; con la respuesta vacía genera una y la muestra una sola vez. La contraseña nunca va en la línea de comandos. Debe cumplir la misma regla que las demás: 8 caracteres o más, con mayúscula, minúscula, número y símbolo.

Entra en `http://govtrace.localhost:8080/login` y llegarás a `/admin/organizations`.

### 3. Crear una organización

1. `/admin/organizations/new`.
2. Nombre, NIT con dígito de verificación (por ejemplo `900123456-8`) y subdominio (por ejemplo `veeduria-smr`).
3. Nombre y correo de su **Administrador inicial**.
4. Al guardar se crea su base de datos y su dominio: `http://veeduria-smr.govtrace.localhost:8080`.

### 4. El enlace de invitación del Administrador

El correo sale a un archivo, no a un buzón. Para ver su enlace:

```bash
make invites
```

Muestra a quién le llegó cada correo, su asunto y el enlace, ya con el puerto correcto. Ábrelo en el navegador y define la contraseña. Entras al panel de la organización (`/admin/inbox`).

### 5. Configurar el territorio

En la organización, `/admin/territory`: elige el departamento y los municipios que vigila (por ejemplo, Magdalena → Santa Marta). Al guardar se despacha la sincronización de contratos con SECOP II.

### 6. Contratos y obras

Los contratos vienen de SECOP II, una fuente externa que consulta el worker:

- **Con internet:** espera a que el worker termine (`make logs S=worker`). Después ves los contratos en `/admin/contracts`.
- **Sin depender de SECOP:** `make demo`, o el fixture de la prueba de extremo a extremo, que crea una organización con un contrato de Santa Marta y su obra anclada:

  ```bash
  docker compose --env-file .env.docker exec -u workspace app php tests/e2e/fixture.php
  ```

  Deja `http://veeduria-e2e.govtrace.localhost:8080` y el veedor `e2e.veedor@correo.co` (contraseña `Veeduria#2026`). Vuelve a crearla en cada corrida.

### 7. Invitar a un veedor

1. Como Administrador, en `/admin/observers`, invita a un correo.
2. Su enlace sale con `make invites`. El nombre del veedor es la parte local de su correo (deuda aceptada).
3. Ábrelo y define su contraseña. El veedor entra a `/reports/new`.

### 8. Tomar y enviar evidencia

Como veedor, en `/reports/new`:

1. **📍 Obras cercanas** (`/worksites/nearby`) o **Buscar Obra** (`/contracts/search`).
2. **¿Qué vio en la obra?** y la foto: **Tomar foto** (cámara trasera) o **Elegir de la galería o un PDF**. La ubicación la toma la app.
3. **Enviar Reporte.** Mientras falte algo, junto al botón se lee qué. El reporte pasa a la cola de sellado.

Cámara y ubicación: ver [arriba](#lo-que-hay-que-saber-antes-de-una-demostración).

### 9. Ver el sellado

- **Como veedor:** `/my-reports` lista sus reportes y `/reports/{id}/receipt` es el recibo. Pasa a **Sellada** en unos segundos: el worker firma y manda la raíz al contrato.
- **Si no avanza:**
  - `make logs S=worker` muestra el error;
  - el Super Administrador ve las fallas y puede re-encolarlas en `/admin/sealing`;
  - la causa más común es un contrato que no coincide con el `.env` (repite `make contract-deploy` y `make restart`).

### 10. Publicar y ver en el mapa

1. Como Administrador, `/admin/inbox`: **Publicar** el reporte (o rechazarlo o retirarlo).
2. Público, sin sesión: el mapa en `http://<subdominio>.govtrace.localhost:8080/`, la obra en `/worksite/{id}`, las estadísticas en `/stats` y los datos abiertos en `/open-data.csv` y `/open-data.json`.
3. Verificación:
   - el validador del navegador en `/verify` (lee el sello del contrato por el RPC);
   - o el verificador independiente: `make verify-check`.

### 11. Otras pantallas para recorrer

- **Panel global:** `/admin/parameters`, `/admin/audit`, `/admin/secop-health`, `/admin/sealing` y `/admin/usage`.
- **Organización:** `/admin/observers`, `/admin/territory`, `/admin/contracts`, `/admin/worksites`, `/admin/organization`, `/admin/audit`, `/admin/summary` y la autorización al Super Administrador en `/admin/authorization`.

## Empezar de cero

```bash
make teardown       # DESTRUCTIVO: borra contenedores y volúmenes, incluida la base de datos
make demo           # o make setup, para el paso a paso
```

La red *standalone* también empieza vacía: `make demo` vuelve a desplegar el contrato si el de `.env` ya no responde. Con el paso a paso, corre `make stellar-up` y `make contract-deploy` de nuevo.

## Mostrarlo fuera de tu equipo

`make demo` funciona en tu equipo. Para que otras personas entren, hay dos caminos; ninguno está probado todavía:

- **Un túnel** (ngrok, Cloudflare Tunnel) hacia el puerto 8080. Cada organización vive en su propio subdominio, así que el túnel tiene que aceptar **subdominios comodín** (`*.tudominio.com`), y hay que cambiar `APP_URL`, `TENANCY_CENTRAL_DOMAINS` y `TENANCY_APEX_DOMAIN`. Un túnel de una sola dirección no sirve. Verifica antes que tu plan soporte los comodines. Con HTTPS, la cámara y la ubicación del celular funcionan.
- **Un entorno intermedio en la nube** (*staging*), apuntando a la testnet de Stellar: es lo que le permite a otras personas hacer el flujo completo por su cuenta, con dominio, HTTPS y correo reales. Es trabajo de infraestructura aparte: está propuesto en `docs/estado-37b.md`.

## Lo que este entorno no prueba

- **AWS KMS:** no se emula aquí. Su prueba es contra AWS, con una transacción en testnet (`docs/estado-37b.md`).
- **El RPC público con restricción por dominio y CORS**, que aplica el proveedor de RPC de la red principal.
- **El SMTP real, las comisiones reales y el bucket de la réplica en otra cuenta.**
- **Testnet:** `make smoke-testnet` sella un reporte en la testnet oficial (necesita `.env.testnet`).
