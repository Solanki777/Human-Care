
// ============================================
// HUMAN CARE - MAIN JAVASCRIPT
// ============================================

document.addEventListener('DOMContentLoaded', function () {

    // ========================================
    // PAGE TYPE
    // ========================================

    const isAdminPage =
        document.body.classList.contains('admin-page');


    // ========================================
    // SMOOTH SCROLL
    // PUBLIC PAGES ONLY
    // ========================================

    if (!isAdminPage) {

        const anchors =
            document.querySelectorAll('a[href^="#"]');

        anchors.forEach(function (anchor) {

            anchor.addEventListener('click', function (e) {

                const href =
                    this.getAttribute('href');

                if (!href || href === '#') {
                    return;
                }

                const target =
                    document.querySelector(href);

                if (target) {

                    e.preventDefault();

                    target.scrollIntoView({
                        behavior: 'smooth'
                    });

                }

            });

        });

    }


    // ========================================
    // CATEGORY FILTER
    // PUBLIC PAGES ONLY
    // ========================================

    if (!isAdminPage) {

        const categoryButtons =
            document.querySelectorAll('.category-btn');

        const learningCards =
            document.querySelectorAll('.learning-card');

        categoryButtons.forEach(function (button) {

            button.addEventListener('click', function () {

                categoryButtons.forEach(function (btn) {
                    btn.classList.remove('active');
                });

                button.classList.add('active');

                const category =
                    button.getAttribute('data-category');

                learningCards.forEach(function (card) {

                    const cardCategory =
                        card.getAttribute('data-category');

                    if (
                        category === 'all' ||
                        cardCategory === category
                    ) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }

                });

            });

        });

    }


    // ========================================
    // SEARCH
    // PUBLIC PAGES ONLY
    // ========================================

    if (!isAdminPage) {

        const searchInputs =
            document.querySelectorAll(
                '.search-input, #searchInput'
            );

        searchInputs.forEach(function (input) {

            input.addEventListener('input', function () {

                const term =
                    input.value.toLowerCase().trim();

                const cards =
                    document.querySelectorAll(
                        '.doctor-card, .hospital-card, .learning-card'
                    );

                cards.forEach(function (card) {

                    const text =
                        card.textContent.toLowerCase();

                    if (text.includes(term)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }

                });

            });

        });

    }


    // ========================================
    // CONTACT FORM
    // PUBLIC PAGES ONLY
    // ========================================

    if (
        document.body.classList.contains('contact-page')
    ) {

        const contactForm =
            document.querySelector('.contact-form');

        if (contactForm) {

            contactForm.addEventListener(
                'submit',
                function (e) {

                    e.preventDefault();

                    alert(
                        'Thank you! We will contact you soon.'
                    );

                    contactForm.reset();

                }
            );

        }

    }


    // ========================================
    // ANIMATION ON SCROLL
    // ========================================

    function animateOnScroll() {

        const elements =
            document.querySelectorAll(
                '.service-card, .doctor-card, .hospital-card, .learning-card'
            );

        if (elements.length === 0) {
            return;
        }

        if (
            'IntersectionObserver' in window
        ) {

            const observer =
                new IntersectionObserver(
                    function (entries) {

                        entries.forEach(function (entry) {

                            if (entry.isIntersecting) {

                                entry.target.style.animation =
                                    'fadeInUp 0.6s ease forwards';

                                observer.unobserve(
                                    entry.target
                                );

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

    }

    animateOnScroll();


    // ========================================
    // ACTIVE NAVIGATION LINK
    // ========================================

    const currentPage =
        window.location.pathname
            .split('/')
            .pop();

    document
        .querySelectorAll('.nav-link')
        .forEach(function (link) {

            const href =
                link.getAttribute('href');

            if (href === currentPage) {
                link.classList.add('active');
            }

        });


    // ========================================
    // PHONE BUTTONS
    // ========================================

    document
        .querySelectorAll('[data-phone]')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    const phone =
                        button.getAttribute('data-phone');

                    if (phone) {
                        window.location.href =
                            'tel:' + phone;
                    }

                }
            );

        });


    // ========================================
    // DIRECTIONS BUTTONS
    // ========================================

    document
        .querySelectorAll('.get-directions')
        .forEach(function (button) {

            button.addEventListener(
                'click',
                function () {

                    const address =
                        button.getAttribute('data-address');

                    if (address) {

                        alert(
                            'Getting directions to: ' +
                            address
                        );

                    }

                }
            );

        });


    // ========================================
    // BACK TO TOP
    // ========================================

    window.addEventListener(
        'scroll',
        function () {

            const button =
                document.querySelector('.back-to-top');

            if (!button) {
                return;
            }

            if (window.scrollY > 300) {

                button.style.display = 'block';

            } else {

                button.style.display = 'none';

            }

        }
    );


    // ========================================
    // CONSOLE MESSAGE
    // ========================================

    console.log(
        '%cWelcome to Human Care 🏥',
        'color:#667eea;font-size:18px;font-weight:bold'
    );

});


// ============================================
// FAQ TOGGLE
// GLOBAL FUNCTION
// ============================================

function toggleFaq(element) {

    const faqItem =
        element.closest('.faq-item');

    if (!faqItem) {
        return;
    }


    // Close all FAQ items
    const faqItems =
        document.querySelectorAll('.faq-item');

    faqItems.forEach(function (item) {

        const answer =
            item.querySelector('.faq-answer');

        const icon =
            item.querySelector('.faq-icon');

        if (item !== faqItem) {

            if (answer) {
                answer.style.display = 'none';
            }

            if (icon) {
                icon.textContent = '+';
            }

        }

    });


    // Selected FAQ
    const answer =
        faqItem.querySelector('.faq-answer');

    const icon =
        faqItem.querySelector('.faq-icon');

    if (!answer || !icon) {
        return;
    }


    // Toggle selected FAQ
    const isOpen =
        answer.style.display === 'block';

    if (isOpen) {

        answer.style.display = 'none';
        icon.textContent = '+';

    } else {

        answer.style.display = 'block';
        icon.textContent = '-';

    }

}