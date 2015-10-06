Craft.Commerce = Craft.Commerce || {};

Craft.Commerce.TableRowAdditionalInfoIcon = Garnish.Base.extend(
    {
        $icon: null,
        hud: null,

        init: function (icon) {
            this.$icon = $(icon);

            this.addListener(this.$icon, 'click', 'showHud');
        },

        showHud: function () {
            if (!this.hud) {

                var item = this.$icon.closest('.infoRow');
                var $hudbody = $("<div/>");
                var $html = $("<div><h2>Details</h2><table class='data fullwidth'><tbody>" +
                    "<tr><td><strong>" + Craft.t('Description') + "</strong></td><td> " + item.data('description') + "</td></tr>" +
                    "<tr><td><strong>" + Craft.t('Quantity') + "</strong></td><td> " + item.data('qty') + "</td></tr>" +
                    "<tr><td><strong>" + Craft.t('Tax Category') + "</strong></td><td><a href='" + item.data('taxcategoryurl') + "'>" + item.data('taxcategory') + "</a></td></tr>" +
                    "<tr><td><strong>" + Craft.t('Price') + "</strong></td><td> " + item.data('price') + "</td></tr>" +
                    "<tr><td><strong>" + Craft.t('Sale Amount') + "</strong></td><td> " + item.data('saleamount') + "</td></tr>" +
                    "<tr><td><strong>" + Craft.t('Sale Price') + "</strong></td><td> " + item.data('saleprice') + "</td></tr>" +
                    "<tr><td><strong>" + Craft.t('Tax') + "</strong></td><td> " + item.data('tax') + "</td></tr>" +
                    "<tr><td><strong>" + Craft.t('Shipping Cost') + "</strong></td><td> " + item.data('shippingcost') + "</td></tr>" +
                    "<tr><td><strong>" + Craft.t('Discount') + "</strong></td><td> " + item.data('discount') + "</td></tr>" +
                    "<tr><td><strong>" + Craft.t('Total') + "</strong></td><td> " + item.data('total') + "</td></tr>" +
                    "<tr><td><strong>" + Craft.t('Note') + "</strong></td><td> " + item.data('note') + "</td></tr>" +
                    "<tr><td><strong>" + Craft.t('On Sale?') + "</strong></td><td> " + item.data('onsale') + "</td></tr>" +
                    "</tbody></table></div>");

                $hudbody.append($html);

                this.hud = new Garnish.HUD(this.$icon, $hudbody, {
                    hudClass: 'hud',
                });
            }
            else {
                this.hud.show();
            }
        }
    });