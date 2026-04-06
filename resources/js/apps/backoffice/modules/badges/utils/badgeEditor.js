import {
    BanknotesIcon,
    BriefcaseIcon,
    CalendarDaysIcon,
    DocumentTextIcon,
    HashtagIcon,
    IdentificationIcon,
    PhotoIcon,
    QrCodeIcon,
    RectangleGroupIcon,
    ShieldCheckIcon,
    Squares2X2Icon,
    UserIcon,
} from '@heroicons/vue/24/outline'

export const TEMPLATE_TYPES = ['staff', 'member', 'voucher']

export const typeFilters = [
    { label: 'Personeel', value: 'staff', icon: BriefcaseIcon },
    { label: 'Leden', value: 'member', icon: IdentificationIcon },
    { label: 'Cadeaubonnen', value: 'voucher', icon: BanknotesIcon },
]

export const fieldCatalog = {
    staff: [
        { label: 'Volledige naam', value: 'full_name', icon: UserIcon },
        { label: 'Voornaam', value: 'first_name', icon: IdentificationIcon },
        { label: 'Achternaam', value: 'last_name', icon: IdentificationIcon },
        { label: 'Functie', value: 'role', icon: BriefcaseIcon },
        { label: 'Badge nummer', value: 'badge_number', icon: HashtagIcon },
        { label: 'RFID UID', value: 'rfid_uid', icon: IdentificationIcon },
    ],
    member: [
        { label: 'Volledige naam', value: 'full_name', icon: UserIcon },
        { label: 'Voornaam', value: 'first_name', icon: IdentificationIcon },
        { label: 'Achternaam', value: 'last_name', icon: IdentificationIcon },
        { label: 'Lidmaatschap', value: 'membership_type', icon: ShieldCheckIcon },
        { label: 'Badge nummer', value: 'badge_number', icon: HashtagIcon },
        { label: 'Geldig tot', value: 'valid_until', icon: CalendarDaysIcon },
    ],
    voucher: [
        { label: 'Titel', value: 'title', icon: DocumentTextIcon },
        { label: 'Voucher code', value: 'voucher_code', icon: HashtagIcon },
        { label: 'Waarde', value: 'voucher_value', icon: BanknotesIcon },
        { label: 'Geldig tot', value: 'valid_until', icon: CalendarDaysIcon },
        { label: 'Omschrijving', value: 'description', icon: DocumentTextIcon },
        { label: 'Voorwaarden', value: 'terms', icon: DocumentTextIcon },
    ],
}

export const sampleData = {
    staff: {
        full_name: 'Alex Vermeulen',
        first_name: 'Alex',
        last_name: 'Vermeulen',
        role: 'Arcade host',
        badge_number: 'S-000184',
        rfid_uid: '04A81B2C91',
    },
    member: {
        full_name: 'Mila Van den Berghe',
        first_name: 'Mila',
        last_name: 'Van den Berghe',
        membership_type: 'Gold Member',
        badge_number: 'M-000842',
        valid_until: '31/12/2026',
    },
    voucher: {
        title: 'GAME-INN Cadeaubon',
        voucher_code: 'VCH-2026-0041',
        voucher_value: '€ 50,00',
        valid_until: '31/12/2026',
        description: 'Vrij te gebruiken voor arcade, VR en snacks.',
        terms: 'Niet inwisselbaar voor cash.',
    },
}

export const elementTools = [
    { type: 'field', label: 'Dataveld', icon: Squares2X2Icon, group: 'dynamic' },
    { type: 'text', label: 'Tekst', icon: DocumentTextIcon, group: 'static' },
    { type: 'photo', label: 'Foto', icon: PhotoIcon, group: 'dynamic' },
    { type: 'image', label: 'Afbeelding', icon: PhotoIcon, group: 'static' },
    { type: 'qr', label: 'QR', icon: QrCodeIcon, group: 'dynamic' },
    { type: 'shape', label: 'Vorm', icon: RectangleGroupIcon, group: 'static' },
]

