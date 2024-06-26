if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.DownloadOrderPdfAction = Garnish.Base.extend({
  action: null,
  $btn: null,
  hud: null,
  types: null,
  $hudBody: null,

  init: function (btn, pdfs, types, action) {
    this.action = action;
    this.$btn = btn;
    this.pdfs = pdfs;
    this.types = types;

    this.$hudBody = $('<div/>', {
      class: 'export-form',
    });

    this.addListener(this.$btn, 'click', 'showHud');
  },

  showHud: function () {
    if (!this.hud) {
      var $pdfField = Craft.ui
        .createSelectField({
          label: Craft.t('commerce', 'PDF'),
          name: 'pdfId',
          options: this.pdfs,
          class: 'fullwidth',
        })
        .appendTo(this.$hudBody);

      var $typeField = Craft.ui
        .createSelectField({
          label: Craft.t('commerce', 'Download Type'),
          name: 'type',
          options: this.types,
          class: 'fullwidth',
        })
        .appendTo(this.$hudBody);

      var $submitBtn = $('<button/>', {
        type: 'submit',
        class: 'btn submit fullwidth formsubmit',
        text: Craft.t('commerce', 'Download'),
      }).appendTo(this.$hudBody);

      var $spinner = $('<div/>', {
        class: 'spinner hidden',
      }).appendTo(this.$hudBody);

      this.hud = new Garnish.HUD(this.$btn, this.$hudBody, {
        hudClass: 'hud',
      });

      this.hud.on('hide', () => {
        this.$btn.removeClass('active');
      });

      var submitting = false;

      $submitBtn.on(
        'click',
        $.proxy(function (ev) {
          ev.preventDefault();
          if (submitting) {
            return;
          }
          submitting = true;

          var $pdfField = this.$hudBody.find('[name="pdfId"]');
          var $typeField = this.$hudBody.find('[name="type"]');
          Craft.elementIndex
            .submitAction(this.action, {
              pdfId: $pdfField.val(),
              downloadType: $typeField.val(),
            })
            .finally(() => {
              submitting = false;
              this.hud.hide();
            });
        }, this)
      );
    } else {
      this.hud.show();
    }
  },
});
