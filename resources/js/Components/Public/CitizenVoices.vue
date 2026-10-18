<script setup>
// It. 66b (US-069, R-LEG-12): cuántos ciudadanos informaron de la obra y qué
// hace la veeduría — solo el número y el estado, nunca sus mensajes, fotos,
// correos ni nombres. Mientras la veeduría no la documenta, "Yo también lo
// vi": otro ciudadano se suma con su correo verificado, una vez por obra, y
// recibe los mismos correos que quien informó.
import { computed, ref } from 'vue';
import { requestCitizenCode, supportCitizenReport } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';
import { vReveal } from '@/lib/reveal.js';

const props = defineProps({
    worksiteId: { type: String, required: true },
    citizens: { type: Object, required: true }, // { count, status, open }
});

const count = ref(props.citizens.count);
const step = ref('closed'); // closed → email → code → joined
const email = ref('');
const authorized = ref(false);
const code = ref('');
const busy = ref(false);
const notice = ref(null);
const refused = ref(null);

const sentence = computed(() => (count.value === 1 ? '1 ciudadano informó sobre esta obra' : `${count.value} ciudadanos informaron sobre esta obra`));

async function askForCode() {
    refused.value = !/^\S+@\S+\.\S+$/.test(email.value.trim())
        ? 'Escriba un correo válido: allí le llega el código.'
        : !authorized.value
          ? 'Para sumarse, autorice el tratamiento de sus datos personales.'
          : null;
    if (refused.value) {
        return;
    }
    busy.value = true;
    try {
        notice.value = (await requestCitizenCode({ email: email.value.trim(), worksite_id: props.worksiteId, data_authorization: true })).message;
        step.value = 'code';
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        busy.value = false;
    }
}

async function join() {
    busy.value = true;
    refused.value = null;
    try {
        notice.value = (await supportCitizenReport({ email: email.value.trim(), code: code.value.trim(), worksite_id: props.worksiteId })).message;
        count.value += 1;
        step.value = 'joined';
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        busy.value = false;
    }
}

const FIELD = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-base';
const BUTTON = 'min-h-12 rounded-xl bg-brand-700 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-brand-800 disabled:opacity-40';
</script>

<template>
    <section aria-labelledby="citizens-title" class="mt-6 flex flex-col gap-3 rounded-2xl bg-white p-5 text-base shadow-soft ring-1 ring-slate-900/5">
        <h2 id="citizens-title" class="sr-only">Lo que informaron los ciudadanos</h2>
        <p data-test="citizens-count" class="text-lg font-semibold text-brand-900">{{ sentence }} · {{ citizens.status }}</p>

        <p v-if="notice" v-reveal="notice" role="status" class="rounded-lg bg-emerald-50 p-3 font-semibold text-emerald-800">{{ notice }}</p>
        <p v-if="refused" v-reveal="refused" role="alert" class="rounded-lg bg-red-50 p-3 text-red-800">{{ refused }}</p>

        <template v-if="citizens.open && step !== 'joined'">
            <template v-if="step === 'closed'">
                <p>¿Usted también lo vio? Súmese: la veeduría sabrá que no es una sola persona, y a usted le avisamos cuando actúe.</p>
                <button type="button" :class="BUTTON" class="self-start" @click="step = 'email'">Yo también lo vi</button>
            </template>
            <template v-else-if="step === 'email'">
                <label for="support-email" class="font-semibold">Su correo</label>
                <input id="support-email" v-model="email" type="email" autocomplete="email" :class="FIELD" />
                <p class="text-sm text-slate-700">Le llega un código para comprobar que es suyo. Nadie ve su correo, ni la veeduría.</p>
                <label for="support-authorization" class="flex min-h-11 items-start gap-3 font-semibold">
                    <input id="support-authorization" v-model="authorized" type="checkbox" class="mt-0.5 size-6 shrink-0" />
                    Autorizo el tratamiento de mis datos personales según la política.
                </label>
                <a href="/privacidad" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center self-start font-semibold underline">Lea la política de tratamiento de datos</a>
                <button type="button" :disabled="busy" :class="BUTTON" class="self-start" @click="askForCode">Enviarme el código para sumarme</button>
            </template>
            <template v-else>
                <label for="support-code" class="font-semibold">El código que le llegó al correo</label>
                <input id="support-code" v-model="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" :class="FIELD" />
                <button type="button" :disabled="busy || !/^\d{6}$/.test(code.trim())" :class="BUTTON" class="self-start" @click="join">Confirmar: yo también lo vi</button>
            </template>
        </template>
    </section>
</template>
