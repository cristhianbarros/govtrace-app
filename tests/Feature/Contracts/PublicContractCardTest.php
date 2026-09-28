<?php

use App\Application\Contracts\GetPublicContractCard;
use App\Domain\Contracts\Contract;
use Database\Seeders\DivipolaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Iteración 8 — Contratos del territorio: listado, búsqueda y tarjeta
 * pública (specs/PLAN.md). Traduce features/US-017.feature (4 casos).
 *
 * US-017 vive en la vista de obra (US-029, it. 26); la ficha de obra
 * todavía no existe (it. 9), así que aquí la tarjeta se pide
 * directamente por el contrato — es el "backend en P1" que la
 * observación O3 del plan describe. R-VER-02: nada de esto pide sesión,
 * por eso los tests no autentican a nadie.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    (new DivipolaSeeder)->run();
});

function contractForCard(array $overrides = []): Contract
{
    return Contract::fromSecop(fn () => Contract::create(array_merge([
        'secop_contract_id' => 'CO1.PCCNTR.1234567',
        'entity_name' => 'Alcaldía de Santa Marta',
        'contractor_name' => 'Constructora Caribe S.A.S.',
        'contract_type' => 'Obra',
        'status' => 'En ejecución',
        'value' => 1_250_000_000,
        'signed_at' => '2026-01-15',
        'end_date' => '2026-09-15', // 8 meses después de firmado
        'department_code' => '47',
        'municipality_code' => '47001',
        'secop_url' => 'https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?x=1',
    ], $overrides)));
}

it('shows the entity, contractor, value and term of the contract, with a link to the original in SECOP', function () {
    $contract = contractForCard();

    $card = (new GetPublicContractCard)->handle($contract);

    // El valor se entrega tal cual ($1.250.000.000 formateado es de la
    // pantalla, it. 26); el plazo en meses sí es cálculo de backend.
    expect($card['entity_name'])->toBe('Alcaldía de Santa Marta')
        ->and($card['contractor_name'])->toBe('Constructora Caribe S.A.S.')
        ->and((float) $card['value'])->toBe(1_250_000_000.00)
        ->and($card['term_months'])->toBe(8)
        ->and($card['secop_url'])->toBe('https://community.secop.gov.co/Public/Tendering/OpportunityDetail/Index?x=1');
});

it('shows the data exactly as SECOP II sends it, without normalizing it', function () {
    $contract = contractForCard(['contractor_name' => 'Constructora Caribe SAS.']);

    $card = (new GetPublicContractCard)->handle($contract);

    expect($card['contractor_name'])->toBe('Constructora Caribe SAS.');
});

it('is available without requiring the visitor to be logged in', function () {
    // Nada en GetPublicContractCard depende de un guard de sesión — se
    // prueba sin iniciar sesión, a propósito (R-VER-02).
    $contract = contractForCard();

    $card = (new GetPublicContractCard)->handle($contract);

    expect($card)->not->toBeEmpty();
});

it('keeps showing the card, with a cancelled banner, when SECOP annuls a contract that already has evidence', function () {
    // Las 2 evidencias publicadas (US-036/037) llegan en it. 10+; lo que
    // corresponde a esta iteración es que la tarjeta del contrato
    // señale el estado 'cancelled' con su propio aviso.
    $contract = contractForCard(['status' => 'cancelled']);

    $card = (new GetPublicContractCard)->handle($contract);

    expect($card['cancelled'])->toBeTrue()
        ->and($card['cancelled_notice'])->toBe('⚠️ Contrato Anulado/Retirado en SECOP');
});
