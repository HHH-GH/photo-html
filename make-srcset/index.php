<?php
// Check if the env file exists and if the constants are defined
$envFile = dirname(__DIR__) . '/env.php';
if (!file_exists($envFile)) {
    die('Missing env.php. Copy env.php.sample, update it, and save it as env.php to begin using the scripts.');
}
// Load env.php
require $envFile;

// Check required constants are defined and not empty
$requiredConstants = [
    'PHOTOS_PUBLIC_BASE_URL',
    'PHOTOS_PRINT_LOCAL_PATH',
    'PHOTOS_SRCSET_RELATIVE_PATH',
    'PHOTOS_SRCSET_ABSOLUTE_PATH'
];

foreach ($requiredConstants as $const) {
    if (!defined($const) || trim(constant($const)) === '') {
        die("Missing or empty config constant: $const");
    }
}

/**
 * THE PROGRAM
 * 
 * Form submission provides these vars in $_POST
 * 1. Eventual online folder e.g. it's location at PHOTOS_PUBLIC_BASE_URL, like PHOTOS_PUBLIC_BASE_URL/[foldername]
 * 2. Photo alt text e.g "Great Wall tower"
 * 3. Photo alt prefix e.g. "Chinese Knot Great Wall", usually the name of the hike for SEO
 * 
 * Validate and clean those variables and add to array $clean_post_data
 * - $clean_post_data["photoset_folder"], a valid folder name, no exploits
 * - $clean_post_data["photoset_alt"], text that is okay to use in an html attribute, no exploits
 * - $clean_post_data["photoset_alt_prefix"], text that is okay to use in an html attribute, no exploits
 * 
 * Create a srcset and standalone image tags according to BUSINESS LOGIC
 * - Read the files in PHOTOS_SRCSET_RELATIVE_PATH and generate HTML img/srcset tags according to BUSINESS LOGIC
 * - Write the img/srcset tags to the page for preview
 * - Print each img/srcset tag in a textarea for copy-paste
 * 
 * What tags are created out of which files?
 * - The JPG/GIF/PNG files in PHOTOS_SRCSET_RELATIVE_PATH are suffixed with their dimensions, and should have a common basename
 * - For the featured image srcset we expect three files suffixed as follows
 *   - _1024x576.jpg
 *   - _720x405.jpg
 *   - _320x215.jpg
 * - For the list image srcset we expect two files suffixed as follows
 *   - _720x405.jpg
 *   - _320x215.jpg
 * - For the 720px img figure tag, two files
 *   - _1024x576.jpg
 *   - _720x405.jpg
 * - And individual image tags are generated for each of these sizes that are present
 *   - _1024x576.jpg
 *   - _720x405.jpg
 *   - _608x344.jpg
 *   - _320x215.jpg
 *   - _192x128.jpg
 *   - _112x112.jpg
 * 
 */

// Set up some variables
$clean_post_data = [
    'photoset_folder' => '',
    'photoset_alt' => '',
    'photoset_alt_prefix' => '',
];

// Version of the img/srcset based on relative file location (for preview, in case the files are not yet on the live site)
$img_srcset_tags_local = [
    'featured_img_srcset_tag' => '',
    'list_img_srcset_tag' => '',
    'figure_img_tag' => '',
    '1024_img_tag' => '',
    '720_img_tag' => '',
    '608_img_tag' => '',
    '320_img_tag' => '',
    '192_img_tag' => '',
    '112_img_tag' => '',    
];

// Makes an img/srcset based on live site file location (for actual use)
$img_srcset_tags_live = [
    'featured_img_srcset_tag' => '',
    'list_img_srcset_tag' => '',
    'figure_img_tag' => '',
    '1024_img_tag' => '',
    '720_img_tag' => '',
    '608_img_tag' => '',
    '320_img_tag' => '',
    '192_img_tag' => '',
    '112_img_tag' => '',    
];

?>
<!doctype html>
<html lang="en">
<head>
<title>Make Srcset - Photos to HTML</title>
<!-- Required meta tags -->
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="robots" content="noindex, nofollow">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="../css/bootstrap.min.css">
<!-- Extra styles -->
<link rel="stylesheet" href="../css/styles.css">

</head>
<body>

