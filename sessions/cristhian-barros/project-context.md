# Contexto del proyecto — GovTrace

> Se completa a medida que el usuario confirma cada punto durante el discovery. Nada aquí se da por cierto hasta que aparezca en las respuestas registradas de `SHARED-MEMORY.md`.

## Producto
- **Nombre:** GovTrace
- **Una línea (del caso):** Plataforma de veeduría ciudadana para reportar el estado de obras públicas con evidencia fotográfica geolocalizada e inmutable.

## Actores (confirmados al iniciar Historias)
- **Super Administrador** *(nuevo)* — controla la plataforma global, provisiona tenants, supervisa nodos RPC y costos de gas.
- **Administrador de Organización (Tenant Admin)** — líder de la ONG/Cámara/Veeduría; gestiona su equipo de veedores y supervisa sus proyectos asignados.
- **Veedor Ciudadano** — usuario de campo: camina la obra, toma la foto, la geoposiciona y la sube.
- **Verificador Anónimo / Público** — ciudadano o periodista; navega el mapa, revisa contratos SECOP II y verifica evidencias en blockchain **sin registrarse**.

## Épicas confirmadas
EPIC-001 Sincronización de contratos (SECOP II) · EPIC-002 Recolección de evidencia · EPIC-003 Sellado criptográfico · EPIC-004 Visualización geoespacial · EPIC-005 Verificación de integridad · EPIC-006 Gestión de cuentas y roles · EPIC-007 Moderación de reportes · EPIC-008 Radicación de denuncias · EPIC-009 Gestión de organizaciones (tenants)

**MVP:** 001, 002, 003, 004, 005, 006, 009. **Fuera:** 007 Moderación, 008 Denuncias (ante la Contraloría). Detalle en `specs/epicas/`.

## Decisiones confirmadas
- **Tenant = Organización.** Subdominio, veedores y evidencias propios. Contratos SECOP II en BD central compartida; cada organización configura las ciudades/departamentos de su mapa.

## Stack (fijado por el framework, no por el discovery)
Laravel 13 (PHP 8.4) + stancl/tenancy + PostgreSQL 16 · Vue 3 + Inertia + Tailwind v4 (Mobile-First).
- **Retiro de contenido:** nunca borrado físico en BD; revocación lógica (soft delete / estado de revocación) desde el Día 1, para no romper la trazabilidad ni la promesa blockchain.
- **Restricción de proyecto:** el MVP apunta a un desarrollo de **5 semanas**; se prefieren soluciones simples (evitar sobreingeniería).
- **Preferencia técnica (no es regla de negocio):** roles con middleware estándar de Laravel + Spatie (laravel-permission).
- **Obra vs Contrato:** el contrato SECOP es inmutable; GovTrace mantiene una ficha de obra propia donde vive la ubicación (fijada por el primer reporte si SECOP no la trae — First-Touch Anchoring).
- **Modelo de participación cerrado:** solo veedores invitados por una organización aportan evidencia; no hay participación ciudadana abierta ni anónima (ni en el MVP ni en el roadmap).
- **Nota técnica (para /plan):** la respuesta de B4 menciona Laravel Horizon, que requiere Redis; el entorno Docker tiene Redis en el perfil `async` y la cola por defecto es `database`.
- **Ficha de obra por organización:** ubicación (First-Touch, corregible por el Admin de Organización) y estado de riesgo propios de cada organización.
- **Nota técnica (para /plan):** los archivos se sirven desde almacenamiento de objetos (S3/MinIO según la respuesta de B9); el entorno Docker aún no incluye MinIO.
- **Nota técnica (para /plan):** B12 menciona "PostGIS/MySQL Spatial" para la proximidad; en Historias se había decidido Haversine sin PostGIS para el MVP, y la base es PostgreSQL. Resolver en /plan.

## Estado final del discovery (2026-09-27)
- 56 historias (P1 20 · P2 23 · P3 13), 64 reglas, 56 YAML con SMART completo, 56 `.feature` (248 escenarios).
- Consolidado en `specs/SPEC.md`; auditoría del discovery en `dqs-lite.md`.
- Siguiente paso: `/plan`.
