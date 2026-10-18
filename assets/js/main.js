/**
 * Public site JavaScript — vanilla JS, no build step, no dependencies.
 * Progressive enhancement only: every page must work with JS disabled.
 */
(function () {
    'use strict';
    document.documentElement.classList.add('js');

    // ---------------------------------------------------------------
    // Mobile navigation toggle
    // ---------------------------------------------------------------
    var navToggle = document.getElementById('navToggle');
    var primaryNav = document.getElementById('primaryNav');

    if (navToggle && primaryNav) {
        navToggle.addEventListener('click', function () {
            var isOpen = primaryNav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && primaryNav.classList.contains('is-open')) {
                primaryNav.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
                navToggle.focus();
            }
        });

        // Close the menu when a link is chosen (mobile).
        primaryNav.addEventListener('click', function (e) {
            if (e.target.tagName === 'A' && window.innerWidth <= 1100) {
                primaryNav.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // ---------------------------------------------------------------
    // Cookie consent banner
    // ---------------------------------------------------------------
    var cookieBanner = document.getElementById('cookieBanner');
    var cookieAccept = document.getElementById('cookieAccept');
    var cookieDecline = document.getElementById('cookieDecline');

    function setCookieConsent(value) {
        var oneYear = 60 * 60 * 24 * 365;
        document.cookie = 'vcc_cookie_consent=' + value + '; max-age=' + oneYear + '; path=/; SameSite=Lax';
        if (cookieBanner) {
            cookieBanner.remove();
        }
        if (value === 'all') {
            loadConsentGatedEmbeds();
        }
    }

    if (cookieAccept) {
        cookieAccept.addEventListener('click', function () { setCookieConsent('all'); });
    }
    if (cookieDecline) {
        cookieDecline.addEventListener('click', function () { setCookieConsent('essential'); });
    }

    function hasFullConsent() {
        return document.cookie.indexOf('vcc_cookie_consent=all') !== -1;
    }

    // ---------------------------------------------------------------
    // Consent-gated embeds (YouTube/Vimeo/Google Maps): only load the
    // real iframe once the visitor has accepted non-essential cookies,
    // or explicitly clicks "Load video/map" on that specific embed.
    // ---------------------------------------------------------------
    function loadConsentGatedEmbeds() {
        document.querySelectorAll('[data-embed-src]').forEach(function (el) {
            activateEmbed(el);
        });
    }

    function activateEmbed(el) {
        if (el.dataset.embedLoaded) return;
        var iframe = document.createElement('iframe');
        iframe.src = el.getAttribute('data-embed-src');
        iframe.title = el.getAttribute('data-embed-title') || 'Embedded content';
        iframe.loading = 'lazy';
        iframe.allowFullscreen = true;
        el.innerHTML = '';
        el.appendChild(iframe);
        el.dataset.embedLoaded = 'true';
    }

    document.querySelectorAll('.video-consent-btn, .map-consent-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrapper = btn.closest('[data-embed-src]');
            if (wrapper) activateEmbed(wrapper);
        });
    });

    if (hasFullConsent()) {
        loadConsentGatedEmbeds();
    }

    // ---------------------------------------------------------------
    // Lightbox for the photo gallery
    // ---------------------------------------------------------------
    var lightbox = document.getElementById('lightbox');
    if (lightbox) {
        var lbImage = lightbox.querySelector('.lightbox__image');
        var lbCaption = lightbox.querySelector('.lightbox__caption');
        var lbClose = lightbox.querySelector('.lightbox__close');
        var lbPrev = lightbox.querySelector('.lightbox__prev');
        var lbNext = lightbox.querySelector('.lightbox__next');
        var items = Array.prototype.slice.call(document.querySelectorAll('.gallery-grid__item'));
        var currentIndex = 0;
        var lastFocused = null;

        function openLightbox(index) {
            currentIndex = index;
            var item = items[index];
            lbImage.src = item.getAttribute('data-full');
            lbImage.alt = item.getAttribute('data-alt') || '';
            lbCaption.textContent = item.getAttribute('data-caption') || '';
            lastFocused = document.activeElement;
            lightbox.classList.add('is-open');
            lbClose.focus();
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            lightbox.classList.remove('is-open');
            lbImage.src = '';
            document.body.style.overflow = '';
            if (lastFocused) lastFocused.focus();
        }

        function showRelative(offset) {
            currentIndex = (currentIndex + offset + items.length) % items.length;
            var item = items[currentIndex];
            lbImage.src = item.getAttribute('data-full');
            lbImage.alt = item.getAttribute('data-alt') || '';
            lbCaption.textContent = item.getAttribute('data-caption') || '';
        }

        items.forEach(function (item, index) {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                openLightbox(index);
            });
        });

        if (lbClose) lbClose.addEventListener('click', closeLightbox);
        if (lbPrev) lbPrev.addEventListener('click', function () { showRelative(-1); });
        if (lbNext) lbNext.addEventListener('click', function () { showRelative(1); });

        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox) closeLightbox();
        });

        document.addEventListener('keydown', function (e) {
            if (!lightbox.classList.contains('is-open')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowLeft') showRelative(-1);
            if (e.key === 'ArrowRight') showRelative(1);
            if (e.key === 'Tab') {
                // Simple focus trap between close/prev/next while open.
                var focusable = [lbPrev, lbNext, lbClose].filter(Boolean);
                var idx = focusable.indexOf(document.activeElement);
                e.preventDefault();
                var nextIdx = e.shiftKey ? (idx <= 0 ? focusable.length - 1 : idx - 1) : (idx === focusable.length - 1 ? 0 : idx + 1);
                focusable[nextIdx].focus();
            }
        });
    }

    // ---------------------------------------------------------------
    // Contact form: basic client-side validation feedback (server-side
    // validation in contact.php is authoritative — this is UX only)
    // ---------------------------------------------------------------
    var contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            var required = contactForm.querySelectorAll('[required]');
            var firstInvalid = null;
            required.forEach(function (field) {
                if (!field.value.trim()) {
                    field.setAttribute('aria-invalid', 'true');
                    if (!firstInvalid) firstInvalid = field;
                } else {
                    field.removeAttribute('aria-invalid');
                }
            });
            if (firstInvalid) {
                e.preventDefault();
                firstInvalid.focus();
            }
        });
    }
})();
