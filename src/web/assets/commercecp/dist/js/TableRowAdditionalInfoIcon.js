if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

Craft.Commerce.TableRowAdditionalInfoIcon = Garnish.Base.extend(
    {
        $icon: null,
        hud: null,

        init: function(icon) {
            this.$icon = $(icon);

            this.addListener(this.$icon, 'click', 'showHud');
        },

        showHud: function() {
            if (!this.hud) {
                var item = this.$icon.closest('.infoRow');
                var $hudBody = $("<div />");
                var $title = $('<h2>Details</h2>').appendTo($hudBody);
                var $table = $("<table class='data fullwidth detailHud'><tbody></tbody></table>").appendTo($hudBody);
                var $tbody = $table.find('tbody');

                var info = item.data('info');

                for (var i = 0; i < info.length; i++) {
                    var $tr = $('<tr />').appendTo($tbody);
                    var $label = $('<td><strong>' + Craft.t('commerce', info[i].label) + '</strong></td><td>').appendTo($tr);

                    var value = info[i].value;
                    var $value;

                    switch (info[i].type) {
                        case 'code':
                            $value = $('<td><code>'+value+'</code></td>');
                            break;
                        case 'response':
                            // Make sure we have proper spaces in it
                            try {
                                value = '<code class="language-json">'+JSON.stringify(JSON.parse(value), undefined, 4)+'</code>';
                            } catch (e) {
                                value = '<code class="language-xml">'+$('<div/>').text(value).html()+'</code>';
                            }

                            $value = $('<td class="highlight"><pre>'+value+'</pre></td>');
                            Prism.highlightElement($value.find('code').get(0));

                            break;
                        default:
                            $value = $('<td>'+value+'</td>');
                    }

                    $value.appendTo($tr);
                }

                this.hud = new Garnish.HUD(this.$icon, $hudBody, {
                    hudClass: 'hud'
                });
            }
            else {
                this.hud.show();
            }
        }
    });

// Borrowed from https://stackoverflow.com/a/7220510/2040791
function syntaxHighlight(json) {
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        var cls = 'number';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'key';
            } else {
                cls = 'string';
            }
        } else if (/true|false/.test(match)) {
            cls = 'boolean';
        } else if (/null/.test(match)) {
            cls = 'null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}