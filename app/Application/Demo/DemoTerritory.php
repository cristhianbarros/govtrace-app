<?php

namespace App\Application\Demo;

use InvalidArgumentException;

/**
 * What make demo shows, by territory (make demo TERRITORIO=…): the
 * organization, the contracts, the worksites and the reports.
 *
 * Magdalena (by default) is all made up. Medellín (it. 43e) mixes two kinds,
 * and keeps them apart on purpose:
 * - Real contracts of SECOP II in the Comuna 13, only with evidences of
 *   "Avance": realism without saying anything false about a real work.
 * - Example worksites, fictitious for anyone who sees them — "de ejemplo" in
 *   the name, a made-up entity and contractor —, which carry the delays and
 *   the abandonment.
 */
final class DemoTerritory
{
    public const NAMES = ['magdalena', 'medellin'];

    /**
     * @param  list<string>  $territory  DIVIPOLA codes the organization watches
     * @param  list<array<string, mixed>>  $contracts  the contracts, as SECOP II columns
     * @param  array<string, array{0: list<string>, 1: array{0: float, 1: float}|null, 2: int}>  $worksites  [contracts it groups, location (null = not anchored yet), photo]
     * @param  list<array{0: string, 1: int, 2: string, 3: int, 4: int, 5: string}>  $reports  [worksite, veedor (0 = Ana, 1 = Luis), classification, days ago, metres north of the worksite, comment]; the first six get published
     */
    private function __construct(
        public readonly string $organizationName,
        public readonly array $territory,
        public readonly array $contracts,
        public readonly array $worksites,
        public readonly string $anchored,
        public readonly array $reports,
    ) {}

    public static function named(string $name): self
    {
        return match ($name) {
            'magdalena' => self::magdalena(),
            'medellin' => self::medellin(),
            default => throw new InvalidArgumentException('TERRITORIO debe ser '.implode(' o ', self::NAMES).'.'),
        };
    }

    /** The name of the worksite that LUGAR anchors, as its first contract calls it. */
    public function anchoredWorksite(): string
    {
        $first = $this->worksites[$this->anchored][0][0];

        return collect($this->contracts)->firstWhere('secop_contract_id', $first)['object'];
    }

