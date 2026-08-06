/*global VuFind, bootstrap */

VuFind.register('copyURL', function copyURL() {
  /**
   * Show popover for 2 seconds
   * @param {object} popover Popover
   */
  function _showPopover(popover) {
    if (popover !== undefined) {
      popover.show();
      setTimeout(() => {
        popover.hide();
      }, 2000);
    }
  }

  /**
   * Initialise copy button
   * @param {Element} button Copy button
   */
  function _initButton(button) {
    if (button.dataset.initialized !== 'true') {
      button.dataset.initialized = 'true';
      if (!button.dataset.url) return;
      const url = button.dataset.url;
      if (!url) return;
      let successPopover;
      const successMessageElement = document.querySelector('#copyUrlSuccess');
      if (successMessageElement) {
        successPopover = new bootstrap.Popover(button.parentNode, {trigger: 'manual', 'html': true, 'content': successMessageElement.innerHTML.trim()});
      }
      let errorPopover;
      const errorMessageElement = document.querySelector('#copyUrlFailure');
      if (errorMessageElement) {
        errorPopover = new bootstrap.Popover(button.parentNode, {trigger: 'manual', 'html': true, 'content': errorMessageElement.innerHTML.trim()});
      }
      button.addEventListener('click', () => {
        navigator.clipboard.writeText(url).then(() => _showPopover(successPopover), () => _showPopover(errorPopover));
      });
      button.classList.remove('hidden');
    }
  }

  /**
   * Initializes the copy URL to clipboard button in the provided container
   * @param {object} params Params (has to include a container element)
   */
  function updateContainer(params) {
    let container = params.container;
    container.querySelectorAll('.copy-url').forEach(_initButton);
  }

  /**
   * Init copy to URL
   */
  function init() {
    updateContainer({container: document});
    VuFind.listen('results-init', updateContainer);
  }

  return { init, updateContainer };
});
