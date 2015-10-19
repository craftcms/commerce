Craft.Commerce = Craft.Commerce || {};

Craft.Commerce.UpdateOrderStatusModal = Garnish.Modal.extend(
    {
        id: null,
        orderStatusId: null,
        $statusSelect: null,
        $orderStatusIdInput: null,
        $message: null,
        $error: null,
        $updateBtn: null,
        $cancelBtn: null,
        init: function (currentStatusHandle, orderStatuses, settings) {
            var self = this;
            this.setSettings(settings, {
                resizable: false
            });
            var currentStatus = {};
            for (i = 0; i < orderStatuses.length; i++) {
                if (currentStatusHandle == orderStatuses[i].handle) {
                    currentStatus = orderStatuses[i];
                }
            }

            this.id = Math.floor(Math.random() * 1000000000);

            var $form = $('<form class="modal fitted" method="post" accept-charset="UTF-8"/>').appendTo(Garnish.$bod);
            var $body = $('<div class="body"></div>').appendTo($form);
            var $inputs = $('<div class="content">' +
                '<input type="hidden" name="orderId" value="' + this.orderId + '"/>' +
                '<input type="hidden" name="action" value="' + Craft.getActionUrl('commerce/orders/updateStatus') + '"/>' +
                Craft.getCsrfInput() +
                '<h2 class="first">' + Craft.t("Update Order Status") + '</h2>' +
                '</div>').appendTo($body);

            this.$orderStatusIdInput = $('<input type="hidden" name="orderStatusId" value="' + currentStatus.id + '"/>').appendTo($body);

            this.$statusSelect = $('<a class="btn menubtn" href="#"><span class="commerce status ' + currentStatus.color + '"></span>' + currentStatus.name + '</a>').appendTo($inputs);

            var $menu = $('<div class="menu"/>').appendTo($inputs);

            var $list = $('<ul class="padded"/>').appendTo($menu);

            var classes = "";
            for (i = 0; i < orderStatuses.length; i++) {
                if (currentStatusHandle == orderStatuses[i].handle) {
                    classes = "sel";
                } else {
                    classes = "";
                }
                $('<li><a data-id="' + orderStatuses[i].id + '" data-color="' + orderStatuses[i].color + '" data-name="' + orderStatuses[i].name + '" class="' + classes + '" href="#"><span class="commerce status ' + orderStatuses[i].color + '"></span>' + orderStatuses[i].name + '</a></li>').appendTo($list);
            }

            this.$message = $('<div class="field">' +
                '<div class="heading">' +
                '<label>' + Craft.t('Message') + '</label>' +
                '<div class="instructions"><p>' + Craft.t('Status change message') + '.</p>' +
                '</div>' +
                '</div>' +
                '<div class="input ltr">' +
                '<textarea class="text fullwidth" rows="2" cols="50" name="message"></textarea>' +
                '</div>' +
                '</div>').appendTo($inputs);

            this.$error = $('<div class="error"/>').appendTo($inputs);

            var $footer = $('<div class="footer"/>').appendTo($form);
            var $btnGroup = $('<div class="btngroup"/>').appendTo($footer);
            var $mainBtnGroup = $('<div class="btngroup right"/>').appendTo($footer);

            this.$updateBtn = $('<input type="button" class="btn submit disabled" value="' + Craft.t('Update') + '"/>').appendTo($mainBtnGroup);
            this.$cancelBtn = $('<input type="button" class="btn" value="' + Craft.t('Cancel') + '"/>').appendTo($btnGroup);

            new Garnish.MenuBtn(this.$statusSelect, {
                onOptionSelect: function (data) {
                    self.orderStatusId = $(data).data('id');
                    self.orderStatusName = $(data).data('name');
                    self.orderStatusColor = $(data).data('color');
                    self.$orderStatusIdInput.val(self.orderStatusId);
                    var newHtml = "<span><span class='commerce status " + self.orderStatusColor + "'></span>" + Craft.uppercaseFirst(self.orderStatusName) + "</span>";
                    self.$statusSelect.html(newHtml);

                    if (self.orderStatusId == currentStatus.id) {
                        self.$updateBtn.addClass('disabled');
                    } else {
                        self.$updateBtn.removeClass('disabled');
                    }
                }
            });


            this.addListener(this.$cancelBtn, 'click', 'hide');
            this.addListener(this.$updateBtn, 'click', function (ev) {
                ev.preventDefault();
                if (!$(ev.target).hasClass('disabled')) {
                    this.updateStatus();
                }
            });
            this.base($form, settings);
        },
        updateStatus: function () {
            var data = {
                'orderStatusId': this.orderStatusId,
                'message': this.$message.find('textarea[name="message"]').val()
            }

            this.settings.onSubmit(data);
        },
        defaults: {
            onSubmit: $.noop
        }
    });
