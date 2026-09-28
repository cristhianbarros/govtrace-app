<script setup>
// US-043-MON: todo el registro de auditoría, para el Super Administrador.
import AuditLog from '@/Components/AuditLog.vue';
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue';
import { fetchGlobalAuditLog } from '@/services/api.js';
</script>

<template>
    <SuperAdminLayout title="Registro de auditoría">
        <AuditLog :fetch-page="fetchGlobalAuditLog" show-organization />
    </SuperAdminLayout>
</template>
