if (typeof Craft.Commerce === typeof undefined) {
    Craft.Commerce = {};
}

Craft.Commerce.UpdateOrderStatusModal = Garnish.Modal.extend(
    {
        id: null,
        orderStatusId: null,
        originalStatus: null,
        currentStatus: null,
        originalStatusId: null,
        $statusSelect: null,
        $selectedStatus: null,
        $orderStatusIdInput: null,
        $message: null,
        $error: null,
        $updateBtn: null,
        $statusMenuBtn: null,
        $cancelBtn: null,
        init: function(currentStatus, orderStatuses, settings) {
            this.id = Math.floor(Math.random() * 1000000000);

            this.setSettings(settings, {
                resizable: false
            });

            this.originalStatusId = currentStatus.id;
            this.currentStatus = currentStatus;

            var $form = $('<form class="modal fitted" method="post" accept-charset="UTF-8"/>').appendTo(Garnish.$bod);
            var $body = $('<div class="body"></div>').appendTo($form);
            var $inputs = $('<div class="content">' +
                '<h2 class="first">' + Craft.t('commerce', "Update Order Status") + '</h2>' +
                '</div>').appendTo($body);

            // Build menu button
            this.$statusSelect = $('<a class="btn menubtn" href="#"><span class="status ' + currentStatus.color + '"></span>' + currentStatus.name + '</a>').appendTo($inputs);
            var $menu = $('<div class="menu"/>').appendTo($inputs);
            var $list = $('<ul class="padded"/>').appendTo($menu);
            var classes = "";
            for (var i = 0; i < orderStatuses.length; i++) {
                if (this.currentStatus.id === orderStatuses[i].id) {
                    classes = "sel";
                } else {
                    classes = "";
                }
                $('<li><a data-id="' + orderStatuses[i].id + '" data-color="' + orderStatuses[i].color + '" data-name="' + orderStatuses[i].name + '" class="' + classes + '"><span class="status ' + orderStatuses[i].color + '"></span>' + orderStatuses[i].name + '</a></li>').appendTo($list);
            }

            this.$selectedStatus = $('.sel', $list);

            // Build message input
            this.$message = $('<div class="field">' +
                '<div class="heading">' +
                '<label>' + Craft.t('commerce', 'Message') + '</label>' +
                '<div class="instructions"><p>' + Craft.t('commerce', 'Status change message') + '.</p>' +
                '</div>' +
                '</div>' +
                '<div class="input ltr">' +
                '<textarea class="text fullwidth" rows="2" cols="50" name="message" maxlength="10000"></textarea>' +
                '</div>' +
                '</div>').appendTo($inputs);

            // Error notice area
            this.$error = $('<div class="error"/>').appendTo($inputs);

            // Footer and buttons
            var $footer = $('<div class="footer"/>').appendTo($form);
            var $mainBtnGroup = $('<div class="btngroup right"/>').appendTo($footer);
            this.$cancelBtn = $('<input type="button" class="btn" value="' + Craft.t('commerce', 'Cancel') + '"/>').appendTo($mainBtnGroup);
            this.$updateBtn = $('<input type="button" class="btn submit" value="' + Craft.t('commerce', 'Update') + '"/>').appendTo($mainBtnGroup);

            this.$updateBtn.addClass('disabled');

            // Listeners and
            this.$statusMenuBtn = new Garnish.MenuBtn(this.$statusSelect, {
                onOptionSelect: $.proxy(this, 'onSelectStatus')
            });

            this.addListener(this.$cancelBtn, 'click', 'hide');
            this.addListener(this.$updateBtn, 'click', function(ev) {
                ev.preventDefault();
                if (!$(ev.target).hasClass('disabled')) {
                    this.updateStatus();
                }
            });
            this.base($form, settings);
        },
        onSelectStatus: function(status) {
            this.deselectStatus();

            this.$selectedStatus = $(status);

            this.$selectedStatus.addClass('sel');

            this.currentStatus = {
                id: $(status).data('id'),
                name: $(status).data('name'),
                color: $(status).data('color')
            };

            var newHtml = "<span><span class='status " + this.currentStatus.color + "'></span>" + Craft.uppercaseFirst(this.currentStatus.name) + "</span>";
            this.$statusSelect.html(newHtml);

            if (this.originalStatusId === this.currentStatus.id) {
                this.$updateBtn.addClass('disabled');
            }
            else {
                this.$updateBtn.removeClass('disabled');
            }
        },

        deselectStatus: function() {
            if (this.$selectedStatus) {
                this.$selectedStatus.removeClass('sel');
            }
        },

        updateStatus: function() {
            var data = {
                'orderStatusId': this.currentStatus.id,
                'message': this.$message.find('textarea[name="message"]').val(),
                'color': this.currentStatus.color,
                'name': this.currentStatus.name
            };

            this.settings.onSubmit(data);
        },
        defaults: {
            onSubmit: $.noop
        }
    });
