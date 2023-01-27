/**
 * @file
 * Provides Media module integration.
 */

(function ($, Drupal, _doc) {

  'use strict';

  var _context = _doc;
  var _md = 'media';
  var _id = 'blazy-' + _md;
  var _idOnce = 'b-' + _md;
  var _player = _md + '--player';
  var _element = '.' + _player;
  var _icon = _md + '__icon';
  var _elIconPlay = '.' + _icon + '--play';
  var _elIconClose = '.' + _icon + '--close';
  var _iFrame = 'iframe';
  var _isPlaying = 'is-playing';
  var _dataIFrameTitle = 'data-' + _iFrame + '-title';
  var _dataUrl = 'data-url';

  /**
   * Blazy media utility functions.
   *
   * @param {HTMLElement} el
   *   The media player HTML element.
   */
  function process(el) {
    var $el = $(el);
    var iframe = $el.find(_iFrame);
    var btn = $el.find(_elIconPlay);

    // Media player toggler is disabled, just display iframe.
    if (!$.isElm(btn)) {
      return;
    }

    var url = $.attr(btn, _dataUrl);
    var title = $.attr(btn, _dataIFrameTitle);
    var newIframe;

    /**
     * Play the media.
     *
     * @param {Event} e
     *   The event triggered by a `click` event.
     *
     * @return {bool|mixed}
     *   Return false if url is not available.
     */
    function play(e) {
      e.preventDefault();

      // oEmbed/ Soundcloud needs internet, fails on disconnected local.
      if (url === '') {
        return false;
      }

      var target = this;
      var player = target.parentNode;
      var playing = $.find(_doc, '.' + _isPlaying);
      var iframe = $.find(player, _iFrame);
      var video = $.find(_doc, 'video');

      url = $.attr(target, _dataUrl);
      title = $.attr(target, _dataIFrameTitle);

      // First, reset any (local) video to avoid multiple videos from playing.
      if ($.isElm(video) && !video.paused) {
        video.pause();
      }

      // Remove other playing video.
      if ($.isElm(playing)) {
        var played = $.find(_doc, '.' + _isPlaying + ' ' + _iFrame);
        // Remove the previous iframe.
        $.remove(played);
        playing.className = playing.className.replace(/(\S+)playing/, '');
      }

      // Appends the iframe.
      $.addClass(player, _isPlaying);

      // Remove the existing iframe on the current clicked iframe.
      $.remove(iframe);

      // Cache iframe for the potential repeating clicks.
      if (!newIframe) {
        newIframe = $.create(_iFrame, _md + '__iframe ' + _md + '__element');

        $.attr(newIframe, {
          src: url,
          allowfullscreen: true,
          title: title
        });
      }

      player.appendChild(newIframe);
    }

    /**
     * Close the media.
     *
     * @param {Event} e
     *   The event triggered by a `click` event.
     */
    function stop(e) {
      e.preventDefault();

      var target = this;
      var player = target.parentNode;
      var iframe = $.find(player, _iFrame);

      if (player.className.match(_isPlaying)) {
        player.className = player.className.replace(/(\S+)playing/, '');
      }

      $.remove(iframe);
    }

    // Remove iframe to avoid browser requesting them till clicked.
    // The iframe is there as Blazy supports non-lazyloaded/ non-JS iframes.
    $.remove(iframe);

    // Plays the media player.
    $el.on('click.' + _id, _elIconPlay, play);

    // Closes the video.
    $el.on('click.' + _id, _elIconClose, stop);
  }

  /**
   * Theme function for a dynamic inline video.
   *
   * @param {Object} settings
   *   An object containing the link element which triggers the lightbox.
   *   This link must have [data-media] attribute containing video metadata.
   *
   * @return {HTMLElement}
   *   Returns a HTMLElement object.
   */
  Drupal.theme.blazyMedia = function (settings) {
    // PhotoSwipe5 has element, PhotoSwipe4 el, etc.
    var el = settings.el || settings.element;
    var $el = $(el);
    var img = $el.find('img');
    var data = $.parse($el.attr('data-' + _md));
    var alt = $.isElm(img) ? Drupal.checkPlain($.attr(img, 'alt', 'Video preview', true)) : '';
    var width = data.width ? parseInt(data.width, 10) : 640;
    var height = data.height ? parseInt(data.height, 10) : 360;
    var pad = data ? ((height / width) * 100).toFixed(2) : 100;
    var imgUrl = $el.attr('data-box-url');
    var href = $el.attr('href');
    var oembedUrl = $el.attr('data-oembed-url', href, true);
    var defClass = _md + '__image ' + _md + '__element';
    var imgClass = settings.imgClass ?
      defClass + ' ' + settings.imgClass :
      defClass;
    var idClass = data.id ? ' ' + _md + '--' + data.id : '';
    var player = data.type === 'video' ? ' ' + _player : '';
    var html = '';

    if (imgUrl) {
      html += '<img src="$imgUrl" class="$imgClass" alt="$alt" loading="lazy" decoding="async" />';
    }

    if (player) {
      html += '<span class="$icon $icon--close"></span>';
      html += '<span class="$icon $icon--play" data-url="$oembed" data-iframe-title="$alt"></span>';
    }

    html = '<div class="$md $idClass $md--switch $player $md--ratio $md--ratio--fluid" style="padding-bottom: $pad%">' + html + '</div>';

    if (!settings.unwrap) {
      html = '<div class="$wrapper $wrapper--inline" style="width: $widthpx">' +
        html +
        '</div>';
    }

    return $.template(html, {
      md: _md,
      icon: _icon,
      idClass: idClass,
      player: player,
      pad: pad,
      imgUrl: imgUrl,
      imgClass: imgClass,
      alt: alt,
      oembed: oembedUrl,
      width: width,
      wrapper: _md + '-wrapper'
    });
  };

  /**
   * Attaches Blazy media behavior to HTML element.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.blazyMedia = {
    attach: function (context) {

      _context = $.context(context);

      $.once(process, _idOnce, _element, _context);

    },
    detach: function (context, setting, trigger) {
      if (trigger === 'unload') {
        $.once.removeSafely(_idOnce, _element, _context);
      }
    }
  };

})(dBlazy, Drupal, this.document);
