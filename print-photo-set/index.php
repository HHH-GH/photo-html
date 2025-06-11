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
 * 1. Photo set title 'photoset_title' e.g. "Gubeikou Great Wall, 2025/06/07"
 * 2. Photo set brief intro 'photoset_intro' e.g. "18 photos from a hike at the Gubeikou Great Wall 
 * 3. Online folder location 'photoset_folder' e.g. it's location at PHOTOS_PUBLIC_BASE_URL, like PHOTOS_PUBLIC_BASE_URL/[foldername]
 * 
 * Validate and clean those variables and add to array $clean_post_data
 * - $clean_post_data["photoset_title"], text that is okay to use in an html attribute or print to the screen, no exploits
 * - $clean_post_data["photoset_intro"], text that is okay to use in an html attribute or print to the screen, no exploits
 * - $clean_post_data["photoset_folder"], a valid folder name, no exploits
 * 
 * Create srcsets and standalone image tags according to BUSINESS LOGIC
 * - Read the files in PHOTOS_SRCSET_RELATIVE_PATH/[foldername] and generate HTML img/srcset tags according to BUSINESS LOGIC
 * - Write the img/srcset tags to the page for preview
 * - Print each img/srcset tag in a textarea for copy-paste
 * 
 * What tags are created out of which files?
 * - For the featured image srcset we expect three files suffixed as follows
 *   - _1024x576.jpg
 *   - _720x405.jpg
 *   - _320x215.jpg
 * - For the list image srcset we expect two files suffixed as follows
 *   - _720x405.jpg
 *   - _320x215.jpg
 * - Individual individual image tags are generated for each of these sizes that are present
 *   - _1024x576.jpg
 *   - _720x405.jpg
 *   - _608x344.jpg
 *   - _320x215.jpg
 *   - _192x128.jpg
 *   - _112x112.jpg
 * - And then all the JPG/PNG/GIF files that are not suffixed as above are turned into a group of figures with image tags 
 */

// Set up some variables

// Cleaned versions of POST data for printing to the screen
$clean_post_data = [
    'photoset_title' => '',
    'photoset_intro' => '',
    'photoset_folder' => '',
];

// Holds html content for each img/srcset based on live site file location (for actual use)
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

$img_srcset_tag_srcs = [
    '1024_src' => '',
    '720_src' => '',
    '320_src' => '',
];

// Hold the list of photos
$photos_list = [];

// Hold the group of figure tags
$photo_set_figures = [];

// Flag some error/success states
$did_validate = "N"; // Default is didn't pass validation, gets set as Y if $has_errors is empty at the end of the validation process
$has_errors = []; // If !empty, there were errors in validation

/**
 * PROCESS THE FORM SUBMISSION
 *
 * 1. Clean and sanitise the POST vars, add to clean_post_data
 * 2. Validate the data (e.g. not empty, the folder location is readable), set in $has_errors or set $did_validate = "Y"
 * 3. Build the list of photos
 * 4. Use the list of photos to create the html tags for the required img and srcset
 */

if($_SERVER['REQUEST_METHOD'] == "POST")
{
    // 1. Clean and sanitise the POST vars, add to $clean_post_data

    // What data is expected from POST
    /**
     * 'photoset_title'        string with characters that are valid for a html attribute tag or to print to the screen as-is
     * 'photoset_intro'        string with characters that are valid for a html attribute tag or to print to the screen as-is
     * 'photoset_folder'   string with characters that are valid for a folder name i.e. numbers, letters, underscores, hyphens - other things are stripped
     */

    // string with characters that are valid for a html attribute tag
    // ENT_QUOTES | ENT_SUBSTITUTE "ensures quotes are safely encoded and handles invalid UTF-8 by subsituting replacement characters"
    $clean_post_data['photoset_title'] = trim( htmlspecialchars( strip_tags( $_POST['photoset_title'] ?? '' ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) );
    $clean_post_data['photoset_intro'] = trim( htmlspecialchars( strip_tags( $_POST['photoset_intro'] ?? '' ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) );

    // string with characters that are valid for a folder name i.e. numbers, letters, underscores, hyphens - other things are stripped
    $clean_post_data['photoset_folder'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['photoset_folder'] ?? '');

}

?>
<!doctype html>
<html lang="en">
<head>
<title>Print Photo Set - Photos to HTML</title>
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
                    <a class="nav-link" aria-current="page" href="./">Print Photo Set</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../make-srcset/">Make Srcset</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container container-xl">
    
    <div class="col-8">

        <header class="page-header my-5">
            <h1>Print Photo Set</h1>
            <p class="lead">Fill in the form and submit to generate HTML code for a set of photos.</p>
        </header>

    </div>

    <div class="col-8" id="formheader">
        
        <form action="./index.php" method="post">

            <div class="mb-3">
                <label for="photoset_title" class="form-label">Title of photo set</label>
                <input type="text" class="form-control" id="photoset_title" name="photoset_title" aria-describedby="photoset_title_help" required maxlength="255">
                <div id="photoset_title_help" class="form-text">e.g. Switchback Great Wall Camping, 2017/10/06</div>
            </div>

            <div class="mb-3">
                <label for="photoset_intro" class="form-label">Brief intro of photo set</label>
                <textarea class="form-control" id="photoset_intro" name="photoset_intro" aria-describedby="photoset_intro_help" required></textarea>
                <div id="photoset_intro_help" class="form-text">A description of what’s in the set of photos e.g. 18 photos from a hike &hellip;</div>
            </div>

            <label for="photoset_folder" class="form-label">FTP folder name</label>
                <div class="input-group mb-3 has-validation">
                <span class="input-group-text" id="photoset_folder_tip"><?php echo PHOTOS_PUBLIC_BASE_URL; ?></span>
                <input type="text" class="form-control" id="photoset_folder" name="photoset_folder" aria-describedby="photoset_folder_tip" required pattern="^[\d\w\-]*" title="Only letters or numbers or dashes">
                <div id="photoset_folder_help" class="form-text">The name of the folder where you uploaded the photos e.g. <samp>20171006-SwitchbackGreatWallCamping</samp></div>
            </div>

            <button type="submit" class="btn btn-primary">Generate the HTML code</button> <a href="./" class="btn btn-link ms-4">Start again</a>

        </form>

    </div>

    <hr>

    <div class="col-6">
        <h2>Photos preview and HTML</h2>
        <p class="lead">Preview the photos, and then copy the HTML.</p>
    </div>

    <hr>

    <div class="col-8" id="formheader">
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