/* global bootstrap */

async function getLicenseAgreement(title) {
  // Make the AJAX call to get the data
  const agreements = await fetch("/AJAX/JSON?method=getLicenseAgreement&title=" + encodeURIComponent(title));
  if (!agreements.ok) return; // don't continue, no data to render to the page
  const agreement_data = await agreements.json();

  // Loop through the results
  agreement_data.data.results.forEach((agreement) => {
    var record = agreement[0];

    // Check if there is data to render
    if (record.concurrent_users || record.authorized_users || record.accessibility_link) {
      // Identify online access link record is associated with
      var link = $("a[href*='" + record.resource_url + "']");

      // Add rendered data from record next to link
      var btn = document.createElement("button");
      var icon = document.createElement("span");
      var label = document.createElement("span");
      icon.setAttribute("class", "icon icon--font fa fa-info icon-link__icon");
      icon.setAttribute("role", "img");
      icon.setAttribute("aria-hidden", "true");
      btn.appendChild(icon);
      label.innerText = "More Info";
      btn.appendChild(label);
      btn.setAttribute("data-bs-content", record.concurrent_users + record.authorized_users + record.accessibility_link);
      btn.setAttribute("data-bs-toggle", "popover");
      btn.setAttribute("data-bs-html", "true");
      btn.setAttribute("class", "placehold");
      link[0].parentNode.append(btn);
      new bootstrap.Popover(btn);
    }
  });
}

$( function pageLoad() {
  var title = $("#title_short")[0];
  if (title == null) {
    return; // There is no title_short element on the page
  }
  // Get the title of the item
  title = title.value;
  var recordId = $("#id")[0].value;

  // Make AJAX request to get the license agreement information if this is an HLM record
  if (title && recordId.substring(0, 3) === "hlm") {
    getLicenseAgreement(title);
  }
});
