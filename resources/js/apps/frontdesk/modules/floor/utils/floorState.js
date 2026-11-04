import { markRaw } from 'vue'
import {
    ComputerDesktopIcon,
    CubeTransparentIcon,
    PuzzlePieceIcon,
    RocketLaunchIcon,
    TvIcon,
} from '@heroicons/vue/24/outline'

const TYPE_ICONS = {
    arcade: markRaw(RocketLaunchIcon),
    pc: markRaw(ComputerDesktopIcon),
    console: markRaw(TvIcon),
    vr: markRaw(CubeTransparentIcon),
    pinball: markRaw(PuzzlePieceIcon),
    other: markRaw(PuzzlePieceIcon),
}

export function typeIcon(type) {
    return TYPE_ICONS[type] || TYPE_ICONS.other
}

export function playscopeDevice(setup) {
    return setup?.devices?.find(d => d.type === 'playscope') ?? null
}

/** busy | free | maintenance | offline */
export function setupState(setup) {
    if (!setup) return 'free'
    if (setup.status === 'maintenance') return 'maintenance'
    if (setup.in_use) return 'busy'
    const pcs = setup.devices.filter(d => d.type === 'playscope')
    if (pcs.length && !pcs.some(d => d.online)) return 'offline'
    return 'free'
}

export const STATE_META = {
    busy: { label: 'Bezet', pill: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200', dot: 'bg-emerald-400', tile: 'border-emerald-500/30' },
    free: { label: 'Vrij', pill: 'border-slate-700 bg-slate-800 text-slate-300', dot: 'bg-slate-500', tile: 'border-slate-800' },
    maintenance: { label: 'Onderhoud', pill: 'border-amber-500/40 bg-amber-500/10 text-amber-200', dot: 'bg-amber-400', tile: 'border-amber-500/30' },
    offline: { label: 'Offline', pill: 'border-slate-700 bg-transparent text-slate-400', dot: 'bg-slate-700 ring-1 ring-slate-500', tile: 'border-slate-800 opacity-60' },
}

export function minutesSince(iso, now = Date.now()) {
    if (!iso) return null
    const t = new Date(iso).getTime()
    if (Number.isNaN(t)) return null
    return Math.max(0, Math.floor((now - t) / 60000))
}

export function formatDuration(seconds) {
    const s = Math.max(0, Math.round(seconds))
    if (s < 60) return `${s} s`
    const m = Math.floor(s / 60)
    const r = s % 60
    return r ? `${m} min ${r} s` : `${m} min`
}

export function formatTime(iso) {
    if (!iso) return ''
    return new Date(iso).toLocaleTimeString('nl-BE', { hour: '2-digit', minute: '2-digit' })
}

export function formatWatts(value) {
    if (value === null || value === undefined) return ''
    return `${Math.round(value)} W`
}
