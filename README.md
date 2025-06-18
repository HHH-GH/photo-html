## Photo HTML

Scripts that take a folder of images and produce HTML code to copy-paste into our CMS.

Public repo for example only, make your own version using this code, whatever.

### Set up
- Save `env.php.sample` as `env.php` and define the constants to suit your server setup.

---

### Print Photo Set

Fill in the form and submit to generate HTML code for a set of photos - will generate featured image, list image srcsets, standalone image tags, and a collection of figure tags with individual images inside.

#### How to
- The files should already have been uploaded to the `PHOTOS_PUBLIC_BASE_URL` set in `env.php`, including (if needed) the six main photos to make srcsets (same rules as Make Srcset below)
- The same files also need to be available to read in their folder in the `PHOTOS_PRINT_LOCAL_PATH` set in `env.php`
- Fill form, submit
- Copy the generated image tags into the CMS fields

---

### Make Srcset

Fill in a form and submit to generate HTML code for featured image, list image srcsets, and standalone image tags, as long as the images are named according to the rules.

#### How to

- The files should already have been uploaded to the `PHOTOS_PUBLIC_BASE_URL` set in `env.php`
- Delete any old photos from the `PHOTOS_SRCSET_RELATIVE_PATH` set in `env.php`
- Copy the new photos into the `PHOTOS_SRCSET_RELATIVE_PATH`, one set at a time
- Fill form, submit
- Copy the generated image tags into the CMS fields

#### Image naming rules

- Expects six photos, same basename, suffixed with the sizes as below (because that's what I need for my use case).
- BASENAME_112x112.jpg
- BASENAME_192x128.jpg
- BASENAME_320x215.jpg
- BASENAME_608x344.jpg
- BASENAME_720x405.jpg
- BASENAME_1024x576.jpg

---

## Thanks to our sponsor

This repo is sponsored by Muzza’s Image Warehouse.