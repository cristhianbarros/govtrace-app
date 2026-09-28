# language: es
@story_id:US-020a @origin:discovery_inicial @priority:1 @epic:EPIC-003
Característica: Smart Contract de sellado en Soroban con control de acceso
  Como Sistema
  quiero desplegar el Smart Contract de sellado en Stellar con control de acceso
  para que ningún tercero pueda registrar sellos falsos

  # Pivote a Stellar (2026-09-28): Soroban (Rust) reemplaza a Solidity y la
  # cuenta selladora (require_auth) reemplaza al RELAYER_ROLE (R-BLK-03).
  Antecedentes:
    Dado que el Smart Contract de sellado está desplegado con la cuenta selladora de GovTrace

  @complexity:medium
  Escenario: La cuenta selladora registra un sello
    Dado que el ledger en curso es el 1200, con hora 1790000000
    Cuando la cuenta selladora registra la raíz "9f2c…a1" de la obra "ab12…ef"
    Entonces el contrato guarda la raíz junto a la referencia de la obra "ab12…ef", la hora 1790000000 y el ledger 1200

  @complexity:medium @negative
  Escenario: Una cuenta que no es la selladora no puede sellar
    Dado que una cuenta externa no es la cuenta selladora
    Cuando esa cuenta intenta registrar la raíz "1111…22" de la obra "ab12…ef"
    Entonces la red rechaza la invocación por falta de autorización
    Y la raíz "1111…22" no queda registrada

  @complexity:medium @negative
  Escenario: Un hash ya registrado no se puede registrar de nuevo
    Dado que la raíz "9f2c…a1" ya está registrada
    Cuando la cuenta selladora intenta registrar otra vez la raíz "9f2c…a1"
    Entonces la invocación es rechazada con "Hash ya registrado"
    Y el sello original no cambia
    Y el servidor descarta el duplicado

  @complexity:medium @negative
  Escenario: Nadie puede modificar ni borrar un sello registrado
    Dado que la raíz "9f2c…a1" está registrada
    Cuando cualquier cuenta, incluida la del Super Administrador, busca cómo modificarla, eliminarla o reemplazar el código del contrato
    Entonces el contrato no ofrece ninguna operación para hacerlo y la raíz permanece igual