    /** Santa Marta and Magdalena: all made up. */
    public static function magdalena(): self
    {
        // [id, entity, contractor, municipality, state, object, months left, value in COP]
        $contracts = [
            ['CO1.PCCNTR.9100001', 'Alcaldía Distrital de Santa Marta', 'Constructora Bahía S.A.S.', '47001', 'En ejecución', 'Pavimentación de la Calle 30, barrio Bastidas', 5, 2_850_000_000],
            ['CO1.PCCNTR.9100002', 'Alcaldía Distrital de Santa Marta', 'Consorcio Parques del Caribe', '47001', 'En ejecución', 'Construcción del parque Los Trupillos', 4, 1_240_000_000],
            ['CO1.PCCNTR.9100003', 'Gobernación del Magdalena', 'Vías del Magdalena S.A.', '47189', 'En ejecución', 'Mejoramiento de la vía Ciénaga – Sevilla', 1, 18_700_000_000],
            ['CO1.PCCNTR.9100004', 'Alcaldía de Fundación', 'Aguas de la Zona Bananera S.A.S.', '47288', 'En ejecución', 'Ampliación del acueducto veredal de Fundación', 6, 3_960_000_000],
            ['CO1.PCCNTR.9100005', 'Alcaldía Distrital de Santa Marta', 'Consorcio Educativo Santa Marta', '47001', 'En ejecución', 'Construcción del colegio distrital de Gaira', 8, 9_450_000_000],
            ['CO1.PCCNTR.9100006', 'Alcaldía Distrital de Santa Marta', 'Interventorías del Norte S.A.S.', '47001', 'En ejecución', 'Interventoría de la construcción del colegio distrital de Gaira', 8, 780_000_000],
            ['CO1.PCCNTR.9100007', 'Alcaldía Distrital de Santa Marta', 'Obras Hidráulicas del Caribe S.A.S.', '47001', 'Terminado', 'Canalización del arroyo San Joaquín', -1, 2_100_000_000],
        ];

        return new self(
            organizationName: 'Veeduría Ciudadana Santa Marta (demo)',
            territory: ['47'],
            contracts: array_map(fn (array $contract) => self::madeUp(...$contract), $contracts),
            worksites: [
                'via' => [['CO1.PCCNTR.9100001'], [11.2408, -74.1990], 1],
                'parque' => [['CO1.PCCNTR.9100002'], [11.2195, -74.2054], 2],
                'vía Ciénaga' => [['CO1.PCCNTR.9100003'], [11.0070, -74.2470], 3],
                'acueducto' => [['CO1.PCCNTR.9100004'], [10.5200, -74.1850], 4],
                'colegio' => [['CO1.PCCNTR.9100005', 'CO1.PCCNTR.9100006'], [11.2350, -74.1900], 5],
                'canal' => [['CO1.PCCNTR.9100007'], null, 6],
            ],
            anchored: 'via',
            reports: [
                ['via', 0, 'Avance', 6, 30, 'Arrancó el fresado de la calzada.'],
                ['vía Ciénaga', 1, 'Retraso', 5, 60, 'No hay maquinaria en el frente desde hace una semana.'],
                ['via', 1, 'Avance', 5, 45, 'Se extendió la base granular en el primer tramo.'],
                ['parque', 1, 'Avance', 4, 40, 'Cimentación de la cancha terminada.'],
                ['parque', 0, 'Retraso', 3, 70, 'El suministro de cemento lleva tres días detenido.'],
                ['vía Ciénaga', 0, 'Abandono', 3, 55, 'La obra está sin personal ni cerramiento.'],
                ['acueducto', 0, 'Avance', 2, 35, 'Instalada la tubería del primer kilómetro.'],
                ['colegio', 1, 'Avance', 1, 25, 'Levantan el segundo piso del bloque A.'],
                ['via', 0, 'Avance', 0, 50, 'Señalización horizontal en el tramo terminado.'],
            ],
        );
    }

