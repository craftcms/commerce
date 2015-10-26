if (typeof Craft.Commerce === typeof undefined)
{
    Craft.Commerce = {};
}

Craft.Commerce.TableRowAdditionalInfoIcon = Garnish.Base.extend(
    {
        $icon: null,
        hud: null,

        init: function (icon)
        {
            this.$icon = $(icon);

            this.addListener(this.$icon, 'click', 'showHud');
        },

        showHud: function ()
        {
            if (!this.hud)
            {

                var item = this.$icon.closest('.infoRow');
                var $hudbody = $("<div/>");
                var $table = $("<div><h2>Details</h2><table class='data fullwidth'><tbody></tbody></table>").appendTo($hudbody);

                var $tbody = $table.find('tbody');

                var rows = item.data('inforow');
                for(i=0;i<rows.length;i++){
                    $('<tr><td><strong>'+Craft.t(rows[i].label)+'</strong></td><td>'+item.data(rows[i].attribute)+'</td></tr>').appendTo($tbody);
                }

                this.hud = new Garnish.HUD(this.$icon, $hudbody, {
                    hudClass: 'hud',
                });
            }
            else
            {
                this.hud.show();
            }
        }
    });
