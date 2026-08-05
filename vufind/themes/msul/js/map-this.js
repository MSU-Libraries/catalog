/* global ArcGisMiddleware */

/**
 * Function to load the middleware file ArcGIS
 * @param {number}  timeoutMs        Length of time to wait for the middleware to be available
 * @param {number}  pollIntervalMs   Frequency to check for the JS
 */
function ensureArcGisScriptLoaded(timeoutMs = 90000, pollIntervalMs = 100) {
  return new Promise((resolve, reject) => {
    if (typeof window.ArcGisMiddleware !== 'undefined') {
      return resolve();
    }

    const waitForGlobal = () => {
      const startTime = Date.now();

      const interval = setInterval(() => {
        if (typeof window.ArcGisMiddleware !== 'undefined') {
          clearInterval(interval);
          //console.debug("ArcGIS middleware parsed and ready!");
          return resolve();
        }

        if (Date.now() - startTime >= timeoutMs) {
          clearInterval(interval);
          return reject(new Error(`Timed out after ${timeoutMs}ms waiting for window.ArcGisMiddleware execution.`));
        }
      }, pollIntervalMs);
    };

    if (!$('script[src*="arcgis-middleware"]').length) {
      const script = document.createElement('script');
      script.src = '/gis-middleware/arcgis-middleware.iife.js';

      // Pull the nonce dynamically from an existing script tag on the page
      const currentNonce = $('script[nonce]').attr('nonce') || document.querySelector('script[nonce]').nonce;
      if (currentNonce) {
        script.setAttribute('nonce', currentNonce);
      }

      script.onload = () => resolve();
      script.onerror = (err) => reject(err);

      document.head.appendChild(script);
    } else {
      // If script tag already exists, just poll for window.ArcGisMiddleware
      waitForGlobal();
    }
  });
}

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
async function renderArcGisMap() {
  const $container = $('#arcgis-result');

  // Only run if the container exists and hasn't been loaded yet
  if ($container.length > 0 && !$container.data('map-loaded')) {
    $container.data('map-loaded', true);

    // Fail if we can't find the parameters we needed from the arc-gis-variables element
    var data = JSON.parse(document.getElementById('arc-gis-variables').textContent);
    const requiredKeys = ['portalUrl', 'mapId', 'buildingId', 'floorId'];
    for (const key of requiredKeys) {
      if (!data[key] || data[key].toString().trim() === "") {
        $container.data('map-loaded', false);
        throw new Error(`Missing required ArcGIS variable: ${key}`);
      }
    }

    // Set esriConfig BEFORE assets are loaded
    window.esriConfig = {
      assetsPath: '/gis-assets',
      portalUrl: data.portalUrl
    };

    // Load the CSS and JS if not already loaded
    $container.html(
      '<div class="text-center my-3">' +
        '<i class="fa fa-spinner fa-spin fa-2x" aria-hidden="true"></i>' +
        '<span class="sr-only">Loading map...</span>' +
      '</div>'
    );
    try {
      //console.debug("waiting for assets to load");
      await ensureArcGisScriptLoaded();
      $container.empty();
    } catch (err) {
      console.error("Failed to load map assets:", err);
      $container.data('map-loaded', false);
    }

    try {
      //console.debug("Initializing ArcGIS Map...");
      const arcGisMiddleware = new ArcGisMiddleware(
        data.portalUrl,
        data.mapId
      );

      arcGisMiddleware.setBuildingById(data.buildingId);
      arcGisMiddleware.setFloorById(data.floorId);

      // Sets the constriants to MSU, East Lansing, MI
      const constraints = {
        geometry: {
          type: "extent",
          xmin: -84.51,
          ymin: 42.67,
          xmax: -84.46,
          ymax: 42.74
        },
        minScale: 5000,
        maxScale: 0
      };
      arcGisMiddleware.setConstraints(constraints);

      // Set the center the zoom level for the map on load
      // this is also the default for the home button
      const additionalAttributes = {
        'center': "-84.48323542024565, 42.7308616988147",
        'zoom': "18",
        'popup-disabled': true
      };
      arcGisMiddleware.setAdditionalAttributes(additionalAttributes);

      // Add the legend to the map
      arcGisMiddleware.setLegend({
        legendHeadingLevel: 2,
        legendHeadingText: "Legend",
        headingLevel: 3
      });

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

  $(async function loadJS() {
    await ensureArcGisScriptLoaded();
  });

  // Run immediately for standalone pages
  renderArcGisMap();

  // Also run after any AJAX call for Lightboxes
  $(document).on("ajaxComplete", function handleAjaxComplete() {
    if ($('#modal').is(':visible')) {
      renderArcGisMap();
    }
  });
});
