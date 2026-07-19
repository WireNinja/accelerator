<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-16">
        <Head title="Accelerator" />

        <section
            class="flex w-full flex-col gap-6 rounded-3xl border border-zinc-200 bg-white p-10 shadow-sm"
        >
            <p class="text-sm font-semibold tracking-[0.2em] text-zinc-500 uppercase">
                WireNinja Accelerator
            </p>
            <h1 class="text-4xl font-bold tracking-tight">Inertia Vue is ready.</h1>
            <p class="max-w-2xl text-zinc-600">
                The Laravel, Filament, Livewire, Inertia, deployment, and developer-tool foundations
                are installed.
            </p>
            <div class="flex flex-wrap gap-3">
                <Link
                    href="/admin"
                    class="rounded-xl bg-zinc-950 px-5 py-3 font-semibold text-white"
                    >Open Filament</Link
                >
                <a
                    href="/livewire"
                    class="rounded-xl border border-zinc-300 px-5 py-3 font-semibold"
                    >Open Livewire</a
                >
            </div>
        </section>
    </main>
</template>
