/**
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2014, Pixel & Tonic, Inc.
 * @license   http://buildwithcraft.com/license Craft License Agreement
 * @see       http://buildwithcraft.com
 * @package   craft.app.resources
 */

Craft.MarketNav = Garnish.Base.extend(
{
    $nav: null,

    init: function(subnav)
    {
        this.$nav = $('#nav-market');

        $('> a', this.$nav).addClass('menubtn');
        $('> a', this.$nav).on('click', function(e) {
            e.preventDefault();
        });


        subnavHtml = '<div id="nav-market-menu" class="menu"><ul>';

        $(subnav).each(function(key, item) {
            console.log(item.selected);
            subnavHtml += '<li>';
            subnavHtml += '<a href="'+Craft.getUrl(item.url)+'"';
            if(item.selected)
            {
                subnavHtml += ' class="sel"';
            }
            subnavHtml += '>'+item.title+'</a>';
            subnavHtml += '</li>';
        });

        subnavHtml += '<ul></div>';

        this.$nav.append(subnavHtml);
    }
});