export function blankTemplate(type) {
    const safeType = TEMPLATE_TYPES.includes(type) ? type : 'staff'
    const labels = {
        staff: 'Nieuwe personeel template',
        member: 'Nieuwe leden template',
        voucher: 'Nieuwe cadeaubon template',
    }

    const accentColor = safeType === 'member' ? '#7c3aed' : safeType === 'voucher' ? '#f59e0b' : '#2563eb'
    const backgroundColor = safeType === 'member' ? '#1e1b4b' : safeType === 'voucher' ? '#451a03' : '#0f172a'
    const firstField = fieldCatalog[safeType]?.[0]?.value || 'full_name'
    const secondField = safeType === 'member' ? 'membership_type' : safeType === 'voucher' ? 'voucher_value' : null
    const secondText = safeType === 'member' ? null : safeType === 'voucher' ? null : 'STAFF'
    const secondLabel = safeType === 'member' ? 'Lidmaatschap' : safeType === 'voucher' ? 'Waarde' : 'Label'

    return {
        name: labels[safeType],
        template_type: safeType,
        description: '',
        is_default: false,
        config_json: {
            width: 1016,
            height: 638,
            backgroundColor,
            backgroundImagePath: '',
            backgroundImageUrl: '',
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            elements: [
                { id: uid(), type: 'shape', label: 'Accent', x: 0, y: 0, width: 1016, height: 110, backgroundColor: accentColor, borderRadius: 0, opacity: 1, zIndex: 1 },
                { id: uid(), type: safeType === 'voucher' ? 'field' : 'field', label: safeType === 'voucher' ? 'Titel' : 'Naam', source: firstField, x: 60, y: 160, width: 680, height: 90, color: '#ffffff', backgroundColor: 'transparent', fontSize: 52, fontWeight: 800, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: secondField ? 'field' : 'text', label: secondLabel, source: secondField, text: secondText, x: 62, y: 270, width: 360, height: 52, color: '#e2e8f0', backgroundColor: 'transparent', fontSize: 26, fontWeight: 700, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'qr', label: 'QR', x: 812, y: 416, width: 150, height: 150, backgroundColor: '#ffffff', borderRadius: 24, opacity: 1, zIndex: 2 },
            ],
        },
    }
}

export function createEditorTemplate(template) {
    return {
        id: template.id ?? null,
        name: template.name,
        template_type: template.template_type,
        description: template.description || '',
        is_default: !!template.is_default,
        config_json: createConfigClone(template.config_json),
    }
}

export function createConfigClone(config) {
    const clone = JSON.parse(JSON.stringify(config))

    clone.backgroundImagePath = clone.backgroundImagePath || ''
    clone.backgroundImageUrl = clone.backgroundImageUrl || ''
    clone.backgroundSize = clone.backgroundSize || 'cover'
    clone.backgroundPosition = clone.backgroundPosition || 'center'
    clone.elements = (clone.elements || []).map((element) => ({
        ...element,
        imagePath: element.imagePath || '',
        imageUrl: element.imageUrl || '',
        fit: element.fit || (element.type === 'logo' ? 'contain' : 'cover'),
    }))

    return clone
}

export function typeLabel(value) {
    if (value === 'member') return 'Leden'
    if (value === 'voucher') return 'Cadeaubonnen'
    return 'Personeel'
}

export function uid() {
    if (typeof window !== 'undefined' && window.crypto?.randomUUID) {
        return window.crypto.randomUUID()
    }

    return `badge-${Math.random().toString(36).slice(2, 10)}`
}

export function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max)
}

export function clampNumber(value, fallback) {
    const number = Number(value)
    return Number.isFinite(number) ? number : fallback
}

export function formatDate(value) {
    if (!value) {
        return 'Onbekend'
    }

    return new Date(value).toLocaleString('nl-BE')
}

export function resolveImageUrl(path, explicitUrl = '') {
    if (explicitUrl) return explicitUrl
    if (!path) return ''
    return path.startsWith('/storage/') ? path : `/storage/${path}`
}

