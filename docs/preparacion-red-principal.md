# Preparar la salida a la red principal

Lo que tiene que hacer el operador de GovTrace (cuentas, dinero y lo legal) para que el sistema salga a la red principal de Stellar, y en qué orden. La lista técnica completa, con lo que ya está listo, sigue en [`docs/go-live.md`](go-live.md).

⬜ pendiente · ✅ hecho

**Una regla para todo el documento:** ninguna llave secreta, contraseña ni URL con token se escribe en un chat, un correo o el repositorio. Van al gestor de secretos (SSM Parameter Store en AWS) o, en el caso de la tesorería, a papel o una billetera física.

---

## 0. Quién opera GovTrace

⬜ **Decidir quién es el operador.** Es el dueño de la cuenta de AWS, de la cuenta en el exchange y del contrato con el proveedor de RPC, y el responsable del tratamiento de los datos (Ley 1581 de 2012).

| Opción | Cuándo sirve | Qué implica |
|---|---|---|
| Una persona natural | Un piloto con pocas veedurías | Responde con su nombre y su patrimonio |
| Una fundación, ESAL o empresa | La operación con veedurías reales | Cuentas y facturas a nombre de la entidad; se entrega entera si el proyecto cambia de manos |

Todo lo que sigue se hace a nombre del operador.

---

## 1. El dominio

- ⬜ Comprar el dominio (por ejemplo, uno `.co`).
- ⬜ Llevar su DNS a Route 53, en la cuenta de AWS del paso 2.
- Cada veeduría vive en un subdominio (`<veeduría>.<dominio>`): el DNS y el certificado usan un comodín (`*.<dominio>`).

**Se entrega:** el nombre del dominio.

---

## 2. La cuenta de AWS

Una cuenta **exclusiva** para GovTrace, no la personal: aísla la facturación y los riesgos, y se puede traspasar.

1. ⬜ Activar MFA en el usuario raíz y no crearle nunca llaves de acceso.
2. ⬜ Crear un usuario para el día a día (IAM Identity Center o IAM), también con MFA.
3. ⬜ Crear un presupuesto en AWS Budgets con alertas a 5, 10 y 15 USD.
4. ⬜ Trabajar en la región `us-east-1` (Norte de Virginia).
5. ⬜ En SES (el correo): verificar el dominio de envío y pedir la salida del modo de prueba, que al principio solo deja escribir a direcciones verificadas.
6. ⬜ Para la firma de la selladora con KMS (it. 37b): un usuario o rol limitado a `kms:CreateKey`, `kms:GetPublicKey`, `kms:Sign` y `kms:ScheduleKeyDeletion`, con su perfil configurado en el equipo de quien despliega.

**Se entrega:** el nombre del perfil y la región. Nunca las llaves de acceso.

**Costo:** unos 20 USD al mes con una instancia t4g.small, o unos 33 con una t4g.medium (detalle en `docs/estado-mvp.md`, sección 5). Las cuentas nuevas suelen traer créditos: confirmarlo al crearla, y los precios en la calculadora de AWS.

---

## 3. El proveedor de RPC de la red principal

La red principal no tiene un RPC público gratuito como el de testnet (D13).

1. ⬜ Crear la cuenta en **QuickNode** o **Validation Cloud** y un acceso a la red principal de Stellar (RPC de Soroban, no Horizon).
2. ⬜ Configurar dos accesos:
   - **privado**, para el servidor: va en `STELLAR_RPC_URL` y, el día del despliegue, en `STELLAR_MAINNET_RPC_URL`;
   - **de solo lectura**, para el validador del navegador: restringido al dominio de GovTrace y con CORS. Va en `STELLAR_PUBLIC_RPC_URL`.
3. ⬜ Revisar el plan: los gratuitos tienen límite de solicitudes.

La aplicación no arranca en la red principal si falta el acceso de solo lectura o si es el mismo que el privado.

**Se entrega:** la URL privada lleva un token, así que va directo a SSM. La de solo lectura se puede compartir: de todos modos queda visible en el navegador.

---

## 4. La tesorería en XLM

La tesorería es una cuenta **fría**: nunca vive en el servidor. Crea la selladora y la patrocinadora, despliega el contrato, extiende su vigencia y recarga a la patrocinadora (D11, D12 y D13).

1. ⬜ **Crear la cuenta de la tesorería en frío:** con una billetera física compatible con Stellar (por ejemplo, Ledger) o con la línea de comandos de Stellar en un equipo sin conexión. La llave secreta se guarda en papel o en la billetera física.
2. ⬜ **Comprar XLM** en un exchange, a nombre del operador:

   | Para qué | XLM |
   |---|---|
   | El contrato: subir el código, desplegar la instancia y extender su vigencia | 28 |
   | El saldo inicial de la patrocinadora (`SPONSOR_STARTING_XLM`) | 100 |
   | La selladora | 1,5 |
   | **Mínimo** | **~130** |

   Se recomiendan unos **200 XLM** para tener margen.
