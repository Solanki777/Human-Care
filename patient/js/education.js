const contentData = window.educationContent || [];

// Category filtering
document.querySelectorAll('.category-btn').forEach(btn => {
    btn.addEventListener('click', function() {

        document.querySelectorAll('.category-btn').forEach(b => {
            b.classList.remove('active');
        });

        this.classList.add('active');

        const category = this.getAttribute('data-category');

        document.querySelectorAll('.learning-card').forEach(card => {
            card.style.display =
                (category === 'all' ||
                 card.getAttribute('data-category') === category)
                    ? 'block'
                    : 'none';
        });
    });
});


function openModal(contentId) {

    const content = contentData.find(c => c.id == contentId);

    if (!content) {
        return;
    }


    // Update view count silently
    fetch('update_view_count.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'content_id=' + contentId
    });


    // Doctor initials
    const nameParts = content.doctor_name.split(' ');
    let initials = '';

    nameParts.forEach(part => {
        if (part) {
            initials += part[0].toUpperCase();
        }
    });


    // Update modal content
    document.getElementById('modalTitle').innerHTML =
        content.icon + ' ' + content.title;

    document.getElementById('modalDoctorAvatar').textContent =
        initials;

    document.getElementById('modalDoctorName').textContent =
        'Dr. ' + content.doctor_name;

    document.getElementById('modalDoctorSpecialty').textContent =
        content.doctor_specialty + ' • ' +
        content.doctor_qualification;

    document.getElementById('modalDescription').textContent =
        content.description;

    document.getElementById('modalContent').textContent =
        content.content;


    // Update meta tags
    document.getElementById('modalMetaTags').innerHTML = `
        <span class="meta-tag">
            📁 ${content.category.charAt(0).toUpperCase() +
            content.category.slice(1)}
        </span>

        <span class="meta-tag">
            📊 ${content.difficulty}
        </span>

        <span class="meta-tag">
            📚 ${content.lesson_count} Lessons
        </span>

        <span class="meta-tag">
            👁️ ${parseInt(content.views) + 1} Views
        </span>
    `;


    // Open modal
    document.getElementById('contentModal').style.display = 'block';

    document.body.style.overflow = 'hidden';
}


function closeModal() {

    document.getElementById('contentModal').style.display = 'none';

    document.body.style.overflow = 'auto';
}


window.onclick = function(e) {

    if (e.target === document.getElementById('contentModal')) {
        closeModal();
    }
};


document.addEventListener('keydown', e => {

    if (e.key === 'Escape') {
        closeModal();
    }

});