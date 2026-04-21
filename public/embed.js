(function () {
    'use strict';

    var script = document.currentScript || (function () {
        var scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();

    var tenant   = script.getAttribute('data-tenant');
    var baseUrl  = script.getAttribute('data-base-url') || (script.src.replace(/\/embed\.js.*$/, ''));
    var color    = script.getAttribute('data-color') || null;
    var targetSelector = script.getAttribute('data-target') || null;

    if (!tenant) {
        console.error('[PlayDrive] data-tenant is verplicht.');
        return;
    }

    // Bouw de iframe URL op
    var params = ['embed=1'];
    if (color) params.push('color=' + encodeURIComponent(color));
    var iframeSrc = baseUrl + '/reserveren/' + encodeURIComponent(tenant) + '?' + params.join('&');

    // Wrapper aanmaken
    var wrapper = document.createElement('div');
    wrapper.setAttribute('data-playdrive-embed', tenant);
    wrapper.style.cssText = 'width:100%;max-width:100%;';

    // Iframe aanmaken
    var iframe = document.createElement('iframe');
    iframe.src        = iframeSrc;
    iframe.title      = 'Reservatieformulier';
    iframe.loading    = 'lazy';
    iframe.style.cssText = [
        'width:100%',
        'border:none',
        'display:block',
        'min-height:600px',
        'height:700px',
        'transition:height 0.25s ease',
    ].join(';');
    iframe.setAttribute('scrolling', 'no');
    iframe.setAttribute('allow', 'clipboard-write');

    wrapper.appendChild(iframe);

    // Invoegen op de juiste plek
    var target = targetSelector ? document.querySelector(targetSelector) : null;
    if (target) {
        target.appendChild(wrapper);
    } else {
        script.parentNode.insertBefore(wrapper, script.nextSibling);
    }

    // Luister naar resize berichten van het iframe
    window.addEventListener('message', function (event) {
        // Controleer dat het bericht van ons iframe komt
        try {
            var origin = new URL(iframeSrc).origin;
            if (event.origin !== origin) return;
        } catch (e) {
            return;
        }

        var data = event.data;
        if (!data || data.type !== 'playdrive:resize') return;
        if (typeof data.height !== 'number') return;

        iframe.style.height = (data.height + 32) + 'px';
    });

    // Luister naar scroll-naar-top bericht (bij stapwisseling)
    window.addEventListener('message', function (event) {
        try {
            var origin = new URL(iframeSrc).origin;
            if (event.origin !== origin) return;
        } catch (e) {
            return;
        }

        var data = event.data;
        if (!data || data.type !== 'playdrive:scroll-top') return;

        var rect = wrapper.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var targetY = rect.top + scrollTop - 20;
        window.scrollTo({ top: targetY, behavior: 'smooth' });
    });
})();
