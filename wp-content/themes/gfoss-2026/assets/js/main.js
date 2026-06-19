(() => {
    const burger = document.querySelector('[data-gfoss-menu-toggle]');
    const nav = document.querySelector('.site-nav');
    if (burger && nav) {
        burger.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            burger.setAttribute('aria-expanded', String(open));
        });
    }
})();
