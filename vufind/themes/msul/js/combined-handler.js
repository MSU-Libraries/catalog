/*global VuFind */

function getSearchRouteUrl(searchClassId) {
  return VuFind.config.get('search-class-urls')[searchClassId] || '/Search/Results';
}

/**
 * Javascript implementation of VuFind\Controller\CombinedController->searchboxAction()
 * Default action is NOT implemented and will fall back to using Controller.
 * When updating this method, you must also update the controller searchboxAction()
 */
function searchboxAction() {
  const form = document.getElementById('searchForm');
  const params = new URLSearchParams(new FormData(form));
  const [type, target] = params.get('type').split(':', 2);

  switch (type) {
  case 'VuFind': {
    const [searchClassId, subType] = target.split('|', 2);
    params.set('type', subType);

    const activeClass = params.get('activeSearchClassId');
    if (activeClass !== searchClassId) {
      params.delete('filter');
    }
    params.delete('activeSearchClassId');

    const baseUrl = getSearchRouteUrl(searchClassId);

    window.location.href = baseUrl + '?' + params.toString();
    return true;
  }
  case 'External': {
    const lookfor = params.get('lookfor') || '';
    let finalTarget = '';
    if (!target.includes('%%lookfor%%')) {
      finalTarget = target + encodeURIComponent(lookfor);
    } else {
      finalTarget = target.replace('%%lookfor%%', encodeURIComponent(lookfor));
    }

    window.location.href = finalTarget;
    return true;
  }
  }
  // Leave invalid `type` values to be processed by the PHP controller
  return false;
}

document.addEventListener('DOMContentLoaded', function DOMContentLoadedEvent() {
  let searchForm = document.getElementById('searchForm');
  if (searchForm != null) {
    searchForm.addEventListener('submit', function searchFormEvent(event) {
      if (searchboxAction()) {
        event.preventDefault();
      }
    });
  }
});
