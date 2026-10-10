// It. 47b — US-008, US-019: el GPS de "Nuevo reporte", una sola vez por pantalla.
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { SLOW_GPS_MESSAGE, useGps } from './useGps.js';

const at = (seconds) => Date.parse('2026-10-10T15:00:00Z') + seconds * 1000;
const fix = (accuracy, seconds = 0) => ({ coords: { latitude: 11.2419, longitude: -74.199, accuracy }, timestamp: at(seconds) });

/** Un GPS de mentira: emit() entrega una lectura a quien lo sigue. */
function fakeGps() {
    const phone = { watchers: [], cleared: [], oneShots: [] };
    phone.geolocation = {
        watchPosition: vi.fn((ok, fail) => phone.watchers.push({ ok, fail }) - 1),
        clearWatch: vi.fn((id) => phone.cleared.push(id)),
        getCurrentPosition: vi.fn((ok, fail) => phone.oneShots.push({ ok, fail })),
    };
    phone.emit = (reading) => phone.watchers.at(-1).ok(reading);
    Object.defineProperty(window.navigator, 'geolocation', { value: phone.geolocation, configurable: true });
    return phone;
}

function withGps() {
    let gps;
    const wrapper = mount(defineComponent({ setup: () => ((gps = useGps()), () => h('div')) }));
    return { gps, wrapper };
}

beforeEach(() => vi.useFakeTimers({ toFake: ['Date', 'setTimeout', 'clearTimeout'], now: at(0) }));
afterEach(() => {
    vi.useRealTimers();
    delete window.navigator.geolocation;
});

describe('useGps', () => {
    it('follows the GPS with high accuracy and no saved readings, and keeps the last precise one apart', () => {
        const phone = fakeGps();
        const { gps } = withGps();
        gps.start();

        expect(phone.geolocation.watchPosition.mock.calls[0][2]).toEqual({ enableHighAccuracy: true, maximumAge: 0, timeout: 60_000 });
        expect(gps.status.value).toBe('searching');

        phone.emit(fix(2000));
        expect(gps.latest.value.accuracy).toBe(2000);
        expect(gps.precise.value).toBeNull();
        expect(gps.status.value).toBe('searching');

        phone.emit(fix(18, 2));
        expect(gps.status.value).toBe('ready');
        expect(gps.precise.value).toMatchObject({ accuracy: 18, capturedAt: '2026-10-10T15:00:02.000Z' });

        phone.emit(fix(120, 4));
        expect(gps.latest.value.accuracy).toBe(120);
        expect(gps.precise.value.accuracy).toBe(18);
    });

    it('a reading of 50 m or less is fresh for 30 seconds', () => {
        const phone = fakeGps();
        const { gps } = withGps();
        gps.start();
        phone.emit(fix(15));

        vi.setSystemTime(at(30));
        expect(gps.fresh()).toMatchObject({ accuracy: 15 });
        vi.setSystemTime(at(31));
        expect(gps.fresh()).toBeNull();
    });

    it('says what to do after a minute without a reading of 50 m or less, and starts again', () => {
        const phone = fakeGps();
        const { gps } = withGps();
        gps.start();
        phone.emit(fix(300));

        vi.advanceTimersByTime(60_000);
        expect(gps.status.value).toBe('slow');
        expect(gps.message.value).toBe(SLOW_GPS_MESSAGE);

        gps.start();
        expect(gps.status.value).toBe('searching');
        expect(phone.geolocation.clearWatch).toHaveBeenCalled();
        phone.emit(fix(20));
        expect(gps.status.value).toBe('ready');
    });

    it('says why when the permission is denied', () => {
        const phone = fakeGps();
        const { gps } = withGps();
        gps.start();
        phone.watchers[0].fail({ code: 1, PERMISSION_DENIED: 1 });

        expect(gps.status.value).toBe('failed');
        expect(gps.message.value).toContain('GovTrace requiere acceso a su ubicación exacta');
    });

    it('asks once more on demand, for a phone that does not move and so sends nothing new', async () => {
        const phone = fakeGps();
        const { gps } = withGps();
        gps.start();

        gps.refresh();
        phone.oneShots[0].ok(fix(12, 5));
        await Promise.resolve(); // la lectura llega en una promesa

        expect(phone.geolocation.getCurrentPosition.mock.calls[0][2]).toEqual({ enableHighAccuracy: true, maximumAge: 0, timeout: 30_000 });
        expect(gps.precise.value.accuracy).toBe(12);
    });

    it('stops following the GPS when the screen closes', () => {
        const phone = fakeGps();
        const { gps, wrapper } = withGps();
        gps.start();

        wrapper.unmount();

        expect(phone.geolocation.clearWatch).toHaveBeenCalledWith(0);
    });
});
