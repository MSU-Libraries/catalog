/* global ArcGisMiddleware */

/**
 * Creates an instance of ArcGisMiddleware
 * to generate the map for the provided floor.
 * 
 * Expects a JSON element with the ID of arc-gis-variables
 * to be present on the page with the following keys:
 *  - floorId
 *
 * Expects an element with the ID of arcgis-result to be present
 * on the page to load the final map into.
 */
function renderArcGisMap() {
  const $container = $('#arcgis-result');

  // Only run if the container exists and hasn't been loaded yet
  if ($container.length > 0 && !$container.data('map-loaded')) {
    //console.debug("Initializing ArcGIS Map...");
    $container.data('map-loaded', true);
    var data = JSON.parse(document.getElementById('arc-gis-variables').textContent);
    //console.debug(data);

    try {
      // Fail if we can't find the parameters we needed from the arc-gis-variables element
      const requiredKeys = ['portalUrl', 'mapId', 'buildingId', 'floorId'];
      for (const key of requiredKeys) {
        if (!data[key] || data[key].toString().trim() === "") {
          throw new Error(`Missing required ArcGIS variable: ${key}`);
        }
      }

      const arcGisMiddleware = new ArcGisMiddleware(
        data.portalUrl,
        data.mapId
      );

      arcGisMiddleware.setBuildingById(data.buildingId);
      arcGisMiddleware.setFloorById(data.floorId);

      const result = arcGisMiddleware.generate();
      if (result.errors.length > 0) {
        throw new Error(result.errors.join(", "));
      }
      $container.empty().append(result.element);
      //console.debug("Map successfully injected.");
    } catch (error) {
      console.error("ArcGisMiddleware Error:", error);
      // Reset flag so we can try again if it was a temporary failure
      $container.data('map-loaded', false);
    }
  }
}

$(function initArcGisMap() {
  //console.debug("ArcGIS Map Controller Active");

  // Run immediately for standalone pages
  renderArcGisMap();

  // Also run after any AJAX call for Lightboxes
  $(document).on("ajaxComplete", function handleAjaxComplete() {
    if ($('#modal').is(':visible')) {
      renderArcGisMap();
    }
  });
});
