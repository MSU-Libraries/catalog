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
  $(".modal").on("focusout", ".get-this-dropdown", function getThisDropDownFocus() {
    var dropdown = $(this).get(0);
    // Need delay to let new focus to happen
    setTimeout(function GetThisSetFocus() {
      // Then check if new focus has left the dropdown
      if (! $.contains(dropdown, document.activeElement)) {
        $(".get-this-dropdown div").removeClass('active');
        $(".get-this-dropdown > ul > li > a").attr("aria-expanded", "false");
      }
    }, 1);
  });
  $(".modal").on("keydown", ".get-this-dropdown > ul > li > a", function getThisLinkKey(e) {
    // Down arrow opens dropdown and focuses on first option
    if (e.keyCode === 40) {
      $(".get-this-dropdown div").addClass('active');
      $(".get-this-dropdown > ul > li > a").attr("aria-expanded", "true");
      setTimeout(function getThisSetLinkFocus() {
        $(".get-this-dropdown div a").first().trigger("focus");
      }, 1);
      return false;
    }
  });
  $(".modal").on("keydown", function getThisDropDownKey(e) {
    // Handle arrow key navigation of dropdown selection
    var dropdown = $(".get-this-dropdown div").get(0);
    if ($.contains(dropdown, document.activeElement)) {
      var par = $(document.activeElement).parent();
      if (e.keyCode === 38 && par.prev("li").length > 0) {
        par.prev("li").children("a").focus();
        return false;
      }
      else if (e.keyCode === 40 && par.next("li").length > 0) {
        par.next("li").children("a").focus();
        return false;
      }
    }
  });
});
