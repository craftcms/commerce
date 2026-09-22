<script setup lang="ts">
  import {ref} from 'vue';

  // See ActionMenuNode.vue/CopyAttributeNode.vue in `cms` — this node holds no
  // form value of its own, so `FormNode`'s shared prop set shouldn't fall
  // through onto the host element.
  defineOptions({inheritAttrs: false});

  type UsageCounterProps = {
    label: string;
    resetUrl: string;
    resetBody: Record<string, unknown>;
    resetLabel: string;
    confirmMessage: string | null;
  };

  // A minimal, hand-authored stand-in for `cms`'s own `FormNodePayload<T>` —
  // only the two members this component actually reads (see window.d.ts for
  // why this can't just be imported from `cms`).
  type FormNodePayload<Props> = {
    uid: string;
    props: Props;
  };

  const props = defineProps<{
    node: FormNodePayload<UsageCounterProps>;
  }>();

  const loading = ref(false);

  /**
   * Posts the reset, then re-fetches this page's own `form` prop via the real,
   * currently-mounted Inertia router (`window.Cp.$router` — see window.d.ts
   * for why this bundle can't just `import {router} from '@inertiajs/vue3'`
   * itself) so the count shown here reflects the fresh, now-cleared total
   * rather than going stale until a full page reload. Mirrors `cms`'s own
   * `AdminTableNode.vue`'s `refreshForm()`.
   */
  async function reset(): Promise<void> {
    const message = props.node.props.confirmMessage ?? window.Craft.t('app', 'Are you sure?');

    if (!confirm(message)) {
      return;
    }

    loading.value = true;
    try {
      await window.Cp.$axios.post(props.node.props.resetUrl, props.node.props.resetBody);
      window.Cp.$router.reload({only: ['form']});
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
