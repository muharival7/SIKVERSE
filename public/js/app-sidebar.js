(() => {
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.querySelector('#sik-sidebar');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    if (!toggle || !sidebar || !backdrop) {
        return;
    }

    const setOpen = (isOpen) => {
        document.body.classList.toggle('sik-sidebar-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Tutup menu' : 'Buka menu');
    };

    toggle.addEventListener('click', () => {
        setOpen(!document.body.classList.contains('sik-sidebar-open'));
    });

    backdrop.addEventListener('click', () => setOpen(false));

    sidebar.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });
})();
