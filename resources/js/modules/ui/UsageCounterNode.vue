<script setup lang="ts">
  import {t, type UiNodePayload} from '@craftcms/cp';
  import {router} from '@inertiajs/vue3';
  import {ref} from 'vue';
  import {sendActionRequest} from '../orders/cp-client';

  // See ActionMenuNode.vue/CopyAttributeNode.vue in `cms` — this node holds no
  // form value of its own, so `UiNode`'s shared prop set shouldn't fall
  // through onto the host element.
  defineOptions({inheritAttrs: false});

  type UsageCounterProps = {
    label: string;
    resetUrl: string;
    resetBody: Record<string, unknown>;
    resetLabel: string;
    confirmMessage: string | null;
  };

  const props = defineProps<{
    node: UiNodePayload<UsageCounterProps>;
  }>();

  const loading = ref(false);

  /**
   * Posts the reset, then re-fetches this page's own `ui` prop so the count
   * shown here reflects the fresh, now-cleared total rather than going stale
   * until a full page reload. Mirrors `cms`'s own `AdminTableNode.vue`'s
   * `refreshUi()`.
   */
  async function reset(): Promise<void> {
    const message = props.node.props.confirmMessage ?? t('Are you sure?');

    if (!confirm(message)) {
      return;
    }

    loading.value = true;
    try {
      await sendActionRequest('POST', props.node.props.resetUrl, {
        data: props.node.props.resetBody,
      });
      router.reload({only: ['ui']});
    } finally {
      loading.value = false;
    }
  }
</script>

<template>
  <div class="flex items-center gap-2" :data-form-node="node.uid">
    <span class="text-sm text-neutral-text-quiet whitespace-nowrap">{{
      node.props.label
    }}</span>
    <craft-button type="button" size="small" :loading="loading" @click="reset">
      {{ node.props.resetLabel }}
    </craft-button>
  </div>
</template>
