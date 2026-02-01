// ===============================
// Main JavaScript – SAFE VERSION
// Works for Public + Admin Pages
// ===============================

const isAdminPage = document.body.classList.contains('admin-page');

/* ===============================
   SIDEBAR TOGGLE
=============================== */
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebar && overlay) {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }
}

/* Close sidebar on outside click (PUBLIC ONLY) */
if (!isAdminPage) {
    document.addEventListener('click', function (event) {
        const sidebar = document.getElementById('sidebar');
        const menuToggle = document.querySelector('.menu-toggle');

        if (
            sidebar &&
            menuToggle &&
            sidebar.classList.contains('active') &&
            !sidebar.contains(event.target) &&
            !menuToggle.contains(event.target)
        ) {
            closeSidebar();
        }
    });
}

/* Prevent closing when clicking inside sidebar */
const sidebar = document.getElementById('sidebar');
if (sidebar) {
    sidebar.addEventListener('click', e => e.stopPropagation());
}

/* ===============================
   SMOOTH SCROLL (PUBLIC ONLY)
=============================== */
if (!isAdminPage) {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
                if (window.innerWidth <= 768) closeSidebar();
            }
        });
    });
}

/* ===============================
   CATEGORY FILTER (PUBLIC ONLY)
=============================== */
if (!isAdminPage) {
    const categoryButtons = document.querySelectorAll('.category-btn');
    const learningCards = document.querySelectorAll('.learning-card');

    categoryButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            categoryButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const category = btn.dataset.category;
            learningCards.forEach(card => {
                card.style.display =
                    category === 'all' || card.dataset.category === category
                        ? 'block'
                        : 'none';
            });
        });
    });
}

/* ===============================
   SEARCH (PUBLIC ONLY)
=============================== */
if (!isAdminPage) {
    const searchInputs = document.querySelectorAll('.search-input, #searchInput');

    searchInputs.forEach(input => {
        input.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document
                .querySelectorAll('.doctor-card, .hospital-card, .learning-card')
                .forEach(card => {
                    card.style.display = card.textContent.toLowerCase().includes(term)
                        ? 'block'
                        : 'none';
                });
        });
    });
}

/* ===============================
   FAQ TOGGLE
=============================== */
function toggleFaq(element) {
    const faqItem = element.closest('.faq-item');
    if (!faqItem) return;

    document.querySelectorAll('.faq-item').forEach(item => {
        item.querySelector('.faq-answer')?.style.display = 'none';
        item.querySelector('.faq-icon') && (item.querySelector('.faq-icon').textContent = '+');
    });

    const answer = faqItem.querySelector('.faq-answer');
    const icon = faqItem.querySelector('.faq-icon');

    if (answer && icon) {
        const isOpen = answer.style.display === 'block';
        answer.style.display = isOpen ? 'none' : 'block';
        icon.textContent = isOpen ? '+' : '-';
    }
}

/* ===============================
   CONTACT FORM (PUBLIC ONLY)
=============================== */
if (document.body.classList.contains('contact-page')) {
    const contactForm = document.querySelector('.contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', e => {
            e.preventDefault();
            alert('Thank you! We will contact you soon.');
            contactForm.reset();
        });
    }
}

/* ===============================
   ANIMATION ON SCROLL
=============================== */
function animateOnScroll() {
    const elements = document.querySelectorAll(
        '.service-card, .doctor-card, .hospital-card, .learning-card'
    );

    if (elements.length === 0) return;

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
            }
        });
    }, { threshold: 0.1 });

    elements.forEach(el => observer.observe(el));
}

document.addEventListener('DOMContentLoaded', () => {
    animateOnScroll();

    /* Sidebar active link */
    const currentPage = location.pathname.split('/').pop();
    document.querySelectorAll('.nav-link').forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
});

/* ===============================
   PHONE & MAP BUTTONS
=============================== */
document.querySelectorAll('[data-phone]').forEach(btn => {
    btn.addEventListener('click', () => {
        location.href = `tel:${btn.dataset.phone}`;
    });
});

document.querySelectorAll('.get-directions').forEach(btn => {
    btn.addEventListener('click', () => {
        alert(`Getting directions to: ${btn.dataset.address}`);
    });
});

/* ===============================
   BACK TO TOP
=============================== */
window.addEventListener('scroll', () => {
    const btn = document.querySelector('.back-to-top');
    if (!btn) return;
    btn.style.display = window.scrollY > 300 ? 'block' : 'none';
});

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ===============================
   CONSOLE
=============================== */
console.log('%cWelcome to Human Care 🏥', 'color:#667eea;font-size:18px;font-weight:bold');
