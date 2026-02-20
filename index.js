document.addEventListener('DOMContentLoaded', function () {

    // ======== MENÚ MÓVIL ========
    const mobileBtn = document.querySelector('.mobile-menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu-overlay');
    const mobileLinks = document.querySelectorAll('.mobile-links a');

    function toggleMenu() {
        const isOpen = mobileMenu.classList.contains('active');
        if (isOpen) {
            mobileMenu.classList.remove('active');
            mobileBtn.classList.remove('active');
            document.body.style.overflow = 'auto'; // Reactivar scroll
        } else {
            mobileMenu.classList.add('active');
            mobileBtn.classList.add('active');
            document.body.style.overflow = 'hidden'; // Bloquear scroll
        }
    }

    if (mobileBtn) {
        mobileBtn.addEventListener('click', toggleMenu);
    }

    // Cerrar menú al hacer clic en un enlace
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (mobileMenu.classList.contains('active')) {
                toggleMenu();
            }
        });
    });

    // Cerrar menú al redimensionar a pantalla grande
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768 && mobileMenu.classList.contains('active')) {
            toggleMenu();
        }
    });

    // ======== ANIMACIÓN AL SCROLL ========
    const observerOptions = {
        threshold: 0.15, // El elemento debe ser 15% visible
        rootMargin: "0px 0px -50px 0px"
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target); // Solo animar una vez
            }
        });
    }, observerOptions);

    document.querySelectorAll('.animate-on-scroll').forEach(el => {
        observer.observe(el);
    });
});

document.addEventListener('DOMContentLoaded', () => {
    loadPublicStock();
});

