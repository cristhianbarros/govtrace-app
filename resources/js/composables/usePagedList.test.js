// It. 52 — un listado de a una página: "Ver 20 más", una búsqueda con pausa, y la
// respuesta de una petición vieja no pisa a la nueva.
import { flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { usePagedList } from './usePagedList.js';

const body = (rows, extra = {}) => ({ data: rows, total: rows.length, has_more: false, ...extra });

beforeEach(() => vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] }));
afterEach(() => vi.useRealTimers());

describe('usePagedList', () => {
    it('loads the first page, and asks for the next one when told to, adding it to the list', async () => {
        const fetch = vi.fn()
            .mockResolvedValueOnce(body([{ id: 1 }, { id: 2 }], { total: 3, has_more: true }))
            .mockResolvedValueOnce(body([{ id: 3 }], { total: 3 }));
        const list = usePagedList(fetch);

        await list.load();
        expect(fetch).toHaveBeenLastCalledWith({ page: 1 });
        expect(list.data.value.map((row) => row.id)).toEqual([1, 2]);
        expect(list.meta.value).toEqual({ total: 3, has_more: true });

        await list.more();
        expect(fetch).toHaveBeenLastCalledWith({ page: 2 });
        expect(list.data.value.map((row) => row.id)).toEqual([1, 2, 3]);
        expect(list.meta.value.has_more).toBe(false);
    });

    it('starts again from the first page when the parameters change', async () => {
        const fetch = vi.fn().mockResolvedValue(body([{ id: 1 }], { has_more: true }));
        const list = usePagedList(fetch);
        await list.load();
        await list.more();

        await list.load({ status: 'new' });

        expect(fetch).toHaveBeenLastCalledWith({ status: 'new', page: 1 });
        expect(list.data.value).toHaveLength(1);
    });

    it('searches after a pause, once per pause and not per key, and an empty search brings everything back', async () => {
        const fetch = vi.fn().mockResolvedValue(body([]));
        const list = usePagedList(fetch);
        await list.load();

        list.search('la');
        list.search('lau');
        list.search('laura');
        vi.advanceTimersByTime(299);
        expect(fetch).toHaveBeenCalledTimes(1);
        vi.advanceTimersByTime(1);
        await flushPromises();

        expect(fetch).toHaveBeenCalledTimes(2);
        expect(fetch).toHaveBeenLastCalledWith({ q: 'laura', page: 1 });

        list.search('  ');
        vi.advanceTimersByTime(300);
        await flushPromises();
        expect(fetch).toHaveBeenLastCalledWith({ page: 1 });
    });

    it('ignores the answer of an old request when a newer one was made', async () => {
        let slow;
        const fetch = vi.fn()
            .mockReturnValueOnce(new Promise((resolve) => (slow = resolve)))
            .mockResolvedValueOnce(body([{ id: 2 }]));
        const list = usePagedList(fetch);

        const first = list.load({ q: 'vieja' });
        await list.load({ q: 'nueva' });
        slow(body([{ id: 1 }]));
        await first;

        expect(list.data.value).toEqual([{ id: 2 }]);
        expect(list.loading.value).toBe(false);
    });

    it('says why it could not load, and keeps what it had when a next page fails', async () => {
        const fetch = vi.fn().mockResolvedValueOnce(body([{ id: 1 }], { has_more: true })).mockRejectedValueOnce(new Error('Network Error'));
        const list = usePagedList(fetch);
        await list.load();

        await list.more();

        expect(list.error.value).toContain('No se pudo conectar con el servidor.');
        expect(list.data.value).toEqual([{ id: 1 }]);
        expect(list.loadingMore.value).toBe(false);
    });
});
