// It. 40b — Strict Mobile-First: app.css apaga el punto de corte `sm` (solo
// existen `md:` y `lg:`), así que una clase `sm:` nunca se aplica. Hasta la
// it. 40b había 11, y los diseños de escritorio que pedían no existían.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const ROOT = join(process.cwd(), 'resources/js/');

function vueFiles(directory) {
    return readdirSync(directory).flatMap((name) => {
        const path = join(directory, name);
        return statSync(path).isDirectory() ? vueFiles(path) : path.endsWith('.vue') ? [path] : [];
    });
}

describe('Puntos de corte', () => {
    it('uses no `sm:` class, which app.css turns off on purpose', () => {
        expect(vueFiles(ROOT).length).toBeGreaterThan(40);
        const offenders = vueFiles(ROOT).flatMap((path) =>
            [...readFileSync(path, 'utf8').matchAll(/(?<![\w-])sm:[\w-[\]/.]+/g)].map((match) => `${path.slice(ROOT.length)}: ${match[0]}`),
        );

        expect(offenders).toEqual([]);
    });
});
