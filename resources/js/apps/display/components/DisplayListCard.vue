<template>
    <section class="flex h-full min-h-0 flex-1 flex-col overflow-hidden rounded-[2rem] border border-white/10 bg-slate-900/65 p-4 shadow-2xl shadow-slate-950/40 backdrop-blur-xl">
        <div class="flex items-center justify-between gap-3 rounded-[1.5rem] border border-white/8 bg-black/20 px-4 py-3">
            <div>
                <div class="text-[11px] uppercase tracking-[0.24em] text-cyan-300/80">Bestellingen</div>
                <div class="mt-1 flex items-center gap-2 text-lg font-semibold text-white">
                    <span>Live overzicht</span>
                    <span class="h-2 w-2 rounded-full bg-cyan-300 live-dot"></span>
                </div>
            </div>
            <Transition name="badge-pop" mode="out-in">
                <div
                    :key="itemCount"
                    class="rounded-full border border-blue-400/20 bg-blue-500/12 px-3 py-1.5 text-sm font-semibold text-blue-100 shadow-lg shadow-blue-500/10"
                >
                    {{ itemCount }} items
                </div>
            </Transition>
        </div>

        <div class="mt-3 flex min-h-0 flex-1 flex-col overflow-hidden rounded-[1.5rem] border border-white/8 bg-black/10">
            <div class="flex-1 overflow-y-auto px-3 py-3 pr-2 custom-scrollbar">
                <TransitionGroup tag="div" name="display-item" class="space-y-3">
                    <div
                        v-for="item in items"
                        :key="item.name"
                        :class="[
                            'flex items-center gap-4 rounded-[1.5rem] border border-white/8 bg-gradient-to-r from-white/7 to-white/4 px-4 py-4 transition-all duration-500',
                            recentItems[item.name] ? 'ring-1 ring-cyan-300/35 shadow-lg shadow-cyan-500/10' : '',
                            newestItemName === item.name ? 'relative overflow-hidden border-cyan-300/20 bg-gradient-to-r from-cyan-400/10 via-white/7 to-white/4' : '',
                        ]"
                    >
                        <div class="flex h-16 w-16 flex-shrink-0 items-center justify-center overflow-hidden rounded-[1.25rem] border border-white/10 bg-slate-950/70 shadow-inner shadow-slate-950/30">
                            <img
                                v-if="getImageUrl(item) && !brokenImages[item.name]"
                                :src="getImageUrl(item)"
                                :alt="item.name"
                                class="h-full w-full object-cover"
                                @error="markBroken(item.name)"
                            >
                            <div v-else class="text-2xl">{{ fallbackEmoji(item.name) }}</div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <div class="truncate text-xl font-bold tracking-tight text-white">{{ item.name }}</div>
                                <Transition name="new-pill" mode="out-in">
                                    <div
                                        v-if="newestItemName === item.name"
                                        class="inline-flex items-center gap-1 rounded-full border border-cyan-300/25 bg-cyan-400/12 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.22em] text-cyan-100"
                                    >
                                        Nieuw
                                    </div>
                                </Transition>
                            </div>
                            <div class="mt-1 flex items-center gap-2 text-sm text-slate-300">
                                <span>€ {{ formatMoney(item.unit_price) }}</span>
                                <span class="h-1 w-1 rounded-full bg-slate-500"></span>
                                <span>{{ item.quantity }} ×</span>
                            </div>
                        </div>

                        <Transition name="qty-pop" mode="out-in">
                            <div
                                :key="`${item.name}-${item.quantity}`"
                                class="flex h-12 min-w-[56px] items-center justify-center rounded-full border border-blue-400/20 bg-blue-500/15 px-3 text-lg font-black text-blue-100 shadow-lg shadow-blue-500/10"
                            >
                                {{ item.quantity }}×
                            </div>
                        </Transition>
                    </div>
                </TransitionGroup>

                <div v-if="!items.length" class="rounded-[1.5rem] border border-dashed border-white/10 bg-black/10 px-4 py-10 text-center text-slate-400">
                    Nog geen artikelen toegevoegd.
                </div>
            </div>
        </div>

        <Transition name="total-pop" mode="out-in">
            <div
                :key="totalKey"
                :class="[
                    'mt-3 rounded-[1.75rem] border border-blue-400/20 bg-gradient-to-r from-blue-500/18 via-cyan-400/12 to-blue-500/18 px-6 py-5 shadow-2xl shadow-blue-500/10 transition-all duration-500',
                    totalHighlighted ? 'scale-[1.02] border-cyan-300/35 shadow-cyan-400/20' : '',
                ]"
            >
                <div class="text-[11px] uppercase tracking-[0.26em] text-blue-100/70">Totaal</div>
                <div class="mt-2 text-[3.5rem] font-black leading-none tracking-tight text-white">€ {{ formatMoney(total) }}</div>
            </div>
        </Transition>
    </section>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    itemCount: {
        type: Number,
        default: 0,
    },
    total: {
        type: Number,
        default: 0,
    },
})

