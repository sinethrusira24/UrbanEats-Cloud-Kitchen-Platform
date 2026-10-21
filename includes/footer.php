<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Component A: Shared Page Footer
 */

declare(strict_types=1);
?>
</div><!-- /.container -->
</main><!-- /.main-content -->

<footer class="site-footer">
    <div class="container">
        <p class="footer-copy">
            &copy; <?= date('Y') ?> UrbanEats Platform. All rights reserved.
        </p>
    </div>
</footer>

<script>
    // Minimal Vanilla JavaScript for responsive mobile menu toggle
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('mobileNavBtn');
        const navMenu = document.getElementById('mainNav');
        if (toggleBtn && navMenu) {
            toggleBtn.addEventListener('click', function () {
                const expanded = toggleBtn.getAttribute('aria-expanded') === 'true';
                toggleBtn.setAttribute('aria-expanded', !expanded);
                navMenu.classList.toggle('show');
            });
        }
    });
</script>
</body>

</html>