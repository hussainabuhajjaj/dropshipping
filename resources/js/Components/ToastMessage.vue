<template>
    <div class="flex items-center gap-3">
        <span v-if="icon" :class="['text-lg', icon]" />
        <div>
            <h3 v-if="title" class="font-bold text-base mb-1">{{ title }}</h3>
            <div class="text-sm">{{ message }}</div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    message: { type: String, required: true },
    title: { type: String, default: '' },
    icon: { type: String, default: '' }
})
</script>

<style scoped>
.flex {
    align-items: center;
}
</style>
