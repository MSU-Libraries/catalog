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
      btn.innerText = "View Access & Accessibility Information";
      btn.setAttribute("data-content", record.concurrent_users + record.authorized_users + record.accessibility_link);
      btn.setAttribute("data-toggle", "popover");
      btn.setAttribute("data-html", "true");
      btn.setAttribute("class", "btn btn-primary msul-access-btn");
      link[0].parentNode.append(btn);
    }
  });
  $('[data-toggle="popover"]').popover();
}

$( function pageLoad() {
  // Get the title of the item
  var title = $("#title_short")[0].value;
  var recordId = $("#id")[0].value;

  // Make AJAX request to get the license agreement information if this is an HLM record
  if (title && recordId.substring(0, 3) === "hlm") {
    getLicenseAgreement(title);
  }
});
