// Small bits of JavaScript: form validation, show/hide password, auto-hide alerts.
(function () {
    'use strict';

    // 1. Client-side validation (the server validates again; this is just for quick feedback)
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            // Buttons marked "formnovalidate" (Update cart, Remove) skip the checks
            if (event.submitter && event.submitter.hasAttribute('formnovalidate')) {
                return;
            }
            var pw = form.querySelector('[name="password"]');
            var cf = form.querySelector('[name="confirm"]');
            if (pw && cf) {
                cf.setCustomValidity(pw.value !== cf.value ? 'Passwords do not match' : '');
            }
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // 2. Show / hide password
    document.querySelectorAll('.toggle-pw').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Hide' : 'Show';
        });
    });

    // 3. Fade out success/info messages after a few seconds
    document.querySelectorAll('.alert.flash.alert-success, .alert.flash.alert-info').forEach(function (el) {
        setTimeout(function () {
            if (window.bootstrap) {
                bootstrap.Alert.getOrCreateInstance(el).close();
            }
        }, 4500);
    });
})();
