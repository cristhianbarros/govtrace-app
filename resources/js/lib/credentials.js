// US-030 y US-031: las mismas reglas que el servidor, para avisar antes de
// enviar. El servidor las vuelve a aplicar (StrongPassword, LoginController).

export const PASSWORD_RULES_MESSAGE =
    'La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, una minúscula, un número y un símbolo especial.';

export const isEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());

/** App\Domain\Auth\Rules\StrongPassword */
export const isStrongPassword = (password) =>
    password.length >= 8 && /[A-Z]/.test(password) && /[a-z]/.test(password) && /[0-9]/.test(password) && /[^a-zA-Z0-9]/.test(password);
