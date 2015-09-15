Craft.MarketVariantIndex = Craft.BaseElementIndex.extend(
    {
        $newVariantBtnGroup: null,
        $newVariantBtn: null,
        showingSidebar: false,

        afterInit: function()
        {
            this.$newVariantBtnGroup = $('<div class="btngroup submit"/>');
            this.$newVariantBtn = $('<a class="btn submit add icon" data-productId="{{ product.id }}" data-productTypeId="{{ productType.id }}">New Variant</a>').appendTo(this.$newVariantBtnGroup);

            this.addListener(this.$newVariantBtn, 'click', function(ev)
            {
                this._openCreateVariantModal(ev.currentTarget.getAttribute('data-productId'),ev.currentTarget.getAttribute('data-productTypeId'));
            });

            this.addButton(this.$newVariantBtnGroup);
            return this.base();
        },

        _openCreateVariantModal:function(productId, productTypeId){
            if (this.$newVariantBtn.hasClass('loading'))
            {
                return;
            }
            this.$newVariantBtn.addClass('inactive');

            new Craft.ElementEditor({
                hudTrigger: this.$newVariantBtnGroup,
                elementType: 'Market_Variant',
                locale: this.locale,
                attributes: {
                    productTypeId: productTypeId,
                    productId: productId,
                },
                onBeginLoading: $.proxy(function()
                {
                    this.$newVariantBtn.addClass('loading');
                }, this),
                onEndLoading: $.proxy(function()
                {
                    this.$newVariantBtn.removeClass('loading');
                    console.log(productTypeId,productId);
                }, this),
                onHideHud: $.proxy(function()
                {
                    this.$newVariantBtn.removeClass('inactive').text("New Variant");
                }, this),
                onSaveElement: $.proxy(function(response)
                {
                    //this.selectElementAfterUpdate(response.id);
                    this.updateElements();
                }, this)
            });
        }
    });