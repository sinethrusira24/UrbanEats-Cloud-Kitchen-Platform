<?php /** footer.php – Shared site footer */ ?>

</main><!-- /#main-content -->

<footer class="site-footer" role="contentinfo">
  <div class="container">
    <p>
      &copy; <?= date('Y') ?> <strong>UrbanEats</strong> Cloud Kitchen &mdash; Sri Lanka.
      Built for <abbr title="IT3113 Advanced Web Technologies">IT3113</abbr>.
    </p>
    <p style="margin-top:var(--sp-2)">
      <a href="<?= app_url('/index.php') ?>">Menu</a> &bull;
      <a href="<?= app_url('/auth/register.php') ?>">Register</a> &bull;
      <a href="<?= app_url('/auth/login.php') ?>">Sign In</a>
    </p>
  </div>
</footer>

<!-- Shared JavaScript -->
<script>
(function () {
  'use strict';

  /* ── Mobile nav toggle ────────────────────────────────── */
  const hamburger = document.getElementById('nav-hamburger');
  const nav       = document.getElementById('main-nav');

  if (hamburger && nav) {
    hamburger.addEventListener('click', function () {
      const open = nav.classList.toggle('open');
      hamburger.setAttribute('aria-expanded', open);
      hamburger.textContent = open ? '✕' : '☰';
    });
  }

  /* ── User dropdown ────────────────────────────────────── */
  const dropdownBtn  = document.getElementById('user-menu-btn');
  const dropdown     = document.getElementById('user-dropdown');

  if (dropdownBtn && dropdown) {
    dropdownBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const open = dropdown.classList.toggle('open');
      dropdownBtn.setAttribute('aria-expanded', open);
    });

    document.addEventListener('click', function () {
      dropdown.classList.remove('open');
      if (dropdownBtn) dropdownBtn.setAttribute('aria-expanded', 'false');
    });
  }

  /* ── Password strength meter ──────────────────────────── */
  const pwInput = document.getElementById('password');
  const fill    = document.getElementById('pw-strength-fill');
  const label   = document.getElementById('pw-strength-text');

  if (pwInput && fill) {
    pwInput.addEventListener('input', function () {
      const val    = pwInput.value;
      let score    = 0;
      let strength = '';

      if (val.length >= 8)                     score++;
      if (/[A-Z]/.test(val))                   score++;
      if (/[0-9]/.test(val))                   score++;
      if (/[^A-Za-z0-9]/.test(val))            score++;

      if      (val.length === 0) { strength = ''; }
      else if (score <= 1)       { strength = 'weak'; }
      else if (score === 2)      { strength = 'fair'; }
      else if (score === 3)      { strength = 'good'; }
      else                       { strength = 'strong'; }

      fill.className = 'pw-strength-fill ' + strength;

      if (label) {
        const labels = { '': '', weak: 'Weak', fair: 'Fair', good: 'Good', strong: 'Strong' };
        label.textContent = labels[strength] || '';
      }
    });
  }

  /* ── Show password toggle ─────────────────────────────── */
  document.querySelectorAll('[data-toggle-pw]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const target = document.getElementById(btn.dataset.togglePw);
      if (!target) return;
      const isText = target.type === 'text';
      target.type  = isText ? 'password' : 'text';
      btn.textContent = isText ? '👁' : '🙈';
      btn.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
    });
  });

  /* ── Auto-dismiss toasts ──────────────────────────────── */
  document.querySelectorAll('.toast').forEach(function (t) {
    setTimeout(function () { t.remove(); }, 4200);
  });

  /* ── Confirm dialogs ──────────────────────────────────── */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.dataset.confirm)) {
        e.preventDefault();
      }
    });
  });

})();
</script>

<?php if (!empty($extraScripts)) echo $extraScripts; ?>

</body>
</html>
