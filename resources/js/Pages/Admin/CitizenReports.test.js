// Iteración 44f — US-059-LEG: los informes de los ciudadanos, como los ve el
// Administrador — sin el correo de quien los envió — para responder o descartar.
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CitizenReports from './CitizenReports.vue';
import { answerCitizenReport, discardCitizenReport, fetchCitizenReports } from '@/services/api.js';

vi.mock('@inertiajs/vue3', async () => await import('@/testing/inertia.js'));
vi.mock('@/services/api.js');

const report = (changes = {}) => ({
    id: 12,
    worksite_id: 7,
    worksite: 'Pavimentación Calle 30',
    received_at: '2026-09-30T15:00:00+00:00',
    message: 'La obra lleva dos semanas sin trabajadores y el cerramiento se cayó.',
    photo_url: '/citizen-reports/12/photo',
    status: 'new',
    status_label: 'Nuevo',
    answer: null,
    ...changes,
});
const button = (wrapper, text) => wrapper.findAll('button').find((candidate) => candidate.text() === text);

async function openReports(rows = [report()]) {
    fetchCitizenReports.mockResolvedValue(rows);
    const wrapper = mount(CitizenReports);
    await flushPromises();
    return wrapper;
}

beforeEach(() => {
    vi.resetAllMocks();
});

describe('Informes ciudadanos', () => {
    it('La veeduría recibe los informes sin ver el correo del ciudadano: the worksite, the date, the message, the photo and its state', async () => {
        const wrapper = await openReports();
        const card = wrapper.get('[data-test="citizen-report"]');

        expect(card.text()).toContain('Pavimentación Calle 30');
        expect(card.text()).toContain('30/09/2026');
        expect(card.text()).toContain('La obra lleva dos semanas sin trabajadores');
        expect(card.text()).toContain('Nuevo');
        expect(card.get('img').attributes('src')).toBe('/citizen-reports/12/photo');
        expect(wrapper.text()).toContain('Ley 850 de 2003, art. 18');
        expect(wrapper.text()).not.toContain('@');
    });

    it('says when there are no reports yet', async () => {
        expect((await openReports([])).text()).toContain('Aún no hay informes de ciudadanos.');
    });

    it('La veeduría responde al ciudadano: writes the answer, and the list shows it answered', async () => {
        answerCitizenReport.mockResolvedValue({ message: 'Respuesta enviada al ciudadano.' });
        const wrapper = await openReports();
        fetchCitizenReports.mockResolvedValue([report({ status: 'answered', status_label: 'Atendido', answer: 'Gracias. El sábado va un veedor.' })]);

        await button(wrapper, 'Responder').trigger('click');
        await wrapper.get('textarea').setValue('Gracias. El sábado va un veedor.');
        await button(wrapper, 'Enviar respuesta').trigger('click');
        await flushPromises();

        expect(answerCitizenReport).toHaveBeenCalledWith(12, 'Gracias. El sábado va un veedor.');
        expect(wrapper.get('[role="status"]').text()).toBe('Respuesta enviada al ciudadano.');
        expect(wrapper.get('[data-test="citizen-report"]').text()).toContain('Atendido');
        expect(wrapper.get('[data-test="citizen-report"]').text()).toContain('Gracias. El sábado va un veedor.');
    });

    it('La veeduría descarta un informe: it asks to confirm first', async () => {
        discardCitizenReport.mockResolvedValue({ message: 'Informe descartado.' });
        const wrapper = await openReports();

        await button(wrapper, 'Descartar').trigger('click');
        expect(discardCitizenReport).not.toHaveBeenCalled();
        await button(wrapper, 'Confirmar descarte').trigger('click');
        await flushPromises();

        expect(discardCitizenReport).toHaveBeenCalledWith(12);
        expect(wrapper.get('[role="status"]').text()).toBe('Informe descartado.');
    });
});
