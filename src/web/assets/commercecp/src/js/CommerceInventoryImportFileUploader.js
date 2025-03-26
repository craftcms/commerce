/* jshint esversion: 6 */
/* globals Craft, Garnish, $ */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

Craft.Commerce.InventoryImportFileUploader = Garnish.Base.extend(
  {
    $container: null,
    $uploadBtn: null,
    $fileInput: null,
    $spinner: null,
    $filenameInput: null,
    $uploadedFile: null,
    $uploadContainer: null,
    uploader: null,

    init: function (containerSelector, settings) {
      this.setSettings(
        settings,
        Craft.Commerce.InventoryImportFileUploader.defaults
      );

      this.$container = $(containerSelector);

      if (!this.$container.length) {
        return;
      }

      this.$uploadContainer = this.$container.find('.upload-container');
      this.$uploadBtn = this.$container.find('.upload-btn');
      this.$fileInput = $(
        '<input type="file" name="file" accept=".csv" style="display:none" />'
      );
      this.$spinner = $('<div class="spinner" style="display:none;"></div>');
      this.$filenameInput = this.$container.find('.import-filename');
      this.$uploadedFile = this.$container.find('.uploaded-file');

      this.$uploadBtn.after(this.$fileInput);
      this.$uploadBtn.after(this.$spinner);

      this.addListener(this.$uploadBtn, 'click', 'onUploadButtonClick');
      this.addListener(
        this.$container.find('.remove-file-btn'),
        'click',
        'onRemoveFileClick'
      );

      this.initUploader();
    },

    onUploadButtonClick: function (e) {
      e.preventDefault();
      this.$fileInput.trigger('click');
    },

    onRemoveFileClick: function (e) {
      e.preventDefault();
      this.$filenameInput.val('');
      this.$uploadedFile.text('');
      this.$uploadContainer.removeClass('has-file');
    },

    initUploader: function () {
      this.uploader = new Craft.Uploader(this.$fileInput, {
        url: Craft.getActionUrl(
          'commerce/inventory-importexport/upload-temp-file'
        ),
        paramName: 'file',
        dropZone: null,
        fileInput: this.$fileInput,
        allowedKinds: null,
        formData: {
          // Include the CSRF token
          [Craft.csrfTokenName]: Craft.csrfTokenValue,
        },
        events: {
          fileuploadstart: this.onUploadStart.bind(this),
          fileuploadfail: this.onUploadFail.bind(this),
          fileuploaddone: this.onUploadDone.bind(this),
        },
      });
    },

    onUploadStart: function () {
      this.$spinner.show();
      this.$uploadBtn.addClass('disabled');
    },

    onUploadFail: function (e, data) {
      this.$spinner.hide();
      this.$uploadBtn.removeClass('disabled');

      var response = data._response.jqXHR.responseJSON || {};
      var error =
        response.error || Craft.t('commerce', 'Failed to upload file');

      Craft.cp.displayError(error);
    },

    onUploadDone: function (e, data) {
      this.$spinner.hide();
      this.$uploadBtn.removeClass('disabled');

      var response = data._response.jqXHR.responseJSON || {};

      if (response.success && response.filename) {
        this.$filenameInput.val(response.filename);

        var fileName = data.files[0].name;
        this.$uploadedFile.text(fileName);
        this.$uploadContainer.addClass('has-file');

        Craft.cp.displayNotice(
          Craft.t('commerce', 'File uploaded successfully')
        );
      } else {
        Craft.cp.displayError(Craft.t('commerce', 'Failed to upload file'));
      }
    },
  },
  {
    defaults: {
      // Default settings
    },
  }
);
