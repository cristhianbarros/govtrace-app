# language: es
@story_id:US-020a @origin:discovery_inicial @priority:1 @epic:EPIC-003
Característica: Smart Contract de sellado con control de acceso
  Como Sistema
  quiero desplegar el Smart Contract de sellado con control de acceso
  para que ningún tercero pueda registrar sellos falsos

  Antecedentes:
    Dado que el Smart Contract de sellado está desplegado
    Y la cuenta del Relayer tiene el rol "RELAYER_ROLE"

  @complexity:medium
  Escenario: El Relayer registra un sello
    Cuando el Relayer registra la raíz "0x9f2c…a1" de la obra 42 con timestamp 1790000000
    Entonces el contrato almacena la raíz como bytes32 junto al ID de obra 42 y el timestamp

  @complexity:medium @negative
  Escenario: Una billetera sin RELAYER_ROLE no puede sellar
    Dado que una billetera externa no tiene el rol "RELAYER_ROLE"
    Cuando esa billetera intenta registrar la raíz "0x1111…22" de la obra 42
    Entonces la transacción es rechazada en la cadena

  @complexity:medium @negative
  Escenario: Un hash ya registrado no se puede registrar de nuevo
    Dado que la raíz "0x9f2c…a1" ya está registrada
    Cuando el Relayer intenta registrar otra vez la raíz "0x9f2c…a1"
    Entonces la transacción es rechazada con "Hash ya registrado"
    Y el servidor descarta el duplicado

  @complexity:medium @negative
  Escenario: Nadie puede modificar ni borrar un sello registrado
    Dado que la raíz "0x9f2c…a1" está registrada
    Cuando cualquier cuenta, incluida la del Super Administrador, intenta modificarla o eliminarla
    Entonces el contrato no ofrece ninguna operación para hacerlo y la raíz permanece igual
