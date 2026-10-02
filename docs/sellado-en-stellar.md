# El sellado en Stellar, explicado

Cómo GovTrace usa la red Stellar para que una evidencia no se pueda cambiar ni antedatar, y cómo cualquiera lo comprueba sin depender de GovTrace. Está escrito para quien no ha trabajado con blockchain: primero las palabras, después la idea, y al final los detalles y la operación.

El código está en tres lugares:

| Pieza | Dónde | Lenguaje |
|---|---|---|
| El contrato inteligente | `contracts/sealing/src/lib.rs` | Rust (Soroban) |
| El servidor que sella | `app/Infrastructure/Stellar/`, `app/Jobs/SealReport.php`, `app/Jobs/ConfirmSeal.php` | PHP (Laravel) |
| El verificador independiente y el validador del navegador | `tools/verify/` y `resources/js/lib/validator.js` | JavaScript |

---

## 1. Las palabras que hay que conocer

| Palabra | Qué es, en GovTrace |
|---|---|
| **Blockchain** | Un registro público que mantienen muchos computadores a la vez. Lo que se escribe queda con su fecha y nadie lo puede borrar ni cambiar después, ni siquiera quien lo escribió. |
| **Stellar** | La blockchain que usa GovTrace. Es pública, rápida (cierra un bloque cada ~5 segundos) y barata. |
| **Ledger** | Cada bloque de Stellar. Tiene un número que solo crece y una hora que pone la red. Un sello dice "quedé en el ledger 4.939.156, el 2026-09-30 a las 15:02". |
| **Cuenta** | Una identidad en Stellar, con una dirección pública (empieza por `G…`) y una llave secreta que firma. Quien tiene la llave actúa en nombre de la cuenta. |
| **XLM** | La moneda de Stellar. Se usa para pagar las comisiones. GovTrace la paga; el veedor nunca la toca. |
| **Comisión** | Lo que cobra la red por escribir. Tiene dos partes: la de inclusión (para entrar al bloque) y la de recursos (cálculo, lectura, escritura y la "renta" del espacio que ocupa el dato). |
| **Contrato inteligente** | Un programa que vive en la blockchain y que nadie puede alterar. En Stellar se escriben en Rust y se llaman contratos de Soroban. El de GovTrace solo sabe guardar sellos y devolverlos. |
| **Hash o huella (SHA-256)** | Un resumen de 64 caracteres que identifica un archivo. Si cambia un solo píxel de la foto, la huella cambia por completo. No se puede reconstruir la foto a partir de la huella. |
| **Árbol de Merkle y su raíz** | Una forma de juntar las huellas de varios archivos en una sola huella, la raíz. Con la raíz sellada y un "camino" corto, se demuestra que un archivo era parte del reporte sin mostrar los demás. |
| **RPC** | El servicio por el que un programa habla con Stellar: pregunta datos, simula y envía transacciones. En desarrollo es el de la red local; en testnet, el público de la fundación Stellar; en la red principal, el de un proveedor (QuickNode o Validation Cloud). |
| **Testnet y red principal** | Testnet es la red de pruebas, gratuita, que la fundación reinicia cada tanto (se borra todo). La red principal (*mainnet*) es la de verdad: lo que se escribe ahí queda para siempre. |
| **Vigencia (TTL) y archivo** | En Stellar cada dato paga una renta por un tiempo. Cuando se vence, la red lo "archiva": lo saca del estado vivo, pero sus datos se conservan y se pueden restaurar. |

---

## 2. La idea en una página

```
 Celular del veedor            Servidor de GovTrace                     Red Stellar
 ──────────────────            ────────────────────                     ───────────
 1. Toma las fotos y
    difumina los rostros
 2. Calcula la huella
    de cada una ───────────▶   3. Comprueba las huellas
                               4. Arma el árbol de Merkle del reporte:
                                  huellas de los archivos + huella
                                  de los metadatos  →  una raíz
                               5. Firma "sellar esta raíz" con la
                                  cuenta selladora; la patrocinadora
                                  paga la comisión ──────────────────▶  6. El contrato guarda:
                                                                           raíz → ledger, hora, obra
                               7. Confirma y guarda el recibo ◀─────────    (y avisa con un evento)

 Cualquier persona, después:
 8. Descarga la foto y su prueba ─▶ 9. Su navegador calcula la huella, recompone la raíz
                                       y le pregunta a Stellar si esa raíz está sellada ──▶ ✅ o ❌
```

