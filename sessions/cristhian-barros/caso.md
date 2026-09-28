# Caso de Negocio: GovTrace - Veeduría Ciudadana Inmutable

> Caso aportado por Cristhian Barros el 2026-09-26 al iniciar `/discovery`. Transcrito literal; es deliberadamente incompleto.

## 1. Contexto y Problema
En ciudades como Santa Marta, existe una profunda desconfianza ciudadana respecto a la ejecución de obras públicas (acueducto, vías, infraestructura deportiva). Los ciudadanos sienten apatía ("no somos dolientes") porque perciben que la evidencia de retrasos o abandonos en las obras es ignorada, o peor aún, que los registros oficiales y actas se alteran retroactivamente en bases de datos centralizadas para evitar sanciones de la Contraloría. Se necesita una plataforma que empodere al ciudadano y haga que la evidencia pública sea tecnológicamente imposible de borrar o alterar.

## 2. Resumen del Producto
GovTrace es una plataforma "CivicTech" y "GovTech" de veeduría ciudadana, diseñada bajo un modelo B2B2C (Multi-tenant) y con un enfoque estricto en Mobile-First. Permite a los ciudadanos reportar el estado real de las obras mediante fotografías geolocalizadas. El núcleo de la plataforma es su integración con bases de datos del Estado (SECOP II) y el uso de tecnología Blockchain pública (Polygon) para generar un sello criptográfico inmutable de cada evidencia y contrato, actuando como un notario digital automático.

## 3. Actores y Roles
*   **Ciudadano / Veedor (Usuario Móvil):** Persona que está en la calle, se acerca a una obra pública y utiliza su teléfono móvil para tomar fotografías y enviar un reporte de estado. No necesita saber de criptomonedas ni instalar billeteras digitales.
*   **Administrador de ONG / Veeduría (Tenant Admin):** Organizaciones (ej. Cámaras de Comercio, ProSantaMarta, ONGs) que pagan por una instancia del sistema para gestionar su propio observatorio ciudadano. Ellos aprueban o moderan los reportes antes de hacerlos públicos.
*   **Auditor / Periodista (Usuario Público):** Cualquier persona que ingresa a la plataforma desde un navegador para ver el mapa de obras, consultar evidencias y verificar matemáticamente que un documento o foto no ha sido alterado desde su creación.

## 4. Flujos Principales
1.  **Sincronización de Contratos:** El sistema se conecta periódicamente a la API de Datos Abiertos de Colombia (SECOP II) para descargar los contratos de obras públicas de una región específica y publicarlos en la plataforma.
2.  **Recolección de Evidencia:** Un Veedor Ciudadano abre la aplicación en su celular, selecciona una obra cercana, toma una foto (evidencia) y añade un comentario (ej. "Obra abandonada hace 2 meses").
3.  **Sellado Criptográfico (Blockchain Invisible):** Cuando la evidencia es recibida, el sistema backend calcula un Hash (huella digital) de la foto y los metadatos. El sistema utiliza su propia billetera (Relayer) para pagar el costo de la red y guarda ese Hash en un Smart Contract en la blockchain.
4.  **Visualización Geoespacial:** La plataforma cuenta con un mapa interactivo público donde las obras se representan como pines. Los pines cambian de color según el estado de la obra o si tienen alertas ciudadanas recientes.
5.  **Verificación de Integridad:** Un Auditor puede descargar la foto de una obra e ingresarla en la herramienta de "Validación" pública. El sistema recalcula el Hash en el navegador del usuario y lo compara con el registro en la blockchain, confirmando al instante si la foto es auténtica o si ha sido manipulada (alterada).

## 5. Requisitos Clave y Restricciones
*   **Mobile-First:** La interfaz de recolección de reportes debe ser extremadamente fácil de usar en pantallas pequeñas, botones grandes y tolerante a malas conexiones a internet móvil.
*   **Cero Fricción Web3:** El uso de blockchain debe ser 100% invisible para los actores. Nadie debe crear cuentas de MetaMask, guardar palabras semilla ni comprar "Gas". El sistema asume esa complejidad.
*   **Transparencia Open Source:** Al ser un proyecto cívico, el código debe poder ser auditado por terceros para garantizar que la plataforma misma no tiene puertas traseras.
*   **Privacidad:** Por ley de protección de datos, NUNCA se guardan nombres, rostros o datos personales en la blockchain, únicamente Hashes SHA-256 irreversibles.
