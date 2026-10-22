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
    // Minimal Vanilla JavaScript for responsive mobile menu & dropdown interaction
    document.addEventListener('DOMContentLoaded', function () {
        // Mobile main menu toggle
        const toggleBtn = document.getElementById('mobileNavBtn');
        const navMenu = document.getElementById('mainNav');
        if (toggleBtn && navMenu) {
            toggleBtn.addEventListener('click', function () {
                const expanded = toggleBtn.getAttribute('aria-expanded') === 'true';
                toggleBtn.setAttribute('aria-expanded', !expanded);
                navMenu.classList.toggle('show');
            });
        }

        // User profile menu dropdown
        const userBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdownContent');
        if (userBtn && userDropdown) {
            userBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = userDropdown.classList.contains('show');
                userDropdown.classList.toggle('show', !isOpen);
                userBtn.setAttribute('aria-expanded', !isOpen);
            });

            document.addEventListener('click', function (e) {
                if (!userBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                    userBtn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // Mobile touch toggle for admin dropdown
        const adminToggle = document.querySelector('.nav-dropdown-toggle');
        const adminMenu = document.querySelector('.nav-dropdown-menu');
        if (adminToggle && adminMenu) {
            adminToggle.addEventListener('click', function (e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    adminMenu.classList.toggle('show');
                }
            });
        }

        // Global Password Visibility Toggle
        document.addEventListener('click', function (e) {
            const toggleBtn = e.target.closest('.password-toggle-btn');
            if (!toggleBtn) return;

            const wrapper = toggleBtn.closest('.password-input-wrapper');
            if (!wrapper) return;

            const passwordInput = wrapper.querySelector('input');
            if (!passwordInput) return;

            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');

            const showIcon = toggleBtn.querySelector('.eye-show');
            const hideIcon = toggleBtn.querySelector('.eye-hide');

            if (isPassword) {
                toggleBtn.setAttribute('aria-label', 'Hide password');
                toggleBtn.setAttribute('title', 'Hide password');
                if (showIcon) showIcon.style.display = 'none';
                if (hideIcon) hideIcon.style.display = 'block';
            } else {
                toggleBtn.setAttribute('aria-label', 'Show password');
                toggleBtn.setAttribute('title', 'Show password');
                if (showIcon) showIcon.style.display = 'block';
                if (hideIcon) hideIcon.style.display = 'none';
            }
        });
    });
</script>
</body>

</html>