<nav class="navbar navbar-dark bg-dark navbar-expand" id="top">
    <div class="container-fluid">
        <a class="navbar-brand" href="../">Photos to HTML</a>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="../print-photo-set/">Print Photo Set</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="./">Make Srcset</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container container-xl">

    <div class="col-8">
        <header class="page-header my-5">
            <h1>Make Srcset</h1>
            <p class="lead">Fill in the form and submit to generate HTML code for featured image and list image srcsets, as long as the images are named according to the rules.</p>

            <ol class="text-left text-muted">
                <li>The files should already have been uploaded to <samp><?php echo PHOTOS_PUBLIC_BASE_URL; ?></samp>
                <li>Delete any old photos from <samp><?php echo PHOTOS_SRCSET_RELATIVE_PATH; ?></samp></li>
                <li>Copy the new photos into <samp><?php echo PHOTOS_SRCSET_RELATIVE_PATH; ?></samp>, one set at a time</li>
                <li>Fill form, submit</li>
                <li>Copy the generated srcsets</li>
                <li>
                    <details>
                        <summary>Image naming rules</summary>
                        <p>Expects six photos, same basename, suffixed with the sizes as below (because that's what I need for my use case).</p>
                        <pre class="bg-light p-4">BASENAME_112x112.jpg
BASENAME_192x128.jpg
BASENAME_320x215.jpg
BASENAME_608x344.jpg
BASENAME_720x405.jpg
BASENAME_1024x576.jpg</pre>
                    </details>
            </ol>

        </header>

    </div>

    <div class="col-8" id="formheader">
        
        <form action="./index.php" method="post">

            <label for="photoset_folder" class="form-label">Eventual online folder</label>
                <div class="input-group mb-3 has-validation">
                <span class="input-group-text" id="photoset_folder_tip"><?php echo PHOTOS_PUBLIC_BASE_URL; ?></span>
                <input type="text" class="form-control" id="photoset_folder" name="photoset_folder" aria-describedby="photoset_folder_tip" required maxlength="255">
                <div id="photoset_folder_help" class="form-text">No leading/trailing slash. This is where the photos are located e.g. <samp>BadalingAncientGreatWall</samp> if the photos are in <samp><?php echo PHOTOS_PUBLIC_BASE_URL; ?>BadalingAncientGreatWall</samp>.</div>
            </div>

            <div class="mb-3">
                <label for="photoset_alt" class="form-label">Photo alt text</label>
                <input type="text" class="form-control" id="photoset_alt" name="photoset_alt" aria-describedby="photoset_alt_help" required maxlength="255">
                <div id="photoset_alt_help" class="form-text">e.g. Hikers on the ABC Great Wall.</div>
            </div>

            <div class="mb-3">
                <label for="photoset_alt_prefix" class="form-label">Photo alt prefix</label>
                <input type="text" class="form-control" id="photoset_alt_prefix" name="photoset_alt_prefix" aria-describedby="photoset_prefix_help" required maxlength="255">
                <div id="photoset_prefix_help" class="form-text">Usually the name of the hike, for SEO, e.g. Chinese Knot Great Wall.</div>
            </div>

            <button type="submit" class="btn btn-primary">Generate the HTML code</button> <a href="./" class="btn btn-link ms-4">Start again</a>

        </form>
        
    </div>

    <hr>

    <div class="col-10">
        <h2>Photos preview and HTML</h2>
        <p class="lead">Preview the photos, and then copy the HTML.</p>

        <?php
            // Print the HTML tags for preview
            $had_tags = "N"; // Write a message if this is still N at the end of the loop
            foreach ( $img_srcset_tags_local as $tag ) {
                if( !empty($tag) AND $tag !== '' ) {
                    echo $tag . "<br>";
                    $had_tags = "Y";
                }
            }
            
            // Write a message if there were no HTML tags to preview
            if( $had_tags === 'N') {
                ?>
                <div class="alert alert-warning" role="alert">
                    No image tags to print. (Yet?)
                </div>
                <?php
            }

        ?>
    </div>

    <hr>

    <div class="col-8">
        <h2>Copy the HTML from here</h2>    
        <p>Copy-paste form goes here</p>
    </div>

    <div class="back-to-top-wrapper">
        <div class="back-to-top-link-container border border-secondary rounded p-2 bg-light mb-2">
            <a href="#formheader" class="back-to-top-link btn btn-link text-nowrap" aria-label="Scroll to Form">Scroll to form</a><br>
            <a href="#top" class="back-to-top-link btn btn-link text-nowrap" aria-label="Scroll to Top">Back to top</a><br>
        </div>
    </div>

</main>

</body>
</html>