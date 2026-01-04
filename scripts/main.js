// Main JavaScript for Human Care Website

// Sidebar Toggle Function
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
}

// Close Sidebar (used when clicking links)
function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }
}

// Close sidebar when clicking outside
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const menuToggle = document.querySelector('.menu-toggle');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && menuToggle && overlay) {
        if (sidebar.classList.contains('active')) {
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                closeSidebar();
            }
        }
    }
});

// Smooth Scrolling for Anchor Links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        
        // Only prevent default if it's not just "#"
        if (href !== '#') {
            e.preventDefault();
            const target = document.querySelector(href);
            
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
                
                // Close sidebar on mobile after navigation
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            }
        }
    });
});

// Education Category Filter
const categoryButtons = document.querySelectorAll('.category-btn');
const learningCards = document.querySelectorAll('.learning-card');

if (categoryButtons.length > 0 && learningCards.length > 0) {
    categoryButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            categoryButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            this.classList.add('active');
            
            const category = this.getAttribute('data-category');
            
            // Filter cards
            learningCards.forEach(card => {
                if (category === 'all' || card.getAttribute('data-category') === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
}

// Search Functionality (Basic)
const searchInputs = document.querySelectorAll('.search-input, #searchInput');

searchInputs.forEach(input => {
    input.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        const cards = document.querySelectorAll('.doctor-card, .hospital-card, .learning-card');
        
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
});

// FAQ Toggle Function
function toggleFaq(element) {
    const faqItem = element.closest('.faq-item');
    const answer = faqItem.querySelector('.faq-answer');
    const icon = element.querySelector('.faq-icon');
    
    // Close all other FAQs
    document.querySelectorAll('.faq-item').forEach(item => {
        if (item !== faqItem) {
            item.querySelector('.faq-answer').style.display = 'none';
            item.querySelector('.faq-icon').textContent = '+';
        }
    });
    
    // Toggle current FAQ
    if (answer.style.display === 'block') {
        answer.style.display = 'none';
        icon.textContent = '+';
    } else {
        answer.style.display = 'block';
        icon.textContent = '-';
    }
}

// Contact Form Handler
function handleContactForm(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData);
    
    // Here you would normally send data to server
    console.log('Form submitted:', data);
    
    // Show success message
    alert('Thank you for contacting us! We will get back to you soon.');
    
    // Reset form
    event.target.reset();
}

// Add contact form handler if form exists
const contactForm = document.querySelector('.contact-form');
if (contactForm) {
    contactForm.addEventListener('submit', handleContactForm);
}

// Animation on Scroll (Simple)
function animateOnScroll() {
    const elements = document.querySelectorAll('.service-card, .doctor-card, .hospital-card, .learning-card');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
            }
        });
    }, {
        threshold: 0.1
    });
    
    elements.forEach(el => {
        observer.observe(el);
    });
}

// Initialize animations when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    animateOnScroll();
    
    // Add active class to current page link in sidebar
    const currentPage = window.location.pathname.split('/').pop();
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    
    sidebarLinks.forEach(link => {
        const linkPage = link.getAttribute('href');
        if (linkPage === currentPage || (currentPage === '' && linkPage === 'index.php')) {
            link.classList.add('active');
        }
    });
});

// Prevent sidebar from closing when clicking inside it
const sidebar = document.getElementById('sidebar');
if (sidebar) {
    sidebar.addEventListener('click', function(event) {
        event.stopPropagation();
    });
}

// Doctor/Hospital Filter by Specialty
const filterSelects = document.querySelectorAll('.filter-select');

filterSelects.forEach(select => {
    select.addEventListener('change', function() {
        const specialty = this.value.toLowerCase();
        const cards = document.querySelectorAll('.doctor-card');
        
        cards.forEach(card => {
            const cardSpecialty = card.querySelector('.specialty');
            if (cardSpecialty) {
                const specialtyText = cardSpecialty.textContent.toLowerCase();
                if (specialty === 'all' || specialtyText.includes(specialty)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            }
        });
    });
});

// Call Now Buttons
document.querySelectorAll('[data-phone]').forEach(button => {
    button.addEventListener('click', function() {
        const phone = this.getAttribute('data-phone');
        window.location.href = `tel:${phone}`;
    });
});

// Get Directions Buttons (would integrate with Google Maps)
document.querySelectorAll('.get-directions').forEach(button => {
    button.addEventListener('click', function() {
        const address = this.getAttribute('data-address');
        // This would open Google Maps with the address
        alert(`Getting directions to: ${address}
(Google Maps integration coming soon!)`);
    });
});

// Loading State for Buttons
function addLoadingState(button, duration = 2000) {
    const originalText = button.textContent;
    button.textContent = 'Loading...';
    button.disabled = true;
    
    setTimeout(() => {
        button.textContent = originalText;
        button.disabled = false;
    }, duration);
}

// Add loading state to primary buttons
document.querySelectorAll('.btn-primary').forEach(button => {
    button.addEventListener('click', function(e) {
        // Only add loading for buttons that don't have href or type="submit"
        if (!this.hasAttribute('href') && this.type !== 'submit') {
            addLoadingState(this);
        }
    });
});

// Print functionality (for medical records, prescriptions, etc.)
function printContent(elementId) {
    const content = document.getElementById(elementId);
    if (content) {
        const printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Print</title>');
        printWindow.document.write('<style>body{font-family: Arial, sans-serif;}</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(content.innerHTML);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
}

// Copy to Clipboard functionality
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}

// Back to Top Button (if you want to add one)
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Show/Hide Back to Top button based on scroll position
window.addEventListener('scroll', function() {
    const backToTopBtn = document.querySelector('.back-to-top');
    if (backToTopBtn) {
        if (window.pageYOffset > 300) {
            backToTopBtn.style.display = 'block';
        } else {
            backToTopBtn.style.display = 'none';
        }
    }
});

// Console welcome message
console.log('%cWelcome to Human Care! 🏥', 'color: #667eea; font-size: 20px; font-weight: bold;');
console.log('%cYour health, our priority', 'color: #764ba2; font-size: 14px;');