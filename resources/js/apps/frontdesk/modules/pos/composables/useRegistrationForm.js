import { computed, reactive } from 'vue'

function getTodayDateString() {
    const now = new Date()
    const year = now.getFullYear()
    const month = String(now.getMonth() + 1).padStart(2, '0')
    const day = String(now.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

function getCurrentTimeString() {
    const now = new Date()
    const hours = String(now.getHours()).padStart(2, '0')
    const minutes = String(now.getMinutes()).padStart(2, '0')

    return `${hours}:${minutes}`
}

function createDefaultForm() {
    return {
        id: null,

        name: '',
        phone: '',
        email: '',
        postal_code: '',
        municipality: '',

        participants_children: 0,
        participants_adults: 0,
        participants_supervisors: 0,

        stats: {
            already_visited: false,
            recommended_by_friend: false,
            internet: false,
            social_media: false,
            facade: false,
            ai: false,
        },

        event_date: getTodayDateString(),
        event_time: getCurrentTimeString(),
        event_type_id: null,
        stay_option_id: null,
        catering_option_id: null,
        outside_opening_hours: false,

        status: 'checked_in',
        comment: '',

        invoice_requested: false,
        invoice_company_name: '',
        invoice_vat_number: '',
        invoice_email: '',
        invoice_address: '',
        invoice_postal_code: '',
        invoice_city: '',
    }
}

export function useRegistrationForm(initialValues = {}, selectedStayOption = null) {
    const defaults = createDefaultForm()

    const form = reactive({
        ...defaults,
        ...initialValues,
        stats: {
            ...defaults.stats,
            ...(initialValues.stats ?? {}),
        },
    })

    const totalParticipants = computed(() => {
        return Number(form.participants_children || 0)
            + Number(form.participants_adults || 0)
            + Number(form.participants_supervisors || 0)
    })

    const plannedEndTime = computed(() => {
        if (!form.event_time || !String(form.event_time).includes(':')) {
            return '--:--'
        }

        const [hours, minutes] = String(form.event_time).split(':').map(Number)

        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return '--:--'
        }

        let durationMinutes = 0

        if (selectedStayOption?.value?.duration_minutes) {
            durationMinutes = Number(selectedStayOption.value.duration_minutes)
        }

        const total = (hours * 60) + minutes + durationMinutes
        const endHours = Math.floor(total / 60) % 24
        const endMinutes = total % 60

        return `${String(endHours).padStart(2, '0')}:${String(endMinutes).padStart(2, '0')}`
    })

    function resetForm() {
        const fresh = createDefaultForm()

        Object.keys(fresh).forEach((key) => {
            if (key === 'stats') {
                Object.assign(form.stats, fresh.stats)
                return
            }

            form[key] = fresh[key]
        })
    }

    function fillForm(values = {}) {
        const fresh = createDefaultForm()

        Object.keys(fresh).forEach((key) => {
            if (key === 'stats') {
                Object.assign(form.stats, fresh.stats, values.stats ?? {})
                return
            }

            form[key] = values[key] ?? fresh[key]
        })
    }

    return {
        form,
        totalParticipants,
        plannedEndTime,
        resetForm,
        fillForm,
    }
}
