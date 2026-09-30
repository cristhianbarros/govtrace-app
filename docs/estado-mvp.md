# Estado del MVP: lo que falta, por área

Al 2026-09-29, sobre `main` en `77e6030` (iteración 39). Para ir abordándolo: cada punto dice qué falta y, cuando depende de ti, qué hay que decidir.

✅ hecho y probado · ⚠️ a medias · ⬜ falta · 🔒 espera algo externo · ❓ decisión tuya

## Resumen

**El alcance funcional está completo y probado:**
- las 56 historias del discovery, en 39 iteraciones;
- los 250 escenarios Gherkin, cada uno con un test que lleva su nombre;
- 761 tests rápidos de backend, 351 de frontend y 17 contra la red Stellar local;
- el sellado probado en testnet, también en ráfaga: 7 evidencias en 7 ledgers seguidos.

**Lo que separa esto de un MVP listo para producción, de más a menos peso:**
1. ~~**Salir a internet con seguridad**~~ — hecho en la it. 41: proxies de confianza, CSP y HSTS, límites de abuso, auditoría de dependencias, logs diarios y todo en español. Falta el certificado, que llega con staging.
2. **Una prueba real en la nube** (staging en AWS, apuntando a testnet): celulares de verdad, HTTPS y correo real.
3. **AWS KMS** para la llave de la selladora (37b): espera la cuenta de AWS.
4. **La usabilidad.** Ya se revisó con capturas y mediciones (`docs/ux-analisis.md`). Hoy no se puede salir de los paneles, la leyenda del mapa queda lejos y hay lenguaje técnico, letra y botones pequeños. Lo corrige la it. 40.
5. **La protección de datos personales** (Ley 1581 de 2012): no está en la SPEC.
6. **La salida a la red principal** (37b): proveedor de RPC, tesorería y restauración con datos reales.

## Listo para producción: la lista

El MVP tiene que estar listo para producción, no solo funcionar. Esta es la vara, punto por punto; cada uno se da por cumplido con una prueba, no con una promesa.

**Seguridad**
- ✅ Lista para ir detrás de TLS: HSTS, cookies seguras, proxies de confianza (it. 41).
- ✅ El proxy con TLS 1.2 y 1.3 y el certificado comodín, probados en local con una CA de prueba: por HTTPS la aplicación sabe que es HTTPS, sin contenido mixto (it. 42a). ⬜ El certificado de Let's Encrypt en AWS (it. 42b).
- ✅ Cabeceras: CSP, `Permissions-Policy`, `nosniff`, `X-Frame-Options`, `Referrer-Policy` (it. 41).
- ✅ Límites de abuso: reportes, API públicas, datos abiertos, inicio de sesión y recuperación de contraseña.
- ✅ Una base de datos por organización, roles probados y log de auditoría inmutable.
- ✅ Ninguna llave en el repositorio (`make secrets-check`). ⬜ Los secretos desde un gestor en el servidor (it. 42).
- 🔒 La llave de la selladora en AWS KMS (37b).
- ✅ Dependencias auditadas en cada PR (`make audit`).
- ⬜ Un análisis dinámico (OWASP ZAP) contra staging, sin hallazgos altos (it. 42).
- ❓ Doble factor para el Super Administrador.

**Confiabilidad**
- ✅ Respaldos cada hora, 30 días, con réplica fuera del sitio y restauración de prueba con límite de 4 h. 🔒 La restauración con datos reales (37b).
- ✅ Sellado resistente a las fallas de la red (US-021) y a las ráfagas (it. 39).
- ✅ Logs diarios con retención (it. 41).
- ✅ Monitor externo configurado y probado. ⬜ Apuntando a staging y producción (it. 42).
- ❓ Seguimiento de errores: un servicio tipo Sentry, o alertas por correo de los errores críticos del log.

**Operación**
- ✅ La plantilla de producción, vigilada por un test (it. 41).
- ✅ La guía de restauración (`docs/restore.md`) y la lista de salida (`docs/go-live.md`).
- ✅ Un despliegue repetible: `deploy/deploy.sh` (imágenes, migraciones, DIVIPOLA, caché, `/up` por HTTPS), probado dos veces seguidas (it. 42a). ⬜ Su guía en AWS (it. 42b).

**Calidad**
- ✅ Suite completa en el pipeline, pruebas de extremo a extremo en un navegador, cada escenario con su test, y reglas probadas rompiéndolas a propósito.
- ✅ La imagen de producción se construye y se prueba entera, detrás de TLS, en cada PR (`make staging-check`, it. 42a). Antes no se construía en el pipeline, y estuvo rota desde la it. 27 sin que nadie lo notara.
- ⬜ El recorrido visual de todas las pantallas (it. 40).
- ❓ Una meta de accesibilidad.

