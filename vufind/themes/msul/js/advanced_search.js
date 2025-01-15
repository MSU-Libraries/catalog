/*global VuFind */
/*exported addGroup, addSearch, deleteGroup, deleteSearch */

var nextGroup = 0;
var groupLength = [];
var deleteGroup, deleteSearch;

function addSearch(group, _fieldValues, isUser = false) {
  var fieldValues = _fieldValues || {};
  // Build the new search
  var inputID = group + '_' + groupLength[group];
  var $newSearch = $($('#new_search_template').html());

  $newSearch.attr('id', 'search' + inputID);
  $newSearch.find('input.form-control')
    .attr('id', 'search_lookfor' + inputID)
    .attr('name', 'lookfor' + group + '[]')
    .val('');
  $newSearch.find('select.adv-term-type option:first-child').attr('selected', 1);
  $newSearch.find('select.adv-term-type')
    .attr('id', 'search_type' + inputID)
    .attr('name', 'type' + group + '[]');
  $newSearch.find('.adv-term-remove')
    .data('group', group)
    .data('groupLength', groupLength[group])
    .on("click", function deleteSearchHandler() {
      return deleteSearch($(this).data('group'), $(this).data('groupLength'));
    });
  // Preset Values
  if (typeof fieldValues.term !== "undefined") {
    $newSearch.find('input.form-control').val(fieldValues.term);
  }
  if (typeof fieldValues.field !== "undefined") {
    $newSearch.find('select.adv-term-type option[value="' + fieldValues.field + '"]').attr('selected', 1);
  }
  if (typeof fieldValues.op !== "undefined") {
    $newSearch.find('select.adv-term-op option[value="' + fieldValues.op + '"]').attr('selected', 1);
  }
  // Insert it
  $("#group" + group + "Holder").before($newSearch);
  // Individual search ops (for searches like EDS)
  if (groupLength[group] === 0) {
    $newSearch.find('.first-op')
      .attr('name', 'op' + group + '[]')
      .removeClass('hidden');
    $newSearch.find('select.adv-term-op').remove();
  } else {
    $newSearch.find('select.adv-term-op')
      .attr('id', 'search_op' + group + '_' + groupLength[group])
      .attr('name', 'op' + group + '[]')
      .removeClass('hidden');
    $newSearch.find('.first-op').remove();
    $newSearch.find('label').remove();
    // Show x if we have more than one search inputs
    $('#group' + group + ' .adv-term-remove').removeClass('hidden');
  }
  groupLength[group]++;

  if (isUser) {
    $newSearch.find('input.form-control').trigger("focus");
  }

  return false;
}

deleteSearch = function _deleteSearch(group, sindex) {
  for (var i = sindex; i < groupLength[group] - 1; i++) {
    var $search0 = $('#search' + group + '_' + i);
    var $search1 = $('#search' + group + '_' + (i + 1));
    $search0.find('input').val($search1.find('input').val());
    var select0 = $search0.find('select')[0];
    var select1 = $search1.find('select')[0];
    select0.selectedIndex = select1.selectedIndex;
  }
  if (groupLength[group] > 1) {
    groupLength[group]--;
    var toRemove = $('#search' + group + '_' + groupLength[group]);
    var parent = toRemove.parent();
    toRemove.remove();
    if (parent.length) {
      parent.find('.adv-search input.form-control').focus();
    }
    if (groupLength[group] === 1) {
      $('#group' + group + ' .adv-term-remove').addClass('hidden'); // Hide x
    }
  }
  return false;
};

function _renumberGroupLinkLabels() {
  $('.adv-group-close').each(function deleteGroupLinkLabel(i, link) {
    $(link).attr('aria-label', VuFind.translate('del_search_num', {'%%num%%': i + 1}));
  });
}

