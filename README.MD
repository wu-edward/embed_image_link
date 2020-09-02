# Embed Image Link

This is a Drupal module to allow images embedded in CKEditor via the Entity Embed module to link to any URL. It 
leverages the functionality from Lightning Media/Lighting Media Image 3.x to use Field Formatters to specify display 
options. The field formatter provided presents additional options to specify whether the image will link to a specified 
URL, what that URL is, and `target="_blank"` and `rel="nofollow"` toggles.

To use:
1. Configure embed button at `/admin/config/content/embed`
2. Click `Edit` link to the relevant embed button
3. Check `Embed image link image` under `Allowed Entity Embed Display plugins` and save.
4. Configure text format at `/admin/config/content/formats`
5. Click `Configure` operation for relevant text format
6. Make sure the embed button is in the `Active Toolbar` for the text format
7. Make sure that `Display embedded entities` is checked under `Enabled filters`
8. Make sure that `<drupal-entity data-* title alt>` is included in the `Allowed HTML tags`
9. Save the text format configuration
10. Edit or create any entity with a formatted text field.
11. Find the embed button appears when the text format is selected in the editor.
12. Press the embed button and select an image.
13. In `Link image to` dropdown field in the `Embed media item` dialog, select `Specify a URL`
14. Enter a URL in the `Specify a URL to link to` field when it appears.
15. Optionally check the `Add rel="nofollow" to links` or `Open link in new window` buttons
16. Press `Embed`
17. Save the entity, and observe that on view the image will link to the specified address.