<script setup>
// R-AUD-01: el aviso de organización suspendida, en cada página pública.
import { usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <p v-if="page.props.organizationNotice" role="status" class="mb-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">
        {{ page.props.organizationNotice }}
    </p>
</template>
