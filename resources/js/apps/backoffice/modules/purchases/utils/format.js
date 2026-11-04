export const STATUS = {
    draft: { label: 'Concept', classes: 'border-slate-600 bg-slate-800 text-slate-200' },
    sent: { label: 'Verstuurd', classes: 'border-amber-500/40 bg-amber-500/15 text-amber-200' },
    signed: { label: 'Ondertekend', classes: 'border-sky-500/40 bg-sky-500/15 text-sky-200' },
    paid: { label: 'Betaald', classes: 'border-emerald-500/40 bg-emerald-500/15 text-emerald-200' },
    cancelled: { label: 'Geannuleerd', classes: 'border-rose-500/30 bg-rose-500/10 text-rose-300' },
}

export function statusLabel(status) {
    return STATUS[status]?.label || status
}

export function statusClasses(status) {
    return STATUS[status]?.classes || STATUS.draft.classes
}

export function formatCurrency(value) {
    return new Intl.NumberFormat('nl-BE', { style: 'currency', currency: 'EUR' }).format(Number(value || 0))
}

export function formatDate(value) {
    if (!value) return '—'
    const [year, month, day] = String(value).slice(0, 10).split('-')
    return `${day}/${month}/${year}`
}

export function formatDateTime(value) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('nl-BE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

export function paymentLabel(method) {
    return method === 'cash' ? 'Contant' : 'Overschrijving'
}
