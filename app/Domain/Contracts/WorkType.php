<?php

namespace App\Domain\Contracts;

use Illuminate\Support\Str;

/**
 * It. 47a (US-016): what kind of public work a contract is, for the veedor's
 * filter. By the words of its object (the first one that appears decides)
 * and, if none fits, by the UNSPSC code SECOP II publishes
 * (codigo_de_categoria_principal). The code alone is not enough: measured
 * in Antioquia, water networks and housing fall under "maintenance" (7210)
 * and a sports court under "specialized trades" (7215) — so it only decides
 * where it is reliable.
 */
enum WorkType: string
{
    case Roads = 'roads';
    case Water = 'water';
    case Housing = 'housing';
    case Education = 'education';
    case Health = 'health';
    case Sports = 'sports';
    case PublicSpace = 'public_space';
    case Other = 'other';

    /** Words of the object, without accents and in lowercase, matched as whole words. */
    private const KEYWORDS = [
        'roads' => ['via', 'vias', 'vial', 'viales', 'carretera', 'carreteras', 'pavimentacion', 'pavimento', 'placa huella', 'placas huella', 'puente', 'puentes', 'malla vial', 'calle', 'calles', 'carrera', 'tunel'],
        'water' => ['acueducto', 'acueductos', 'alcantarillado', 'alcantarillados', 'agua potable', 'saneamiento', 'pozo septico', 'pozos septicos', 'unidad sanitaria', 'unidades sanitarias', 'aguas residuales', 'aguas lluvias', 'ptar', 'redes de agua', 'red de agua'],
        'housing' => ['vivienda', 'viviendas', 'habitacional', 'habitacionales'],
        'education' => ['escuela', 'escuelas', 'colegio', 'colegios', 'institucion educativa', 'instituciones educativas', 'sede educativa', 'sedes educativas', 'centro educativo', 'centros educativos', 'restaurante escolar', 'restaurantes escolares', 'aula', 'aulas', 'universidad', 'jardin infantil'],
        'health' => ['hospital', 'hospitales', 'centro de salud', 'centros de salud', 'puesto de salud', 'puestos de salud', 'clinica', 'unidad de salud'],
        'sports' => ['cancha', 'canchas', 'polideportivo', 'polideportivos', 'coliseo', 'coliseos', 'escenario deportivo', 'escenarios deportivos', 'placa deportiva', 'placas deportivas', 'unidad deportiva', 'unidades deportivas', 'piscina', 'piscinas', 'estadio', 'estadios'],
        'public_space' => ['parque', 'parques', 'plaza', 'plazas', 'plazoleta', 'plazoletas', 'anden', 'andenes', 'sendero', 'senderos', 'espacio publico', 'malecon', 'alameda', 'zona verde', 'zonas verdes', 'boulevard'],
    ];

    /** UNSPSC prefixes that do say the kind of work: residential buildings, and highways and roads. */
    private const UNSPSC = [
        '7211' => 'housing',
        '721411' => 'roads',
    ];

    public static function classify(?string $object, ?string $unspsc): self
    {
        $text = ' '.SearchText::of($object).' ';
        $first = null;

        foreach (self::KEYWORDS as $type => $words) {
            foreach ($words as $word) {
                if (preg_match('/(?<=[^a-z0-9])'.preg_quote($word, '/').'(?=[^a-z0-9])/', $text, $match, PREG_OFFSET_CAPTURE)
                    && ($first === null || $match[0][1] < $first[1])) {
                    $first = [$type, $match[0][1]];
                }
            }
        }

        if ($first !== null) {
            return self::from($first[0]);
        }

        $code = Str::after((string) $unspsc, 'V1.');
        foreach (self::UNSPSC as $prefix => $type) {
            if (str_starts_with($code, $prefix)) {
                return self::from($type);
            }
        }

        return self::Other;
    }

    public function label(): string
    {
        return match ($this) {
            self::Roads => 'Vías y puentes',
            self::Water => 'Agua y saneamiento',
            self::Housing => 'Vivienda',
            self::Education => 'Educación',
            self::Health => 'Salud',
            self::Sports => 'Deporte y recreación',
            self::PublicSpace => 'Espacio público',
            self::Other => 'Otras',
        };
    }
}
