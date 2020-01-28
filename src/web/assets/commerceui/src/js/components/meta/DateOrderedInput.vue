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
                    @input="onDateInput"
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
                    @input="onDateInput"
            />

            <div data-icon="time"></div>
        </div>
    </div>
</template>


<script>
    /* global Craft */
    /* global $ */

    import debounce from 'lodash.debounce'

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

        methods: {
            onDateChange() {
                this.$emit('update', {
                    date: this.dateValue,
                    time: this.timeValue,
                })
            },

            onDateInput: debounce(function() {
                this.onDateChange()
            }, 1000)
        },

        mounted() {
            // Date
            $(this.$refs.dateInput).datepicker($.extend({
                defaultDate: new Date()
            }, Craft.datepickerOptions))

            $(this.$refs.dateInput).on('change', function(event) {
                this.dateValue = event.target.value
                this.onDateChange()
            }.bind(this))

            // Time
            $(this.$refs.timeInput).timepicker($.extend({}, Craft.timepickerOptions))

            $(this.$refs.timeInput).on('change', function(event) {
                this.timeValue = event.target.value
                this.onDateChange()
            }.bind(this))

            // Update values
            this.dateValue = this.date.date
            this.timeValue = this.date.time
        }
    }
</script>
