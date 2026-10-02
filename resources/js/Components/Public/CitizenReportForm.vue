<script setup>
// US-059-LEG (it. 44f): informar a la veeduría lo que se vio en una obra —
// su deber es recibirlo (Ley 850 de 2003, art. 18) —, sin cuenta y en dos
// pasos: el correo, que se verifica con un código, y después el código, el
// mensaje y una foto opcional, que el navegador limpia como la del veedor.
// El informe no se sella ni se publica.
import { computed, ref } from 'vue';
import { prepareEvidence } from '@/lib/evidence/prepare.js';
import { requestCitizenCode, sendCitizenReport } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const props = defineProps({
    worksiteId: { type: String, required: true }, // it. 46c: su identificador público
});

const MIN_MESSAGE = 20;
const MAX_MESSAGE = 2000;
const step = ref('email'); // email → code → sent
const email = ref('');
const authorized = ref(false);
const code = ref('');
const message = ref('');
const photo = ref(null);
const busy = ref(false);
const notice = ref(null);
const refused = ref(null);
const number = ref(null);
const remaining = computed(() => MAX_MESSAGE - message.value.length);

async function askForCode() {
    refused.value = !/^\S+@\S+\.\S+$/.test(email.value.trim())
        ? 'Escriba un correo válido: allí le llega el código.'
        : !authorized.value
          ? 'Para informar a la veeduría, autorice el tratamiento de sus datos personales.'
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

async function choosePhoto(event) {
    const [file] = event.target.files ?? [];
    photo.value = file ? (await prepareEvidence(file)).file : null;
}

async function send() {
    refused.value = !/^\d{6}$/.test(code.value.trim())
        ? 'Escriba el código de 6 dígitos que le llegó al correo.'
        : message.value.trim().length < MIN_MESSAGE
          ? `Cuéntele a la veeduría qué vio: al menos ${MIN_MESSAGE} caracteres.`
          : null;
    if (refused.value) {
        return;
    }
    const form = new FormData();
    form.append('email', email.value.trim());
    form.append('code', code.value.trim());
    form.append('worksite_id', String(props.worksiteId));
    form.append('message', message.value.trim());
    if (photo.value) {
        form.append('photo', photo.value);
    }
    busy.value = true;
    try {
        const answer = await sendCitizenReport(form);
        notice.value = answer.message;
        number.value = answer.number;
        step.value = 'sent';
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        busy.value = false;
    }
}

function startOver() {
    step.value = 'email';
    code.value = '';
    refused.value = null;
    notice.value = null;
}

const FIELD = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-3 text-base';
const BUTTON = 'min-h-12 rounded-xl bg-brand-700 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-brand-800 disabled:opacity-40';
</script>

<template>
    <!-- It. 40e: una invitación cálida, en terracota, distinta de las tarjetas de la obra. -->
    <section id="informar" aria-labelledby="informar-title" class="mt-6 flex flex-col gap-3 rounded-2xl border-l-8 border-warm-500 bg-warm-50 p-5 text-base shadow-soft">
        <h2 id="informar-title" class="text-lg font-semibold">Informar a esta veeduría</h2>
        <p>¿Vio algo en esta obra? Cuénteselo a la veeduría: la ley la obliga a recibir lo que le informen los ciudadanos (Ley 850 de 2003, art. 18). No hace falta tener cuenta.</p>

        <p v-if="notice" role="status" class="rounded-lg bg-emerald-50 p-3 font-semibold text-emerald-800">{{ notice }}</p>
        <p v-if="refused" role="alert" class="rounded-lg bg-red-50 p-3 text-red-800">{{ refused }}</p>

        <template v-if="step === 'email'">
            <label for="citizen-email" class="font-semibold">Su correo</label>
            <input id="citizen-email" v-model="email" type="email" autocomplete="email" :class="FIELD" />
            <p class="text-sm text-slate-700">Le llega un código para comprobar que es suyo. La veeduría no ve su correo: le responde desde GovTrace.</p>
            <label for="citizen-authorization" class="flex min-h-11 items-start gap-3 font-semibold">
                <input id="citizen-authorization" v-model="authorized" type="checkbox" class="mt-0.5 size-6 shrink-0" />
                Autorizo el tratamiento de mis datos personales según la política.
            </label>
            <a data-test="data-policy" href="/privacidad" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center self-start font-semibold underline">Lea la política de tratamiento de datos</a>
            <button type="button" :disabled="busy" :class="BUTTON" @click="askForCode">Enviarme el código</button>
        </template>

        <template v-else-if="step === 'code'">
            <label for="citizen-code" class="font-semibold">El código que le llegó al correo</label>
            <input id="citizen-code" v-model="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" :class="FIELD" />
            <label for="citizen-message" class="font-semibold">¿Qué vio en la obra?</label>
            <textarea id="citizen-message" v-model="message" rows="4" :maxlength="MAX_MESSAGE" :class="FIELD"></textarea>
            <p class="text-sm text-slate-700">{{ remaining }} caracteres disponibles.</p>
            <label for="citizen-photo" class="font-semibold">Una foto (opcional)</label>
            <input id="citizen-photo" type="file" accept="image/*" class="text-base" @change="choosePhoto" />
            <p class="text-sm text-slate-700">Se le quitan la ubicación y los datos del teléfono antes de enviarla.</p>
            <button type="button" :disabled="busy" :class="BUTTON" @click="send">Enviar a la veeduría</button>
            <button type="button" class="inline-flex min-h-11 items-center self-start font-semibold underline" @click="startOver">Pedir otro código</button>
        </template>

        <p v-else-if="number" class="font-semibold">Su informe es el n.º {{ number }}. Le llegó también a su correo.</p>
    </section>
</template>
