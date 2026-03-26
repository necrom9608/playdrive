import { computed, reactive } from 'vue'

function createDefaultForm() {
    return {
        name: '',
        phone: '',
        email: '',
        postal_code: '',
        municipality: '',

        kids_count: 0,
        adults_count: 0,
        supervisors_count: 0,

        stats: {
            already_visited: false,
            recommended_by_friend: false,
            internet: false,
            social_media: false,
            facade: false,
            ai: false,
        },

        event_date: '',
        start_time: '',
        duration: '2h',
        outside_opening_hours: false,

        event_type: 'free_play',
        catering_option: 'none',
        status: 'new',
        comment: '',

        invoice_requested: false,
        invoice_company_name: '',
        invoice_vat_number: '',
        invoice_email: '',
        invoice_street: '',
        invoice_house_number: '',
        invoice_postal_code: '',
        invoice_city: '',
    }
}

export function useRegistrationForm(initialValues = {}) {
    const form = reactive({
        ...createDefaultForm(),
        ...initialValues,
        stats: {
            ...createDefaultForm().stats,
            ...(initialValues.stats ?? {}),
        },
    })

    const totalParticipants = computed(() => {
        return Number(form.kids_count || 0)
            + Number(form.adults_count || 0)
            + Number(form.supervisors_count || 0)
    })

    const plannedEndTime = computed(() => {
        if (!form.start_time || !String(form.start_time).includes(':')) {
            return '--:--'
        }

        const [hours, minutes] = form.start_time.split(':').map(Number)

        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return '--:--'
        }

        let durationMinutes = 120

        if (form.duration === '1h') durationMinutes = 60
        if (form.duration === '2h') durationMinutes = 120
        if (form.duration === '3h') durationMinutes = 180
        if (form.duration === 'half_day') durationMinutes = 240
        if (form.duration === 'full_day') durationMinutes = 480

        const total = (hours * 60) + minutes + durationMinutes
        const endHours = Math.floor(total / 60) % 24
        const endMinutes = total % 60

        return `${String(endHours).padStart(2, '0')}:${String(endMinutes).padStart(2, '0')}`
    })

    function resetForm() {
        const defaults = createDefaultForm()

        Object.keys(defaults).forEach((key) => {
            if (key === 'stats') {
                Object.assign(form.stats, defaults.stats)
                return
            }

            form[key] = defaults[key]
        })
    }


    return {
        form,
        totalParticipants,
        plannedEndTime,
        resetForm,
    }
}
