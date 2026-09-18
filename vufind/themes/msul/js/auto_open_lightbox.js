document.addEventListener('DOMContentLoaded', function handleAutoLogin() {
  const autoLoginLink = document.querySelector('.js-auto-login[data-auto-open="true"]');

  if (autoLoginLink) {
    autoLoginLink.click();
  }
});
