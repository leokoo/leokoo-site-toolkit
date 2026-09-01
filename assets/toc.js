document.addEventListener('DOMContentLoaded', function() {
    const tocWrappers = document.querySelectorAll('[data-zehoro-toc]');
    if (!tocWrappers.length) return;

    // Translated default label, injected by wp_localize_script; falls back to
    // English if the localization object isn't present.
    const defaultLabel = (window.zehoroTocL10n && window.zehoroTocL10n.defaultLabel)
        || 'Sections in this article';

    tocWrappers.forEach(wrapper => {
        const toggle = wrapper.querySelector('.zehoro-toc-toggle');
        const activeText = wrapper.querySelector('.zehoro-toc-active-text');
        const links = wrapper.querySelectorAll('.zehoro-toc-list a');
        
        const header = wrapper.querySelector('.zehoro-toc-header');
        if (header) {
            header.addEventListener('click', () => {
                wrapper.classList.toggle('is-open');
            });
        }

        // Close dropdown when a link is clicked (useful on mobile)
        links.forEach(link => {
            link.addEventListener('click', () => {
                wrapper.classList.remove('is-open');
            });
        });

        // Scroll spy logic
        const headings = [];
        links.forEach(link => {
            const id = link.getAttribute('href').substring(1);
            const el = document.getElementById(id);
            if (el) {
                headings.push({ id: id, el: el, text: link.textContent, linkEl: link });
            }
        });

        if (!headings.length) return;

        let ticking = false;
        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    updateActiveHeading();
                    ticking = false;
                });
                ticking = true;
            }
        });

        function updateActiveHeading() {
            let currentId = '';
            let currentText = defaultLabel;
            let activeLink = null;
            
            // Offset for sticky headers
            const scrollOffset = 150; 

            for (let i = headings.length - 1; i >= 0; i--) {
                const heading = headings[i];
                const rect = heading.el.getBoundingClientRect();
                
                // If the heading has scrolled past the top offset
                if (rect.top <= scrollOffset) {
                    currentId = heading.id;
                    currentText = heading.text;
                    activeLink = heading.linkEl;
                    break;
                }
            }

            // Track the actual plain text on a data attribute since the marquee
            // state fills the element with duplicated spans.
            const prevText = activeText.getAttribute('data-current-text') || '';

            if (activeText && prevText !== currentText) {
                activeText.setAttribute('data-current-text', currentText);
                // textContent (never innerHTML): heading text is untrusted, and a
                // heading like "&lt;img onerror=…&gt;" decodes to live markup the
                // moment it round-trips through innerHTML. Keep it inert.
                activeText.textContent = currentText; // Reset to plain text first

                activeText.classList.remove('is-marquee');
                activeText.style.animation = 'none';

                setTimeout(() => {
                    const parent = activeText.parentElement;
                    if (activeText.scrollWidth > parent.clientWidth) {
                        // It overflows! Duplicate the text for continuous scrolling —
                        // built with createElement/textContent so nothing is parsed as HTML.
                        const gap = 40; // 40px gap between the two copies
                        activeText.textContent = '';
                        const first = document.createElement('span');
                        first.textContent = currentText;
                        const second = document.createElement('span');
                        second.textContent = currentText;
                        second.style.paddingLeft = gap + 'px';
                        second.setAttribute('aria-hidden', 'true');
                        activeText.append(first, second);

                        // We need to translate by exactly the width of the first span + the gap
                        const dist = first.getBoundingClientRect().width + gap;
                        activeText.style.setProperty('--scroll-dist', `-${dist}px`);

                        void activeText.offsetWidth; // Reflow
                        activeText.style.animation = '';
                        activeText.classList.add('is-marquee');
                    }
                }, 50);
            }

            links.forEach(link => link.classList.remove('is-active'));
            if (activeLink) {
                activeLink.classList.add('is-active');
            }
        }
        
        // On mobile, start collapsed (PHP outputs is-open, mobile shows dropdown)
        if (window.innerWidth <= 768) {
            wrapper.classList.remove('is-open');
        } else {
            wrapper.classList.remove('is-open'); // Force close on desktop too if cached
        }

        // Init state
        updateActiveHeading();
    });
});