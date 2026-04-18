<template>
    <div class="member-app min-h-screen bg-slate-950 text-slate-100 flex flex-col">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_18%,rgba(59,130,246,0.18),transparent_30%),radial-gradient(circle_at_80%_78%,rgba(168,85,247,0.14),transparent_28%),radial-gradient(circle_at_top,#0f2d63_0%,#071327_46%,#030814_100%)]" />
        <div class="pointer-events-none absolute -left-16 -top-10 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl" />
        <div class="pointer-events-none absolute -bottom-8 -right-10 h-64 w-64 rounded-full bg-purple-500/16 blur-3xl" />

        <div class="relative flex-1 flex flex-col justify-center px-5 py-8 pt-safe">
            <div class="mb-8 text-center">
                <div class="text-xs uppercase tracking-[0.3em] text-blue-400 mb-1">PlayDrive</div>
                <h1 class="splash-title text-3xl text-white">PLAYDRIVE</h1>
            </div>
            <router-view />
        </div>
    </div>
</template>
