<script setup>
// US-001 y US-011: el listado de organizaciones del Super Administrador,
// desde donde da de alta una nueva y corrige el NIT de una existente (a
// solicitud formal de la organización). US-003a: suspenderla o reactivarla.
// US-003b: darla de baja, con doble confirmación; es definitivo.
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import LoadState from '@/Components/LoadState.vue';
import RowAction from '@/Components/RowAction.vue';
import DecommissionAction from '@/Components/SuperAdmin/DecommissionAction.vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { useLoader } from '@/composables/useLoader.js';
import {
    assignAdministrator,
    deactivateAdministrator,
    fetchOrganizationDetail,
    fetchOrganizations,
    reactivateAdministrator,
    reactivateOrganization,
    resendAdministratorInvitation,
    revokeAdministratorInvitation,
    suspendOrganization,
    updateOrganizationLegalData,
} from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';
import { formatDay } from '@/lib/format.js';

const { data: organizations, loading, error, load } = useLoader(fetchOrganizations);

const editing = ref(null); // la organización cuyos datos legales se corrigen
// It. 44d (R-LEG-06): su NIT, su inscripción o los dos.
const legal = ref({ name: '', nit: '', registrationNumber: '', registrationAuthority: '' }); // it. 43f (V8): y su razón social
const saving = ref(false);
const saved = ref(null);
const refused = ref(null);

async function edit(organization) {
    saved.value = null;
    refused.value = null;
    editing.value = await fetchOrganizationDetail(organization.id);
    legal.value = {
        name: editing.value.name ?? '',
        nit: editing.value.nit ?? '',
        registrationNumber: editing.value.registration_number ?? '',
        registrationAuthority: editing.value.registration_authority ?? '',
    };
}

async function save() {
    saving.value = true;
    refused.value = null;
    try {
        saved.value = (
            await updateOrganizationLegalData(editing.value.id, {
                name: legal.value.name.trim(),
                nit: legal.value.nit.trim() || null,
                registration_number: legal.value.registrationNumber.trim() || null,
                registration_authority: legal.value.registrationAuthority.trim() || null,
            })
        ).message;
        editing.value = null;
        await load();
    } catch (failure) {
        refused.value = errorMessage(failure);
    } finally {
        saving.value = false;
    }
}

async function changed(message) {
    saved.value = message;
    await load();
}

// It. 43a (V2): asignar el Administrador a una organización que no tiene; it. 43j (V3): u otro más.
const assigning = ref(null); // el id de la organización
const newAdministrator = ref({ name: '', email: '' });
const assignError = ref(null);

function startAssigning(organization) {
    assigning.value = organization.id;
    newAdministrator.value = { name: '', email: '' };
    assignError.value = null;
}

async function assign(organization) {
    assignError.value = null;
    try {
        await changed((await assignAdministrator(organization.id, { ...newAdministrator.value })).message);
        assigning.value = null;
    } catch (failure) {
        assignError.value = errorMessage(failure);
    }
}

const administratorStyle = { active: 'bg-emerald-100 text-emerald-800', pending: 'bg-amber-100 text-amber-900', expired: 'bg-red-100 text-red-800', inactive: 'bg-slate-200 text-slate-700' };

const statusStyle = { Activa: 'bg-emerald-100 text-emerald-800', Suspendida: 'bg-amber-100 text-amber-900', 'Dada de baja': 'bg-slate-200 text-slate-700' };

onMounted(load);
</script>

