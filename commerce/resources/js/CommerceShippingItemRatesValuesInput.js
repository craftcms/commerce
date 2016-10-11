if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

/**
 * Class Craft.Commerce.RevenueWidget
 */
Craft.Commerce.CommerceShippingItemRatesValuesInput = Craft.BaseInputGenerator.extend({
    startListening: function ()
    {
        if (this.listening)
        {
            return;
        }

        this.listening = true;

        this.addListener(this.$source, 'textchange', 'onTextChange');
        this.addListener(this.$form, 'submit', 'onFormSubmit');
    },
    updateTarget: function ()
    {
        var sourceVal = this.$source.val();
        var targetVal = this.generateTargetValue(sourceVal);
        console.log(sourceVal);
        this.$target.prop('placeholder', targetVal);
    },
    onFormSubmit: function ()
    {
        if (this.timeout)
        {
            clearTimeout(this.timeout);
        }
    }
});
