<script setup>
import { computed } from 'vue'
import { ICONS, ORBIT } from './icons.js'

/**
 * Embleem met de acht zone-icoontjes die rondcirkelen (zoals de hero van game-inn.be).
 * Met een actieve zone licht dat icoon oranje op en schuift de boog eronder;
 * zonder zone lichten de icoontjes om beurten op.
 */
const props = defineProps({
    emblem: { type: String, required: true },
    zone: { type: String, default: null },
    label: { type: String, default: '' },
})

const index = computed(() => ORBIT.indexOf(props.zone))
const items = ORBIT.map((key, i) => ({ key, angle: `${i * 45}deg`, delay: `${(i * 0.6).toFixed(1)}s` }))
</script>

<template>
    <div class="gi-orbit" :class="{ lit: index < 0 }">
        <div class="dial"></div>
        <div class="rail"></div>
        <div class="icons">
            <div class="boog" :style="{ transform: `rotate(${Math.max(0, index) * 45}deg)`, opacity: index < 0 ? 0 : 1 }"></div>
            <span
                v-for="item in items"
                :key="item.key"
                class="ico"
                :class="{ aan: item.key === zone }"
                :style="{ '--a': item.angle, '--d': item.delay }"
            >
                <span class="rechtop">
                    <svg viewBox="0 0 851 851" aria-hidden="true"><path :d="ICONS[item.key]" /></svg>
                </span>
            </span>
        </div>
        <div class="core"><img :src="emblem" alt="" draggable="false"></div>
        <Transition name="gi-nu" mode="out-in">
            <div v-if="label" :key="label" class="nu">{{ label }}</div>
        </Transition>
    </div>
</template>

<style scoped>
.gi-orbit { position: relative; aspect-ratio: 1; }
.core { position: absolute; inset: 29%; animation: gi-float 6s ease-in-out infinite; }
.core::before {
    content: ""; position: absolute; inset: -18%; border-radius: 50%;
    background: radial-gradient(circle, rgba(91, 141, 224, .45), rgba(242, 119, 36, .18) 45%, transparent 68%);
    filter: blur(20px); animation: gi-breathe 4s ease-in-out infinite;
}
.core img { position: relative; width: 100%; filter: drop-shadow(0 0 30px rgba(91, 141, 224, .45)); }
.rail {
    position: absolute; inset: 18%; border-radius: 50%;
    background: conic-gradient(from 0deg, rgba(91, 141, 224, .12), rgba(91, 141, 224, .75) 14%, rgba(91, 141, 224, .12) 32%, rgba(91, 141, 224, .12));
    -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 2px));
    mask: radial-gradient(farthest-side, transparent calc(100% - 2px), #000 calc(100% - 2px));
    animation: gi-spin 9s linear infinite;
}
.dial {
    position: absolute; inset: -1%; border-radius: 50%;
    background: repeating-conic-gradient(rgba(255, 255, 255, .22) 0 .5deg, transparent .5deg 7.5deg);
    -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 6px), #000 calc(100% - 6px));
    mask: radial-gradient(farthest-side, transparent calc(100% - 6px), #000 calc(100% - 6px));
    opacity: .7;
}
.boog {
    position: absolute; inset: 18%; border-radius: 50%;
    transition: transform .6s cubic-bezier(.3, .8, .3, 1), opacity .4s ease;
    background: conic-gradient(from -28deg, transparent, #f27724 10deg 46deg, transparent 56deg);
    -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 3px));
    mask: radial-gradient(farthest-side, transparent calc(100% - 3px), #000 calc(100% - 3px));
    filter: drop-shadow(0 0 6px rgba(242, 119, 36, .9));
}
.icons { position: absolute; inset: 0; animation: gi-spin 80s linear infinite; }
.ico {
    position: absolute; width: 14%; aspect-ratio: 1; left: 43%; top: 43%;
    transform: rotate(var(--a)) translateY(-300%) rotate(calc(var(--a) * -1));
}
.ico .rechtop { display: block; width: 100%; height: 100%; animation: gi-spin 80s linear infinite reverse; }
.ico svg {
    width: 100%; height: 100%; fill: #e9e7e1; transform-origin: center;
    filter: drop-shadow(0 6px 14px rgba(0, 0, 0, .5));
    transition: fill .35s ease, transform .35s cubic-bezier(.2, .9, .3, 1.4), filter .35s ease;
}
.ico.aan svg {
    fill: #f27724; transform: scale(1.22);
    filter: drop-shadow(0 0 18px rgba(242, 119, 36, .75)) drop-shadow(0 6px 14px rgba(0, 0, 0, .5));
}
.lit .ico svg { animation: gi-lit 4.8s ease-in-out infinite; animation-delay: var(--d); }
.nu {
    position: absolute; left: 50%; bottom: 25%; transform: translateX(-50%); z-index: 3;
    padding: 5px 12px; border-radius: 999px; white-space: nowrap;
    background: rgba(10, 12, 17, .75); border: 1px solid rgba(242, 119, 36, .45); backdrop-filter: blur(6px);
    font: 600 10px/1 Orbitron, sans-serif; letter-spacing: .16em; text-transform: uppercase; color: #ff9147;
}
.gi-nu-enter-active, .gi-nu-leave-active { transition: opacity .25s ease; }
.gi-nu-enter-from, .gi-nu-leave-to { opacity: 0; }

@keyframes gi-float { 50% { transform: translateY(-8px); } }
@keyframes gi-breathe { 50% { opacity: .55; transform: scale(1.08); } }
@keyframes gi-spin { to { transform: rotate(360deg); } }
@keyframes gi-lit { 0%, 12%, 100% { fill: #e9e7e1; transform: scale(1); } 5% { fill: #f27724; transform: scale(1.12); } }
</style>