<template>
    <SuperAdminLayout title="Organizaciones">
        <div class="flex flex-col gap-4">
            <Link href="/admin/organizations/new" class="min-h-11 self-start rounded-xl bg-brand-700 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                + Nueva organización
            </Link>

            <p v-if="saved" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ saved }}</p>

            <LoadState
                :loading="loading"
                :error="error"
                :empty="organizations?.length === 0"
                loading-text="Cargando organizaciones…"
                empty-text="Aún no hay organizaciones registradas."
                illustration="team"
                @retry="load"
            >
                <ul class="flex flex-col gap-2">
                    <li v-for="organization in organizations" :key="organization.id" data-test="organization-row" class="rounded-2xl bg-white p-3 text-sm shadow-soft ring-1 ring-slate-900/5">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="font-semibold">{{ organization.name }}</p>
                                <p class="text-slate-600">{{ organization.identification }} · {{ organization.subdomain }}</p>
                                <!-- It. 46b: la resolución o el certificado que adjuntó al pedir el alta. -->
                                <a v-if="organization.has_registration_document" :href="`/admin/organizations/${organization.id}/registration-document`" class="inline-flex min-h-11 items-center font-semibold text-brand-800 underline">Documento de inscripción (PDF)</a>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="statusStyle[organization.status] ?? 'bg-slate-100 text-slate-700'">
                                    {{ organization.status }}
                                </span>
                                <button type="button" class="min-h-11 rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 px-3 text-sm font-semibold" @click="edit(organization)">Editar datos legales</button>
                            </div>
                        </div>

                        <!-- It. 43g (V7): si autorizó al Super Administrador a reportar en su nombre (US-042-SEC), hasta cuándo. -->
                        <p v-if="organization.authorized_until" data-test="authorization" class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg bg-brand-50 p-3 text-sm text-brand-900">
                            <span>Lo autorizó a reportar en su nombre hasta el {{ formatDay(organization.authorized_until) }}.</span>
                            <Link :href="`/admin/organizations/${organization.id}/report`" class="inline-flex min-h-11 items-center font-semibold underline">Reportar en su nombre</Link>
                        </p>

                        <!-- It. 43a (V2): quién la administra y el estado de su invitación. It. 43j (V3): pueden ser varios. -->
                        <section v-if="organization.status !== 'Dada de baja'" data-test="administrators" class="mt-3 rounded-lg bg-slate-50 p-3" :aria-label="`Administradores de ${organization.name}`">
                            <p class="text-sm font-semibold text-slate-700">Administradores</p>
                            <ul v-if="organization.administrators?.length" class="mt-1 flex flex-col gap-2">
                                <li v-for="administrator in organization.administrators" :key="administrator.id" class="flex flex-col gap-2">
                                    <p class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold">{{ administrator.name }}</span>
                                        <span class="text-slate-700">{{ administrator.email }}</span>
                                        <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="administratorStyle[administrator.status]">{{ administrator.label }}</span>
                                    </p>
                                    <div v-if="administrator.status === 'pending' || administrator.status === 'expired'" class="flex flex-wrap gap-2">
                                        <RowAction label="Reenviar invitación" :run="() => resendAdministratorInvitation(organization.id, administrator.id)" @done="changed" />
                                        <RowAction
                                            label="Revocar invitación"
                                            confirm-label="Confirmar revocación"
                                            warning="El enlace enviado dejará de servir. Después podrá asignar otro Administrador."
                                            :run="() => revokeAdministratorInvitation(organization.id, administrator.id)"
                                            @done="changed"
                                        />
                                    </div>
                                    <RowAction
                                        v-else-if="administrator.status === 'active'"
                                        label="Desactivar"
                                        confirm-label="Confirmar desactivación"
                                        warning="Ya no podrá entrar al panel de la organización. Lo que hizo queda en el registro de auditoría."
                                        :run="() => deactivateAdministrator(organization.id, administrator.id)"
                                        @done="changed"
                                    />
                                    <RowAction v-else-if="administrator.status === 'inactive'" label="Reactivar" :run="() => reactivateAdministrator(organization.id, administrator.id)" @done="changed" />
                                </li>
                            </ul>
                            <p v-else class="mt-1 text-sm text-slate-700">Sin Administrador: nadie puede gestionar sus veedores.</p>
                            <form v-if="assigning === organization.id" class="mt-2 flex flex-col gap-2" novalidate @submit.prevent="assign(organization)">
                                <label :for="`administrator-name-${organization.id}`" class="text-sm font-semibold text-slate-700">Nombre</label>
                                <input :id="`administrator-name-${organization.id}`" v-model="newAdministrator.name" name="administrator-name" type="text" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-base" />
                                <label :for="`administrator-email-${organization.id}`" class="text-sm font-semibold text-slate-700">Correo electrónico</label>
                                <input :id="`administrator-email-${organization.id}`" v-model="newAdministrator.email" name="administrator-email" type="email" class="min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-base" />
                                <p v-if="assignError" role="alert" class="text-sm text-red-700">{{ assignError }}</p>
                                <div class="flex gap-2">
                                    <button type="submit" class="min-h-11 rounded-xl bg-brand-700 px-3 text-sm font-semibold text-white hover:bg-brand-800">Enviar invitación</button>
                                    <button type="button" class="min-h-11 rounded-lg border bg-white px-3 text-sm font-semibold" @click="assigning = null">Cancelar</button>
                                </div>
                            </form>
                            <button v-else type="button" class="mt-2 min-h-11 rounded-lg border bg-white px-3 text-sm font-semibold" @click="startAssigning(organization)">
                                {{ organization.administrators?.length ? 'Agregar otro Administrador' : 'Asignar Administrador' }}
                            </button>
                        </section>

                        <!-- Dada de baja es definitivo: ni suspender, ni reactivar, ni otra baja. -->
                        <div v-if="organization.status !== 'Dada de baja'" class="mt-2 flex flex-col gap-2">
                            <RowAction
                                v-if="organization.status === 'Suspendida'"
                                label="Reactivar"
                                :run="() => reactivateOrganization(organization.id)"
                                @done="changed"
                            />
                            <RowAction
                                v-else
                                label="Suspender"
                                confirm-label="Confirmar suspensión"
                                warning="Sus usuarios no podrán entrar ni enviar reportes; su mapa público seguirá disponible, con un aviso."
                                :run="() => suspendOrganization(organization.id)"
                                @done="changed"
                            />
                            <DecommissionAction :organization-id="organization.id" @done="changed" />
                        </div>

                        <form v-if="editing?.id === organization.id" class="mt-3 flex flex-col gap-2" novalidate @submit.prevent="save">
                            <label for="legal-name" class="text-xs font-semibold text-slate-700">Razón social</label>
                            <input id="legal-name" v-model="legal.name" type="text" maxlength="150" class="rounded-lg border border-slate-300 px-3 py-2 text-base" />
                            <p class="text-sm text-slate-700">Su NIT, su inscripción o los dos.</p>
                            <label for="nit" class="text-xs font-semibold text-slate-700">NIT</label>
                            <input id="nit" v-model="legal.nit" type="text" class="rounded-lg border border-slate-300 px-3 py-2 text-base" />
                            <label for="registration-number" class="text-xs font-semibold text-slate-700">Resolución o acta de inscripción</label>
                            <input id="registration-number" v-model="legal.registrationNumber" type="text" placeholder="Resolución 012 de 2026" class="rounded-lg border border-slate-300 px-3 py-2 text-base" />
                            <label for="registration-authority" class="text-xs font-semibold text-slate-700">Entidad que la registró</label>
                            <input id="registration-authority" v-model="legal.registrationAuthority" type="text" placeholder="Personería de Santa Marta" class="rounded-lg border border-slate-300 px-3 py-2 text-base" />
                            <p v-if="refused" role="alert" class="text-sm text-red-700">{{ refused }}</p>
                            <div class="flex gap-2">
                                <button type="submit" :disabled="saving" class="min-h-11 rounded-xl bg-brand-700 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40 hover:bg-brand-800">
                                    {{ saving ? 'Guardando…' : 'Guardar datos legales' }}
                                </button>
                                <button type="button" class="min-h-11 rounded-xl border border-brand-200 text-brand-800 hover:bg-brand-50 px-3 py-2 text-sm font-semibold" @click="editing = null">Cancelar</button>
                            </div>
                        </form>
                    </li>
                </ul>
            </LoadState>
        </div>
    </SuperAdminLayout>
</template>