**Cumplimiento**
- ❓ La política de tratamiento de datos (Ley 1581 de 2012) y la autorización de cada veedor.

**Rendimiento**
- ✅ La capacidad del sellado, medida: 10 a 12 sellos por minuto (it. 39).
- ❓ Una prueba de carga con el volumen que esperas.

## 1. Interfaz (UX/UI)

| | Qué | Detalle |
|---|---|---|
| ✅ | Todas las pantallas de la SPEC | 28 páginas: la app del veedor (PWA, con modo sin conexión), el panel del Administrador, el panel global y el sitio público. Mobile-first, con los estados carga / error / vacío / éxito probados en Vitest. Los botones de 44 px se cumplen en la app del veedor y el sitio público, no en los paneles: ahí la mayoría mide 26 a 41 px. |
| ⚠️ | **Revisadas el 2026-09-29; falta corregir** | Un análisis experto de las 26 vistas, en celular y escritorio, con capturas y mediciones: `docs/ux-analisis.md`. **La accesibilidad técnica está sana:** axe incumple solo 2 reglas en 52 vistas y el contraste cumple AA. **La usabilidad no:** el Administrador y el Super Administrador no pueden cerrar sesión, la Bandeja no dice de qué obra es cada foto, la leyenda del mapa queda lejos, y hay lenguaje técnico, letra y botones pequeños. Lo corrige la **it. 40**, en tres partes: 40a, 40b y 40c. |
| ✅ | Correos y mensajes en español (it. 41) | `APP_LOCALE=es` y `lang/es`: los correos ya no traen las frases en inglés de la plantilla de Laravel ("Regards", "If you're having trouble clicking…"), y los mensajes de validación por defecto y las páginas de error salen en español. |
| ⬜ | Accesibilidad | ❓ La SPEC no fija una meta. Propuesta: WCAG 2.2 AA más las reglas R-UX de `docs/ux-analisis.md` (sección 2), medidas en el pipeline con `make ux-check`. Lo que falta no es contraste ni etiquetas: es el tamaño de letra y de botones, y el color como único significado. |
| ⚠️ | Cámara y GPS en un celular | El navegador los exige con HTTPS: en local, solo desde el mismo equipo. Se prueban de verdad en staging. |
| ⬜ | Perfil del veedor | ❓ Su nombre es la parte local del correo (deuda aceptada). Ninguna historia pide editarlo. |
| ⚠️ | Mapas | ❓ Las imágenes del mapa vienen de `tile.openstreetmap.org`, cuya política no admite tráfico de producción intenso. Para la red principal, un proveedor de mapas con su propia llave. |

## 2. Requisitos funcionales

| | Qué | Detalle |
|---|---|---|
| ✅ | Historias, escenarios y reglas | De las 66 reglas de la SPEC, 64 en ✅ (`specs/AUDIT.md`). |
| 🔒 | R-CFG-01 y R-BCK-05 | La red principal (preparada y probada en testnet, sin desplegar) y la restauración con datos reales. Esperan producción (37b). |
| ⚠️ | SECOP II de verdad | Los tests usan respuestas grabadas (R-TST-02). La sincronización contra la API real no se ha visto correr de punta a punta en un entorno desplegado: se comprueba en staging. |
| ⬜ | Correo real | Hoy sale a un archivo (`make invites`). En staging, por SMTP (Amazon SES). |
| ⬜ | **Protección de datos personales** | ❓ La SPEC no menciona la Ley 1581 de 2012. GovTrace trata correos, nombres y la ubicación exacta de cada reporte. Hace falta una política de tratamiento de datos publicada y la autorización de cada veedor al activar su cuenta. Primero es una decisión legal; después, una pantalla y el registro de esa autorización. |
| ⬜ | Doble factor | ❓ No está en la SPEC. La cuenta del Super Administrador controla todas las organizaciones: recomiendo un segundo factor (TOTP) al menos para ella. |

## 3. Requisitos no funcionales

| | Qué | Detalle |
|---|---|---|
| ✅ | Respaldos | Cada hora, guardados 30 días, con réplica fuera del sitio y una restauración de prueba que falla si pasa de 4 h. 🔒 Falta la restauración de producción. |
| ✅ | Sellado bajo carga | Por turnos (it. 39): 10 a 12 sellos por minuto con una selladora, y ninguna ráfaga gasta intentos. |
| ✅ | Rotación de logs (it. 41) | La plantilla de producción usa `LOG_STACK=daily` con 14 días (en desarrollo, un solo archivo llegó a pesar 345 MB). Un test vigila la plantilla. |
| ⬜ | Pruebas de carga | ❓ No hay, y la SPEC no fija tiempos de respuesta. ¿Cuántos veedores y visitantes esperas en la primera salida? |
| ⚠️ | Monitoreo | El monitor externo (Gatus) está configurado y probado (US-044-MON), sin apuntar a un entorno real. No hay seguimiento de errores (tipo Sentry): quedan en el log. |
| ⚠️ | Calendario en UTC | Deuda aceptada: la sincronización de las 02:00 corre a las 21:00 en Colombia. |
| ⚠️ | Pipeline | El Jenkinsfile corre completo en un entorno de cero. Faltan las credenciales de testnet de Jenkins (sección 2 de `docs/estado-37b.md`). |

