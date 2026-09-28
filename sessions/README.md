# Sesiones de Discovery

Cada discovery se documenta aquí siguiendo la convención de BDD 2.0: un discovery = una carpeta de sesión.

Al correr `/discovery` (o `/spec`) por primera vez, el skill pregunta tu nombre y crea:

```
sessions/<slug>/
├── README.md            — qué es esta sesión, quién la corrió, estado
├── caso.md              — el caso de negocio tal como se aportó
├── SHARED-MEMORY.md     — estado del discovery (fase actual, respuestas)
├── project-context.md   — contexto del módulo que se va confirmando
├── discovery-log.md     — bitácora: qué se cubrió en cada fase
└── dqs-lite.md          — reporte de cobertura al cerrar (fase Gherkin)
```

Commitea esta carpeta junto con las specs: es la constancia del proceso de descubrimiento, no solo del resultado.