    /**
     * The Comuna 13 of Medellín (it. 43e). The real contracts are those of the
     * Escuela Municipal San Javier, as SECOP II published them on 2026-09-30;
     * the places, from OpenStreetMap. The school's pin is near the San Javier
     * station: SECOP has no coordinates, so it is approximate.
     */
    public static function medellin(): self
    {
        $example = fn (string $id, string $object, string $state, int $monthsLeft, int $value) => self::madeUp($id, 'Entidad de ejemplo (ficticia)', 'Contratista de ejemplo S.A.S. (ficticio)', '05001', $state, $object, $monthsLeft, $value);

        return new self(
            organizationName: 'Veeduría Ciudadana Comuna 13 (demo)',
            territory: ['05001'],
            contracts: [
                self::real('CO1.PCCNTR.9033732', 'CO1.BDOS.9132077', 'U.T SAN JAVIER 2026', 'CONSTRUCCIÓN DE LA SECCIÓN ESCUELA MUNICIPAL SAN JAVIER Y ESPACIO PÚBLICO ASOCIADO EN EL DISTRITO ESPECIAL DE CIENCIA; TECNOLOGÍA E INNOVACIÓN DE MEDELLÍN.', 15_364_531_133, 'CO1.NTC.9158086'),
                self::real('CO1.PCCNTR.9047349', 'CO1.BDOS.9164779', 'CONSORCIO INTERVENTORES TT 25', 'INTERVENTORÍA INTEGRAL PARA LA CONSTRUCCIÓN DE LA SECCIÓN ESCUELA MUNICIPAL SAN JAVIER Y ESPACIO PÚBLICO ASOCIADO EN EL DISTRITO ESPECIAL DE CIENCIA; TECNOLOGÍA E INNOVACIÓN DE MEDELLÍN', 1_562_752_122, 'CO1.NTC.9191148'),
                $example('EJEMPLO-C13-001', 'Obra de ejemplo: andenes de La Pradera', 'En ejecución', 5, 850_000_000),
                $example('EJEMPLO-C13-002', 'Obra de ejemplo: parque infantil de El Salado', 'En ejecución', 4, 620_000_000),
                $example('EJEMPLO-C13-003', 'Obra de ejemplo: escaleras de Las Independencias', 'En ejecución', 1, 1_480_000_000),
                $example('EJEMPLO-C13-004', 'Obra de ejemplo: placa deportiva del Veinte de Julio', 'En ejecución', 6, 910_000_000),
                $example('EJEMPLO-C13-005', 'Obra de ejemplo: canalización de una quebrada', 'Terminado', -1, 2_300_000_000),
            ],
            worksites: [
                'escuela' => [['CO1.PCCNTR.9033732', 'CO1.PCCNTR.9047349'], [6.2569996, -75.6139695], 5],
                'andenes' => [['EJEMPLO-C13-001'], [6.2606640, -75.6110203], 1],
                'parque' => [['EJEMPLO-C13-002'], [6.2552554, -75.6265846], 2],
                'escaleras' => [['EJEMPLO-C13-003'], [6.2492297, -75.6237007], 3],
                'placa' => [['EJEMPLO-C13-004'], [6.2522921, -75.6208362], 4],
                'quebrada' => [['EJEMPLO-C13-005'], null, 6],
            ],
            anchored: 'andenes',
            reports: [
                ['andenes', 0, 'Avance', 6, 30, 'Arrancó el cambio de las baldosas del andén.'],
                ['escaleras', 1, 'Retraso', 5, 60, 'No hay personal en el frente desde hace una semana.'],
                ['andenes', 1, 'Avance', 5, 45, 'Terminado el primer tramo del andén.'],
                // Un contrato real: solo "Avance", y dicho como lo que es, una visita de demostración.
                ['escuela', 1, 'Avance', 4, 40, 'Visita de demostración: se ve actividad en la obra.'],
                ['parque', 0, 'Retraso', 3, 70, 'Los materiales llevan tres días sin llegar.'],
                ['escaleras', 0, 'Abandono', 3, 55, 'La obra está sin personal ni cerramiento.'],
                ['placa', 0, 'Avance', 2, 35, 'Nivelaron el terreno de la placa.'],
                ['escuela', 1, 'Avance', 1, 25, 'Visita de demostración: la obra avanza.'],
                ['andenes', 0, 'Avance', 0, 50, 'Instalaron las rampas de las esquinas.'],
            ],
        );
    }

    /** @return array<string, mixed> a made-up contract, dated around today */
    private static function madeUp(string $id, string $entity, string $contractor, string $municipality, string $state, string $object, int $monthsLeft, int $value): array
    {
        return [
            'secop_contract_id' => $id,
            'entity_name' => $entity,
            'contractor_name' => $contractor,
            'object' => $object,
            'value' => $value,
            'contract_type' => 'Obra',
            'status' => $state,
            'signed_at' => now()->subMonths(3)->toDateString(),
            'end_date' => now()->addMonths($monthsLeft)->toDateString(),
            'department_code' => substr($municipality, 0, 2),
            'municipality_code' => $municipality,
            'process_number' => 'DEMO-'.substr($id, -3),
            'secop_url' => null,
        ];
    }

    /** @return array<string, mixed> a contract of the Escuela Municipal San Javier, as SECOP II published it on 2026-09-30 */
    private static function real(string $id, string $process, string $contractor, string $object, int $value, string $notice): array
    {
        return [
            'secop_contract_id' => $id,
            'entity_name' => 'EMPRESA DE DESARROLLO URBANO DE MEDELLIN',
            'contractor_name' => $contractor,
            'object' => $object,
            'value' => $value,
            'contract_type' => 'Obra',
            'status' => 'En ejecución',
            'signed_at' => '2026-02-04',
            'end_date' => '2027-01-26',
            'department_code' => '05',
            'municipality_code' => '05001',
            'process_number' => $process,
            'secop_url' => "https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?noticeUID={$notice}&isFromPublicArea=True&isModal=true&asPopupView=true",
        ];
    }
}
