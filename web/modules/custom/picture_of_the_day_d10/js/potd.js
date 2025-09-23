(function (Drupal, $, drupalSettings, once) {
  'use strict';

  Drupal.behaviors.pictureOfTheDay = {
    attach: function (context) {
      $('body').on('click', '.potd-reload', function (e) {
        e.preventDefault();
        var $link = $(this);
        var url = $link.attr('href');
        var $container = $link.closest('.tab-pane');
        if (!url || !$container.length) return;
        $container.addClass('is-loading');
        $.ajax({ url: url, method: 'GET' })
          .done(function (html) {
            // Replace the item HTML.
            var $html = $('<div>').html(html);
            $container.replaceWith($html.find('.tab-pane'));
          })
          .always(function () { $container.removeClass('is-loading'); });
      });
    }
  };

})(Drupal, jQuery, drupalSettings, once);

