/**
 * Editable table class
 */

if (typeof Craft.Market == 'undefined')
{
    Craft.Market = {};
}

Craft.Market.EditableTable = Garnish.Base.extend(
    {
        id: null,
        baseName: null,
        columns: null,
        sorter: null,
        biggestId: -1,

        $table: null,
        $tbody: null,
        $addRowBtn: null,

        init: function(id, baseName, columns, settings)
        {
            this.id = id;
            this.baseName = baseName;
            this.columns = columns;
            this.setSettings(settings, Craft.Market.EditableTable.defaults);

            this.$table = $('#'+id);
            this.$tbody = this.$table.children('tbody');

            this.sorter = new Craft.DataTableSorter(this.$table, {
                helperClass: 'editabletablesorthelper',
                copyDraggeeInputValuesToHelper: true
            });

            var $rows = this.$tbody.children();

            for (var i = 0; i < $rows.length; i++)
            {
                new Craft.EditableTable.Row(this, $rows[i]);
            }

            this.$addRowBtn = this.$table.next('.add');
            this.addListener(this.$addRowBtn, 'activate', 'addRow');
        },

        addRow: function()
        {
            var rowId = this.settings.rowIdPrefix+(this.biggestId+1),
                rowHtml = Craft.Market.EditableTable.getRowHtml(rowId, this.columns, this.baseName, {}),
                $tr = $(rowHtml).appendTo(this.$tbody);

            new Craft.EditableTable.Row(this, $tr);
            this.sorter.addItems($tr);

            // Focus the first input in the row
            $tr.find('input,textarea,select').first().focus();

            // onAddRow callback
            this.settings.onAddRow($tr);
        }
    },
    {
        textualColTypes: ['singleline', 'multiline', 'number'],
        defaults: {
            rowIdPrefix: '',
            onAddRow: $.noop,
            onDeleteRow: $.noop
        },

        getRowHtml: function(rowId, columns, baseName, values)
        {
            var rowHtml = '<tr data-id="'+rowId+'">';

            for (var colId in columns)
            {
                var col = columns[colId],
                    name = baseName+'['+rowId+']['+col.attribute+']',
                    value = (typeof values[col.attribute] != 'undefined' ? values[col.attribute] : ''),
                    textual = Craft.inArray(col.type, Craft.Market.EditableTable.textualColTypes);

                rowHtml += '<td class="'+(textual ? 'textual' : '')+' '+(typeof col['class'] != 'undefined' ? col['class'] : '')+'"' +
                (typeof col['width'] != 'undefined' ? ' width="'+col['width']+'"' : '') +
                '>';

                switch (col.type)
                {
                    case 'select':
                    {
                        rowHtml += '<div class="select small"><select name="'+name+'">';

                        var hasOptgroups = false;

                        for (var key in col.options)
                        {
                            var option = col.options[key];

                            if (typeof option.optgroup != 'undefined')
                            {
                                if (hasOptgroups)
                                {
                                    rowHtml += '</optgroup>';
                                }
                                else
                                {
                                    hasOptgroups = true;
                                }

                                rowHtml += '<optgroup label="'+option.optgroup+'">';
                            }
                            else
                            {
                                var optionLabel = (typeof option.label != 'undefined' ? option.label : option),
                                    optionValue = (typeof option.value != 'undefined' ? option.value : key),
                                    optionDisabled = (typeof option.disabled != 'undefined' ? option.disabled : false);

                                rowHtml += '<option value="'+optionValue+'"'+(optionValue == value ? ' selected' : '')+(optionDisabled ? ' disabled' : '')+'>'+optionLabel+'</option>';
                            }
                        }

                        if (hasOptgroups)
                        {
                            rowHtml += '</optgroup>';
                        }

                        rowHtml += '</select></div>';

                        break;
                    }

                    case 'checkbox':
                    {
                        rowHtml += '<input type="hidden" name="'+name+'">' +
                        '<input type="checkbox" name="'+name+'" value="1"'+(value ? ' checked' : '')+'>';

                        break;
                    }

                    default:
                    {
                        rowHtml += '<textarea name="'+name+'" rows="1">'+value+'</textarea>';
                    }
                }

                rowHtml += '</td>';
            }

            rowHtml += '<td class="thin action"><a class="move icon" title="'+Craft.t('Reorder')+'"></a></td>' +
            '<td class="thin action"><a class="delete icon" title="'+Craft.t('Delete')+'"></a></td>' +
            '</tr>';

            return rowHtml;
        }
    });