3. ⬜ **Enviar primero una cantidad pequeña de prueba** a la dirección pública de la tesorería (la que empieza por `G`) y, cuando llegue, el resto. Una cuenta de Stellar necesita al menos 1 XLM para existir.
4. ⬜ **Guardar los comprobantes** de compra y de envío para la contabilidad.

**Después de la salida:**
- La llave de la tesorería solo se usa el día del despliegue y cada ~180 días para extender la vigencia del contrato (unos 27 XLM; el sistema avisa 30 días antes).
- La patrocinadora se recarga según el uso. **Cada sello costó entre 0,24 y 0,28 XLM en testnet**: unos 25 XLM por cada 100 reportes. La alerta salta bajo 50 XLM, unos 200 sellos. La cifra real se confirma con el primer sello en la red principal.

**Se entrega:** solo la dirección pública de la tesorería.

---

## 5. Lo legal

1. ⬜ **Los datos del responsable del tratamiento**, que van en `.env` de producción:

   | Variable | Qué lleva |
   |---|---|
   | `PRIVACY_CONTROLLER_NAME` | Nombre o razón social |
   | `PRIVACY_CONTROLLER_ID` | NIT o cédula |
   | `PRIVACY_CONTROLLER_ADDRESS` | Domicilio y dirección |
   | `PRIVACY_CONTROLLER_EMAIL` | Correo para ejercer los derechos |
   | `PRIVACY_CONTROLLER_PHONE` | Teléfono |
   | `PRIVACY_HOSTING` | Dónde están los servidores, por ejemplo "Amazon Web Services, en Estados Unidos" |

   Mientras falte uno, la política de `/privacidad` se ve como borrador.
2. ⬜ **La revisión de un abogado:**
   - el texto de la política de tratamiento de datos (`resources/js/Pages/Public/Privacy.vue`);
   - el nombre "Prueba Pericial Criptográfica";
   - la declaración de impedimentos del veedor (Ley 850 de 2003, art. 19);
   - los 30 días de retención de los informes ciudadanos.
3. ⬜ **El Registro Nacional de Bases de Datos de la SIC, si aplica.** Según el Decreto 090 de 2018, hoy solo están obligadas las sociedades y ESAL con activos de más de 100.000 UVT y las entidades públicas. El abogado lo confirma.

**Se entregan:** los seis datos del responsable.

---

## Resumen

| Paso | Quién | Qué se entrega | Costo aproximado | Hace falta para |
|---|---|---|---|---|
| 0. Operador | El equipo decide | Quién es | — | Todo lo demás |
| 1. Dominio | El operador | El nombre | El del dominio | Staging y producción |
| 2. AWS | El operador, en la consola | Perfil y región | 20–33 USD al mes | Staging y producción |
| 3. RPC | El operador, en el proveedor | La URL de solo lectura | Según el plan | Producción |
| 4. Tesorería | El operador, con el exchange | La dirección pública (`G…`) | ~200 XLM al inicio | Producción |
| 5. Legal | El operador y un abogado | Los 6 datos del responsable | Honorarios | Producción |

## Lo que sigue, una vez hecho lo anterior

Con el **dominio y la cuenta de AWS** ya se puede trabajar contra testnet, aunque lo demás siga en trámite:

1. **It. 42b:** la infraestructura en AWS (instancia con su rol, buckets, SES, Route 53, el certificado comodín de Let's Encrypt y los secretos en SSM).
2. **It. 37b:** la firma de la selladora con AWS KMS, sin que la llave salga del servicio. Primero se comprueba que KMS firme con Ed25519 el hash de la transacción tal cual.
3. **El ensayo general:** todo el procedimiento en AWS contra testnet.

Con **el RPC, la tesorería y lo legal**, la salida a la red principal, paso a paso en [`docs/go-live.md`](go-live.md):

4. El despliegue: `CONFIRM_MAINNET=yes make network-deploy NETWORK=mainnet`, con la llave de la tesorería solo para ese comando.
5. La aplicación: el entorno desde `.env.production.example`, las migraciones, la DIVIPOLA, el primer Super Administrador (`make admin`) y el segundo desde el panel (it. 46a), el worker y el calendario.
6. La prueba de humo: un reporte real hasta "Sellada", revisado en Stellar Expert, en el validador y en el verificador independiente, y el contrato en `tools/verify/contracts.json`.
7. Los respaldos fuera del sitio, la restauración de prueba con datos reales y el monitor externo.

**Importante:** los sellos de testnet no pasan a la red principal. La fundación de Stellar reinicia testnet cada tanto y los borra; producción arranca de cero, y desde ahí los sellos quedan para siempre.
