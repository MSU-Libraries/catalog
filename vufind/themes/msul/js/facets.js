/*global VuFind */

/* --- Facet List --- */
VuFind.register('facetList', function FacetList() {
  function getCurrentContainsValue() {
    const containsEl = document.querySelector('.ajax_param[data-name="contains"]');
    return containsEl ? containsEl.value : null;
  }

  function setCurrentContainsValue(val) {
    const containsEl = document.querySelector('.ajax_param[data-name="contains"]');
    if (containsEl) {
      containsEl.value = val;
    }
  }

  function overrideHref(selector, overrideParams = {}) {
    $(selector).each(function overrideHrefEach() {
      const dummyDomain = 'https://www.example.org'; // we need this since the URL class cannot parse relative URLs
      let url = new URL(dummyDomain + $(this).attr('href'));
      Object.entries(overrideParams).forEach(([key, value]) => {
        url.searchParams.set(key, value);
      });
      url = url.href;
      url = url.replaceAll(dummyDomain, '');
      $(this).attr('href', url);
    });
  }

  function updateHrefContains() {
    const overrideParams = { contains: getCurrentContainsValue() };
    overrideHref('.js-facet-sort', overrideParams);
    overrideHref('.js-facet-next-page', overrideParams);
    overrideHref('.js-facet-prev-page', overrideParams);
  }

  function getContent(overrideParams = {}) {
    const ajaxParams = $('.ajax_params').data('params');
    let url = ajaxParams.urlBase;

    for (let [key, val] of Object.entries(ajaxParams)) {
      if (key in overrideParams) {
        val = overrideParams[key];
      }
      url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(val);
    }

    const contains = getCurrentContainsValue();
    if (contains) {
      url += '&contains=' + encodeURIComponent(contains);
    }

    if (!("facetsort" in overrideParams)) {
      const sort = $('.js-facet-sort.active').data('sort');
      if (sort !== undefined) {
        url += '&facetsort=' + encodeURIComponent(sort);
      }
    }

    url += '&ajax=1';

    return Promise.resolve($.ajax({
      url: url
    }));
  }

  function updateContent(overrideParams = {}) {
    $('#facet-info-result').html(VuFind.loading());
    getContent(overrideParams).then(html => {
      let htmlList = '';
      $(VuFind.updateCspNonce(html)).find('.full-facet-list').each(function itemEach() {
        htmlList += $(this).prop('outerHTML');
      });
      $('#facet-info-result').html(htmlList);
      updateHrefContains();
      VuFind.lightbox_facets.setup();
    });
  }

  // Useful function to delay callbacks, e.g. when using a keyup event
  // to detect when the user stops typing.
  // See also: https://stackoverflow.com/questions/1909441/how-to-delay-the-keyup-handler-until-the-user-stops-typing
  var inputCallbackTimeout = null;
  function registerCallbacks() {
    $('.facet-lightbox-filter').removeClass('hidden');

    $('.ajax_param[data-name="contains"]').on('input', function onInputChangeFacetList(event) {
      clearTimeout(inputCallbackTimeout);
      if (event.target.value.length < 1) {
        $('#btn-reset-contains').addClass('hidden');
      } else {
        $('#btn-reset-contains').removeClass('hidden');
      }
      inputCallbackTimeout = setTimeout(function onInputTimeout() {
        updateContent({facetpage: 1});
      }, 500);
    });

    $('#btn-reset-contains').on('click', function onResetClick() {
      setCurrentContainsValue('');
      $('#btn-reset-contains').addClass('hidden');
      updateContent({facetpage: 1});
    });
  }

  function setup() {
    if ($.isReady) {
      registerCallbacks();
    } else {
      $(function ready() {
        registerCallbacks();
      });
    }
  }

  return { setup: setup, getContent: getContent, updateContent: updateContent };
});

