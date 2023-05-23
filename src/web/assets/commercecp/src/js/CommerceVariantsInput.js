/* jshint esversion: 6 */
/* globals Craft, Garnish, $ */
if (typeof Craft.Commerce === typeof undefined) {
  Craft.Commerce = {};
}

/**
 * VariantsInput class
 */
Craft.Commerce.VariantsInput = Garnish.Base.extend(
  {
    $container: null,
    $addBtn: null,
    $addBtnItem: null,
    $cards: null,

    init: function (container, settings) {
      this.$container = $(container);
      this.setSettings(settings, Craft.Commerce.VariantsInput.defaults);

      // Is this already an variant input?
      if (this.$container.data(this.settings.handle)) {
        console.warn('Double-instantiating a purchasables input on an element');
        this.$container.data(this.settings.handle).destroy();
      }

      this.$container.data(this.settings.handle, this);

      this.$addBtn = this.$container.find('.purchasable-cards__add-btn');
      this.$addBtnItem = this.$addBtn.closest('li');
      this.$cards = this.$container.find('> .purchasable-card');

      for (let i = 0; i < this.$cards.length; i++) {
        this.initCard(this.$cards.eq(i));
      }

      this.updateAddButton();

      this.addListener(this.$addBtn, 'click', () => {
        this.createVariant();
      });

      // Check to see if we need to open a variant edit immediately
      if (window.commerceOpenVariantId) {
        const $card = this.$container.find(
          '> .purchasable-card[data-id="' + window.commerceOpenVariantId + '"]'
        );

        if ($card) {
          $card.trigger('click');
        }
      }
    },

    initCard: function ($card) {
      this.addListener($card, 'click', (ev) => {
        if (!$(ev.target).closest('.menubtn').length) {
          this.editVariant($card);
        }
      });

      const $actionBtn = $card.find('.menubtn').disclosureMenu();
      if ($actionBtn.length) {
        const menu = $actionBtn.data('trigger');
        const $menu = menu.$container;

        // Activate edit button
        const $editBtn = $menu.find('[data-action="edit"]');
        this.addListener($editBtn, 'click', (ev) => {
          ev.stopPropagation();
          this.editVariant($card);
        });

        // Activate delete button
        const $deleteBtn = $menu.find('[data-action="delete"]');
        this.addListener($deleteBtn, 'click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          if (
            confirm(
              Craft.t(
                'commerce',
                'Are you sure you want to delete this variant?'
              )
            )
          ) {
            this.$addBtn.addClass('loading');
            const variantId = $card.data('id');
            const draftId = $card.data('draft-id');
            Craft.sendActionRequest('POST', 'elements/delete', {
              data: {
                elementId: variantId,
                draftId: draftId,
              },
            })
              .then(() => {
                $card.remove();
                $menu.remove();
                menu.destroy();
                this.$cards = this.$cards.not($card);
                this.updateAddButton();

                this.trigger('deleteVariant', {
                  variantId,
                  draftId,
                });
              })
              .finally(() => {
                this.$addBtn.removeClass('loading');
              });
          }
        });
      }
    },

    editVariant: function ($card, settings) {
      const slideout = Craft.createElementEditor(
        'craft\\commerce\\elements\\Variant',
        $card,
        settings
      );

      slideout.on('submit', (ev) => {
        this.trigger('saveVariant', {
          data: ev.data,
        });

        Craft.sendActionRequest('POST', 'commerce/variants/card-html', {
          data: {
            variantId: ev.data.id,
          },
        }).then((response) => {
          const $newCard = $(response.data.html);
          if ($card) {
            $card.replaceWith($newCard);
            this.$cards = this.$cards.not($card);
          } else {
            $newCard.insertBefore(this.$addBtnItem);
          }
          Craft.initUiElements($newCard);
          this.initCard($newCard);
          this.$cards = this.$cards.add($newCard);
          this.updateAddButton();
        });
      });
    },

    updateAddButton: function () {
      if (this.canCreateVariant()) {
        this.$addBtn.removeClass('hidden');
      } else {
        this.$addBtn.addClass('hidden');
      }
    },

    canCreateVariant: function () {
      return (
        !this.settings.maxVariants ||
        this.$cards.length < this.settings.maxVariants
      );
    },

    canDeleteVariant: function () {
      return this.$cards.length !== 1;
    },

    createVariant: function () {
      if (!this.canCreateVariant()) {
        throw 'No more variants can be created.';
      }

      this.$addBtn.addClass('loading');

      Craft.sendActionRequest('POST', 'elements/create', {
        data: {
          elementType: 'craft\\commerce\\elements\\Variant',
          productId: this.settings.productId,
        },
      })
        .then((ev) => {
          this.editVariant(null, {
            elementId: ev.data.element.id,
            draftId: ev.data.element.draftId,
          });
        })
        .finally(() => {
          this.$addBtn.removeClass('loading');
        });
    },

    destroy: function () {
      this.$container.removeData(this.settings.handle);
      this.base();
    },
  },
  {
    productId: null,
    defaults: {
      maxVariants: null,
      handle: 'variants',
    },
  }
);