export const presets = [
    {
        id: 'preset-staff-modern',
        name: 'Staff Modern Dark',
        template_type: 'staff',
        description: 'Donkere staff badge met foto links, groot naamveld en QR rechtsonder.',
        is_default: false,
        config_json: {
            width: 1016,
            height: 638,
            backgroundColor: '#111827',
            backgroundImagePath: '',
            backgroundImageUrl: '',
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            elements: [
                { id: uid(), type: 'shape', label: 'Header glow', x: 0, y: 0, width: 1016, height: 120, backgroundColor: '#4f46e5', borderRadius: 0, opacity: 0.95, zIndex: 1 },
                { id: uid(), type: 'logo', label: 'Logo', x: 44, y: 36, width: 180, height: 48, imagePath: '', imageUrl: '', backgroundColor: '#312e81', fit: 'contain', borderRadius: 18, opacity: 1, zIndex: 2 },
                { id: uid(), type: 'photo', label: 'Foto', x: 54, y: 158, width: 260, height: 336, imagePath: '', imageUrl: '', backgroundColor: '#1e293b', fit: 'cover', borderRadius: 26, opacity: 1, zIndex: 2 },
                { id: uid(), type: 'text', label: 'Staff tag', text: 'STAFF', x: 348, y: 158, width: 188, height: 56, color: '#c4b5fd', backgroundColor: '#312e81', fontSize: 26, fontWeight: 800, borderRadius: 22, textAlign: 'center', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Naam', source: 'full_name', x: 348, y: 236, width: 520, height: 92, color: '#ffffff', backgroundColor: 'transparent', fontSize: 54, fontWeight: 800, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Functie', source: 'role', x: 350, y: 336, width: 410, height: 54, color: '#94a3b8', backgroundColor: 'transparent', fontSize: 26, fontWeight: 500, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Badge nummer', source: 'badge_number', x: 350, y: 456, width: 250, height: 40, color: '#cbd5e1', backgroundColor: 'transparent', fontSize: 24, fontWeight: 600, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'qr', label: 'QR', x: 794, y: 396, width: 168, height: 168, backgroundColor: '#ffffff', borderRadius: 24, opacity: 1, zIndex: 2 },
            ],
        },
    },
    {
        id: 'preset-member-classic',
        name: 'Member Purple Club',
        template_type: 'member',
        description: 'Member badge met foto, premium label en een opvallend onderste accentbalk.',
        is_default: false,
        config_json: {
            width: 1016,
            height: 638,
            backgroundColor: '#1e1b4b',
            backgroundImagePath: '',
            backgroundImageUrl: '',
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            elements: [
                { id: uid(), type: 'shape', label: 'Accent', x: 0, y: 520, width: 1016, height: 118, backgroundColor: '#8b5cf6', borderRadius: 0, opacity: 1, zIndex: 1 },
                { id: uid(), type: 'logo', label: 'Logo', x: 58, y: 50, width: 190, height: 52, imagePath: '', imageUrl: '', backgroundColor: '#5b21b6', fit: 'contain', borderRadius: 18, opacity: 1, zIndex: 2 },
                { id: uid(), type: 'text', label: 'Member tag', text: 'MEMBER', x: 770, y: 56, width: 188, height: 52, color: '#f5f3ff', backgroundColor: '#5b21b6', fontSize: 24, fontWeight: 800, borderRadius: 20, textAlign: 'center', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'photo', label: 'Foto', x: 58, y: 154, width: 250, height: 310, imagePath: '', imageUrl: '', backgroundColor: '#312e81', fit: 'cover', borderRadius: 28, opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Naam', source: 'full_name', x: 348, y: 178, width: 560, height: 84, color: '#ffffff', backgroundColor: 'transparent', fontSize: 50, fontWeight: 800, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Lidmaatschap', source: 'membership_type', x: 350, y: 278, width: 350, height: 48, color: '#ddd6fe', backgroundColor: 'transparent', fontSize: 28, fontWeight: 600, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Badge nummer', source: 'badge_number', x: 350, y: 360, width: 280, height: 42, color: '#cbd5e1', backgroundColor: 'transparent', fontSize: 24, fontWeight: 600, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Geldig tot', source: 'valid_until', x: 350, y: 414, width: 250, height: 40, color: '#cbd5e1', backgroundColor: 'transparent', fontSize: 22, fontWeight: 600, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'qr', label: 'QR', x: 794, y: 338, width: 168, height: 168, backgroundColor: '#ffffff', borderRadius: 24, opacity: 1, zIndex: 2 },
            ],
        },
    },
    {
        id: 'preset-voucher-gold',
        name: 'Voucher Gold Gift',
        template_type: 'voucher',
        description: 'Warme voucher lay-out met titel, waarde en QR-code voor cadeaubonnen.',
        is_default: false,
        config_json: {
            width: 1016,
            height: 638,
            backgroundColor: '#3f1d0a',
            backgroundImagePath: '',
            backgroundImageUrl: '',
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            elements: [
                { id: uid(), type: 'shape', label: 'Accent', x: 0, y: 0, width: 1016, height: 126, backgroundColor: '#f59e0b', borderRadius: 0, opacity: 0.95, zIndex: 1 },
                { id: uid(), type: 'logo', label: 'Logo', x: 56, y: 38, width: 190, height: 50, imagePath: '', imageUrl: '', backgroundColor: '#92400e', fit: 'contain', borderRadius: 18, opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Titel', source: 'title', x: 60, y: 180, width: 620, height: 92, color: '#ffffff', backgroundColor: 'transparent', fontSize: 52, fontWeight: 800, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Waarde', source: 'voucher_value', x: 62, y: 298, width: 260, height: 60, color: '#fde68a', backgroundColor: 'transparent', fontSize: 34, fontWeight: 800, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Voucher code', source: 'voucher_code', x: 62, y: 378, width: 420, height: 44, color: '#e5e7eb', backgroundColor: 'transparent', fontSize: 24, fontWeight: 600, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Geldig tot', source: 'valid_until', x: 62, y: 434, width: 260, height: 42, color: '#d1d5db', backgroundColor: 'transparent', fontSize: 22, fontWeight: 600, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'field', label: 'Omschrijving', source: 'description', x: 62, y: 500, width: 560, height: 76, color: '#f8fafc', backgroundColor: 'transparent', fontSize: 20, fontWeight: 500, borderRadius: 0, textAlign: 'left', opacity: 1, zIndex: 2 },
                { id: uid(), type: 'qr', label: 'QR', x: 794, y: 374, width: 168, height: 168, backgroundColor: '#ffffff', borderRadius: 24, opacity: 1, zIndex: 2 },
            ],
        },
    },
]
