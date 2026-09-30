// It. 40c: si una pantalla es la abierta — la misma ruta, o una de sus hijas
// (/admin/organizations/new es de Organizaciones), con o sin parámetros.
export function isCurrentScreen(url, href) {
    return url === href || url.startsWith(`${href}?`) || url.startsWith(`${href}/`);
}
