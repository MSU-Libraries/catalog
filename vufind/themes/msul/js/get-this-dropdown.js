const KEY_CODE = {
  DOWN_ARROW: 40,
  UP_ARROW: 38,
};
$(function pageLoad() {
  /**
   * Whether the dropdown is open
   * @param {Element} parentDropdown The dropdown we check is triggered
   * @returns {boolean} Whether the dropdown is open
   */
  function isDropdownOpen(parentDropdown) {
    const dropdown = parentDropdown.querySelector(".dropdown");
    return dropdown ? dropdown.classList.contains("active") : false;
  }

  /**
   * Whether the dropdown is targeted
   * @param {Event} click Event triggered by the user
   * @returns {Element | false} Whether the dropdown if it is targeted by the event, false otherwise
   */
  function isDropdownTargeted(click) {
    if (click.target.matches("*[data-js-dropdown], *[data-js-dropdown] *")) {
      return click.target.closest('*[data-js-dropdown]');
    }
    return false;
  }

  /**
   * Whether the link for the current holding is the element being clicked
   * @param {Event} click Event triggered by the user
   * @returns {Element|false} Whether the dropdown if it is targeted by the event, false otherwise
   */
  function isDropdownHeaderTargeted(click) {
    if (click.target.matches("*[data-js-dropdown-header], *[data-js-dropdown-header] *")) {
      return click.target.closest('*[data-js-dropdown]');
    }
    return false;
  }

  /**
   * Whether to show the arrow pointing up or down for the dropdown
   * @param {Element} parentDropdown The dropdown we check is triggered
   * @param {boolean} open True to show the arrow pointing up, False pointing down
   */
  function toggleDropdownArrow(parentDropdown, open) {
    parentDropdown.querySelector(".fa-close-dropdown").style.display = open ? 'block' : 'none';
    parentDropdown.querySelector(".fa-open-dropdown").style.display = open ? 'none' : 'block';
  }

  /**
   * Open / close the dropdown
   * @param {Element} parentDropdown The dropdown we check is triggered
   */
  function toggleDropdown(parentDropdown) {
    const a = parentDropdown.querySelector("*[data-js-dropdown-header] a");
    const siblingDiv = parentDropdown.querySelector(".dropdown");

    if (siblingDiv) {
      siblingDiv.classList.toggle("active");
    }

    const ariaExpanded = a.getAttribute("aria-expanded");
    a.setAttribute("aria-expanded", ariaExpanded === "true" ? "false" : "true");

    const isOpen = isDropdownOpen(parentDropdown);
    if (isOpen) {
      const firstOption = parentDropdown.querySelector(".dropdown a");
      if (firstOption) firstOption.focus();
    }
    toggleDropdownArrow(parentDropdown, isOpen);
  }

  /**
   * Listener on events to open the dropdown
   * @param {Event} e Event triggered by the user
   */
  function openDropdownListener(e) {
    const dropdown = isDropdownHeaderTargeted(e);
    if (dropdown) {
      toggleDropdown(dropdown);
      e.preventDefault();
    }
  }

  /**
   * Listener on events to close the dropdown
   * @param {Event} e Event triggered by the user
   */
  function closeDropdownListener(e) {
    const dropdown = isDropdownTargeted(e);
    if (dropdown && isDropdownOpen(dropdown)) {
      // Need delay to let new focus to happen
      setTimeout(() => {
        if (dropdown.contains(document.activeElement)) return;
        toggleDropdown(dropdown);
      }, 1);
    }
  }

  /**
   * Listener on events to open the dropdown if bottom arrow is pressed
   * @param {Event} e Event triggered by the user
   */
  function openDropdownOnKeyDown(e) {
    const dropdown = isDropdownHeaderTargeted(e);
    if (dropdown && !isDropdownOpen(dropdown) && e.keyCode === KEY_CODE.DOWN_ARROW) {
      toggleDropdown(dropdown);
      e.preventDefault();
    }
  }

  /**
   * Switch the focus to a sibling element in the dropdown
   * @param {HTMLElement} elem Element to focus
   * @returns {boolean} Focus was successful
   */
  function focusSiblingElement(elem) {
    if (elem) {
      const link = elem.querySelector("a");
      if (link) link.focus();
      return true;
    }
    return false;
  }

  /**
   * Listener on keys pressed (up/down) to change the element focused in the dropdown
   * @param {Event} e Event triggered by the user
   */
  function arrowKeysListener(e) {
    const dropdown = isDropdownTargeted(e);
    // If dropdown is not defined or the focus is on an element not in the container
    if (dropdown === false || !dropdown.contains(document.activeElement)) return;

    const li = document.activeElement.closest("li");
    if (!li) return;

    if (e.keyCode === KEY_CODE.DOWN_ARROW) {
      if (focusSiblingElement(li.nextElementSibling)) {
        e.preventDefault();
      }
    } else if (e.keyCode === KEY_CODE.UP_ARROW) {
      if (focusSiblingElement(li.previousElementSibling)) {
        e.preventDefault();
      }
    }
  }

  document.querySelector("body").addEventListener("click", openDropdownListener);
  document.querySelector("body").addEventListener("focusout", closeDropdownListener);
  document.querySelector("body").addEventListener("keydown", openDropdownOnKeyDown);
  document.querySelector("body").addEventListener("keydown", arrowKeysListener);
});