## 4. Seguridad

| | Qué | Detalle |
|---|---|---|
| ⚠️ | **HTTPS** | Listo y probado en local (it. 42a): nginx termina TLS 1.2 y 1.3 con un certificado comodín, y por HTTPS la aplicación sabe que es HTTPS (hizo falta un arreglo en Apache: `mod_remoteip` le ocultaba al proxy). Falta el certificado de Let's Encrypt en AWS (it. 42b). |
| ✅ | Laravel detrás de un proxy (it. 41) | Confía en el proxy de la red privada (`TRUSTED_PROXIES`), y solo en su `X-Forwarded-For` y `X-Forwarded-Proto`. Nunca en `X-Forwarded-Host` ni `-Port`, que además nginx borra: con ellos se envenenarían los enlaces. |
| ✅ | Cabeceras de seguridad (it. 41) | CSP en toda respuesta, con los dos únicos orígenes externos (las imágenes del mapa y el RPC público de Stellar), `Permissions-Policy` (cámara y ubicación solo para el sitio) y, por HTTPS, HSTS. Probada en un Chromium de verdad: ninguna pantalla pública ni del veedor la viola. |
| ✅ | Cookies de sesión | Cifradas y `Secure` en la plantilla de producción. |
| ✅ | Límites de abuso (it. 41) | 30 reportes por veedor y por hora (la bandeja de salida de la PWA, que guarda 10, cabe entera); 120 consultas públicas por visitante y por minuto; 10 descargas de datos abiertos. Un visitante es su IP real detrás del proxy. Pasado el límite, 429 en español, y la PWA guarda el reporte y lo envía sola después. ❓ Los números. |
| 🔒 | **AWS KMS** (D11 b, 37b) | Elegido. **El emulador local no sirve:** LocalStack 3.8, el gratuito, no soporta llaves Ed25519, y la versión actual exige licencia. La prueba de concepto se hace contra AWS. ❓ Sigue abierta la decisión de pasar también la patrocinadora a KMS (recomendado: es la que tiene los fondos). |
| ✅ | Llaves fuera del repositorio | `make secrets-check`. En producción, desde un gestor de secretos (pendiente, `docs/go-live.md`). |
| ✅ | Dependencias (it. 41) | `make audit` (composer audit y npm audit de producción, nivel alto o más) corre en el pipeline, en su propia etapa. Hoy, sin vulnerabilidades conocidas. |
| ✅ | Lo que ya protege | Una base de datos por organización; los roles probados, incluido el caso negativo; log de auditoría inmutable; SVG saneados; fotos con EXIF rechazadas; hashes recalculados en el servidor; contrato sin `upgrade`; sin `v-html` en el frontend. |
| ⬜ | Análisis de seguridad | Nadie ha atacado un entorno desplegado (por ejemplo, con OWASP ZAP). Se hace en staging. |

## 5. La prueba real en la nube (staging en AWS)

### ¿Tu cuenta personal o una exclusiva para GovTrace?

**Recomiendo una cuenta exclusiva:**
- **Aislamiento:** una llave filtrada o un recurso olvidado no toca tu cuenta personal ni tu facturación.
- **Traspaso:** si GovTrace pasa a una organización o fundación, se entrega la cuenta entera.
- **Créditos:** hoy, una cuenta nueva recibe **100 USD en créditos**, y hasta **100 USD más** por cinco actividades de 20 USD cada una: lanzar una instancia EC2, configurar RDS, crear una función Lambda, usar Bedrock y crear un presupuesto en AWS Budgets.
  - Con el **plan gratuito**, AWS no cobra nada hasta que lo pases a pago. Vence a los 6 meses o cuando se acaban los créditos. Si no lo pasas a pago en 90 días, AWS cierra la cuenta.
  - Tus 20 USD de créditos son de tu cuenta personal: los créditos promocionales no pasan de una cuenta a otra, salvo compartidos dentro de una organización de AWS.

### Qué haces tú en la cuenta nueva

1. Activar MFA en el usuario raíz, y nunca crearle llaves de acceso.
2. Crear un usuario para el día a día (IAM Identity Center o IAM), también con MFA.
3. Crear un presupuesto en AWS Budgets con alertas a 5, 10 y 15 USD. Además, es una de las actividades de 20 USD.
4. Elegir la región: **us-east-1** (Norte de Virginia), la más barata y con todos los servicios.
5. Para la prueba de concepto de KMS: un usuario o rol limitado a `kms:CreateKey`, `kms:GetPublicKey`, `kms:Sign` y `kms:ScheduleKeyDeletion`, con su perfil configurado en tu equipo. Me dices el nombre del perfil y la región, nunca las llaves.

