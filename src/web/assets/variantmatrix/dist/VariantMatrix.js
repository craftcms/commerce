!function(){function t(e){return t="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(t){return typeof t}:function(t){return t&&"function"==typeof Symbol&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},t(e)}!function(e){"undefined"===t(Craft.Commerce)&&(Craft.Commerce={}),Craft.Commerce.VariantMatrix=Garnish.Base.extend({id:null,fieldBodyHtml:null,fieldFootHtml:null,inputNamePrefix:null,inputIdPrefix:null,$container:null,$variantContainer:null,$addVariantBtn:null,variantSort:null,variantSelect:null,defaultVariant:null,totalNewVariants:0,singleColumnMode:!1,init:function(t,i,n,s){this.id=t,this.fieldBodyHtml=i,this.fieldFootHtml=n,this.inputNamePrefix=s,this.inputIdPrefix=Craft.formatInputId(this.inputNamePrefix),this.$container=e("#"+this.id),this.$variantContainer=this.$container.children(".blocks"),this.$addVariantBtn=this.$container.children(".btn");var r=this.$variantContainer.children(),l=Craft.Commerce.VariantMatrix.getCollapsedVariantIds();this.variantSort=new Garnish.DragSort(r,{handle:"> .actions > .move",axis:"y",filter:e.proxy((function(){return this.variantSort.$targetItem.hasClass("sel")?this.variantSelect.getSelectedItems():this.variantSort.$targetItem}),this),collapseDraggees:!0,magnetStrength:4,helperLagBase:1.5,helperOpacity:.9,onSortChange:e.proxy((function(){this.variantSelect.resetItemOrder()}),this)}),this.variantSelect=new Garnish.Select(this.$variantContainer,r,{multi:!0,vertical:!0,handle:"> .checkbox, > .titlebar",checkboxMode:!0});for(var o=0;o<r.length;o++){var d=r.eq(o),c="string"==typeof(t=d.data("id"))&&t.match(/new(\d+)/);c&&c[1]>this.totalNewVariants&&(this.totalNewVariants=parseInt(c[1]));var h=new a(this,d);h.id&&-1!==e.inArray(""+h.id,l)&&h.collapse(),Craft.Commerce.initUnlimitedStockCheckbox(d)}this.addListener(this.$addVariantBtn,"click",(function(){this.addVariant()})),this.addListener(this.$container,"resize","handleContainerResize"),Garnish.$doc.ready(e.proxy(this,"handleContainerResize")),this.$container.width()&&this.handleContainerResize()},setDefaultVariant:function(t){this.defaultVariant&&this.defaultVariant.unsetAsDefault(),t.setAsDefault(),this.defaultVariant=t},addVariant:function(t){this.totalNewVariants++;var i="new"+this.totalNewVariants,n=e('<div class="variant-matrixblock matrixblock" data-id="'+i+'"><input type="hidden" name="'+this.inputNamePrefix+"["+i+'][enabled]" value="1"/><input class="default-input" type="hidden" name="'+this.inputNamePrefix+"["+i+'][isDefault]" value=""><div class="titlebar"><div class="preview"></div></div><div class="checkbox" title="'+Craft.t("commerce","Select")+'"></div><div class="actions"><div class="status off" title="'+Craft.t("commerce","Disabled")+'"></div><a class="default-btn" title="'+Craft.t("commerce","Set as the default variant")+'">'+Craft.t("commerce","Default")+'</a> <a class="settings icon menubtn" title="'+Craft.t("commerce","Actions")+'" role="button"></a> <div class="menu"><ul class="padded"><li><a data-icon="collapse" data-action="collapse">'+Craft.t("commerce","Collapse")+'</a></li><li class="hidden"><a data-icon="expand" data-action="expand">'+Craft.t("commerce","Expand")+'</a></li><li><a data-icon="disabled" data-action="disable">'+Craft.t("commerce","Disable")+'</a></li><li class="hidden"><a data-icon="enabled" data-action="enable">'+Craft.t("commerce","Enable")+'</a></li></ul><hr class="padded"/><ul class="padded"><li><a data-icon="+" data-action="add">'+Craft.t("commerce","Add variant above")+'</a></li></ul><hr class="padded"/><ul class="padded"><li><a data-icon="remove" data-action="delete">'+Craft.t("commerce","Delete")+'</a></li></ul></div><a class="move icon" title="'+Craft.t("commerce","Reorder")+'" role="button"></a> </div></div>');t?n.insertBefore(t):n.appendTo(this.$variantContainer);var s=e('<div class="fields"/>').appendTo(n),r=this.getParsedVariantHtml(this.fieldBodyHtml,i),l=this.getParsedVariantHtml(this.fieldFootHtml,i),o=e(r);o.find("#related-sales-field").remove(),o.appendTo(s),this.singleColumnMode&&this.setVariantsToSingleColMode(n),n.css(this.getHiddenVariantCss(n)).velocity({opacity:1,"margin-bottom":10},"fast",e.proxy((function(){n.css("margin-bottom",""),Garnish.$bod.append(l),Craft.initUiElements(s),Craft.Commerce.initUnlimitedStockCheckbox(n);var t=new a(this,n);this.variantSort.addItems(n),this.variantSelect.addItems(n),Garnish.requestAnimationFrame((function(){Garnish.scrollContainerToElement(n)})),1===this.$variantContainer.children().length&&this.setDefaultVariant(t)}),this))},collapseSelectedVariants:function(){this.callOnSelectedVariants("collapse")},expandSelectedVariants:function(){this.callOnSelectedVariants("expand")},disableSelectedVariants:function(){this.callOnSelectedVariants("disable")},enableSelectedVariants:function(){this.callOnSelectedVariants("enable")},deleteSelectedVariants:function(){this.callOnSelectedVariants("selfDestruct")},callOnSelectedVariants:function(t){for(var e=0;e<this.variantSelect.$selectedItems.length;e++)this.variantSelect.$selectedItems.eq(e).data("variant")[t]()},getHiddenVariantCss:function(t){return{opacity:0,marginBottom:-t.outerHeight()}},getParsedVariantHtml:function(t,e){return"string"==typeof t?t.replace(/__VARIANT__/g,e):""},handleContainerResize:function(){this.$container.width()<700?this.singleColumnMode||(this.setVariantsToSingleColMode(this.variantSort.$items),this.singleColumnMode=!0):this.singleColumnMode&&(this.setVariantsToTwoColMode(this.variantSort.$items),this.variantSort.$items.removeClass("single-col"),this.singleColumnMode=!1)},setVariantsToSingleColMode:function(t){t.addClass("single-col").find("> .fields > .custom-fields").addClass("meta")},setVariantsToTwoColMode:function(t){t.removeClass("single-col").find("> .fields > .custom-fields").removeClass("meta")}},{collapsedVariantStorageKey:"Craft-"+Craft.siteUid+".Commerce.VariantMatrix.collapsedVariants",getCollapsedVariantIds:function(){return"string"==typeof localStorage[Craft.Commerce.VariantMatrix.collapsedVariantStorageKey]?Craft.filterArray(localStorage[Craft.Commerce.VariantMatrix.collapsedVariantStorageKey].split(",")):[]},setCollapsedVariantIds:function(t){localStorage[Craft.Commerce.VariantMatrix.collapsedVariantStorageKey]=t.join(",")},rememberCollapsedVariantId:function(t){if("undefined"!=typeof Storage){var a=Craft.Commerce.VariantMatrix.getCollapsedVariantIds();-1===e.inArray(""+t,a)&&(a.push(t),Craft.Commerce.VariantMatrix.setCollapsedVariantIds(a))}},forgetCollapsedVariantId:function(t){if("undefined"!=typeof Storage){var a=Craft.Commerce.VariantMatrix.getCollapsedVariantIds(),i=e.inArray(""+t,a);-1!==i&&(a.splice(i,1),Craft.Commerce.VariantMatrix.setCollapsedVariantIds(a))}}});var a=Garnish.Base.extend({matrix:null,$container:null,$titlebar:null,$fieldsContainer:null,$previewContainer:null,$actionMenu:null,$collapsedInput:null,$defaultInput:null,$defaultBtn:null,isNew:null,id:null,collapsed:!1,init:function(t,a){this.matrix=t,this.$container=a,this.$titlebar=a.children(".titlebar"),this.$previewContainer=this.$titlebar.children(".preview"),this.$fieldsContainer=a.children(".fields"),this.$defaultInput=this.$container.children(".default-input"),this.$defaultBtn=this.$container.find("> .actions > .default-btn"),this.$container.data("variant",this),this.id=this.$container.data("id"),this.isNew=!this.id||"string"==typeof this.id&&"new"===this.id.substr(0,3);var i=this.$container.find("> .actions > .settings"),n=new Garnish.MenuBtn(i);this.$actionMenu=n.menu.$container,n.menu.settings.onOptionSelect=e.proxy(this,"onMenuOptionSelect"),Garnish.hasAttr(this.$container,"data-collapsed")&&this.collapse(),this.addListener(this.$titlebar,"dblclick",(function(t){t.preventDefault(),this.toggle()})),"1"===this.$defaultInput.val()&&this.matrix.setDefaultVariant(this),this.addListener(this.$defaultBtn,"click",(function(t){t.preventDefault(),this.matrix.setDefaultVariant(this)}))},toggle:function(){this.collapsed?this.expand():this.collapse(!0)},collapse:function(t){if(!this.collapsed){this.$container.addClass("collapsed");for(var a="",i=this.$fieldsContainer.find("> .meta > .field:first-child, > .custom-fields .field"),n=0;n<i.length;n++){for(var s=e(i[n]).children(".input").find('select,input[type!="hidden"],textarea,.label'),r="",l=0;l<s.length;l++){var o,d=e(s[l]);if(d.hasClass("label")){var c=d.parent().parent();if(c.hasClass("lightswitch")&&(c.hasClass("on")&&d.hasClass("off")||!c.hasClass("on")&&d.hasClass("on")))continue;o=d.text()}else o=Craft.getText(Garnish.getInputPostVal(d));o instanceof Array&&(o=o.join(", ")),o&&(o=Craft.trim(o))&&(r&&(r+=", "),r+=o)}r&&(a+=(a?" <span>|</span> ":"")+r)}this.$previewContainer.html(a),this.$fieldsContainer.velocity("stop"),this.$container.velocity("stop"),t?(this.$fieldsContainer.velocity("fadeOut",{duration:"fast"}),this.$container.velocity({height:30},"fast")):(this.$previewContainer.show(),this.$fieldsContainer.hide(),this.$container.css({height:30})),setTimeout(e.proxy((function(){this.$actionMenu.find("a[data-action=collapse]:first").parent().addClass("hidden"),this.$actionMenu.find("a[data-action=expand]:first").parent().removeClass("hidden")}),this),200),this.isNew?this.$collapsedInput?this.$collapsedInput.val("1"):this.$collapsedInput=e('<input type="hidden" name="'+this.matrix.inputNamePrefix+"["+this.id+'][collapsed]" value="1"/>').appendTo(this.$container):Craft.Commerce.VariantMatrix.rememberCollapsedVariantId(this.id),this.collapsed=!0}},expand:function(){if(this.collapsed){this.$container.removeClass("collapsed"),this.$fieldsContainer.velocity("stop"),this.$container.velocity("stop");var t=this.$container.height();this.$container.height("auto"),this.$fieldsContainer.css("display","flex");var a=this.$container.height();if(this.$container.height(t),this.$fieldsContainer.hide().velocity("fadeIn",{duration:"fast",display:"flex"}),this.$container.velocity({height:a},"fast",e.proxy((function(){this.$previewContainer.html(""),this.$container.height("auto")}),this)),setTimeout(e.proxy((function(){this.$actionMenu.find("a[data-action=collapse]:first").parent().removeClass("hidden"),this.$actionMenu.find("a[data-action=expand]:first").parent().addClass("hidden")}),this),200),!this.isNew&&"undefined"!=typeof Storage){var i=Craft.Commerce.VariantMatrix.getCollapsedVariantIds(),n=e.inArray(""+this.id,i);-1!==n&&(i.splice(n,1),Craft.Commerce.VariantMatrix.setCollapsedVariantIds(i))}this.isNew?this.$collapsedInput&&this.$collapsedInput.val(""):Craft.Commerce.VariantMatrix.forgetCollapsedVariantId(this.id),this.collapsed=!1}},disable:function(){return this.$container.children('input[name$="[enabled]"]:first').val(""),this.$container.addClass("disabled"),setTimeout(e.proxy((function(){this.$actionMenu.find("a[data-action=disable]:first").parent().addClass("hidden"),this.$actionMenu.find("a[data-action=enable]:first").parent().removeClass("hidden")}),this),200),this.collapse(!0),!0},enable:function(){return this.$container.children('input[name$="[enabled]"]:first').val("1"),this.$container.removeClass("disabled"),setTimeout(e.proxy((function(){this.$actionMenu.find("a[data-action=disable]:first").parent().removeClass("hidden"),this.$actionMenu.find("a[data-action=enable]:first").parent().addClass("hidden")}),this),200),!0},setAsDefault:function(){this.$defaultInput.val("1"),this.$defaultBtn.addClass("sel").attr("title","")},unsetAsDefault:function(){this.$defaultInput.val(""),this.$defaultBtn.removeClass("sel").attr("title","Set as the default variant"),this.$actionMenu.find("a[data-action=disable]:first").parent().removeClass("disabled")},isDefault:function(){return"1"===this.$defaultInput.val()},onMenuOptionSelect:function(t){var a=this.matrix.variantSelect.totalSelected>1&&this.matrix.variantSelect.isSelected(this.$container);switch(e(t).data("action")){case"collapse":a?this.matrix.collapseSelectedVariants():this.collapse(!0);break;case"expand":a?this.matrix.expandSelectedVariants():this.expand();break;case"disable":a?this.matrix.disableSelectedVariants():this.disable();break;case"enable":a?this.matrix.enableSelectedVariants():(this.enable(),this.expand());break;case"add":this.matrix.addVariant(this.$container);break;case"delete":a?confirm(Craft.t("commerce","Are you sure you want to delete the selected variants?"))&&this.matrix.deleteSelectedVariants():this.selfDestruct()}},selfDestruct:function(){this.$container.velocity(this.matrix.getHiddenVariantCss(this.$container),"fast",e.proxy((function(){if(this.$container.remove(),this.isDefault()){var t=this.matrix.$variantContainer.children(":first-child").data("variant");t&&this.matrix.setDefaultVariant(t)}}),this))}})}(jQuery)}();
//# sourceMappingURL=VariantMatrix.js.map