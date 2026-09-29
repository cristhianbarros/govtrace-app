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
import { fetchOrganizationDetail, fetchOrganizations, reactivateOrganization, suspendOrganization, updateOrganizationNit } from '@/services/api.js';
import { errorMessage } from '@/services/errors.js';

const { data: organizations, loading, error, load } = useLoader(fetchOrganizations);

const editing = ref(null); // {id, name, nit} de la organización que se corrige
const nit = ref('');
const saving = ref(false);
const saved = ref(null);
const refused = ref(null);

async function edit(organization) {
    saved.value = null;
    refused.value = null;
    editing.value = await fetchOrganizationDetail(organization.id);
    nit.value = editing.value.nit;
}

async function save() {
    saving.value = true;
    refused.value = null;
    try {
        saved.value = (await updateOrganizationNit(editing.value.id, nit.value)).message;
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

const statusStyle = { Activa: 'bg-emerald-100 text-emerald-800', Suspendida: 'bg-amber-100 text-amber-900', 'Dada de baja': 'bg-slate-200 text-slate-700' };

onMounted(load);
</script>

<template>
    <SuperAdminLayout title="Organizaciones">
        <div class="flex flex-col gap-4">
            <Link href="/admin/organizations/new" class="self-start rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">
                + Nueva organización
            </Link>

            <p v-if="saved" role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">{{ saved }}</p>

            <LoadState
                :loading="loading"
                :error="error"
                :empty="organizations?.length === 0"
                loading-text="Cargando organizaciones…"
                empty-text="Aún no hay organizaciones registradas."
                @retry="load"
            >
                <ul class="flex flex-col gap-2">
                    <li v-for="organization in organizations" :key="organization.id" data-test="organization-row" class="rounded-lg bg-white p-3 text-sm">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="font-semibold">{{ organization.name }}</p>
                                <p class="text-slate-600">{{ organization.nit }} · {{ organization.subdomain }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded px-2 py-0.5 text-xs font-semibold" :class="statusStyle[organization.status] ?? 'bg-slate-100 text-slate-700'">
                                    {{ organization.status }}
                                </span>
                                <button type="button" class="rounded-lg border px-2 py-1 text-xs font-semibold" @click="edit(organization)">Editar NIT</button>
                            </div>
                        </div>

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
                            <label for="nit" class="text-xs font-semibold text-slate-700">NIT</label>
                            <input id="nit" v-model="nit" type="text" class="rounded-lg border border-slate-300 px-3 py-2 text-base" />
                            <p v-if="refused" role="alert" class="text-sm text-red-700">{{ refused }}</p>
                            <div class="flex gap-2">
                                <button type="submit" :disabled="saving" class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white disabled:opacity-40">
                                    {{ saving ? 'Guardando…' : 'Guardar NIT' }}
                                </button>
                                <button type="button" class="rounded-lg border px-3 py-2 text-sm font-semibold" @click="editing = null">Cancelar</button>
                            </div>
                        </form>
                    </li>
                </ul>
            </LoadState>
        </div>
    </SuperAdminLayout>
</template>
