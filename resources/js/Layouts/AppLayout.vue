<script setup>
defineProps({
    title: {
        type: String,
        default: 'GovTrace',
    },
});
</script>

<template>
    <!-- Mobile-first shell: full-height column on phones, centered and wider only from md: up. -->
    <div class="flex min-h-dvh flex-col bg-slate-50 text-slate-900">
        <header class="sticky top-0 z-10 bg-slate-900 px-4 pt-[env(safe-area-inset-top)] text-white">
            <div class="mx-auto flex h-14 w-full items-center md:max-w-3xl lg:max-w-5xl">
                <h1 class="text-lg font-semibold">{{ title }}</h1>
            </div>
        </header>

        <main class="mx-auto w-full flex-1 p-4 md:max-w-3xl lg:max-w-5xl">
            <slot />
        </main>

        <!-- Optional bottom navigation: thumb-reachable on phones. -->
        <nav
            v-if="$slots.nav"
            class="sticky bottom-0 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]"
        >
            <div class="mx-auto flex w-full md:max-w-3xl lg:max-w-5xl">
                <slot name="nav" />
            </div>
        </nav>
    </div>
</template>
