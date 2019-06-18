<template>
    <div class="datetimewrapper">
        <div class="datewrapper">
            <input
                    v-model="dateValue"
                    ref="dateInput"
                    type="text"
                    class="text"
                    autocomplete="false"
                    size="10"
                    placeholder=" "
            />

            <div data-icon="date"></div>
        </div>
        <div class="timewrapper">
            <input
                    v-model="timeValue"
                    ref="timeInput"
                    type="text"
                    class="text"
                    autocomplete="false"
                    size="10"
                    placeholder=" "
            />

            <div data-icon="time"></div>
        </div>
    </div>
</template>


<script>
    /* global Craft */
    /* global $ */

    export default {
        props: {
            date: {
                type: Object,
            }
        },

        data() {
            return {
                dateValue: '',
                timeValue: '',
            }
        },

        mounted() {
            // Date
            $(this.$refs.dateInput).datepicker($.extend({
                // defaultDate: new Date(2019, 1, 24)
            }, Craft.datepickerOptions))

            $(this.$refs.dateInput).on('change', function(event) {
                this.dateValue = event.target.value
            })

            // Time
            $(this.$refs.timeInput).timepicker($.extend({}, Craft.timepickerOptions))

            $(this.$refs.timeInput).on('change', function(event) {
                this.timeValue = event.target.value
            })

            // Update values
            this.dateValue = this.date.date
            this.timeValue = this.date.time
        }
    }
</script>