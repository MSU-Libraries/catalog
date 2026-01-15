# FOLIO Tasks
<!-- markdownlint-disable MD024 Duplicate headers -->

## The check out process

If you ever need to verify a feature that requires an item be
checked out to your user, or if you need to test the renewal
process through VuFind, these are the steps you can do in
FOLIO to check out and in an item to yourself for testing
purposes.

### Required Access

This requires that you have either administrator access to
the FOLIO tenant, or access to the following modules:
Inventory, Check out, Check in, Users

### Steps

1. Find an item that is available for checkout in your VuFind instance and
   get the instance ID
2. In the Inventory module in FOLIO, search for that instance ID
3. Copy the item barcode from one of the available holdings
4. Go to the Check out module (if you get a failure due to the service point
   not being set for your user, you must go into the Users module, open your
   user, edit it, then set a service point)
5. On the left search for your NetID, on the right paste in the barcode for
   the item you copied, then click End session
6. To update the due date, open the User module, find your user, expand the
   Loans section and click on the items. You can then select the item and
   update the due date to any date you want. This will allow you to test
   renewals through VuFind.
7. To check the item back in, go to the Check in module and enter the
   item barcode, then End session

## Finding API permissions

This process helps you find the minimal permissions required to make
a specific FOLIO API call.

### Steps

1. Find the documentation for the API call on the
   [official documentation](https://dev.folio.org/reference/api/)
   and note which module you found the call in (i.e. `mod-*`)
2. Go to the GitHub repository for that module. For example `mod-circulation`
   is located at [https://github.com/folio-org/mod-circulation](https://github.com/folio-org/mod-circulation)
3. Within the repository, go into the `descriptors` directory and open the
   `ModuleDescriptor` JSON file
4. Find your API endpoint within that file and look for the `permissionsRequired`
   tag within that block
5. If you are on a pre-Sunflower release, you can simply add that permission to your
   user or role, otherwise continue to the next steps
6. Go to `https://{folio_url}/settings/developer/capabilities` and search for the
   permission you found by permissionName to see that the capability resource, application,
   and action for it
7. Go to `https://{folio_url}/settings/authorization-roles/{my-role-id}`, click edit,
   and under "Capabilities", find the matching resource and application then
   check the box for the appropriate action
