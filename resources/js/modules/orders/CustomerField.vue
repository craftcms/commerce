<script setup lang="ts">
    import {displayError, displayNotice, sendActionRequest} from './cp-client';
    import {computed, nextTick, onBeforeUnmount, ref, watch} from 'vue';

    interface Customer {
        id: number;
        name: string | null;
        email: string | null;
    }

    interface ComboboxOption {
        label: string;
        value: string;
        disabled?: boolean;
        data?: Record<string, unknown>;
    }

    const props = defineProps<{
        customer: {chipHtml: string} | null;
        editable: boolean;
        completed: boolean;
        searchUrl: string;
        createUrl: string;
        labels: Record<string, string>;
    }>();

    const emit = defineEmits<{
        select: [customerId: number | null];
    }>();

    const MIN_QUERY_LENGTH = 2;
    const SEARCH_DELAY = 250;
    const SLOW_SEARCH_DELAY = 400;
    const CREATE_PREFIX = 'create:';

    const root = ref<HTMLElement | null>(null);
    const replacing = ref(false);
    const query = ref('');
    const results = ref<Customer[]>([]);
    const canCreate = ref(false);
    const searched = ref(false);
    const searching = ref(false);
    const slow = ref(false);
    const picked = ref(false);
    let intended = false;
    let intentTimer: ReturnType<typeof setTimeout> | undefined;

    let searchTimer: ReturnType<typeof setTimeout> | undefined;
    let slowTimer: ReturnType<typeof setTimeout> | undefined;
    let blurTimer: ReturnType<typeof setTimeout> | undefined;
    let request = 0;

    const showSearch = computed(
        () => props.editable && (!props.customer || replacing.value)
    );

    const validEmail = computed(() =>
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(query.value.trim())
    );

    const options = computed<ComboboxOption[]>(() => {
        const keywords = query.value;

        if (slow.value && searching.value) {
            return [
                {
                    label: props.labels.customerSearching,
                    value: '__searching__',
                    disabled: true,
                    data: {keywords},
                },
            ];
        }

        if (results.value.length) {
            return results.value.map((customer) => ({
                label: customer.name || customer.email || String(customer.id),
                value: String(customer.id),
                data: {
                    hint: customer.name ? customer.email : null,
                    keywords,
                },
            }));
        }

        if (!searched.value || searching.value) {
            return [];
        }

        if (validEmail.value && canCreate.value) {
            return [
                {
                    label: props.labels.createCustomerFor.replace(
                        '{email}',
                        query.value.trim()
                    ),
                    value: `${CREATE_PREFIX}${query.value.trim()}`,
                    data: {keywords, icon: 'plus'},
                },
            ];
        }

        return [
            {
                label: props.labels.noCustomersFound,
                value: '__none__',
                disabled: true,
                data: {keywords},
            },
        ];
    });

    watch(
        () => props.customer,
        () => {
            replacing.value = false;
            reset();
        }
    );

    onBeforeUnmount(() => {
        clearTimeout(searchTimer);
        clearTimeout(slowTimer);
        clearTimeout(blurTimer);
        clearTimeout(intentTimer);
    });

    function reset(): void {
        clearTimeout(searchTimer);
        clearTimeout(slowTimer);
        request++;
        query.value = '';
        results.value = [];
        searched.value = false;
        searching.value = false;
        slow.value = false;
    }

    function typedText(combobox: HTMLElement): string {
        const input =
            (combobox as unknown as {_inputNode?: HTMLInputElement})
                ._inputNode ?? combobox.querySelector('input');

        return input?.value ?? '';
    }

    function onInput(event: Event): void {
        if (picked.value) {
            return;
        }

        const raw = typedText(event.target as HTMLElement);

        if (
            options.value.some(
                (option) => !option.disabled && option.label === raw
            )
        ) {
            return;
        }

        const text = raw.trim();

        if (text === query.value) {
            return;
        }

        query.value = text;
        clearTimeout(searchTimer);

        if (text.length < MIN_QUERY_LENGTH) {
            request++;
            results.value = [];
            searched.value = false;
            searching.value = false;
            slow.value = false;
            closeSoon();

            return;
        }

        searchTimer = setTimeout(() => void search(text), SEARCH_DELAY);
    }

    async function search(text: string): Promise<void> {
        const current = ++request;
        searching.value = true;
        slow.value = false;
        clearTimeout(slowTimer);
        slowTimer = setTimeout(() => {
            if (current === request) {
                slow.value = true;
            }
        }, SLOW_SEARCH_DELAY);

        try {
            const {data} = await sendActionRequest<{
                customers: Customer[];
                canCreate: boolean;
            }>('GET', props.searchUrl, {params: {query: text}});

            if (current !== request) {
                return;
            }

            results.value = data.customers;
            canCreate.value = data.canCreate;
            searched.value = true;
            void openList();
        } catch (error: any) {
            if (current === request) {
                displayError(error?.response?.data?.message);
            }
        } finally {
            if (current === request) {
                searching.value = false;
                slow.value = false;
                clearTimeout(slowTimer);
            }
        }
    }

    function combobox(): (HTMLElement & {opened?: boolean}) | null {
        return root.value?.querySelector('craft-combobox') ?? null;
    }

    async function closeList(): Promise<void> {
        await nextTick();
        const element = combobox();

        if (element) {
            element.opened = false;
        }
    }

    function closeSoon(): void {
        void closeList();
        requestAnimationFrame(() => void closeList());
        setTimeout(() => void closeList(), 60);
    }

    watch(options, (list) => {
        if (!list.length) {
            closeSoon();
        }
    });

    function markIntent(): void {
        intended = true;
        clearTimeout(intentTimer);
        intentTimer = setTimeout(() => (intended = false), 1000);
    }

    function onPointerDown(event: PointerEvent): void {
        if (
            (event.target as HTMLElement | null)?.closest(
                'craft-option, [role="option"]'
            )
        ) {
            markIntent();
        }
    }

    function onKeyDown(event: KeyboardEvent): void {
        if (event.key === 'Enter') {
            markIntent();
        }
    }

    async function openList(): Promise<void> {
        await nextTick();
        const element = combobox();

        if (element && root.value?.contains(document.activeElement)) {
            element.opened = true;
        }
    }

    async function onChange(event: Event): Promise<void> {
        const combobox = event.target as HTMLElement & {
            updateComplete?: Promise<unknown>;
            modelValue?: string;
        };

        await combobox.updateComplete;
        const value = String(combobox.modelValue ?? '');
        const option = options.value.find(
            (option) => !option.disabled && option.value === value
        );

        if (!option) {
            return;
        }

        const chosen = intended;
        intended = false;

        if (!chosen) {
            combobox.modelValue = '';
            reset();

            return;
        }

        picked.value = true;
        clearTimeout(blurTimer);

        if (value.startsWith(CREATE_PREFIX)) {
            await create(value.slice(CREATE_PREFIX.length), combobox);
        } else {
            emit('select', Number(value));
        }

        picked.value = false;
    }

    async function create(
        email: string,
        combobox: HTMLElement & {modelValue?: string}
    ): Promise<void> {
        try {
            const {data} = await sendActionRequest<{
                message?: string;
                customer: Customer;
            }>('POST', props.createUrl, {data: {email}});

            if (data.message) {
                displayNotice(data.message);
            }

            emit('select', data.customer.id);
        } catch (error: any) {
            const errors = error?.response?.data?.errors;

            displayError(errors?.email?.[0] ?? error?.response?.data?.message);
            combobox.modelValue = '';
        }
    }

    function onEscape(event: KeyboardEvent): void {
        if (props.customer && replacing.value && !combobox()?.opened) {
            event.stopPropagation();
            cancelReplacing();
        }
    }

    function cancelReplacing(): void {
        if (props.customer && replacing.value) {
            replacing.value = false;
            reset();
        }
    }

    function onFocusOut(event: FocusEvent): void {
        if (!props.customer || picked.value) {
            return;
        }

        const next = event.relatedTarget as Node | null;

        if (next && root.value?.contains(next)) {
            return;
        }

        clearTimeout(blurTimer);
        blurTimer = setTimeout(() => {
            if (
                !picked.value &&
                !root.value?.contains(document.activeElement)
            ) {
                cancelReplacing();
            }
        }, 200);
    }

    function startReplacing(): void {
        if (props.completed) {
            replacing.value = true;
            void focusSearch();

            return;
        }

        emit('select', null);
    }

    async function focusSearch(): Promise<void> {
        await new Promise((resolve) => setTimeout(resolve, 0));
        const combobox = root.value?.querySelector('craft-combobox') as
            | (HTMLElement & {updateComplete?: Promise<unknown>})
            | null;

        await combobox?.updateComplete;
        (
            (combobox as unknown as {_inputNode?: HTMLInputElement})
                ?._inputNode ?? combobox?.querySelector('input')
        )?.focus();
    }
</script>

<template>
    <div
        ref="root"
        class="customer-field"
        @focusout="onFocusOut"
        @pointerdown.capture="onPointerDown"
        @keydown.capture="onKeyDown"
    >
        <template v-if="showSearch">
            <craft-combobox
                :label="labels.customer"
                label-sr-only
                require-option-match
                :placeholder="labels.searchCustomers"
                .options="options"
                .modelValue="''"
                @input="onInput"
                @change="onChange"
                @keydown.esc="onEscape"
            />
        </template>
        <div v-else-if="customer" class="customer-field__selected">
            <span v-html="customer.chipHtml" />
            <craft-button
                v-if="editable"
                type="button"
                icon
                size="small"
                @click="startReplacing"
            >
                <craft-icon
                    name="xmark"
                    :label="
                        completed
                            ? labels.changeCustomer
                            : labels.removeCustomer
                    "
                />
            </craft-button>
        </div>
        <template v-else>{{ labels.noCustomer }}</template>
    </div>
</template>

<style>
    .customer-field {
        min-inline-size: 0;
        inline-size: 100%;
    }

    .customer-field__selected {
        display: flex;
        align-items: center;
        gap: var(--c-spacing-sm, 0.5rem);
        flex-wrap: wrap;
    }
</style>
