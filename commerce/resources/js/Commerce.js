(function($){

if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

Craft.Commerce.initUnlimitedStockCheckbox = function ($container){
    $container.find('input.unlimited-stock:first').change(Craft.Commerce.handleUnlimitedStockCheckboxChange);
}

Craft.Commerce.handleUnlimitedStockCheckboxChange = function(ev)
{
    var $checkbox = $(ev.currentTarget),
        $text = $checkbox.parent().prevAll('.textwrapper:first').children('.text:first');

    if ($checkbox.prop('checked'))
    {
        $text.prop('disabled', true).addClass('disabled').val('');
    }
    else
    {
        $text.prop('disabled', false).removeClass('disabled').focus();
    }
};

})(jQuery);
