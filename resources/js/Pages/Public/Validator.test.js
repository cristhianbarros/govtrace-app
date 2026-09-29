// Iteración 27 — US-024 (UI): el validador público, sin sesión. Dos modos
// en esta página — un archivo solo (libre) o el archivo con su prueba de
// inclusión —; el contextual vive en cada tarjeta de la línea de tiempo.
// El veredicto lo da lib/validator.js, probado aparte.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Validator from './Validator.vue';
import { validate } from '@/lib/validator.js';
import { findProof } from '@/services/api.js';
import { page } from '@/testing/inertia.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');
vi.mock('@/lib/validator.js', async (original) => ({ ...(await original()), validate: vi.fn() }));

const STELLAR = { rpc_url: 'https://soroban-testnet.stellar.org', network_passphrase: 'Test SDF Network ; September 2015', contracts: ['CABXHM74HFSAZD4FDFDONSIDJOVJBU7ZJXYBCHY3JJDCQUDFTHBT2WUI'], explorer_url: 'https://stellar.expert/explorer/testnet' };
const AUTHENTIC = { verdict: 'authentic', message: '✅ Archivo Auténtico e Inmutable. Sellado el 2026-09-27 en el ledger #61234567.', explorerUrl: 'https://stellar.expert/explorer/testnet/tx/abc' };

const photo = new File(['foto'], 'obra-gaira.jpg', { type: 'image/jpeg' });
const proof = new File(['{}'], 'evidencia.prueba.json', { type: 'application/json' });

async function choose(wrapper, selector, file) {
    const input = wrapper.get(selector);
    Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
    await input.trigger('change');
}

const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

beforeEach(() => {
    vi.resetAllMocks();
    page.props = { organization: 'Veeduría Ciudadana Santa Marta', organizationLogo: null, organizationNotice: null, stellar: STELLAR };
});

describe('Validador público', () => {
    it('checks a file in the free mode, with the network the page gives, and shows the green banner', async () => {
        validate.mockResolvedValue(AUTHENTIC);
        const wrapper = mount(Validator);

        await choose(wrapper, 'input[data-test="file"]', photo);
        await flushPromises();

        expect(validate).toHaveBeenCalledWith(expect.objectContaining({ file: photo, mode: 'free', stellar: STELLAR, findProof }));
        const banner = wrapper.get('[data-test="verdict"]');
        expect(banner.attributes('role')).toBe('status');
        expect(banner.classes()).toContain('bg-green-600');
        expect(banner.text()).toContain(AUTHENTIC.message);
        expect(banner.get('a').attributes()).toMatchObject({ href: AUTHENTIC.explorerUrl, target: '_blank', rel: 'noopener noreferrer' });
    });

    it('shows that it is verifying, without the file ever leaving the browser', async () => {
        validate.mockReturnValue(new Promise(() => {}));
        const wrapper = mount(Validator);

        await choose(wrapper, 'input[data-test="file"]', photo);

        expect(wrapper.text()).toContain('Verificando en su navegador…');
        expect(wrapper.text()).toContain('El archivo no sale de su equipo');
    });

    it('shows the red banner for an altered file, and a warning for one not found', async () => {
        validate.mockResolvedValueOnce({ verdict: 'altered', message: '❌ Archivo Alterado o Falso. Las huellas criptográficas no coinciden con la blockchain.' });
        const wrapper = mount(Validator);
        await button(wrapper, 'Archivo y su prueba').trigger('click');
        await choose(wrapper, 'input[data-test="file"]', photo);
        await choose(wrapper, 'input[data-test="proof"]', proof);
        await button(wrapper, 'Verificar').trigger('click');
        await flushPromises();

        const red = wrapper.get('[data-test="verdict"]');
        expect(red.attributes('role')).toBe('alert');
        expect(red.classes()).toContain('bg-red-600');

        validate.mockResolvedValueOnce({ verdict: 'not_found', message: '⚠️ Archivo no encontrado. No hay registro de este documento en GovTrace.' });
        await button(wrapper, 'Un archivo').trigger('click');
        await choose(wrapper, 'input[data-test="file"]', photo);
        await flushPromises();

        const warning = wrapper.get('[data-test="verdict"]');
        expect(warning.classes()).toContain('bg-amber-100');
        expect(warning.classes()).not.toContain('bg-red-600');
    });

    it('asks for the file and its proof in the attached mode, and sends nothing to GovTrace', async () => {
        validate.mockResolvedValue(AUTHENTIC);
        const wrapper = mount(Validator);
        await button(wrapper, 'Archivo y su prueba').trigger('click');

        expect(button(wrapper, 'Verificar').attributes('disabled')).toBeDefined();
        await choose(wrapper, 'input[data-test="file"]', photo);
        expect(button(wrapper, 'Verificar').attributes('disabled')).toBeDefined();
        await choose(wrapper, 'input[data-test="proof"]', proof);
        await button(wrapper, 'Verificar').trigger('click');
        await flushPromises();

        expect(validate).toHaveBeenCalledWith(expect.objectContaining({ file: photo, proofFile: proof, mode: 'attached', stellar: STELLAR }));
        expect(findProof).not.toHaveBeenCalled();
    });

    it('accepts photos and PDFs from the picker, and says the limits', () => {
        const wrapper = mount(Validator);

        expect(wrapper.get('input[data-test="file"]').attributes('accept')).toBe('.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf');
        expect(wrapper.text()).toContain('JPG, PNG o PDF, hasta 10 MB');
    });
});
