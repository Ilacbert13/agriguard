// AGRIGUARD login + auth password fields
// - Toggle password visibility without relying on Lucide's DOM replacement.

import { domReady } from '../shared/domReady';

domReady(() => {
    document.querySelectorAll('.input-wrap.with-password-toggle').forEach((wrap) => {
        const toggleBtn = wrap.querySelector('[data-password-toggle]');
        const passwordInput = wrap.querySelector('[data-password-input]');
        if (!toggleBtn || !passwordInput) {
            return;
        }

        const eyeIconWrap = toggleBtn.querySelector('[data-eye-icon]');
        const eyeOffIconWrap = toggleBtn.querySelector('[data-eye-off-icon]');

        const sync = () => {
            const isPasswordHidden = passwordInput.type === 'password';

            if (eyeIconWrap) eyeIconWrap.classList.toggle('hidden', !isPasswordHidden);
            if (eyeOffIconWrap) eyeOffIconWrap.classList.toggle('hidden', isPasswordHidden);

            toggleBtn.setAttribute('aria-label', isPasswordHidden ? 'Show password' : 'Hide password');
        };

        toggleBtn.addEventListener('click', () => {
            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
            sync();
        });

        sync();
    });
});
