document.addEventListener('DOMContentLoaded', function handleAutoLogin() {
  const lightboxElement = document.querySelector('[data-auto-open="true"]');

  if (lightboxElement) {
    lightboxElement.click();
  }
});
