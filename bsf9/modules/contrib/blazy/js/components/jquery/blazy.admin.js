/**
 * @file
 * Provides admin utilities.
 */

(function ($, _d, Drupal, _doc) {

  'use strict';

  var _context = _doc;
  var _desc = 'description';
  var _checkbox = 'form-checkbox';
  var _idTooltip = 'b-' + _desc;
  var _idCheckbox = 'b-' + _checkbox;
  var _idForm = 'b-form';
  var _vanillaOn = 'form--vanilla-on';
  var _vanillaOff = 'form--vanilla-off';
  var _elTootip = '.' + _desc + ', .form-item__' + _desc;
  var _elCheckbox = '.' + _checkbox;
  var _form = 'form--slick';
  var _elForm = '.' + _form;
  var _elFormItem = '.form-item';
  var _elExpandable = '.js-expandable';
  var _elHint = '.b-hint';
  var _isFocused = 'is-focused';
  var _isHovered = 'is-hovered';
  var _isSelected = 'is-selected';
  var _addClass = 'addClass';
  var _removeClass = 'removeClass';
  var _checked = 'checked';
  var _change = 'change';
  var _click = 'click';

  /**
   * Blazy admin utility functions.
   *
   * @param {HTMLElement} form
   *   The Blazy form wrapper HTML element.
   */
  function blazyForm(form) {
    var t = $(form);

    function cleanSwitch(el) {
      el.removeClass(function (index, css) {
        return (css.match(/(^|\s)form--media-switch-\S+/g) || []).join(' ');
      });
    }

    $('.details-legend-prefix', t).removeClass('element-invisible');

    t[$('.' + _checkbox + '--vanilla', t).prop(_checked) ? _addClass : _removeClass](_vanillaOn);

    t.on(_click, '.' + _checkbox, function () {
      var $input = $(this);
      var checked = $input.prop(_checked);

      $input[checked ? _addClass : _removeClass]('on');

      if ($input.hasClass(_checkbox + '--vanilla')) {
        t[checked ? _addClass : _removeClass](_vanillaOn);
        t[checked ? _removeClass : _addClass](_vanillaOff);

        if (checked) {
          cleanSwitch(t);
          $('select[name$="[media_switch]"]', t).val('');
        }
      }
    });

    $('select[name$="[style]"]', t).off(_change).on(_change, function () {
      var $select = $(this);
      var value = $select.val();

      t.removeClass(function (index, css) {
        return (css.match(/(^|\s)form--style-\S+/g) || []).join(' ');
      });

      if (value === '') {
        t.addClass('form--style-off form--style-is-grid');
      }
      else {
        t.addClass('form--style-on form--style-' + value);
        if (value === 'column' || value === 'grid' || value === 'flex' || value === 'nativegrid') {
          t.addClass('form--style-is-grid');
        }
      }
    }).change();

    $('input[name$="[grid]"]', t).off(_change).on(_change, function () {
      var $select = $(this);
      var value = $select.val();

      t[value === '' ? _removeClass : _addClass]('form--grid-on');
    }).change();

    t.on(_click, 'input[name$="[override]"]', function () {
      var $input = $(this);
      var checked = $input.prop(_checked);

      t[checked ? _addClass : _removeClass]('form--override-on');
    });

    $('select[name$="[responsive_image_style]"]', t).off(_change).on(_change, function () {
      var $select = $(this);
      t[$select.val() === '' ? _removeClass : _addClass]('form--responsive-image-on');
    }).change();

    $('select[name$="[media_switch]"]', t).off(_change).on(_change, function () {
      var $select = $(this);
      var value = $select.val();

      cleanSwitch(t);

      t[value === '' ? _removeClass : _addClass]('form--media-switch-on');
      t[value === '' ? _removeClass : _addClass]('form--media-switch-' + value);
      var nobox = (value === '' || value === 'content' || value === 'media' || value === 'rendered');
      t[nobox ? _removeClass : _addClass]('form--media-switch-lightbox');
    }).change();

    t.on('mouseenter touchstart', _elHint, function () {
      $(this).closest(_elFormItem).addClass(_isHovered);
    });

    t.on('mouseleave touchend', _elHint, function () {
      $(this).closest(_elFormItem).removeClass(_isHovered);
    });

    t.on(_click, _elHint, function () {
      $('.form-item.' + _isSelected, t).removeClass(_isSelected);
      $(this).parent().toggleClass(_isSelected);
    });

    t.on(_click, '.description, .form-item__description', function () {
      $(this).closest('.' + _isSelected).removeClass(_isSelected);
    });

    t.off('focus').on('focus', _elExpandable, function () {
      $(this).parent().addClass(_isFocused);
    });

    t.off('blur').on('blur', _elExpandable, function () {
      $(this).parent().removeClass(_isFocused);
    });
  }

  /**
   * Blazy admin tooltip function.
   *
   * @param {HTMLElement} elm
   *   The Blazy form item description HTML element.
   */
  function blazyTooltip(elm) {
    var $tip = $(elm);

    // Claro removed description for BEM form-item__description.
    if (!$tip.hasClass(_desc)) {
      $tip.addClass(_desc);
    }

    if (!$tip.siblings(_elHint).length) {
      $tip.closest(_elFormItem).append('<span class="b-hint">?</span>');
    }
  }

  /**
   * Blazy admin checkbox function.
   *
   * @param {HTMLElement} elm
   *   The Blazy form item checkbox HTML element.
   */
  function blazyCheckbox(elm) {
    var $elm = $(elm);

    if (!$elm.next('.field-suffix').length) {
      $elm.after('<span class="field-suffix"></span>');
    }
  }

  /**
   * Attaches Blazy form behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyAdmin = {
    attach: function (context) {

      _context = _d.context(context);

      _d.once(blazyTooltip, _idTooltip, _elTootip, _context);
      _d.once(blazyCheckbox, _idCheckbox, _elCheckbox, _context);
      _d.once(blazyForm, _idForm, _elForm, _context);
    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        _d.once.removeSafely(_idTooltip, _elTootip, _context);
        _d.once.removeSafely(_idCheckbox, _elCheckbox, _context);
        _d.once.removeSafely(_idForm, _elForm, _context);
      }
    }
  };

})(jQuery, dBlazy, Drupal, this.document);
