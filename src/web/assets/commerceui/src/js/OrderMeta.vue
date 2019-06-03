<template>
    <div>
        <div class="buttons">
            <a class="btn" @click.prevent="someAction()">Some Action</a>
        </div>

        <pre><code>{{$root.draft}}</code></pre>
    </div>
</template>

<script>
    import { EventBus } from './event-bus.js';

    export default {
        name: 'order-meta-app',

        methods: {
            someAction() {
                EventBus.$emit('someAction')
            }
        },
    }
</script>

