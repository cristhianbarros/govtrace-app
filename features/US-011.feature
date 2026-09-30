# language: es
@story_id:US-011 @origin:discovery_inicial @priority:1 @epic:EPIC-009
Característica: Actualización del NIT y datos legales por el Super Administrador
  Como Super Administrador
  quiero actualizar el NIT y los datos legales de una organización a solicitud formal
  para mantener su validez legal sin abrir un vector de suplantación

  Antecedentes:
    Dado que existe la organización "Veeduría Ciudadana Santa Marta" con NIT "900123456-8"

  @complexity:medium
  Escenario: Actualización exitosa del NIT con registro de auditoría
    Dado que estoy autenticado como Super Administrador en el panel global
    Y la organización presentó una solicitud formal de cambio de razón social
    Cuando cambio el NIT de la organización a "901234567-7"
    Entonces el NIT de la organización es "901234567-7"
    Y el log de auditoría registra quién hizo el cambio, cuándo, el NIT anterior "900123456-8" y el nuevo "901234567-7"

  @complexity:low @negative
  Escenario: No se puede cambiar a un NIT que ya usa otra organización
    Dado que estoy autenticado como Super Administrador en el panel global
    Y existe otra organización con NIT "901234567-7"
    Cuando cambio el NIT de la organización a "901234567-7"
    Entonces el cambio es rechazado
    Y veo el mensaje "Ya existe una organización registrada con el NIT ingresado."

  @complexity:low @negative
  Escenario: No se puede cambiar a un NIT con dígito de verificación inválido
    Dado que estoy autenticado como Super Administrador en el panel global
    Cuando cambio el NIT de la organización a "901234567-2"
    Entonces el cambio es rechazado
    Y veo el mensaje "El NIT ingresado no es válido o el dígito de verificación no coincide con el algoritmo de la DIAN."

  @complexity:low @negative
  Escenario: El Administrador de Organización no puede editar el NIT ni los datos legales
    Dado que estoy autenticado como Administrador de la organización "Veeduría Ciudadana Santa Marta"
    Cuando intento cambiar el NIT de mi organización a "901234567-7"
    Entonces la acción es rechazada
    Y el NIT de la organización sigue siendo "900123456-8"

  # It. 44d (docs/proceso-actual.md A5): la inscripción ante la personería o la cámara de comercio es
  # un dato legal más, y una organización tiene su NIT, su inscripción o los dos.

  @complexity:medium @origin:proceso_actual
  Escenario: Actualización de la inscripción con registro de auditoría
    Dado que estoy autenticado como Super Administrador en el panel global
    Cuando le agrego a la organización la inscripción "Acta 45 de 2025" ante "Cámara de Comercio de Santa Marta"
    Entonces la organización tiene su NIT y su inscripción
    Y el log de auditoría registra los datos legales anteriores y los nuevos

  @complexity:low @negative @origin:proceso_actual
  Escenario: No se puede dejar una organización sin NIT ni inscripción
    Dado que la organización solo tiene su inscripción
    Cuando intento quitarle la inscripción sin darle un NIT
    Entonces el cambio es rechazado con "Ingrese el NIT de la organización, o el número de la resolución o el acta de su inscripción y la entidad que la registró."