function addGroup(_firstTerm, _firstField, _join, isUser = false) {
  var firstTerm = _firstTerm || '';
  var firstField = _firstField || '';
  var join = _join || '';

  var $newGroup = $($('#new_group_template').html());
  $newGroup.find('.adv-group-label') // update label
    .attr('for', 'search_lookfor' + nextGroup + '_0');
  $newGroup.attr('id', 'group' + nextGroup);
  $newGroup.find('.search_place_holder')
    .attr('id', 'group' + nextGroup + 'Holder')
    .removeClass('hidden');
  $newGroup.find('.add_search_link')
    .attr('id', 'add_search_link_' + nextGroup)
    .data('nextGroup', nextGroup)
    .on("click", function addSearchHandler() {
      return addSearch($(this).data('nextGroup'), {}, true);
    })
    .removeClass('hidden');
  $newGroup.find('.adv-group-close')
    .data('nextGroup', nextGroup)
    .on("click", function deleteGroupHandler() {
      return deleteGroup($(this).data('nextGroup'));
    });
  $newGroup.find('select.form-control')
    .attr('id', 'search_bool' + nextGroup)
    .attr('name', 'bool' + nextGroup + '[]');
  $newGroup.find('.search_bool')
    .attr('for', 'search_bool' + nextGroup);
  if (join.length > 0) {
    $newGroup.find('option[value="' + join + '"]').attr('selected', 1);
  }

  // Insert
  $('#groupPlaceHolder').before($newGroup);
  _renumberGroupLinkLabels();

  // Populate
  groupLength[nextGroup] = 0;
  addSearch(nextGroup, {term: firstTerm, field: firstField}, isUser);
  // Show join menu
  if (nextGroup > 0) {
    $('#groupJoin').removeClass('hidden');
    // Show x
    $('.adv-group-close').removeClass('hidden');
  }

  $newGroup.children('input.form-control').first().trigger("focus");

  return nextGroup++;
}

deleteGroup = function _deleteGroup(group) {
  // Find the group and remove it
  $("#group" + group).remove();
  _renumberGroupLinkLabels();

  // If the last group was removed, add an empty group
  if ($('.adv-group').length === 0) {
    addGroup();
  } else if ($('#advSearchForm .adv-group').length === 1) {
    $('#groupJoin').addClass('hidden'); // Hide join menu
    $('.adv-group .adv-group-close').addClass('hidden'); // Hide x
  }
  return false;
};

function toggleExpansion(itemOrEvent) {
  // There is a bug with firefox 122, it jumps higher on the page when toggling for the fist time
  // To reproduce the bug, load the page, scroll down to the list, refresh the page, try to toggle an element without doing anything before
  let leveledCheckbox;
  if (itemOrEvent.target !== undefined) {
    leveledCheckbox = $(itemOrEvent.target).closest('.leveledCheckbox');
  } else {
    leveledCheckbox = itemOrEvent;
  }
  let expanding = !leveledCheckbox.hasClass('expanded');
  leveledCheckbox.toggleClass('expanded', expanding);
  let nextItem = leveledCheckbox.next();
  while (nextItem.length > 0 && parseInt($(nextItem).attr('data-level')) > 0) {
    $(nextItem).toggleClass('visibleLevel', expanding);
    nextItem = nextItem.next();
  }
}

function openParentIfChildChecked() {
  $('.leveledCheckboxes').each(function openParent() {
    let item = $(this).find('.leveledCheckbox').last();
    let openParent = false;
    let currentLevel = parseInt($(item).attr('data-level'));
    let previousLevel;
    let previousItem = item.prev();
    let previousItemCheckbox = $(previousItem).find('input[type="checkbox"]');
    while (previousItem.length > 0) {
      previousLevel = parseInt($(previousItem).attr('data-level'));
      // Handle if it's a superior level
      if (previousLevel < currentLevel && openParent === true) {
        toggleExpansion(previousItem);
      } else if (openParent === false && previousItemCheckbox.prop('checked')) {
        // If it's the same level, and we don't know yet if we have to open the parent and it's checked
        openParent = true;
      }
      previousItem = previousItem.prev();
      previousItemCheckbox = $(previousItem).find('input[type="checkbox"]');
      currentLevel = previousLevel;
      if (currentLevel === 0) {
        openParent = false;
      }
    }
  });
}

