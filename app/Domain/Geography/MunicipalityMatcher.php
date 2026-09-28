<?php

namespace App\Domain\Geography;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Matches the free-text location SECOP II sends in a contract against the
 * DIVIPOLA table, normalizing accents and case so "Cienaga", "Ciénaga" and
 * "CIÉNAGA" all resolve to the same municipality (US-013, R-INT-03). A name
 * that matches nothing returns null — the sync job decides what to do with
 * that (discard and report, see US-014).
 *
 * The municipality is looked up inside its department whenever SECOP says
 * which one: 67 DIVIPOLA names repeat across departments (Armenia is the
 * capital of Quindío and a town in Antioquia).
 */
class MunicipalityMatcher
{
    /**
     * Places SECOP II calls by their short name while DIVIPOLA keeps the
     * official one ("Cali" vs "SANTIAGO DE CALI"), keyed by department code
     * and normalized SECOP spelling. Found by matching every departamento /
     * ciudad pair of SECOP II works contracts against DIVIPOLA on
     * 2026-09-27; add a line when the sync health panel reports a new one.
     */
    private const SECOP_ALIASES = [
        '05|DON MATIAS' => '05237',
        '05|SAN VICENTE' => '05674',
        '05|SANTAFE DE ANTIOQUIA' => '05042',
        '13|CARTAGENA' => '13001',
        '13|MOMPOS' => '13468',
        '19|PIENDAMO' => '19548',
        '52|CUASPUD' => '52224',
        '52|TUMACO' => '52835',
        '54|CUCUTA' => '54001',
        '70|TOLU VIEJO' => '70823',
        '73|MARIQUITA' => '73443',
        '76|CALI' => '76001',
        '86|LEGUIZAMO' => '86573',
    ];

    /** @var Collection<string, Municipality>|null keyed by DIVIPOLA code */
    private ?Collection $municipalities = null;

    /** @var Collection<string, Collection<int, Municipality>>|null keyed by normalized name */
    private ?Collection $municipalitiesByName = null;

    /** @var Collection<int, Department>|null */
    private ?Collection $departments = null;

    /**
     * Resolves the "departamento" and "ciudad" fields of a SECOP II contract.
     */
    public function matchSecopLocation(string $departamento, string $ciudad): ?Municipality
    {
        $department = $this->matchDepartment($departamento);

        if (! $department) {
            return null;
        }

        // Bogotá is at once a department and its only municipality: SECOP's
        // "Bogotá", "Distrito Capital" and "No Definido" there all mean 11001.
        $inDepartment = $this->municipalities()->where('department_code', $department->code);

        if ($inDepartment->count() === 1) {
            return $inDepartment->first();
        }

        return $this->match($ciudad, $department->code);
    }

    public function matchDepartment(string $secopName): ?Department
    {
        $needle = $this->normalize($secopName);

        return $this->departments()->first(
            fn (Department $department) => in_array($needle, [$this->normalize($department->name), $this->normalize($department->secopName())], true)
        );
    }

    /**
     * Without a department, only a name that exists in exactly one
     * department resolves — an ambiguous one returns null, never a guess.
     */
    public function match(string $secopName, ?string $departmentCode = null): ?Municipality
    {
        $needle = $this->normalize($secopName);

        $candidates = $this->municipalitiesByName()->get($needle, collect())
            ->when($departmentCode, fn (Collection $found) => $found->where('department_code', $departmentCode));

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        $alias = self::SECOP_ALIASES["{$departmentCode}|{$needle}"] ?? null;

        return $alias ? $this->municipalities()->get($alias) : null;
    }

    public function normalize(string $name): string
    {
        return Str::of($name)->ascii()->upper()->squish()->toString();
    }

    /** @return Collection<string, Municipality> */
    private function municipalities(): Collection
    {
        return $this->municipalities ??= Municipality::query()->get(['code', 'name', 'department_code'])->keyBy('code');
    }

    /** @return Collection<string, Collection<int, Municipality>> */
    private function municipalitiesByName(): Collection
    {
        return $this->municipalitiesByName ??= $this->municipalities()->values()->groupBy(fn (Municipality $m) => $this->normalize($m->name));
    }

    /** @return Collection<int, Department> */
    private function departments(): Collection
    {
        return $this->departments ??= Department::query()->get(['code', 'name']);
    }
}
