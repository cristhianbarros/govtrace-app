# Prueba de carga

`make load-test` usa [k6](https://k6.io) (en Docker) para ver cómo responde GovTrace con mucha gente a la vez. Corre dos escenarios al mismo tiempo, contra una veeduría de pruebas:

- **Público:** ciudadanos que miran el mapa, la lista de obras, las estadísticas y los filtros. Es una tasa constante de peticiones por segundo.
- **Veedor:** veedores que entran, piden la lista de obras de su municipio y envían un reporte con una foto de 1920 px, cada 5 a 10 s.

## Cómo correrla

```sh
make load-test                                   # local: la veeduría de make e2e, 2 minutos
DURATION=30s make load-test                      # una prueba corta
PUBLIC_RATE=20 VEEDORES=10 DURATION=5m make load-test
BASE_URL=https://<veeduría>.govtrace.duckdns.org VEEDOR_EMAIL=… VEEDOR_PASSWORD=… CONTRACT_ID=… make load-test
```

El resumen queda en `storage/framework/testing/load/resumen.json`.

Cada escenario se prende con su cantidad (con 0, no corre): `PUBLIC_RATE` (peticiones por segundo), `VEEDORES` y `ADMINS`.

### La bandeja del Administrador (it. 52)

Un tercer escenario, `ADMINS=n`: administradores que abren su bandeja y piden la segunda página. Para que importe, la veeduría necesita muchas evidencias por revisar:

```sh
make e2e                                    # la organización de pruebas
make load-seed N=5000                       # 5.000 evidencias por revisar (clones de una)
KEEP_DATA=1 PUBLIC_RATE=0 VEEDORES=0 ADMINS=20 DURATION=30s make load-test
```

`KEEP_DATA=1` es necesario: sin él, `make load-test` rehace la organización de pruebas (como `make e2e`) y borra lo sembrado.

**Medido el 2026-10-10, en local, con 5.002 evidencias por revisar y 20 administradores a la vez durante 30 s:**

| | Antes (todas de una vez) | Después (20 por página) |
|---|---|---|
| Una petición | 4,5 MB, 0,74 s | 18 KB, 0,17 s |
| Bandeja, mediana | 1.692 ms | 322 ms |
| Bandeja, p95 | 2.701 ms | 750 ms |
| Datos recibidos en 30 s | 485 MB | 6,5 MB |

## Qué mira

| Umbral | Valor |
|---|---|
| Peticiones públicas, p95 | menos de 1,5 s |
| Envío de un reporte con foto, p95 | menos de 5 s |
| Errores de lo que la app acepta | menos de 1 % |

## Los límites de la app cuentan

La app limita las peticiones por diseño (`config/limits.php`): 120 por minuto y por IP en lo público (`PUBLIC_REQUESTS_PER_MINUTE`) y 30 reportes por hora y por veedor (`REPORTS_PER_VEEDOR_PER_HOUR`). Un solo generador de carga sale por una sola IP, y los alcanza enseguida.

- **Un 429 no es una falla:** se cuenta aparte, en `limitadas`. Que aparezca prueba que la defensa funciona.
- **Para medir la capacidad sin ellos,** el entorno de la prueba los sube mientras dura. En local, con `PUBLIC_REQUESTS_PER_MINUTE` y `REPORTS_PER_VEEDOR_PER_HOUR` en el `.env`. **En staging, esto cambia la configuración de un sitio público: hay que volver a desplegar `main` al terminar.**

## Qué ensucia una prueba en staging

- Cada reporte es real: queda guardado, sellado en testnet y en la Bandeja de la veeduría. Conviene una veeduría solo para pruebas.
- La patrocinadora paga cada sello (XLM de testnet, gratis). Con ráfagas largas, el sellado va por turnos, uno por ledger (it. 39): la cola de sellos crece y se vacía después.
- Los reportes de una prueba se ven en la Bandeja. Hay que rechazarlos o dar de baja la veeduría de pruebas al terminar.

## Lo público, con la caché (it. 56)

`PUBLIC_CACHE_SECONDS` (60 por defecto; 0 la apaga) guarda el mapa, la lista, los filtros, las estadísticas y la vista de cada obra por organización. Para medir lo público **sin** caché, ponga `PUBLIC_CACHE_SECONDS=0` en el entorno de la prueba; con ella, el valor de siempre.

Dos cosas que conviene saber antes de medir:
- **Local no sirve para medir la capacidad de lo público.** El PHP de desarrollo no tiene opcache (cada petición recompila Laravel) y satura en unas 20 peticiones por segundo con o sin caché. La medida buena es en staging (imagen `qa`).
- **Lo caro de una petición pública no es armar el mapa:** es identificar a la organización y conectar a su base. La caché evita las consultas de las obras y los reportes (probado: cero consultas en la segunda petición); lo demás sigue ahí. Los endpoints públicos de lectura tampoco abren una sesión por visitante (it. 56).
