

document.addEventListener('DOMContentLoaded', function () {

    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');


    // Stop if sidebar elements do not exist
    if (!menuToggle || !sidebar || !overlay) {

        console.warn('Doctor sidebar elements not found.');

        return;
    }


    // ================================================
    // OPEN SIDEBAR
    // ================================================

    function openSidebar() {

        sidebar.classList.add('active');

        overlay.classList.add('active');

        document.body.classList.add('sidebar-open');

    }


    // ================================================
    // CLOSE SIDEBAR
    // ================================================

    function closeSidebar() {

        sidebar.classList.remove('active');

        overlay.classList.remove('active');

        document.body.classList.remove('sidebar-open');

    }


    // ================================================
    // TOGGLE SIDEBAR
    // ================================================

    function toggleSidebar() {

        if (sidebar.classList.contains('active')) {

            closeSidebar();

        } else {

            openSidebar();

        }

    }


    // ================================================
    // MENU BUTTON
    // ================================================

    menuToggle.addEventListener('click', function (e) {

        e.preventDefault();

        e.stopPropagation();

        toggleSidebar();

    });


    // ================================================
    // OVERLAY
    // ================================================

    overlay.addEventListener('click', function () {

        closeSidebar();

    });


    // ================================================
    // ESCAPE KEY
    // ================================================

    document.addEventListener('keydown', function (e) {

        if (e.key === 'Escape') {

            closeSidebar();

        }

    });


    // ================================================
    // CLICK OUTSIDE SIDEBAR
    // ================================================

    document.addEventListener('click', function (e) {

        if (
            sidebar.classList.contains('active') &&
            !sidebar.contains(e.target) &&
            !menuToggle.contains(e.target)
        ) {

            closeSidebar();

        }

    });


    // ================================================
    // PREVENT SIDEBAR CLICKS FROM CLOSING
    // ================================================

    sidebar.addEventListener('click', function (e) {

        e.stopPropagation();

    });

});



