// ============================================
// HUMAN CARE - HOME PAGE JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function () {

    // ========================================
    // HOME PAGE ANIMATIONS
    // ========================================

    const elements = document.querySelectorAll(
        '.service-card, .stats-card'
    );

    if (
        elements.length > 0 &&
        'IntersectionObserver' in window
    ) {

        const observer = new IntersectionObserver(
            function (entries) {

                entries.forEach(function (entry) {

                    if (entry.isIntersecting) {

                        entry.target.style.animation =
                            'fadeInUp 0.6s ease forwards';

                        observer.unobserve(entry.target);
                    }

                });

            },
            {
                threshold: 0.1
            }
        );

        elements.forEach(function (element) {
            observer.observe(element);
        });

    } else {

        elements.forEach(function (element) {

            element.style.animation =
                'fadeInUp 0.6s ease forwards';

        });

    }

});