const brokenImages = reactive({})
const recentItems = reactive({})
const totalHighlighted = ref(false)
const previousQuantities = ref(new Map())
const newestItemName = ref('')
const totalKey = computed(() => Number(props.total ?? 0).toFixed(2))
let newestItemTimeout = null
let audioContext = null

watch(
    () => props.items,
    (nextItems) => {
        const nextMap = new Map()
        let shouldPlayItemSound = false

        for (const item of nextItems) {
            const quantity = Number(item?.quantity ?? 0)
            nextMap.set(item.name, quantity)

            const previousQuantity = previousQuantities.value.get(item.name)
            if (previousQuantity == null || quantity > previousQuantity) {
                recentItems[item.name] = true
                newestItemName.value = item.name
                shouldPlayItemSound = true

                window.setTimeout(() => {
                    delete recentItems[item.name]
                }, 1400)
            }
        }

        previousQuantities.value = nextMap

        if (newestItemTimeout) {
            clearTimeout(newestItemTimeout)
        }

        newestItemTimeout = window.setTimeout(() => {
            newestItemName.value = ''
        }, 1800)

        if (shouldPlayItemSound) {
            playSound(880, 0.028, 'triangle')
            window.setTimeout(() => playSound(1040, 0.022, 'triangle'), 55)
        }
    },
    { deep: true, immediate: true },
)

watch(
    () => props.total,
    (nextTotal, previousTotal) => {
        if (previousTotal == null || Number(nextTotal) === Number(previousTotal)) {
            return
        }

        totalHighlighted.value = true
        playSound(640, 0.035, 'sine')
        window.setTimeout(() => {
            totalHighlighted.value = false
        }, 900)
    },
)

function markBroken(name) {
    brokenImages[name] = true
}

function getImageUrl(item) {
    return item?.image_url || item?.image || item?.product_image_url || item?.thumbnail_url || ''
}

function fallbackEmoji(name) {
    const value = String(name || '').toLowerCase()

    if (value.includes('cola') || value.includes('fanta') || value.includes('sprite') || value.includes('drink')) {
        return '🥤'
    }

    if (value.includes('bier') || value.includes('wine')) {
        return '🍺'
    }

    if (value.includes('chips') || value.includes('nacho') || value.includes('snack')) {
        return '🍿'
    }

    if (value.includes('burger') || value.includes('pizza') || value.includes('croque')) {
        return '🍔'
    }

    if (value.includes('koffie') || value.includes('coffee')) {
        return '☕'
    }

    if (value.includes('ice') || value.includes('ijs')) {
        return '🍦'
    }

    return '🎮'
}

function formatMoney(value) {
    return Number(value ?? 0).toFixed(2).replace('.', ',')
}

async function playSound(frequency, duration = 0.03, type = 'sine') {
    if (typeof window === 'undefined') {
        return
    }

    const AudioContextClass = window.AudioContext || window.webkitAudioContext
    if (!AudioContextClass) {
        return
    }

    try {
        if (!audioContext) {
            audioContext = new AudioContextClass()
        }

        if (audioContext.state === 'suspended') {
            await audioContext.resume()
        }

        const oscillator = audioContext.createOscillator()
        const gain = audioContext.createGain()

        oscillator.type = type
        oscillator.frequency.value = frequency
        gain.gain.value = 0.0001

        oscillator.connect(gain)
        gain.connect(audioContext.destination)

        const now = audioContext.currentTime
        gain.gain.exponentialRampToValueAtTime(0.022, now + 0.01)
        gain.gain.exponentialRampToValueAtTime(0.0001, now + duration)

        oscillator.start(now)
        oscillator.stop(now + duration + 0.02)
    } catch {
        // best effort only; browsers can block autoplay audio contexts
    }
}
</script>

<style scoped>
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(96, 165, 250, 0.45) rgba(15, 23, 42, 0.4);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 10px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.35);
    border-radius: 9999px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(96, 165, 250, 0.45);
    border-radius: 9999px;
    border: 2px solid rgba(15, 23, 42, 0.35);
}

.live-dot {
    animation: live-pulse 1.8s ease-in-out infinite;
}

@keyframes live-pulse {
    0% {
        transform: scale(0.95);
        opacity: 0.85;
    }
    50% {
        transform: scale(1.15);
        opacity: 1;
    }
    100% {
        transform: scale(0.95);
        opacity: 0.85;
    }
}

.display-item-enter-active,
.display-item-leave-active {
    transition: all 280ms ease;
}

.display-item-enter-from,
.display-item-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.98);
}

.qty-pop-enter-active,
.qty-pop-leave-active,
.badge-pop-enter-active,
.badge-pop-leave-active,
.total-pop-enter-active,
.total-pop-leave-active,
.new-pill-enter-active,
.new-pill-leave-active {
    transition: all 220ms ease;
}

.qty-pop-enter-from,
.qty-pop-leave-to,
.badge-pop-enter-from,
.badge-pop-leave-to,
.total-pop-enter-from,
.total-pop-leave-to,
.new-pill-enter-from,
.new-pill-leave-to {
    opacity: 0;
    transform: scale(0.92);
}
</style>
