<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-3xl font-bold text-white">Dashboard</h2>
            <p class="mt-2 text-slate-400">
                Overzicht van de belangrijkste Frontdesk informatie.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-sm text-slate-400">Vandaag</div>
                <div class="mt-2 text-2xl font-bold text-white">0</div>
                <div class="mt-1 text-sm text-slate-500">Reservaties</div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-sm text-slate-400">Open orders</div>
                <div class="mt-2 text-2xl font-bold text-white">0</div>
                <div class="mt-1 text-sm text-slate-500">Actief aan de balie</div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-sm text-slate-400">Vouchers</div>
                <div class="mt-2 text-2xl font-bold text-white">0</div>
                <div class="mt-1 text-sm text-slate-500">Vandaag verwerkt</div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-sm text-slate-400">Taken</div>
                <div class="mt-2 text-2xl font-bold text-white">0</div>
                <div class="mt-1 text-sm text-slate-500">Openstaand</div>
            </div>
        </div>
    </div>
</template>