function toggleChildren(check, item) {
  let itemLevel = parseInt($(item).attr('data-level'));
  let nextItem = item.next();
  while (nextItem.length > 0 && parseInt($(nextItem).attr('data-level')) > itemLevel) {
    $(nextItem).find('input[type="checkbox"]').prop('checked', check);
    $(nextItem).find('input[type="checkbox"]').prop('indeterminate', false);
    nextItem = nextItem.next();
  }
  return nextItem;
}

function checkChildrenIfParentCheckedRoutine() {
  $('.leveledCheckboxes').each(function checkChildren() {
    let item = $(this).find('.leveledCheckbox').first();
    let checked;
    while (item.length > 0) {
      checked = item.find('input[type="checkbox"]').prop('checked');
      if (checked) {
        item = toggleChildren(checked, item);
      } else {
        item = item.next();
      }
    }
  });
}

/**
 * Recursive function
 * @param parentItem
 * @return item last handled item
 */
function uncheckChildrenIfParentChecked(parentItem) {
  let itemChecked = $(parentItem).find('input[type="checkbox"]').prop('checked');
  let itemIndeterminate = $(parentItem).find('input[type="checkbox"]').prop('indeterminate');
  let itemLevel = parseInt($(parentItem).attr('data-level'));

  let nextItem = parentItem.next();
  if (itemChecked) {
    // If it's not a parent it won't enter the loop
    // If it's a parent, and it's checked, uncheck all the children
    while (itemChecked && nextItem.length > 0 && parseInt($(nextItem).attr('data-level')) > itemLevel) {
      $(nextItem).find('input[type="checkbox"]').prop('checked', false);
      $(nextItem).find('input[type="checkbox"]').prop('indeterminate', false);
      nextItem = nextItem.next();
    }
  } else if (itemIndeterminate) {
    // If it's indeterminate, it can only be a parent, run function on the first child
    nextItem = uncheckChildrenIfParentChecked(nextItem);
  }
  // If unchecked, parent or not, do nothing

  return nextItem;
}

/**
 * Routine using the recursive function
 * @param item
 */
function uncheckChildrenIfParentCheckedRoutine(item) {
  let tmp = item;
  while (tmp.length > 0) {
    tmp = uncheckChildrenIfParentChecked(tmp);
  }
}

/**
 * Check the state of previous elements in the list and for parents, toggling to adapt the checkmark
 * @param itemToStartWith
 * @param indeterminate if we know at this point that the parent will be in indeterminate state
 * @param runWholeList if we stop at a root parent or go through the entire list (when starting at the last element)
 */
function checkAndUpdatePreviousLeveledCheckboxes(itemToStartWith, indeterminate = undefined, runWholeList = false) {
  let previousItemChecked, previousItemIndeterminate, previousItemCheckbox, currentLevel;
  let itemChecked = $(itemToStartWith).find('input[type="checkbox"]').prop('checked');
  let previousItem = itemToStartWith;
  let previousLevel = parseInt($(previousItem).attr('data-level'));
  do {
    currentLevel = previousLevel;
    previousItem = previousItem.prev();
    if (previousItem.length === 0) break;
    previousLevel = parseInt($(previousItem).attr('data-level'));
    previousItemCheckbox = $(previousItem).find('input[type="checkbox"]');

    // Handle if it's a superior level
    if (previousLevel < currentLevel) {
      if (indeterminate === true) {
        previousItemCheckbox.prop('indeterminate', true);
        previousItemCheckbox.prop('checked', false);
      } else {
        previousItemCheckbox.prop('checked', itemChecked);
        previousItemCheckbox.prop('indeterminate', false);
      }
    } else {
      // If it's the same level
      previousItemChecked = previousItemCheckbox.prop('checked');
      previousItemIndeterminate = previousItemCheckbox.prop('indeterminate');
      if (itemChecked !== previousItemChecked || previousItemIndeterminate) {
        indeterminate = true;
      }
    }
    if (currentLevel === 0) {
      indeterminate = undefined;
      itemChecked = previousItemCheckbox.prop('checked');
    }
  } while (currentLevel > 0 || runWholeList);
}