Lo que queda en la blockchain es solo la **raíz** (una huella), la **referencia de la obra** (otra huella) y el **ledger y la hora** que pone la red. Ni las fotos, ni el nombre del veedor, ni su ubicación salen de GovTrace.

---

## 3. Las piezas

### 3.1 El contrato (`contracts/sealing/src/lib.rs`)

Tiene tres funciones y nada más:

| Función | Quién la usa | Qué hace |
|---|---|---|
| `__constructor(sealer)` | Se ejecuta una sola vez, al desplegar | Fija para siempre cuál es la cuenta selladora |
| `seal(worksite, root)` | Solo la cuenta selladora (`require_auth`) | Guarda la raíz con la referencia de la obra, la hora y el número del ledger. Si la raíz ya estaba, responde el error `HashAlreadyRegistered` (#1). Publica el evento `sealed` |
| `get_seal(root)` | Cualquiera, sin cuenta | Devuelve el sello de esa raíz, o nada |

Lo que **no** tiene, a propósito:
- **No hay `upgrade`:** el código desplegado no se puede reemplazar. Si algún día hay que cambiarlo, se despliega otro contrato y el verificador consulta todos los que ha usado GovTrace (`tools/verify/contracts.json`).
- **No hay forma de modificar ni borrar un sello.**
- **No hay forma de cambiar la cuenta selladora:** si su llave se perdiera o se filtrara, se despliega otro contrato.

Cada sello se guarda como dato "persistente" con la vigencia máxima que permite la red (sección 7).

### 3.2 Las tres cuentas de GovTrace

| Cuenta | Para qué | Fondos | Dónde está su llave |
|---|---|---|---|
| **Selladora** | Firma cada sello. Es la única que el contrato acepta | Ninguno (1,5 XLM de reserva) | Hoy, en una variable de entorno; antes de la red principal, en AWS KMS, sin salir de ahí (it. 37b) |
| **Patrocinadora** | Paga la comisión de cada sello con un *fee bump*: envuelve la transacción de la selladora y paga por ella | El saldo de unos días de sellos | Variable de entorno (gestor de secretos) |
| **Tesorería** | Crea las otras dos, despliega el contrato, extiende su vigencia y recarga a la patrocinadora | La reserva | En frío, fuera del servidor |

Las decisiones están en `specs/PLAN.md` (D11, D12 y D13). Lo que tiene que hacer el operador para la red principal está en `docs/preparacion-red-principal.md`.

### 3.3 El servidor (`app/Infrastructure/Stellar/`)

| Archivo | Qué hace |
|---|---|
| `StellarRpc.php` | Habla con el RPC: simular, enviar, consultar transacciones, leer datos del contrato y eventos, y saldos |
| `StellarSealingNetwork.php` | Arma la transacción del sello, la firma, la envuelve con el fee bump y la envía; después busca el resultado |
| `SealerTurn.php` | El turno de la selladora: Stellar acepta una sola transacción pendiente por cuenta, así que se sella de a una por ledger, para todas las organizaciones (it. 39) |
| `MainnetConfiguration.php` | Las protecciones de la red principal: la app no arranca sin un RPC público separado del privado |

Los trabajos en cola son `SealReport` (enviar) y `ConfirmSeal` (confirmar).

### 3.4 El verificador (`tools/verify/`) y el validador del navegador

- **El validador** (`/verify` en el sitio de cada veeduría) corre en el navegador de quien verifica. Calcula la huella del archivo, recompone la raíz con la prueba y le pregunta a Stellar directamente, por el RPC público, sin pasar por el servidor de GovTrace.
- **El verificador independiente** es un programa abierto, en el repositorio, que hace lo mismo desde la consola. Funciona aunque GovTrace desaparezca: solo necesita el archivo, su prueba (`.prueba.json`) y un RPC de Stellar.

---

## 4. Qué pasa cuando llega un reporte

1. **En el celular** (`resources/js/lib/evidence/`), cada foto se escala a 1920 px, se le quitan los metadatos (ubicación, modelo del teléfono) y se calcula su huella SHA-256. El reporte viaja con las huellas.
2. **El servidor comprueba** que la huella de cada archivo recibido coincida con la que calculó el celular, y guarda el archivo tal cual, sin recomprimir.
3. **Arma el árbol de Merkle** (`PrepareReportSeal`, `MerkleTree`):
   - las hojas son las huellas de los archivos, en orden, y al final la huella de un JSON con los metadatos del reporte: hora de captura, clasificación, comentario, latitud, longitud y el seudónimo del veedor;
   - cada nodo es `SHA-256(menor || mayor)` de sus dos hijos ("pares ordenados"), así la prueba no necesita decir de qué lado va cada uno;
   - la raíz es lo único que se sella. La referencia de la obra es `SHA-256("organización:id de la obra")`.
4. **`SealReport`** toma el turno de la selladora y:
   1. **simula** la transacción `seal(obra, raíz)` en el RPC, que dice cuántos recursos consume y cuánto cuestan;
   2. le pone esos recursos y su comisión, y una validez de 4 minutos (`TRANSACTION_VALIDITY_SECONDS`);
   3. la **firma** la selladora y la **envuelve** la patrocinadora con el fee bump;
   4. la **envía** (`sendTransaction`). Si la red responde `PENDING` o `DUPLICATE`, sigue; si la rechaza, reintenta más tarde.
5. **`ConfirmSeal`** pregunta por la transacción (`getTransaction`) hasta que entra en un ledger, y guarda el recibo: la transacción, el ledger, la hora de la red y la comisión que se cobró.
6. **Si la raíz ya estaba sellada** (la simulación devuelve el error #1), no es un fallo: se lee el sello existente y se toma ese recibo.

Cada archivo guarda su prueba de inclusión: la lista de hermanos que llevan de su huella a la raíz.

---

## 5. Cómo se verifica

Con el archivo y su prueba (`.prueba.json`, formato `govtrace-proof/1`), el validador o el verificador:

1. calculan la huella del archivo y comprueban que es la de la prueba;
2. recomponen la raíz con las hojas y el camino de Merkle;
3. le piden a Stellar el dato `Seal(raíz)` del contrato (`getLedgerEntries`) y comprueban que existe;
4. muestran el ledger y la hora en que se selló, y el enlace a Stellar Expert, un explorador público que no es de GovTrace.

La prueba **no** lleva el JSON de metadatos, porque tiene el comentario y las coordenadas exactas; solo su huella, que es una hoja. El JSON lleva el seudónimo del veedor, que es un HMAC con una llave del servidor, así que no se puede adivinar probando.

Si el archivo cambió en un solo píxel, su huella no está en ningún sello y el resultado es "Archivo Alterado o Falso".

---

## 6. Cuánto cuesta y quién paga

| Concepto | Cuánto | Quién paga |
|---|---|---|
| Cada sello | Entre **0,24 y 0,28 XLM** en testnet (7 sellos costaron 1,99 XLM). La mayor parte es la renta del dato por ~180 días | La patrocinadora |
| Subir el código, desplegar el contrato y extender su vigencia | ~28 XLM al inicio | La tesorería |
| Extender la vigencia del contrato | ~27 XLM cada ~180 días (`make network-extend`) | La tesorería |

- **Alerta de saldo:** bajo 50 XLM, unos 200 sellos (D12, `sponsor_balance_alert_threshold_xlm`).
- **Pausa:** bajo 2 XLM, el sellado se detiene solo (`STELLAR_SPONSOR_MIN_BALANCE_XLM`); los reportes esperan en cola y nada se pierde.
- **Vigencia del contrato:** el sistema avisa 30 días antes de que venza (it. 32).

La red principal tiene las mismas reglas de comisiones que testnet, pero la cifra real se confirma con el primer sello allí.

---

## 7. La vigencia (TTL) y el archivo

En Stellar todo dato paga renta por un tiempo:

- **Cada sello** se guarda con la vigencia máxima que permite la red: en la red principal, ~180 días (3.110.400 ledgers). La paga la patrocinadora dentro de la comisión del sello.
- **El contrato** (su instancia, donde está la cuenta selladora, y su código) tiene su propia vigencia, que extiende la tesorería.

Cuando la vigencia de un sello se vence, la red lo **archiva**: lo saca del estado vivo, pero **sus datos se conservan** y se pueden restaurar. El verificador lo sabe: si el RPC devuelve el sello con `liveUntilLedgerSeq` vencido o en cero, lo da por auténtico y avisa que está archivado.

⚠️ **Pendiente de confirmar antes de la red principal:** la documentación del RPC dice que `liveUntilLedgerSeq` "puede ser cero si la entrada ya no está viva", lo que indica que también devuelve las entradas archivadas, pero no lo dice de forma explícita. Hay que comprobarlo con un sello real archivado (en una red local con la vigencia máxima reducida, o en testnet esperando su vencimiento). Si no las devolviera, la alternativa es que la tesorería extienda la vigencia de los sellos antes de que venzan, con su costo de renta.

---

## 8. Cuando algo falla

| Qué pasa | Qué hace GovTrace |
|---|---|
| El RPC no responde, o la red está congestionada | Reintenta a 1, 5, 15 y 60 minutos (`SealingRetryPolicy`). Tras 5 intentos, el reporte queda en "Falla de Sellado" y el Super Administrador lo puede volver a encolar desde **Sellado** |
| Otra transacción de la selladora está pendiente | Espera su turno, sin gastar intentos (it. 39) |
| Se envió pero no hubo respuesta | Antes de reenviar, busca si la raíz ya quedó sellada y toma ese recibo |
| La patrocinadora se queda sin saldo | Pausa el sellado y avisa a los Super Administradores activos y al webhook |
| Una evidencia lleva más de 2 horas en cola | Avisa al Super Administrador y al Administrador de la organización |
| La vigencia del contrato está por vencer | Avisa 30 días antes |
| La llave de la selladora se filtra | El atacante podría agregar sellos falsos, pero no cambiar ni antedatar los que ya existen. Se despliega otro contrato con otra selladora y se registra en `tools/verify/contracts.json` |

---

## 9. Operación

| Comando | Para qué |
|---|---|
| `make stellar-up` | La red local de Stellar, con su RPC y un "friendbot" que regala XLM de prueba |
| `make contract-test` | Las pruebas del contrato en Rust, rustfmt, clippy y la interfaz exacta del WASM |
| `make contract-deploy` | Despliega el contrato en la red local y deja su ID en `.env` |
| `make contract-smoke` | Con transacciones reales: sella, rechaza una cuenta externa y rechaza un duplicado |
| `make test-stellar` | Las pruebas de Laravel contra la red local (grupo `stellar`) |
| `make verify-check` | El verificador independiente comprueba una evidencia publicada, aislado con el nodo de Stellar solo |
| `make testnet-setup` · `make smoke-testnet` | Cuentas y contrato en testnet, y un reporte hasta "Sellada" midiendo la comisión |
| `make network-deploy NETWORK=testnet\|mainnet` | El despliegue real: la tesorería crea las cuentas, despliega y extiende. En la red principal exige `CONFIRM_MAINNET=yes` |
| `make network-extend` | Extiende la vigencia del contrato |

Los scripts están en `contracts/sealing/scripts/`. La lista de salida a la red principal está en `docs/go-live.md`.

---

## 10. Revisión frente a las guías de Stellar (2026-10-02)

Se revisó el contrato y el uso del RPC contra las guías oficiales que publica Stellar para desarrolladores ([skills.stellar.org](https://skills.stellar.org/): *Stellar Smart Contracts*, con su lista de seguridad, y *RPC & Horizon APIs*).

### El contrato

| Punto de la lista de seguridad | GovTrace |
|---|---|
| Toda función privilegiada exige autorización de una dirección guardada, no de un parámetro | ✅ `seal` exige la firma de la selladora, leída del almacenamiento |
| La inicialización ocurre una sola vez | ✅ `__constructor`, que no se puede volver a ejecutar |
| Llamadas a otros contratos validadas | ✅ No llama a ningún contrato |
| Aritmética comprobada | ✅ No hace cuentas; `overflow-checks = true` en el perfil de compilación |
| Claves de almacenamiento tipadas, sin colisiones | ✅ `enum DataKey` con `#[contracttype]` |
| Vigencias críticas extendidas; la vigencia no se usa como seguridad | ✅ Cada sello con la vigencia máxima; la instancia, por la tesorería; nada depende de que algo venza |
| Ciclos acotados | ✅ No tiene ciclos |
| Eventos de los cambios auditables, códigos de error estables | ✅ Evento `sealed`; error #1 |
| Camino de actualización controlado | ✅ No hay actualización, a propósito: es la garantía de inmutabilidad |
| Versión del SDK igual a la del protocolo de la red | ✅ `soroban-sdk = "=27.0.6"`, protocolo 27, y el target `wasm32v1-none` |

No hizo falta cambiar el contrato. Cambiarlo obligaría a desplegar otro y a registrarlo en el verificador.

### El RPC

| Recomendación | GovTrace |
|---|---|
| Simular antes de enviar | ✅ Siempre, y usa los recursos y la comisión que devuelve la simulación |
| Validar la red antes de firmar | ✅ La frase de la red viene de la configuración. En la red principal, la app no arranca sin un RPC público separado del privado, y el despliegue se niega con un RPC que no sea de esa red |
| Consultar `getTransaction` hasta tener el resultado | ✅ `ConfirmSeal`, con plazo y reintentos |
| Reintentos con espera creciente | ✅ 1, 5, 15 y 60 minutos |
| No depender de la historia del RPC (~7 días) | ✅ El recibo se guarda al sellar, y la verificación lee el dato del contrato (`getLedgerEntries`), que no vence con la historia |
| La comisión de inclusión sube con la congestión | ✅ El fee bump ofrece como base la comisión completa de la transacción interna, así que su puja de inclusión queda cerca de 0,24 XLM, muy por encima de los picos de congestión. En congestión, cada transacción paga la menor puja que entró a ese ledger, no la suya: la puja alta asegura la entrada sin subir el costo. En el peor caso, el costo extra queda acotado: el fee bump nunca paga más del doble de la comisión del sello |
| El reembolso de los recursos no usados en un fee bump | ✅ Desde 2024 ([stellar-core #4168](https://github.com/stellar/stellar-core/issues/4165)) va a quien paga el fee bump, la patrocinadora, y no a la selladora. En la red local, la selladora no acumula saldo |
| Margen sobre los recursos simulados | ➖ Usa los de la simulación tal cual. Un sello escribe una sola clave nueva, así que el riesgo de que el estado cambie entre la simulación y el envío es mínimo |

No hizo falta cambiar el código del servidor. Se dejó explicada en `StellarSealingNetwork::signedSeal` la razón de la puja de inclusión, para que nadie la "corrija" bajándola a la mínima.

### Pendientes

- Confirmar con un sello archivado que el RPC lo devuelve (sección 7).
- Comprobar en testnet que la selladora no acumula los reembolsos.
- Correr un analizador estático (Scout, de CoinFabrik) sobre el contrato antes de la red principal.

---

## 11. Para seguir aprendiendo

- [Contratos inteligentes en Stellar](https://developers.stellar.org/docs/build/smart-contracts/overview)
- [Archivo de estado (TTL)](https://developers.stellar.org/docs/learn/fundamentals/contract-development/storage/state-archival)
- [Métodos del RPC](https://developers.stellar.org/docs/data/apis/rpc)
- [Guías de Stellar para desarrolladores](https://skills.stellar.org/)
- [Stellar Expert](https://stellar.expert), el explorador público