/* --- Side Facets --- */
var multiFacetsSelection; // Defined in results.php, here to prevent pipeline from failing
VuFind.register('sideFacets', function SideFacets() {
  let globalAddedParams = [];
  let globalRemovedParams = [];
  let initialRawParams = window.location.search.substring(1).split('&');
  let initialFilteredParams = initialRawParams.filter(function isFilter(obj) {
    return obj.startsWith(encodeURI('filter[]='));
  });
  let dateSelectorId;

  function stickApplyFiltersButtonAtTopWhenScrolling() {
    let applyFilters = $('#apply-filters');
    let blankBrick = $('#blank-brick');
    $(window).scroll(function fixButton() {
      // To handle delayed loading elements changing the elements offset in the page
      // We update the offset, depending if we past the button or not
      if ($(window).scrollTop() > blankBrick.offset().top) {
        applyFilters.addClass('fixed');
      } else {
        applyFilters.removeClass('fixed');
      }
    });
  }

  function showLoadingOverlay() {
    let elem;
    if (this === undefined) {
      elem = $('#search-sidebar .collapse');
    } else {
      elem = $(this).closest(".collapse");
    }
    elem.append(
      '<div class="facet-loading-overlay">'
      + '<span class="facet-loading-overlay-label">'
      + VuFind.loading()
      + '</span></div>'
    );
  }

  function handleDateSelector() {
    if (dateSelectorId === undefined) {
      return;
    }

    let dateParams = [];
    let allEmptyDateParams = true;
    $('form#' + dateSelectorId + ' .date-fields input').each(function checkDateParams() {
      if (window.location.search.match(this.name)) {
        // If the parameter is already present we update it
        let count = initialRawParams.length;
        for (let i = 0; i < count; i++) {
          if (initialRawParams[i].startsWith(this.name + '=')) {
            initialRawParams[i] = encodeURI(this.name + '=' + this.value); // Update
            // If not empty we add it to date params
            if (this.value !== '') {
              allEmptyDateParams = false;
            }
            break;
          }
        }
      } else {
        dateParams.push(encodeURI(this.name + '=' + this.value));
        if (this.value !== '') {
          allEmptyDateParams = false;
        }
      }
    });
    // If at least one parameter is not null we continue the routine for the final URL
    if (allEmptyDateParams === false) {
      globalAddedParams = globalAddedParams.concat(dateParams);
      let fieldName = $('form#' + dateSelectorId + ' input[name="daterange[]"]').val();
      let dateRangeParam = encodeURI('daterange[]=' + fieldName);
      if (!window.location.search.match(dateRangeParam)) {
        globalAddedParams.push(dateRangeParam);
      }
    }
  }

  function getHrefWithNewParams() {
    handleDateSelector();
    // Unique parameters
    initialRawParams = initialRawParams.filter(function onlyUnique(value, index, array) {
      return array.indexOf(value) === index;
    });
    // Removing parameters
    initialRawParams = initialRawParams.filter(function tmp(obj) { return !globalRemovedParams.includes(obj); });
    // Adding parameters
    initialRawParams = initialRawParams.concat(globalAddedParams);
    return window.location.pathname + '?' + initialRawParams.join('&');
  }

  function applyMultiFacetsSelection() {
    $('#applyMultiFacetsSelection').off();
    showLoadingOverlay();
    window.location.assign(getHrefWithNewParams());
  }

  function applyFiltersAnimation() {
    document.getElementById('applyMultiFacetsSelection').classList.add('transform');
    setTimeout(function fn() {
      document.getElementById('applyMultiFacetsSelection').classList.remove('transform');
    }, 2000);
  }

  function dateSelectorInit() {
    dateSelectorId = $('div.facet form .date-fields').parent().attr('id');
    if (dateSelectorId !== undefined) {
      $('form#' + dateSelectorId + ' input[type="submit"]').remove();
      $('form#' + dateSelectorId + ' input').on('change', applyFiltersAnimation);
    }
  }

  function multiFacetsSelectionHandling(e) {
    e.preventDefault();
    let elem = $(e.currentTarget);

    if (elem.hasClass('facet')) {
      elem.toggleClass('active');
    }
    let icon = elem.find('.icon');
    if (icon.hasClass('fa-check-square-o')) {
      icon.removeClass('fa-check-square-o');
      icon.addClass('fa-square-o');
    } else if (icon.hasClass('fa-square-o')) {
      icon.addClass('fa-check-square-o');
      icon.removeClass('fa-square-o');
    }
    let href = elem.attr('href');
    if (href[0] === '?') {
      href = href.substring(1);
    } else {
      href = href.substring(window.location.pathname.length + 1);
    }
    let clickedParams = href.split('&').filter(function isAdded(obj) {
      if (!obj.startsWith(encodeURI('filter[]='))) {
        return false;
      }
      // If the element was previously added (in JS)
      // we remove it from the corresponding array (coming back to initial state)
      let indexAdd = globalAddedParams.indexOf(obj);
      if (indexAdd !== -1) {
        globalAddedParams.splice(indexAdd, 1);
        return false;
      } else {
        return true;
      }
    });
    let addedParams = clickedParams.filter(function isAdded(obj) {
      // We want to keep only if they are not already in current params
      return !initialFilteredParams.includes(obj);
    });
    let removedParams = initialFilteredParams.filter(function isRemoved(obj) {
      // If param present in clicked but not initial
      if (clickedParams.includes(obj) === false) {
        // If the element was previously removed (in JS)
        // we remove it from the corresponding array (coming back to initial state)
        let indexRemoved = globalRemovedParams.indexOf(obj);
        if (indexRemoved !== -1) {
          globalRemovedParams.splice(indexRemoved, 1);
          return false;
        } else {
          return true;
        }
      } else {
        // If param in both clicked and initial we don't want it
        return false;
      }
    });
    if (addedParams.length !== 1 || addedParams[0] !== "") {
      // We don't concat if there is only one empty element
      globalAddedParams = globalAddedParams.concat(addedParams);
    }
    if (removedParams.length !== 1 || removedParams[0] !== "") {
      // We don't concat if there is only one empty element
      globalRemovedParams = globalRemovedParams.concat(removedParams);
    }
    applyFiltersAnimation();
  }

  function facetLinkClicked(e) {
    if (multiFacetsSelection === true) {
      multiFacetsSelectionHandling(e);
    } else {
      showLoadingOverlay();
    }
  }

  function multiFacetsSelectionInit() {
    $('a.facet,.facet a').click(multiFacetsSelectionHandling);
    dateSelectorInit();
    // Adding the button
    $('#search-sidebar h2').first().after(
      '<div id="blank-brick">' +
      '</div>' +
      '<div id="apply-filters">' +
      '<button id="applyMultiFacetsSelection" type="submit" class="btn btn-primary">Apply filters</button>' +
      '</div>'
    );
    $('#applyMultiFacetsSelection').click(applyMultiFacetsSelection);
    stickApplyFiltersButtonAtTopWhenScrolling();
  }

  function activateFacetBlocking(context) {
    let finalContext = (typeof context === "undefined") ? $(document.body) : context;
    finalContext.find('a.facet:not(.narrow-toggle),.facet a').click(showLoadingOverlay);
  }

  function facetClickHandling(context) {
    if (multiFacetsSelection === true) {
      let finalContext = (typeof context === "undefined") ? $(document.body) : context;
      finalContext.find('a.facet:not(.narrow-toggle),.facet a').click(multiFacetsSelectionHandling);
    } else {
      activateFacetBlocking(context);
    }
  }

  function activateSingleAjaxFacetContainer() {
    var $container = $(this);
    var facetList = [];
    var $facets = $container.find('div.collapse.in[data-facet], div.collapse.show[data-facet], .checkbox-filter[data-facet]');
    $facets.each(function addFacet() {
      if (!$(this).data('loaded')) {
        facetList.push($(this).data('facet'));
      }
    });
    if (facetList.length === 0) {
      return;
    }
    const querySuppressed = $container.data('querySuppressed');
    let query = window.location.search.substring(1);
    if (querySuppressed) {
      // When the query is suppressed we can't use the page URL directly since it
      // doesn't contain the actual query, so take the full query and update any
      // parameters that may have been dynamically modified (we deliberately avoid)
      // touching anything else to avoid encoding issues e.g. with brackets):
      const storedQuery = new URLSearchParams($container.data('query'));
      const windowQuery = new URLSearchParams(query);
      ['sort', 'limit', 'page'].forEach(key => {
        const val = windowQuery.get(key);
        if (null !== val) {
          storedQuery.set(key, val);
        } else {
          storedQuery.delete(key);
        }
      });
      query = storedQuery.toString();
    }
    var request = {
      method: 'getSideFacets',
      searchClassId: $container.data('searchClassId'),
      location: $container.data('location'),
      configIndex: $container.data('configIndex'),
      querySuppressed: querySuppressed,
      extraFields: $container.data('extraFields'),
      enabledFacets: facetList
    };
    $container.find('.facet-load-indicator').removeClass('hidden');
    $.getJSON(VuFind.path + '/AJAX/JSON?' + query, request)
      .done(function onGetSideFacetsDone(response) {
        $.each(response.data.facets, function initFacet(facet, facetData) {
          var containerSelector = typeof facetData.checkboxCount !== 'undefined'
            ? '.checkbox-filter' : ':not(.checkbox-filter)';
          var $facetContainer = $container.find(containerSelector + '[data-facet="' + facet + '"]');
          $facetContainer.data('loaded', 'true');
          if (typeof facetData.checkboxCount !== 'undefined') {
            if (facetData.checkboxCount !== null) {
              $facetContainer.find('.avail-count').text(
                facetData.checkboxCount.toString().replace(/\B(?=(\d{3})+\b)/g, VuFind.translate('number_thousands_separator'))
              );
            }
          } else if (typeof facetData.html !== 'undefined') {
            $facetContainer.html(VuFind.updateCspNonce(facetData.html));
            facetClickHandling($facetContainer);
          }
          $facetContainer.find('.facet-load-indicator').remove();
        });
        VuFind.lightbox.bind($('.sidebar'));
        VuFind.emit('VuFind.sidefacets.loaded');
      })
      .fail(function onGetSideFacetsFail() {
        $container.find('.facet-load-indicator').remove();
        $container.find('.facet-load-failed').removeClass('hidden');
      });
  }

  function loadAjaxSideFacets() {
    $('.side-facets-container-ajax').each(activateSingleAjaxFacetContainer);
  }

  function facetSessionStorage(e, data) {
    var source = $('#result0 .hiddenSource').val();
    var id = e.target.id;
    var key = 'sidefacet-' + source + id;
    sessionStorage.setItem(key, data);
  }

  function init() {
    if (multiFacetsSelection === true) {
      multiFacetsSelectionInit();
    } else {
      // Display "loading" message after user clicks facet:
      activateFacetBlocking();
    }

    $('.facet-group .collapse').each(function openStoredFacets(index, item) {
      var source = $('#result0 .hiddenSource').val();
      var storedItem = sessionStorage.getItem('sidefacet-' + source + item.id);
      if (storedItem) {
        const oldTransitionState = VuFind.disableTransitions(item);
        try {
          if ((' ' + storedItem + ' ').indexOf(' in ') > -1) {
            $(item).collapse('show');
          } else if (!$(item).data('forceIn')) {
            $(item).collapse('hide');
          }
        } finally {
          VuFind.restoreTransitions(item, oldTransitionState);
        }
      }
    });

    // Save state on collapse/expand:
    $('.facet-group').on('shown.bs.collapse', (e) => facetSessionStorage(e, 'in'));
    $('.facet-group').on('hidden.bs.collapse', (e) => facetSessionStorage(e, 'collapsed'));

    // Side facets loaded with AJAX
    if (VuFind.getBootstrapMajorVersion() === 3) {
      $('.side-facets-container-ajax')
        .find('div.collapse[data-facet]:not(.in)')
        .on('shown.bs.collapse', loadAjaxSideFacets);
    } else {
      document.querySelectorAll('.side-facets-container-ajax div[data-facet]').forEach((collapseEl) => {
        collapseEl.addEventListener('shown.bs.collapse', loadAjaxSideFacets);
      });
    }
    loadAjaxSideFacets();

    // Keep filter dropdowns on screen
    $(".search-filter-dropdown").on("shown.bs.dropdown", function checkFilterDropdownWidth(e) {
      var $dropdown = $(e.target).find(".dropdown-menu");
      if ($(e.target).position().left + $dropdown.width() >= window.innerWidth) {
        $dropdown.addClass("dropdown-menu-right");
      } else {
        $dropdown.removeClass("dropdown-menu-right");
      }
    });
  }

  return { init: init, showLoadingOverlay: showLoadingOverlay, facetLinkClicked: facetLinkClicked };
});