### La arquitectura que propongo

Una sola máquina, sin balanceador de carga: un balanceador cuesta más que todo lo demás junto.

| Pieza | Para qué | Costo aproximado |
|---|---|---|
| **EC2 t4g.small** (2 vCPU ARM, 2 GB) | El mismo stack de Docker, apuntando a testnet con el contrato oficial, sin la red Stellar local. Si 2 GB no alcanzan, t4g.medium (4 GB). | ~12 USD/mes (~24 la t4g.medium) |
| Rol de la instancia | Permisos solo para firmar con la llave de KMS, los dos buckets y enviar correo. **Ninguna llave guardada en el servidor.** | gratis |
| **HTTPS con Let's Encrypt** | Un certificado comodín (`*.dominio`), validado por DNS en Route 53. | gratis |
| Dominio y zona en Route 53 | Cada organización es un subdominio: el comodín va en el DNS y en el certificado. | el dominio, si hay que comprarlo; la zona, 0,50 USD/mes |
| IP pública | La dirección de la máquina. | 0,005 USD/hora, ~3,65 USD/mes |
| Disco (EBS gp3, 20 GB) | El sistema, Docker y PostgreSQL. | ~1,60 USD/mes |
| S3 | Las evidencias, y los respaldos en otro bucket de otra región, con versionado. | centavos |
| SES | El correo. Empieza en modo sandbox (solo a direcciones verificadas) hasta pedir la salida. | centavos |
| KMS | La llave de la selladora (y la de la patrocinadora, si lo decides). | 1 USD/mes por llave, más 0,15 USD por cada 10.000 firmas; las firmas asimétricas no entran en la capa gratuita |

**Total: unos 20 USD al mes con la t4g.small, o unos 33 con la t4g.medium.** Con los 100 USD de una cuenta nueva, unos 5 meses de staging; con los 200, los 6 meses del plan gratuito. Con tus 20 USD, cerca de un mes. Los precios son de us-east-1: confírmalos en la calculadora de AWS antes de crear nada.

**Sobre el certificado:** con Let's Encrypt no se paga. Los certificados de ACM son gratis solo detrás de un balanceador o de CloudFront; para usar uno dentro de la máquina, ACM cobra 15 USD por nombre y 149 USD por un comodín.

### Qué hay que construir para staging (una iteración)

- El proxy con HTTPS (Let's Encrypt comodín) y HSTS, y Laravel confiando en ese proxy.
- Un `docker-compose` de staging: S3 y SES de verdad en vez de LocalStack y el log de correo, y testnet en vez de la red local.
- Los secretos desde SSM Parameter Store (gratis en su nivel estándar), no en archivos.
- La firma con KMS (37b), si la prueba de concepto pasa.
- Un script de despliegue, con su guía, y el monitor externo apuntando a staging.

## 6. Orden propuesto

| # | Qué | Cierra | Modelo |
|---|---|---|---|
| 1 | **Tú:** la cuenta de AWS y el perfil de KMS (sección 5) | Desbloquea el 2 y el 4 | — |
| 2 | **37b, KMS:** la prueba de concepto y la firma detrás de `SealingNetwork` | La llave de la selladora fuera del servidor | Opus max |
| 3 | **It. 41, salir a internet:** TLS listo, proxies de confianza, HSTS, CSP, `Permissions-Policy`, límites de abuso, auditoría de dependencias en el pipeline, rotación de logs, correos en español | Seguridad (sección 4) | Opus xhigh |
| 4 | **It. 42, staging en AWS** con testnet | La prueba real | Opus xhigh |
| 5 | **It. 40, usable por cualquiera:** 40a lo urgente, 40b navegación y legibilidad, 40c orientación (`docs/ux-analisis.md`) | UX/UI (sección 1) | Sonnet |
| 6 | **37b, red principal:** proveedor de RPC, tesorería, despliegue y restauración con datos reales | Producción | Opus xhigh |

El 3 no necesita AWS: se puede hacer mientras creas la cuenta.

**Decisiones tuyas (❓):**
- el segundo factor para el Super Administrador;
- la política de datos personales (Ley 1581) y la autorización de los veedores;
- la meta de accesibilidad y las decisiones de UX (sección 9 de `docs/ux-analisis.md`);
- el límite de reportes por veedor;
- el perfil del veedor;
- el proveedor de mapas para la red principal;
- la patrocinadora en KMS;
- cuántos usuarios esperas, para las pruebas de carga.
