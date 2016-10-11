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
                var $hudBody = $("<div />");
                var $title = $('<h2>Details</h2>').appendTo($hudBody);
                var $table = $("<table class='data fullwidth'><tbody></tbody></table>").appendTo($hudBody);
                var $tbody = $table.find('tbody');

                var info = item.data('info');

                for(i=0; i < info.length; i++)
                {
                    var $tr = $('<tr />').appendTo($tbody);
                    var $label = $('<td><strong>'+Craft.t(info[i].label)+'</strong></td><td>').appendTo($tr);
                    var $value = $('<td>'+info[i].value+'</td>').appendTo($tr);
                }

                this.hud = new Garnish.HUD(this.$icon, $hudBody, {
                    hudClass: 'hud',
                });
            }
            else
            {
                this.hud.show();
            }
        }
    });