/* --- Lightbox Facets --- */
VuFind.register('lightbox_facets', function LightboxFacets() {
  function lightboxFacetSorting() {
    var sortButtons = $('.js-facet-sort');
    function sortAjax(button) {
      var sort = $(button).data('sort');
      VuFind.facetList.updateContent({facetsort: sort});
      $('.full-facet-list').addClass('hidden');
      sortButtons.removeClass('active');
    }
    sortButtons.off('click');
    sortButtons.on('click', function facetSortButton() {
      sortAjax(this);
      $(this).addClass('active');
      return false;
    });
  }

  function setup() {
    lightboxFacetSorting();
    $('.js-facet-next-page').on("click", function facetLightboxMore() {
      let button = $(this);
      const page = parseInt(button.attr('data-page'), 10);
      if (button.attr('disabled')) {
        return false;
      }
      button.attr('disabled', 1);
      button.html(VuFind.translate('loading_ellipsis'));

      const overrideParams = {facetpage: page, layout: 'lightbox', ajax: 1};
      VuFind.facetList.getContent(overrideParams).then(data => {
        $(data).find('.js-facet-item').each(function eachItem() {
          button.before($(this).prop('outerHTML'));
        });
        const list = $(data).find('.js-facet-item');
        if (list.length && $(data).find('.js-facet-next-page').length) {
          button.attr('data-page', page + 1);
          button.attr('href', button.attr('href').replace(/facetpage=\d+/, 'facetpage=' + (page + 1)));
          button.html(VuFind.translate('more_ellipsis'));
          button.removeAttr('disabled');
        } else {
          button.remove();
        }
      });
      return false;
    });
    const updateFacetListHeightFunc = function () {
      const margin = 230;
      $('#modal .lightbox-scroll').css('max-height', window.innerHeight - margin);
    };
    $(window).on('resize', updateFacetListHeightFunc);
    // Initial resize:
    updateFacetListHeightFunc();
  }

  return { setup: setup };
});

function registerSideFacetTruncation() {
  VuFind.truncate.initTruncate('.truncate-facets', '.facet__list__item');
  // Only top level is truncatable with hierarchical facets:
  VuFind.truncate.initTruncate('.truncate-hierarchical-facets', '> li');
}

VuFind.listen('VuFind.sidefacets.loaded', registerSideFacetTruncation);
