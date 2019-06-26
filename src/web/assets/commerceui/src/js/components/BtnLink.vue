<template>
    <button type="button" class="btn-link" @click.prevent="$emit('click')">
        <slot></slot>
    </button>
</template>