function runCheckAndUpdatePreviousLeveledCheckboxesOnWholeList() {
  $('.leveledCheckboxes').each(function updatePreviousLevel() {
    checkAndUpdatePreviousLeveledCheckboxes($(this).find('.leveledCheckbox').last(), undefined, true);
  });
}

function toggleCheck(item) {
  if ($(item.originalEvent.originalTarget).closest('.expander').length > 0) return;
  const checkboxContainer = $(item.target).parents('.leveledCheckbox');
  if (!checkboxContainer.hasClass('leveledCheckbox')) return;

  const itemChecked = $(checkboxContainer).find('input[type="checkbox"]').prop('checked');

  // Modifying children
  // If the select checkbox contains a sub selection, check / uncheck the sub selection
  let nextItem = toggleChildren(itemChecked, checkboxContainer);

  let indeterminate, currentItemChecked, currentItemIndeterminate;
  // Continue looping to see the state of the other elements in the (sub)list
  while (nextItem.length > 0 && parseInt($(nextItem).attr('data-level')) > 0) {
    // If the current element is in a different state than the checkbox clicked by the user
    currentItemChecked = $(nextItem).find('input[type="checkbox"]').prop('checked');
    currentItemIndeterminate = $(nextItem).find('input[type="checkbox"]').prop('indeterminate');
    if (itemChecked !== currentItemChecked || currentItemIndeterminate) {
      indeterminate = true;
      break;
    }
    nextItem = nextItem.next();
  }

  // Modifying parents and checking previous items
  checkAndUpdatePreviousLeveledCheckboxes(checkboxContainer, indeterminate, false);
}

function JSifyLeveledSelect() {
  // Leveling part
  $('.leveledCheckbox').closest('.leveledCheckboxes').addClass('expandableLeveledCheckboxes');
  $('.leveledCheckbox').each(function leveledCheckbox() {
    $(this).html($(this).html().replace(/&nbsp;/g, ''));
    $(this).css('padding-left', parseInt($(this).attr('data-level')) * 1.5 + 'rem');
    $(this).on('click', this, toggleCheck);
  });

  // Checkboxes preparation on page load
  $('.leveledCheckboxes').each(function leveledCheckBox() {
    // Prevent the submission of all the children in the request if the parent is selected
    let leveledCheckboxes = $(this);
    $(this).closest('form').on('submit', this, function preventChildren() {
      uncheckChildrenIfParentCheckedRoutine(leveledCheckboxes.find('.leveledCheckbox').first());
    });
  });
  checkChildrenIfParentCheckedRoutine();
  runCheckAndUpdatePreviousLeveledCheckboxesOnWholeList();

  // Expansion and toggling part
  let leveledCheckboxes = $('.leveledCheckbox[data-level]');
  let prevLevel = 0;
  let currentLevel = 0;
  let expander = $('#jsContent .expander').prop('outerHTML');
  for (let i = leveledCheckboxes.length - 1; i >= 0; i--) {
    currentLevel = parseInt($(leveledCheckboxes[i]).attr('data-level'));
    $(leveledCheckboxes[i]).prepend(expander);
    if (currentLevel < prevLevel) {
      $(leveledCheckboxes[i]).addClass('expandable');
    } else {
      $(leveledCheckboxes[i]).addClass('notExpandable');
    }
    prevLevel = currentLevel;
  }
  openParentIfChildChecked();
  $('.leveledCheckbox .expander').on('click', this, toggleExpansion);
}

$(function advSearchReady() {
  $('.clear-btn').on("click", function clearBtnClick() {
    //MSUL a11y announces All Fields Cleared on click
    window.setTimeout(function fn() {
      document.getElementById('msulClearall').innerText = 'All Fields';
    }, 10);
    window.setTimeout(function fn() {
      document.body.removeChild(document.getElementById('msulClearall').innerText = '');
    }, 5000);
    //MSUL end a11y fix
    $('input[type="text"]').val('');
    $('input[type="checkbox"],input[type="radio"]').each(function onEachCheckbox() {
      var checked = $(this).data('checked-by-default');
      checked = (checked == null) ? false : checked;
      $(this).prop("checked", checked);
    });
    $("option:selected").prop("selected", false);
  });
  JSifyLeveledSelect();
});
