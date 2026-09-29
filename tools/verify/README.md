# Verificador independiente de GovTrace

Comprueba que un archivo descargado de GovTrace es **exactamente** el que se
selló en la red Stellar, sin pasar por GovTrace: ni por sus servidores ni por
su base de datos (US-026).

## Qué necesita

- **Node.js 20 o más nuevo.** No hay nada que instalar: el verificador no
  tiene dependencias.
- **Esta carpeta** (`tools/verify`), tal cual está en el repositorio.
- **El archivo y su prueba de inclusión** (`evidencia-….prueba.json`). Se
  descargan juntos desde la tarjeta de la evidencia, en el mapa público de la
  organización.
- **Un RPC de Stellar** de la red de la prueba. Para la testnet se usa
  `https://soroban-testnet.stellar.org`. Para la red pública, hay que indicar
  uno con `--rpc`. Los proveedores están en
  <https://developers.stellar.org/docs/data/apis/rpc/providers>.

## Uso

```sh
node verify.mjs evidencia-9b5b32acf022.jpg evidencia-9b5b32acf022.prueba.json
```

| Opción | Para qué |
|---|---|
| `--rpc URL` | Otro RPC de Stellar, o uno para una red que no tiene RPC conocido. |
| `--contract C…` | Un contrato más en el que buscar el sello, como el de otra instalación de GovTrace (se puede repetir). |

| Código de salida | Resultado |
|---|---|
| `0` | **AUTÉNTICO**: el archivo es exactamente el que se selló. |
| `1` | **NO COINCIDE**: el archivo no es el que se selló, o la prueba no corresponde a lo que tiene la red. |
| `2` | **No se pudo verificar**: faltan argumentos o archivos, la prueba está mal formada, o el RPC no responde o es de otra red. No dice nada del archivo. |

## Qué comprueba

Hace tres comprobaciones, en orden. La primera que falla es la respuesta.

1. **El archivo es el de la prueba.** Su SHA-256 es el que dice la prueba.
2. **El archivo está en el árbol sellado.** Las hojas de la prueba (los
   archivos del reporte, en orden, y al final el hash de sus metadatos)
   forman su raíz de Merkle, con el archivo en su lugar. Además, el camino de
   Merkle del archivo lleva a esa misma raíz.
3. **La red tiene esa raíz sellada en un contrato de GovTrace**, en el ledger
   y a la hora que dice la prueba, y para la misma obra. El verificador
   pregunta por la raíz en **todos** los contratos de GovTrace de esa red,
   también en los de versiones anteriores, en una sola consulta: los sellos
   antiguos siguen siendo verificables (R-MNT-01).
   - Esos contratos salen de [`contracts.json`](contracts.json), o de
     `--contract`. El contrato que nombra la prueba es solo informativo: el
     verificador no le cree, porque cualquiera puede desplegar en Stellar un
     contrato parecido y sellar en él lo que quiera.
   - El sello se lee directamente como una entrada del ledger
     (`getLedgerEntries`), así que no hace falta ninguna cuenta. Si el sello
     está **archivado** porque pasó su vigencia (TTL), sus datos siguen en la
     red y la verificación vale igual; el verificador lo avisa.

El árbol usa el mismo esquema en el servidor, en el navegador y aquí
([`lib/merkle.mjs`](lib/merkle.mjs)):

- cada hoja es un SHA-256;
- cada nodo es `SHA-256(menor || mayor)`, comparando los bytes, así que la
  prueba no necesita decir de qué lado va cada hermano;
- un nodo sin pareja sube tal cual al nivel siguiente;
- la prueba es la lista de hermanos, de abajo hacia arriba.

## En qué se confía

- **En el RPC que se consulte**, que reporta lo que hay en la red. Conviene
  usar uno de confianza, o repetir la verificación con dos proveedores
  distintos.
- **En [`contracts.json`](contracts.json)**, que está en este repositorio y
  tiene su historia en git. Un contrato que se reemplaza se queda en la
  lista: sus sellos siguen valiendo.
- En nada más de GovTrace.

## Qué no trae la prueba

La prueba no trae los metadatos del reporte: el comentario del veedor ni sus
coordenadas exactas. El mapa público solo muestra coordenadas aproximadas
(R-PRIV-02). La prueba sí trae el hash de esos metadatos, porque es una hoja
del árbol, pero ese hash no permite reconstruirlos: los metadatos llevan el
seudónimo del veedor, que es un HMAC con una llave que solo tiene el servidor.

## Desarrollo

- `make test-front`: los tests de `test/` (Vitest). Repiten las respuestas
  reales de la red local (`tests/fixtures/verify/sealed.json`) y comparan el
  XDR byte a byte con el que arma el SDK de Stellar del servidor.
- `make verify-check`: GovTrace sella y publica una evidencia en la red local.
  Luego el verificador la comprueba en un contenedor aislado, que solo ve
  esta carpeta, los dos archivos y el nodo de Stellar.
