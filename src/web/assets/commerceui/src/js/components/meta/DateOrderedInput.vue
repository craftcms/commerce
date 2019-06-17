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

        computed: {
            computedDateTimeValue() {
                return this.dateValue + ';' + this.timeValue
            }
        },

        methods: {
            formatAMPM(date) {
                let hours = date.getHours();
                let minutes = date.getMinutes();
                let ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12; // the hour '0' should be '12'
                minutes = minutes < 10 ? '0'+minutes : minutes;
                let strTime = hours + ':' + minutes + ' ' + ampm;
                return strTime;
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

            this.dateValue = this.date.date
            this.timeValue = this.date.time
        }
    }
</script>