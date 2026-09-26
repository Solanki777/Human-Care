function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(section => {
        section.classList.remove('active');
        section.classList.add('hidden');
    });

    document.getElementById(sectionId)?.classList.remove('hidden');
    document.getElementById(sectionId)?.classList.add('active');
}