<script setup>
// US-058-LEG (it. 44e): la política de tratamiento de datos personales (Ley
// 1581 de 2012), con el contenido mínimo del Decreto 1074 de 2015 (art.
// 2.2.2.25.3.1): el responsable, el tratamiento y su finalidad, los derechos,
// quién atiende, el procedimiento y la vigencia. En lenguaje claro.
//
// El texto es un borrador para revisión legal (specs/PLAN.md, it. 44e). Cambiarlo
// es publicar una versión nueva: DataPolicy::VERSION.
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    policy: { type: Object, required: true },
});

const page = usePage();
const PENDING = 'Por completar';
const controller = computed(() => props.policy.controller);
const missingText = computed(() => {
    const missing = props.policy.missing;
    return missing.length > 1 ? `${missing.slice(0, -1).join(', ')} y ${missing.at(-1)}` : missing[0];
});
</script>

<template>
    <Head title="Política de tratamiento de datos" />
    <AppLayout :title="page.props.organization ?? 'GovTrace'" :logo="page.props.organizationLogo">
        <article class="mx-auto flex max-w-2xl flex-col gap-4 text-base leading-relaxed">
            <Link href="/" class="inline-flex min-h-11 items-center self-start text-base font-semibold text-slate-700 underline">← Volver</Link>
            <h1 class="text-xl font-semibold">Política de tratamiento de datos personales</h1>
            <p v-if="policy.missing.length" data-test="draft" role="note" class="rounded-lg border-2 border-amber-500 bg-amber-50 p-3 font-semibold text-amber-900">
                Borrador: faltan {{ missingText }} del responsable. Esta política todavía no rige.
            </p>
            <p class="text-slate-700">Rige desde el {{ policy.effective_date }} (versión {{ policy.version }}). Cumple la Ley 1581 de 2012 y el Decreto 1074 de 2015.</p>

            <section aria-labelledby="controller" class="flex flex-col gap-2">
                <h2 id="controller" class="text-lg font-semibold">Quién responde por sus datos</h2>
                <p>El responsable del tratamiento de sus datos es:</p>
                <dl class="grid grid-cols-1 gap-1 rounded-lg bg-white p-3 md:grid-cols-[auto_1fr] md:gap-x-4">
                    <dt class="font-semibold">Nombre</dt><dd>{{ controller.name ?? PENDING }}</dd>
                    <dt class="font-semibold">Identificación</dt><dd>{{ controller.identification ?? PENDING }}</dd>
                    <dt class="font-semibold">Domicilio y dirección</dt><dd>{{ controller.address ?? PENDING }}</dd>
                    <dt class="font-semibold">Correo</dt><dd>{{ controller.email ?? PENDING }}</dd>
                    <dt class="font-semibold">Teléfono</dt><dd>{{ controller.phone ?? PENDING }}</dd>
                </dl>
                <!-- Decisión del usuario (it. 44f): el operador responde; las veedurías, usuarias autorizadas. -->
                <p>Es el operador central de GovTrace. Las veedurías usan la plataforma como usuarios autorizados.</p>
            </section>

            <section aria-labelledby="purposes" class="flex flex-col gap-2">
                <h2 id="purposes" class="text-lg font-semibold">Qué datos tratamos y para qué</h2>
                <ul class="flex list-disc flex-col gap-2 pl-5">
                    <li><strong>De quien tiene cuenta</strong> (Administradores y veedores): su nombre, su correo y su contraseña, guardada cifrada. Sirven para darle acceso, invitarlo y dejar constancia de lo que hace en el registro de auditoría.</li>
                    <li><strong>De los reportes de un veedor:</strong> las fotos o el PDF, el comentario, la fecha y la hora, y la ubicación exacta de su celular. Son evidencia del control social de las obras públicas (Ley 850 de 2003). En el mapa se publica una ubicación aproximada, a unos 100 metros, nunca la exacta. Tampoco se publica el nombre del veedor: sus reportes llevan un seudónimo.</li>
                    <li><strong>De quien informa a una veeduría</strong> desde el mapa, sin cuenta: su correo, su mensaje y, si la envía, una foto sin metadatos. El correo sirve para comprobar que es suyo, con un código, y para responderle; se guarda cifrado y la veeduría no lo ve. El informe no se publica.</li>
                    <li><strong>De quien visita el sitio:</strong> la dirección IP y el navegador, por seguridad y para frenar abusos. No hay publicidad ni rastreo de terceros. El mapa se dibuja con OpenStreetMap: su navegador le pide los mapas a sus servidores.</li>
                    <li><strong>Las fotos pueden mostrar a otras personas</strong> o placas de vehículos. La veeduría revisa cada evidencia antes de publicarla y puede retirarla.</li>
                    <li><strong>Datos sensibles y de menores de edad:</strong> GovTrace no los pide. Si una foto los muestra, la veeduría la retira (Ley 1581 de 2012, artículos 5 a 7).</li>
                </ul>
                <p v-if="policy.hosting">Los datos se guardan en servidores de {{ policy.hosting }}.</p>
            </section>

            <section aria-labelledby="public-network" class="flex flex-col gap-2">
                <h2 id="public-network" class="text-lg font-semibold">Qué queda en la red pública</h2>
                <p>Para que nadie pueda cambiar una evidencia, GovTrace registra en la red pública Stellar su huella (un hash SHA-256). La huella no contiene datos personales ni permite reconstruir el archivo, pero no se puede borrar de la red.</p>
            </section>

            <section aria-labelledby="rights" class="flex flex-col gap-2">
                <h2 id="rights" class="text-lg font-semibold">Sus derechos</h2>
                <p>Según el artículo 8 de la Ley 1581 de 2012, usted puede:</p>
                <ul class="flex list-disc flex-col gap-1 pl-5">
                    <li>Conocer, actualizar y corregir sus datos.</li>
                    <li>Pedir la prueba de que autorizó su tratamiento.</li>
                    <li>Saber para qué se han usado.</li>
                    <li>Revocar la autorización o pedir que se borren, cuando no haya un deber legal de conservarlos.</li>
                    <li>Presentar quejas ante la Superintendencia de Industria y Comercio.</li>
                    <li>Consultarlos gratis.</li>
                </ul>
            </section>

            <section aria-labelledby="procedure" class="flex flex-col gap-2">
                <h2 id="procedure" class="text-lg font-semibold">Cómo ejercerlos</h2>
                <p>Escriba a {{ controller.email ?? 'el correo del responsable (por completar)' }} con su nombre, su documento de identidad y lo que pide.</p>
                <ul class="flex list-disc flex-col gap-1 pl-5">
                    <li><strong>Si pregunta por sus datos</strong> (una consulta), le respondemos en máximo 10 días hábiles (Ley 1581 de 2012, artículo 14).</li>
                    <li><strong>Si pide corregirlos, actualizarlos, borrarlos o revocar su autorización</strong> (un reclamo), le respondemos en máximo 15 días hábiles (artículo 15).</li>
                    <li>Si la respuesta no le satisface, puede quejarse ante la Superintendencia de Industria y Comercio (artículo 16).</li>
                </ul>
                <p>Los reportes son evidencia del control social de una obra pública. Si pide borrar uno, se estudia frente al deber de conservarlo, y la veeduría puede retirarlo del mapa.</p>
            </section>

            <section aria-labelledby="validity" class="flex flex-col gap-2">
                <h2 id="validity" class="text-lg font-semibold">Desde cuándo rige y cuánto tiempo se guardan</h2>
                <p>Esta política rige desde el {{ policy.effective_date }}. Los datos se guardan mientras exista la cuenta y, después, durante estos plazos:</p>
                <ul class="flex list-disc flex-col gap-1 pl-5">
                    <li>El registro de auditoría, para siempre.</li>
                    <li>La relación entre un seudónimo y su veedor, 5 años desde su último reporte.</li>
                    <li>Los archivos de una organización dada de baja, 5 años.</li>
                    <li>Los informes de los ciudadanos, con su correo cifrado, mientras la veeduría esté en GovTrace.</li>
                </ul>
            </section>
        </article>
    </AppLayout>
</template>
