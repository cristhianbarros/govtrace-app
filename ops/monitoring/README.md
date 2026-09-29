# Monitoreo externo (US-044-MON)

GovTrace avisa al Super Administrador, por **correo** y por **webhook**, cuando
lleva **más de 5 minutos** sin responder. Una interrupción más corta no genera
alerta. Al volver, avisa que se recuperó.

## Por qué afuera

El monitor corre en **otra máquina** que la de GovTrace. Uno en el mismo
servidor se cae con él, y nadie se entera. Un VPS pequeño basta. Tampoco
necesita la base de datos de GovTrace: solo pide `https://<dominio>/up`, la
ruta de salud de Laravel, que responde 200 si la aplicación está viva.

## La herramienta

[Gatus](https://github.com/TwiN/gatus) (Apache-2.0). Su configuración es un
archivo YAML (`gatus.yaml`), así que vive en el repositorio y se prueba. La
regla:

| Variable | Producción | Qué hace |
|---|---|---|
| `MONITOR_INTERVAL` | `1m` | un chequeo por minuto |
| `MONITOR_FAILURE_THRESHOLD` | `6` | alerta tras 6 fallas seguidas: más de 5 minutos |

Una caída de 3 minutos son 3 fallas y no alerta; una de 6 minutos, sí.

## Instalarlo

```sh
cp monitor.env.example monitor.env   # completar: URL, correo (SMTP) y webhook
docker compose up -d
```

- **Correo:** cualquier SMTP con TLS (puerto 587). Gatus no manda la contraseña
  por una conexión sin cifrar.
- **Webhook:** una URL que reciba un POST con `{"text": "…"}`, como un webhook
  entrante de Slack. Para Teams o Discord, ajustar `body` en `gatus.yaml`.
- `monitor.env` no se versiona: lleva la contraseña del correo.

## Probarlo

```sh
make monitoring-check
```

Levanta Gatus de verdad, con esta misma configuración, contra un "GovTrace" de
prueba (un nginx que se detiene y se arranca), un receptor de webhooks y un
servidor de correo de prueba (Mailpit). Usa chequeos de **1 segundo** en vez
de 1 minuto: la misma regla, en segundos.

1. Una caída de 3 s no genera alertas.
2. Una caída de 9 s genera el webhook y el correo de caída.
3. Al volver, llega el webhook de recuperación.
