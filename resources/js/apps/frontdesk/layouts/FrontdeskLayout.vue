<template>
    <div class="flex h-screen overflow-hidden bg-slate-950 text-slate-100">
        <div class="flex min-h-0 flex-1 flex-col">
            <FrontdeskHeader />

            <main class="min-h-0 flex-1 overflow-hidden p-4">
                <router-view />
            </main>

            <FrontdeskStatusBar />
        </div>

        <NewMemberToast />
    </div>
</template>

<script setup>
import FrontdeskHeader from '../components/FrontdeskHeader.vue'
import FrontdeskStatusBar from '../components/FrontdeskStatusBar.vue'
import NewMemberToast from '../components/NewMemberToast.vue'
</script>
