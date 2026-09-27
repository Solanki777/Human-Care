document.addEventListener('DOMContentLoaded', function () {
    // Doctor status tabs
    window.showTab = function (tabName) {
        document.querySelectorAll('.tab').forEach(tab => {
            tab.classList.remove('active');
        });

        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });

        const selectedTab = document.getElementById(tabName);
        const clickedButton = document.querySelector(
            `.tab[onclick="showTab('${tabName}')"]`
        );

        if (selectedTab && clickedButton) {
            selectedTab.classList.add('active');
            clickedButton.classList.add('active');
        }
    };

    // Reject doctor modal
    window.showRejectModal = function (doctorId) {
        document.getElementById('reject_doctor_id').value = doctorId;
        document.getElementById('rejectModal').classList.add('active');
    };

    window.closeRejectModal = function () {
        document.getElementById('rejectModal').classList.remove('active');
    };

    // Delete doctor modal
    window.confirmDelete = function (doctorId, doctorName) {
        document.getElementById('delete_doctor_id').value = doctorId;

        document.getElementById('deleteMessage').textContent =
            `Are you sure you want to delete Dr. ${doctorName}?`;

        document.getElementById('deleteModal').classList.add('active');
    };

    window.closeDeleteModal = function () {
        document.getElementById('deleteModal').classList.remove('active');
    };

    // Close modals when clicking outside their content
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.classList.remove('active');
            }
        });
    });

    // Close modals with Escape
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(modal => {
                modal.classList.remove('active');
            });
        }
    });
});