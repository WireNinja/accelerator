<?php

use Livewire\Component;

new class extends Component {};
?>

<main class="mx-auto flex min-h-screen max-w-3xl items-center px-6 py-16">
    <section class="flex w-full flex-col gap-6 rounded-3xl border border-zinc-200 bg-white p-10 shadow-sm">
        <p class="text-sm font-semibold tracking-[0.2em] text-zinc-500 uppercase">WireNinja Accelerator</p>
        <h1 class="text-4xl font-bold tracking-tight">Livewire is ready.</h1>
        <p class="max-w-2xl text-zinc-600">
            This is a Livewire v4 single-file page. Inertia Vue remains available beside it.
        </p>
        <div class="flex flex-wrap gap-3">
            <a href="/admin" class="rounded-xl bg-zinc-950 px-5 py-3 font-semibold text-white">Open Filament</a>
            <a href="/inertia" class="rounded-xl border border-zinc-300 px-5 py-3 font-semibold">Open Inertia</a>
        </div>
    </section>
</main>
