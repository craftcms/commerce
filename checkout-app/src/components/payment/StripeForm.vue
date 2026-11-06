<script setup>
    import {onBeforeUnmount, onMounted, ref} from 'vue';
    import {loadStripe} from '@stripe/stripe-js';
    import {config} from '../../api.js';
    import {followRedirect, payErrorMessage, postPay} from '../../payment.js';

    const props = defineProps({
        gateway: {type: Object, required: true},
        cart: {type: Object, required: true},
        paymentAmount: {type: String, default: null},
    });
    const emit = defineEmits(['ready', 'error']);

    const element = ref(null);
    const loading = ref(true);
    const failure = ref(null);
    let stripe;
    let elements;
    let paymentElement;
    let transaction;

    const style = (name, fallback) =>
        getComputedStyle(document.documentElement)
            .getPropertyValue(name)
            .trim() || fallback;

    /**
     * Billing details we already collected, so Stripe doesn’t ask for them again. The email always comes from the cart,
     * so the form can’t disagree with the customer’s email (the form is rebuilt when it changes).
     */
    function billingDetails() {
        const a = props.cart.billingAddress;
        if (!a?.countryCode) return {email: props.cart.email || undefined};

        return {
            name:
                a.fullName ||
                [a.firstName, a.lastName].filter(Boolean).join(' ') ||
                undefined,
            email: props.cart.email || undefined,
            address: {
                line1: a.addressLine1 || '',
                line2: a.addressLine2 || '',
                city: a.locality || '',
                state: a.administrativeArea || '',
                postal_code: a.postalCode || '',
                country: a.countryCode,
            },
        };
    }

    onMounted(async () => {
        try {
            const body = new FormData();
            body.set('gatewayId', props.gateway.id);
            if (config.returnUrlHash)
                body.set('redirect', config.returnUrlHash);
            if (props.paymentAmount)
                body.set('paymentAmount', props.paymentAmount);

            // Creates the PaymentIntent for the cart as it stands now
            const [stripeJs, {ok, json}] = await Promise.all([
                loadStripe(props.gateway.publishableKey),
                postPay(body),
            ]);

            if (!ok) throw new Error(payErrorMessage(json));

            if (json.cart?.isCompleted) {
                followRedirect(json);
                return;
            }

            stripe = stripeJs;
            transaction = json;

            elements = stripe.elements({
                clientSecret: json.redirectData.client_secret,
                appearance: {
                    theme:
                        document.documentElement.dataset.scheme === 'dark' ||
                        (document.documentElement.dataset.scheme === 'auto' &&
                            window.matchMedia('(prefers-color-scheme: dark)')
                                .matches)
                            ? 'night'
                            : 'stripe',
                    variables: {
                        colorPrimary: style('--checkout-brand', '#4f46e5'),
                        borderRadius: '8px',
                        fontFamily:
                            'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                    },
                },
            });

            // We supply the billing details at confirmation; whatever we don’t have yet, Stripe still collects (never the email)
            paymentElement = elements.create('payment', {
                layout: 'tabs',
                fields: {
                    billingDetails: props.cart.billingAddress?.countryCode
                        ? 'never'
                        : {email: 'never'},
                },
                // Prefills Link’s own sign-up fields, so the customer isn’t asked for their email again
                defaultValues: {
                    billingDetails: {
                        email: props.cart.email || undefined,
                        name: billingDetails().name,
                    },
                },
            });
            paymentElement.mount(element.value);
            paymentElement.on('ready', () => {
                loading.value = false;
                emit('ready');
            });
        } catch (e) {
            loading.value = false;
            failure.value = e.message;
            emit('error', e.message);
        }
    });

    onBeforeUnmount(() => paymentElement?.destroy());

    /**
     * Confirms the payment with Stripe. On success Stripe sends the customer to the payment action’s
     * `complete-payment` URL, which finishes the order. Resolves to an error message if it fails.
     */
    async function confirm() {
        const url = new URL(config.completePaymentUrl, window.location.origin);
        url.searchParams.set(
            'commerceTransactionHash',
            transaction.transactionHash
        );
        url.searchParams.set(
            'commerceTransactionId',
            transaction.transactionId
        );

        const {error} = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: url.toString(),
                payment_method_data: {billing_details: billingDetails()},
            },
        });

        return error ? error.message : null;
    }

    defineExpose({confirm});
</script>

<template>
    <div>
        <div
            v-if="loading"
            class="h-24 animate-pulse rounded-lg bg-zinc-100"
            aria-hidden="true"
        />
        <p
            v-if="failure"
            role="alert"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            {{ failure }}
        </p>
        <div v-show="!failure" ref="element" />
    </div>
</template>
