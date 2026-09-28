# Contribuir a govtrace-app

Gracias por tu interés. govtrace-app es una plataforma Open Source y Mobile-First para la recolección inmutable de evidencia ciudadana. Estas reglas existen para que cualquier persona pueda clonar, levantar y contribuir sin configuraciones arcanas.

## Levantar el proyecto

Solo necesitas Docker (con el plugin compose) y `make`. PHP 8.4, Composer, PostgreSQL y Node corren en contenedores.

```bash
make setup     # crea .env y .env.docker, construye la imagen, instala dependencias, migra y compila el frontend
make up        # los días siguientes
make help      # todos los comandos
```

`http://govtrace.localhost:8080/up` debe responder 200. Si los puertos 8080/5432 o la subred `172.29.0.0/24` chocan con algo de tu máquina, ajústalos en `.env.docker`.

## Reglas obligatorias

### 1. TDD — el test va primero
- Todo cambio de comportamiento empieza con un test que **falla** (rojo), después el código mínimo que lo hace pasar (verde), después el refactor.
- Backend: Pest en `tests/`. Frontend: Vitest + Vue Test Utils junto al componente en `resources/js/`.
- Cada escenario Gherkin de `features/` tiene su test, nombrado igual que el escenario.
- Un PR sin tests, o con tests en rojo, no se mergea.

### 2. Estilo — PSR-12 vía Laravel Pint
- El código PHP sigue PSR-12 con el preset de Laravel Pint.
- Antes de commitear: `make fmt`.
- El pipeline corre `./vendor/bin/pint --test` y falla si hay archivos sin formatear.

### 3. Mobile-First
- Las utilidades de Tailwind sin prefijo son el diseño de teléfono. Solo existen `md:` y `lg:` para escalar a tablet/escritorio.
- Touch targets de al menos 44×44px.

### 4. Flujo de Git
- Una rama por feature desde `main` (`feature/<slug>`), un commit por iteración con tests en verde.
- Mensajes: `feat: iteración N — <resumen> (US-XXX)`.
- Todo PR pasa por el pipeline de Jenkins (Build → Format Check → Test Backend → Test Frontend).

### 5. Código en inglés, especificación en español
- Clases, métodos y variables en inglés. Historias, criterios y `.feature` en español.

## Correr las pruebas

```bash
make lint        # Pint (no modifica)
make test        # Pest
make test-front  # Vitest
make test-all    # los tres, igual que CI
```

## Licencia

Al contribuir aceptas que tu aporte se publique bajo la [licencia MIT](LICENSE).
