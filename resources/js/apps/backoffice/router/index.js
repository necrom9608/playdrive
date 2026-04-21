import { createRouter, createWebHistory } from 'vue-router'

import DashboardPage from '../modules/dashboard/pages/DashboardPage.vue'
import ReportingPage from '../modules/reporting/pages/ReportingPage.vue'
import DayTotalsPage from '../modules/daytotals/pages/DayTotalsPage.vue'
import ProductManagementPage from '../modules/product-management/pages/ProductManagementPage.vue'
import BadgeCreatorPage from '../modules/badges/pages/BadgeCreatorPage.vue'
import CateringOptionsPage from '../modules/options/pages/CateringOptionsPage.vue'
import EventTypesPage from '../modules/options/pages/EventTypesPage.vue'
import StayOptionsPage from '../modules/options/pages/StayOptionsPage.vue'
import StaffPage from '../modules/staff/pages/StaffPage.vue'
import StaffAttendancePage from '../modules/staff-attendance/pages/StaffAttendancePage.vue'
import DevicesPage from '../modules/devices/pages/DevicesPage.vue'
import VoucherTemplatesPage from '../modules/voucher-templates/pages/VoucherTemplatesPage.vue'
import CardsPage from '../modules/cards/pages/CardsPage.vue'
import MailLogsPage from '../modules/maillogs/pages/MailLogsPage.vue'
import OpeningHoursPage from '../modules/opening-hours/pages/OpeningHoursPage.vue'
import BookingFormConfigPage from '../modules/booking-form/pages/BookingFormConfigPage.vue'
import EmailTemplatesPage from '../modules/email-templates/pages/EmailTemplatesPage.vue'

const routes = [
    { path: '/', name: 'backoffice.dashboard', component: DashboardPage },
    { path: '/reporting', name: 'backoffice.reporting', component: ReportingPage },
    { path: '/daytotals', name: 'backoffice.daytotals', component: DayTotalsPage },
    { path: '/catalog', name: 'backoffice.product-management', component: ProductManagementPage },
    { path: '/badges', name: 'backoffice.badges', component: BadgeCreatorPage },
    { path: '/voucher-templates', name: 'backoffice.voucher-templates', component: VoucherTemplatesPage },
    { path: '/cards', name: 'backoffice.cards', component: CardsPage },
    {
        path: '/products',
        redirect: { name: 'backoffice.product-management', query: { tab: 'products' } },
    },
    {
        path: '/product-categories',
        redirect: { name: 'backoffice.product-management', query: { tab: 'categories' } },
    },
    {
        path: '/pricing-engine',
        redirect: { name: 'backoffice.product-management', query: { tab: 'pricing-rules' } },
    },
    { path: '/catering-options', name: 'backoffice.catering-options', component: CateringOptionsPage },
    { path: '/event-types', name: 'backoffice.event-types', component: EventTypesPage },
    { path: '/stay-options', name: 'backoffice.stay-options', component: StayOptionsPage },
    { path: '/staff', name: 'backoffice.staff', component: StaffPage },
    { path: '/staff-attendance', name: 'backoffice.staff-attendance', component: StaffAttendancePage },
    { path: '/devices', name: 'backoffice.devices', component: DevicesPage },
    { path: '/mail-logs', name: 'backoffice.mail-logs', component: MailLogsPage },
    { path: '/opening-hours', name: 'backoffice.opening-hours', component: OpeningHoursPage },
    { path: '/booking-form', name: 'backoffice.booking-form', component: BookingFormConfigPage },
    { path: '/email-templates', name: 'backoffice.email-templates', component: EmailTemplatesPage },
]

export default createRouter({
    history: createWebHistory('/backoffice/'),
    routes,
})
