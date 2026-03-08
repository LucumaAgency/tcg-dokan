(function ($) {
  'use strict';

  var $search = $('#tcg-card-search');
  var $cardId = $('#tcg-linked-card-id');
  var $preview = $('#tcg-card-preview');
  var $changeBtn = $('#tcg-change-card');

  /**
   * Hide the product title field and move card selector to the top of the form.
   */
  function setupFormLayout() {
    var $titleField = $('input[name="post_title"]').closest('.dokan-form-group');
    if ($titleField.length) {
      $titleField.hide();
      console.log('[TCG] Title field hidden');
    }

    var $cardSelector = $('.tcg-card-selector');
    var $form = $cardSelector.closest('form');
    if ($form.length && $cardSelector.length) {
      var $firstGroup = $form.find('.dokan-form-group').first();
      if ($firstGroup.length && !$firstGroup.hasClass('tcg-card-selector')) {
        $cardSelector.insertBefore($firstGroup);
        console.log('[TCG] Card selector moved to top of form');
      }
    }
  }

  /**
   * Initialize autocomplete with local card data (no AJAX for search).
   */
  function initAutocomplete() {
    var allCards = tcgDokan.cards || [];
    console.log('[TCG] Initializing autocomplete with', allCards.length, 'preloaded cards');

    $search.autocomplete({
      source: function (request, response) {
        var term = request.term.toLowerCase();
        console.log('[TCG] Searching locally for:', term);

        var matches = [];
        for (var i = 0; i < allCards.length && matches.length < 15; i++) {
          if (allCards[i].label.toLowerCase().indexOf(term) !== -1) {
            matches.push(allCards[i]);
          }
        }

        console.log('[TCG] Local results:', matches.length, 'cards found');

        if (!matches.length) {
          response([{ label: tcgDokan.i18n.noResults, value: '', id: 0 }]);
          return;
        }
        response(matches);
      },
      minLength: 2,
      delay: 100,
      select: function (event, ui) {
        if (!ui.item.id) {
          event.preventDefault();
          return;
        }

        console.log('[TCG] Card selected:', ui.item.id, ui.item.value);

        $search.val(ui.item.value).prop('readonly', true);
        $cardId.val(ui.item.id);

        loadCardPreview(ui.item.id);

        var $title = $('input[name="post_title"]');
        if ($title.length) {
          $title.val(ui.item.value);
        }

        return false;
      },
    });

    // Custom render for autocomplete items.
    $search.autocomplete('instance')._renderItem = function (ul, item) {
      if (!item.id) {
        return $('<li>').append('<div class="tcg-ac-item tcg-ac-noresult">' + item.label + '</div>').appendTo(ul);
      }

      var info =
        '<div class="tcg-ac-info">' +
        '<strong>' + $('<span>').text(item.value).html() + '</strong>' +
        (item.set_code ? '<br><small>' + $('<span>').text(item.set_code).html() + '</small>' : '') +
        (item.set_rarity ? ' <small class="tcg-ac-rarity">' + $('<span>').text(item.set_rarity).html() + '</small>' : '') +
        '</div>';

      return $('<li>')
        .append('<div class="tcg-ac-item">' + info + '</div>')
        .appendTo(ul);
    };
  }

  /**
   * Load card preview via AJAX (only when a card is selected, not during search).
   */
  function loadCardPreview(cardId) {
    console.log('[TCG] Loading preview for card:', cardId);
    $.ajax({
      url: tcgDokan.ajaxUrl,
      dataType: 'json',
      data: {
        action: 'tcg_get_ygo_card_data',
        nonce: tcgDokan.nonce,
        card_id: cardId,
      },
      success: function (resp) {
        if (!resp.success) {
          console.error('[TCG] Preview load failed:', resp.data);
          return;
        }

        console.log('[TCG] Preview loaded for:', resp.data.title);

        var card = resp.data;
        var html = '<div class="tcg-preview-inner">';

        if (card.thumbnail) {
          html += '<div class="tcg-preview-img"><img src="' + card.thumbnail + '" alt=""></div>';
        }

        html += '<div class="tcg-preview-info">';
        html += '<h4>' + $('<span>').text(card.title).html() + '</h4>';

        var meta = card.meta;
        if (meta._ygo_set_code) {
          html += '<p><strong>Set:</strong> ' + $('<span>').text(meta._ygo_set_code).html() + '</p>';
        }
        if (meta._ygo_set_rarity) {
          html += '<p><strong>Rarity:</strong> ' + $('<span>').text(meta._ygo_set_rarity).html() + '</p>';
        }
        if (meta._ygo_typeline) {
          html += '<p><strong>Type:</strong> ' + $('<span>').text(meta._ygo_typeline).html() + '</p>';
        }
        if (meta._ygo_atk) {
          var stat = 'ATK/' + meta._ygo_atk;
          if (meta._ygo_def) stat += ' DEF/' + meta._ygo_def;
          html += '<p><strong>Stats:</strong> ' + $('<span>').text(stat).html() + '</p>';
        }
        if (meta._ygo_level) {
          html += '<p><strong>Level:</strong> ' + meta._ygo_level + '</p>';
        }
        if (meta._ygo_rank) {
          html += '<p><strong>Rank:</strong> ' + meta._ygo_rank + '</p>';
        }
        if (meta._ygo_linkval) {
          html += '<p><strong>Link:</strong> ' + meta._ygo_linkval + '</p>';
        }

        var prices = meta._ygo_ref_prices;
        if (prices && typeof prices === 'object') {
          var priceLabels = { tcgplayer: 'TCGPlayer', cardmarket: 'Cardmarket', ebay: 'eBay', amazon: 'Amazon' };
          var priceHtml = '';
          $.each(priceLabels, function (key, label) {
            if (prices[key] && prices[key] !== '0' && prices[key] !== '0.00') {
              priceHtml += '<span class="tcg-ref-price">' + label + ': $' + $('<span>').text(prices[key]).html() + '</span> ';
            }
          });
          if (priceHtml) {
            html += '<p class="tcg-preview-prices"><strong>Ref:</strong> ' + priceHtml + '</p>';
          }
        }

        html += '</div></div>';

        $preview.html(html).show();
      },
      error: function (xhr, status, error) {
        console.error('[TCG] Preview AJAX error:', status, error);
      },
    });
  }

  /**
   * "Change card" button handler.
   */
  function initChangeButton() {
    $(document).on('click', '#tcg-change-card', function () {
      console.log('[TCG] Changing linked card');
      $search.val('').prop('readonly', false).focus();
      $cardId.val('');
      $preview.hide().empty();
      $(this).remove();

      var $title = $('input[name="post_title"]');
      if ($title.length) {
        $title.val('');
      }
    });
  }

  /**
   * On edit page: load preview if card is already linked.
   */
  function loadExistingPreview() {
    var existingId = $cardId.val();
    if (existingId && parseInt(existingId, 10) > 0) {
      console.log('[TCG] Loading existing linked card:', existingId);
      loadCardPreview(existingId);
    }
  }

  // Init on DOM ready.
  $(function () {
    if (!$search.length) {
      console.log('[TCG] Card search field not found, skipping init');
      return;
    }

    console.log('[TCG] Initializing vendor form');
    setupFormLayout();
    forceManageStock();
    initAutocomplete();
    initChangeButton();
    loadExistingPreview();
  });

  /**
   * Force "manage stock" checkbox to checked and show stock fields.
   */
  function forceManageStock() {
    var $checkbox = $('#_manage_stock');
    if ($checkbox.length && !$checkbox.is(':checked')) {
      $checkbox.prop('checked', true).trigger('change');
      console.log('[TCG] Manage stock forced on');
    }
    // Show the stock management fields.
    $('.show_if_stock').show();
  }
})(jQuery);
