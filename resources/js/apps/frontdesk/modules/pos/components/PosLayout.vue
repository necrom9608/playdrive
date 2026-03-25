<template>
    <div class="grid h-full min-h-0 grid-cols-12 gap-4">
        <RegistrationsPanel class="col-span-6 h-full min-h-0" />
        <ProductsPanel class="col-span-4 h-full min-h-0" />
        <OrderPanel class="col-span-2 h-full min-h-0" />
    </div>
</template>

<script setup>
import RegistrationsPanel from './registrations/RegistrationsPanel.vue'
import ProductsPanel from './products/ProductsPanel.vue'
import OrderPanel from './order/OrderPanel.vue'
</script>
