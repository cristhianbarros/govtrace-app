<script setup>
// US-043-MON: el registro de auditoría de la organización, para su Administrador.
import AuditLog from '@/Components/AuditLog.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { fetchAuditLog } from '@/services/api.js';
</script>

<template>
    <AdminLayout title="Registro de auditoría">
        <AuditLog :fetch-page="fetchAuditLog" />
    </AdminLayout>
</template>
