<?php

namespace App\Domain\Organization;

use App\Domain\Organization\Exceptions\LogoRejected;
use DOMAttr;
use DOMDocument;
use DOMElement;

/**
 * R-SEC-04 (US-007): an SVG logo is kept only with what draws. It works by
 * allowlist — elements and attributes that are known to only paint; any
 * other one is removed, so a new trick isn't missed. Out go scripts, event
 * handlers, foreignObject (HTML inside the SVG), animations (they can
 * rewrite links), style sheets, images and any reference that leaves the
 * document. A DOCTYPE is rejected outright: entities can expand without
 * limit or read files from the server.
 *
 * The logo is also served with a CSP that runs nothing (OrganizationLogo-
 * Controller): if something ever got through, the browser still wouldn't
 * execute it.
 */
final class SvgSanitizer
{
    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    private const ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'linearGradient', 'radialGradient', 'stop', 'clipPath', 'mask', 'pattern',
    ];

    private const ATTRIBUTES = [
        'id', 'class', 'style', 'transform', 'viewBox', 'preserveAspectRatio', 'version', 'width', 'height',
        'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'd', 'points', 'pathLength',
        'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'opacity', 'color',
        'clip-path', 'clip-rule', 'mask', 'display', 'visibility',
        'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline', 'letter-spacing', 'dx', 'dy',
        'offset', 'stop-color', 'stop-opacity', 'gradientUnits', 'gradientTransform', 'spreadMethod',
        'patternUnits', 'patternContentUnits', 'patternTransform', 'clipPathUnits', 'maskUnits', 'maskContentUnits',
        'href', // solo #referencias internas, ver cleanAttribute()
    ];

    private const TEXT_ELEMENTS = ['text', 'tspan', 'title', 'desc'];

    public function sanitize(string $svg): string
    {
        if (stripos($svg, '<!DOCTYPE') !== false || stripos($svg, '<!ENTITY') !== false) {
            throw LogoRejected::invalidFormat();
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;
        if (! $loaded || ! $root || $root->localName !== 'svg' || $root->namespaceURI !== self::SVG_NAMESPACE) {
            throw LogoRejected::invalidFormat();
        }

        $this->clean($root);

        return $document->saveXML($root);
    }

    private function clean(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $value = $this->cleanAttribute($attribute);

            if ($value === null) {
                $element->removeAttributeNode($attribute);
            } else {
                $attribute->value = $value;
            }
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if ($child->namespaceURI === self::SVG_NAMESPACE && in_array($child->localName, self::ELEMENTS, true)) {
                    $this->clean($child);
                } else {
                    $element->removeChild($child);
                }
            } elseif ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                // Texto, solo el que se dibuja; afuera de text/tspan/title/desc, solo espacios.
                if (! in_array($element->localName, self::TEXT_ELEMENTS, true) && trim($child->textContent) !== '') {
                    $element->removeChild($child);
                }
            } else {
                // Comentarios e instrucciones de procesamiento.
                $element->removeChild($child);
            }
        }
    }

    /** The value to keep, or null to remove the attribute. */
    private function cleanAttribute(DOMAttr $attribute): ?string
    {
        $name = $attribute->localName;
        $value = trim($attribute->value);

        if ($attribute->namespaceURI !== null && $attribute->namespaceURI !== self::XLINK_NAMESPACE) {
            return null;
        }
        if (! in_array($name, self::ATTRIBUTES, true)) {
            return null;
        }
        // Enlaces: solo a algo dentro del mismo documento.
        if ($name === 'href') {
            return str_starts_with($value, '#') ? $value : null;
        }
        // En style, cada declaración por separado: se va la peligrosa, no las demás.
        if ($name === 'style') {
            $declarations = array_filter(array_map('trim', explode(';', $value)), fn (string $declaration) => $declaration !== '' && $this->isSafe($declaration));

            return $declarations === [] ? null : implode(';', $declarations);
        }

        return $this->isSafe($value) ? $value : null;
    }

    /** Sin código ni recursos de afuera: url(...) solo hacia el mismo documento, p. ej. fill="url(#degradado)". */
    private function isSafe(string $value): bool
    {
        if (preg_match('/javascript:|expression\s*\(|@import|behavior\s*:/i', $value)) {
            return false;
        }

        preg_match_all('/url\s*\(\s*[\'"]?\s*([^\'")\s]*)/i', $value, $urls);

        foreach ($urls[1] as $target) {
            if (! str_starts_with($target, '#')) {
                return false;
            }
        }

        return true;
    }
}
