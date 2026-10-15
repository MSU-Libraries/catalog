document.addEventListener('DOMContentLoaded', function handleAutoLogin() {
  const lightboxElement = document.querySelector('[data-auto-open="1"]');

  if (lightboxElement) {
    lightboxElement.click();
  }
});
