        </div>
</div>
<script>
$(document).ready(function() {
    // Toggle submenu when clicking on menu headers
    $('.menu-header').click(function(e) {
        e.preventDefault();
        $(this).siblings('.submenu').slideToggle();
    });

    // Keep submenus open for active items
    $('.submenu .active').parent().parent().show();

    // Mobile menu toggle
    $('.menu-toggle').click(function() {
        $(this).toggleClass('active');
        $('.admin-menu').toggleClass('active');
    });

    // Close menu when clicking outside on mobile
    $(document).click(function(e) {
        if ($(window).width() <= 768) {
            if (!$(e.target).closest('.admin-menu, .menu-toggle').length) {
                $('.admin-menu').removeClass('active');
                $('.menu-toggle').removeClass('active');
            }
        }
    });

    const currentPage = window.location.pathname;
    $('.admin-menu a[href="' + currentPage + '"]').addClass('active');
});
</script>
</body>
</html>