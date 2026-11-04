const currency = new Intl.NumberFormat('nl-BE', { style: 'currency', currency: 'EUR' })
const currencyRound = new Intl.NumberFormat('nl-BE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 })
const number = new Intl.NumberFormat('nl-BE')
const percent = new Intl.NumberFormat('nl-BE', { maximumFractionDigits: 1 })

export function formatCurrency(value, { round = false } = {}) {
    if (value === null || value === undefined) return '—'
    return (round ? currencyRound : currency).format(Number(value || 0))
}

export function formatNumber(value) {
    if (value === null || value === undefined) return '—'
    return number.format(Number(value || 0))
}

export function formatPercent(value) {
    return `${percent.format(Number(value || 0))}%`
}

function toDate(isoDate) {
    const [y, m, d] = String(isoDate).split('-').map(Number)
    return new Date(y, m - 1, d)
}

/** "wo. 28 okt" */
export function formatDayShort(isoDate) {
    if (!isoDate) return ''
    return new Intl.DateTimeFormat('nl-BE', { weekday: 'short', day: 'numeric', month: 'short' })
        .format(toDate(isoDate))
        .replace(/\.$/, '')
}

/** "28 okt 2025" */
export function formatDate(isoDate) {
    if (!isoDate) return ''
    return new Intl.DateTimeFormat('nl-BE', { day: 'numeric', month: 'short', year: 'numeric' }).format(toDate(isoDate))
}

export function weekdayShort(isoDate) {
    return new Intl.DateTimeFormat('nl-BE', { weekday: 'short' }).format(toDate(isoDate)).replace(/\.$/, '')
}

export function dayOfMonth(isoDate) {
    return toDate(isoDate).getDate()
}

export function isMonday(isoDate) {
    return toDate(isoDate).getDay() === 1
}

export function isFirstOfMonth(isoDate) {
    return toDate(isoDate).getDate() === 1
}

export function monthShort(isoDate) {
    return new Intl.DateTimeFormat('nl-BE', { month: 'short' }).format(toDate(isoDate)).replace(/\.$/, '')
}

export function todayIso() {
    const now = new Date()
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}
