document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.getAttribute('aria-controls'));
    const eyeIcon = button.querySelector('[data-eye-icon]');
    const eyeOffIcon = button.querySelector('[data-eye-off-icon]');

    if (!input || !eyeIcon || !eyeOffIcon) {
        return;
    }

    button.addEventListener('click', () => {
        const showPassword = input.type === 'password';

        input.type = showPassword ? 'text' : 'password';
        button.setAttribute('aria-label', showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        button.setAttribute('aria-pressed', String(showPassword));
        eyeIcon.hidden = showPassword;
        eyeOffIcon.hidden = !showPassword;
        input.focus({ preventScroll: true });
    });
});
