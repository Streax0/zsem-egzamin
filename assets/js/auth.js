document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.passwordToggle);
      const icon = button.querySelector('i');
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      icon?.classList.toggle('bi-eye', !show);
      icon?.classList.toggle('bi-eye-slash', show);
    });
  });
});
