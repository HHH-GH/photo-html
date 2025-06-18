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

// Cleaned versions of POST data for printing to the screen
$clean_post_data = [
    'photoset_folder' => '',
    'photoset_alt' => '',
    'photoset_alt_prefix' => '',
];
$clean_photo_caption = ''; // the same as photoset_alt, but doesn't need the htmlspecialchars when output to screen (htmlspecialchars done when it was cleaned) 

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

// Flag some error/success states
$did_validate = "N"; // Default is didn't pass validation, gets set as Y if $has_errors is empty at the end of the validation process
$has_errors = []; // If !empty, there were errors in validation

// Alt / Alt prefix separator
define("IMG_ALT_PREFIX_SEPARATOR", " | ");

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
     * 'photoset_folder'        string with characters that are valid for a folder name i.e. numbers, letters, underscores, hyphens - other things are stripped
     * 'photoset_alt'           string with characters that are valid for a html attribute tag
     * 'photoset_alt_prefix'    string with characters that are valid for a html attribute tag
     */

    // string with characters that are valid for a folder name i.e. numbers, letters, underscores, hyphens - other things are stripped
    $clean_post_data['photoset_folder'] = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_POST['photoset_folder'] ?? '');

    // string with characters that are valid for a html attribute tag
    // ENT_QUOTES | ENT_SUBSTITUTE "ensures quotes are safely encoded and handles invalid UTF-8 by subsituting replacement characters"
    $clean_post_data['photoset_alt'] = trim( htmlspecialchars( strip_tags( $_POST['photoset_alt'] ?? '' ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) );
    $clean_post_data['photoset_alt_prefix'] = trim( htmlspecialchars( strip_tags( $_POST['photoset_alt_prefix'] ?? '' ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) );


    // 2. Validate the data (e.g. not empty, the folder location is readable), set in $has_errors or set $did_validate = "Y"

    // 2a. Photoset folder variable is not empty
    if( empty( $clean_post_data['photoset_folder'] ) )
    {
        $has_errors['photoset_folder'] = "Photo folder location is required.";
        $did_validate = "N";
    }

    // 2b. Photoset alt is not empty
    if( empty( $clean_post_data['photoset_alt'] ) )
    {
        $has_errors['photoset_alt'] = "Photo alt text is required.";
        $did_validate = "N";
    }
    else
    {
        // Strip a trailing period for the alt
        $clean_post_data['photoset_alt'] = rtrim( $clean_post_data['photoset_alt'], '.' );

        // Add a trailing period back for the caption
        $clean_photo_caption = $clean_post_data['photoset_alt'].'.';
    }

    // 2c. The photos folder exists and we can open it, a trailing slash is assumed
    if (!is_dir(PHOTOS_SRCSET_RELATIVE_PATH) || !is_readable(PHOTOS_SRCSET_RELATIVE_PATH)) {
        $has_errors['photoset_dir'] = "The <code>PHOTOS_SRCSET_RELATIVE_PATH</code> set in <code>env.php</code> location was not found or was not readable.";
        $did_validate = "N";
    } else {
        
        try {
            foreach (new DirectoryIterator(PHOTOS_SRCSET_RELATIVE_PATH) as $fileInfo) {
                // Get jpgs, pngs, gifs only
                if (
                    $fileInfo->isFile() &&
                    preg_match('/\.(jpe?g|gif|png)$/i', $fileInfo->getFilename())
                ) {
                    $filePath = $fileInfo->getPathname();
                    $size = @getimagesize($filePath);

                    if ($size !== false) {
                        $photos_list[] = [
                            'filename' => $fileInfo->getFilename(),
                            'width'    => $size[0],
                            'height'   => $size[1],
                        ];
                    }
                }
            }   
    
        } catch (UnexpectedValueException $e) {
            $has_errors['photoset_dir'] = "Failed to read <code>PHOTOS_SRCSET_RELATIVE_PATH</code>: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $did_validate = "N";
        }
    }

    // 2d. We were able to build the list of photos
    if( empty($photos_list) )
    {
        $has_errors['photoset_dir'] = "No photos were found in the <code>PHOTOS_SRCSET_RELATIVE_PATH</code> location set in <code>env.php</code>";
        $did_validate = "N";
    }

    // 3. Last check - were there any errors?

    if( !empty($has_errors) )
    {
        $did_validate = "N";
    }
    else
    {
        // Got here with no errors
        $did_validate = "Y"; // Success
    }


    // 4. Use the list of photos to create the html tags for the required img and srcset

    /**  
     * What do we want?
     * A standalone image tag for each of the allowed sizes, with `class="img"` added for legacy images
     * - linking to the location in `PHOTOS_PUBLIC_BASE_URL/$clean_post_data['photoset_folder']/`
     * - with width, height, and an alt tag made out of $clean_post_data['photoset_alt'] (and $clean_post_data['photoset_alt_prefix'], if present)
     * The src attribute for the 1024 and 720 and 320px sizes, linking to the location in `PHOTOS_PUBLIC_BASE_URL/$clean_post_data['photoset_folder']/`
     * to use in a srcset
     * 
     */

    $image_template = '<img src="%s" alt="%s" width="%d" height="%d"%s>';
    $image_alt = $clean_post_data['photoset_alt'];
    $image_alt_plus_prefix = !empty( $clean_post_data['photoset_alt_prefix'] ) ? $clean_post_data['photoset_alt_prefix'].IMG_ALT_PREFIX_SEPARATOR.$clean_post_data['photoset_alt'] : $clean_post_data['photoset_alt'];

    foreach( $photos_list as $image ) 
    {
        // 1024 image
        if( strpos( $image['filename'], '_1024x576' ) !== FALSE )
        {
            // The standalone image
            $img_srcset_tags_live['1024_img_tag'] = sprintf(
                $image_template,
                PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'],
                $image_alt_plus_prefix,
                $image['width'],
                $image['height'],
                '' // No class added
            );

            // The src attribute
            $img_srcset_tag_srcs['1024_src'] = PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'];
            
        }

        // 720 image
        if( strpos( $image['filename'], '_720x405' ) !== FALSE )
        {
            // The standalone image
            $img_srcset_tags_live['720_img_tag'] = sprintf(
                $image_template,
                PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'],
                $image_alt,
                $image['width'],
                $image['height'],
                '' // No class added
            );

            // The src attribute
            $img_srcset_tag_srcs['720_src'] = PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'];
        }

        // 608 image
        if( strpos( $image['filename'], '_608x344' ) !== FALSE )
        {
            // The standalone image
            $img_srcset_tags_live['608_img_tag'] = sprintf(
                $image_template,
                PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'],
                $image_alt_plus_prefix,
                $image['width'],
                $image['height'],
                ' class="img"' // Class added to legacy sizes
            );

        }

        // 320 image
        if( strpos( $image['filename'], '_320x215' ) !== FALSE )
        {
            // The standalone image
            $img_srcset_tags_live['320_img_tag'] = sprintf(
                $image_template,
                PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'],
                $image_alt,
                $image['width'],
                $image['height'],
                '' // No class added
            );

            // The src attribute
            $img_srcset_tag_srcs['320_src'] = PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'];
        }

        // 192 image
        if( strpos( $image['filename'], '_192x128' ) !== FALSE )
        {
            // The standalone image
            $img_srcset_tags_live['192_img_tag'] = sprintf(
                $image_template,
                PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'],
                $image_alt,
                $image['width'],
                $image['height'],
                ' class="img"' // Class added to legacy sizes
            );
        }

        // 112 image
        if( strpos( $image['filename'], '_112x112' ) !== FALSE )
        {
            // The standalone image
            $img_srcset_tags_live['112_img_tag'] = sprintf(
                $image_template,
                PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'],
                $image_alt,
                $image['width'],
                $image['height'],
                ' class="img"' // Class added to legacy sizes
            );
        }
    }


    // Can we make up any of the srcset images?
    // Featured image srcset
    // - needs 1024, 720, 320 images
    // - defaults to showing the largest image in src
    // - 1024x576 aspect ratio set
    // - sizes="100vw" so the browser picks the largest one that fits the screensize at page load
    $featured_image_srcset_template = '<img src="%s" width="1024" height="576" srcset="%s 1024w, %s 720w, %s 320w" sizes="100w" alt="%s">';
    if(
        !empty( $img_srcset_tag_srcs['1024_src'] )
        AND !empty( $img_srcset_tag_srcs['720_src'] )
        AND !empty( $img_srcset_tag_srcs['320_src'] )
    )
    {
        $img_srcset_tags_live['featured_img_srcset_tag'] = sprintf(
            $featured_image_srcset_template,
            $img_srcset_tag_srcs['1024_src'],
            $img_srcset_tag_srcs['1024_src'],
            $img_srcset_tag_srcs['720_src'],
            $img_srcset_tag_srcs['320_src'],
            $image_alt_plus_prefix
        );
    }

    // List image srcset
    // - needs 720, 320 images
    // - defaults to showing the 320 image in src (assuming thumbnail in list page view, large screen)
    // - 320x215 aspect ratio set
    // - sizes: 592px is where the list page view changes to a card with the image at the top, use the 720px image but shrink to fit 592px or lower
    $list_image_srcset_template = '<img src="%s" width="320" height="215" srcset="%s 720w, %s 320w" sizes="(min-width: 592px) 320px, 100vw" alt="%s">';
    if(
        !empty( $img_srcset_tag_srcs['720_src'] )
        AND !empty( $img_srcset_tag_srcs['320_src'] )
    )
    {
        $img_srcset_tags_live['list_img_srcset_tag'] = sprintf(
            $list_image_srcset_template,
            $img_srcset_tag_srcs['320_src'],
            $img_srcset_tag_srcs['720_src'],
            $img_srcset_tag_srcs['320_src'],
            $image_alt
        );
    }


    // Can we make the figure image?
    // Needs the 720 image tag and the src for the 1024 image
    // Produces:
    // - a figure tag and caption ...
    // - with the 720px image wrapped in a link to the 1024 image ...
    // - and the caption wrapped in a link to the 1024 image ...
    // - with a rel="lytebox" attribute on the links that is picked up by JavaScript for a lightbox effect on click.
    $figure_720_template = '<figure>'."\n\t<a href=".'"%s" rel="lytebox">'."\n\t\t%s\n\t</a>\n\t<figcaption>%s (<a href=".'"%s" rel="lytebox">'."Click for larger image</a>)</figcaption>\n</figure>";
    if(
        !empty( $img_srcset_tags_live['720_img_tag'] )
        AND !empty( $img_srcset_tag_srcs['1024_src'] )
    )
    {
        $img_srcset_tags_live['figure_img_tag'] = sprintf(
            $figure_720_template,
            $img_srcset_tag_srcs['1024_src'],
            $img_srcset_tags_live['720_img_tag'],
            $clean_photo_caption,
            $img_srcset_tag_srcs['1024_src']
        );
    }

}
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
                <li>Copy the generated image tags into the CMS fields</li>
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

<?php

// Any messages to print?
// Were there any errors?

// Fields failed validation
if( !empty ( $has_errors ) )
{
    echo '<div class="alert alert-danger mb-4" role="alert">Please check for missing information</div>';
}

// Couldn't read the PHOTOS_SRCSET_RELATIVE_PATH folder
if( !empty ($has_errors['photoset_dir']) ){
    echo '<div class="alert alert-danger mb-4" role="alert">'.$has_errors['photoset_dir'].'</div>';
}



// No errors and passed validation
else if( $did_validate === "Y" )
{
    echo '<div class="alert alert-success mb-3" role="alert">See below for the preview and HTML</div>';
}
?>
    </div>

    <div class="col-8">
        
        <form action="./index.php" method="post">

            <label for="photoset_folder" class="form-label">Eventual online folder (required)</label>
                <div class="input-group mb-3 has-validation">
                <span class="input-group-text" id="photoset_folder_tip"><?php echo PHOTOS_PUBLIC_BASE_URL; ?></span>
                <input type="text" class="form-control<?php
				if( !empty ($has_errors['photoset_folder']) ){
					echo " is-invalid";
				}
				?>" id="photoset_folder" name="photoset_folder" aria-describedby="photoset_folder_tip" required maxlength="255" value="<?php echo $clean_post_data['photoset_folder']; ?>">
                <?php
				if( !empty ($has_errors['photoset_folder']) ){
					echo '<div class="invalid-feedback">'.$has_errors['photoset_folder'].'</div>';
				}
				?>                
                <div id="photoset_folder_help" class="form-text">No leading/trailing slash. This is where the photos are located e.g. <samp>BadalingAncientGreatWall</samp> if the photos are in <samp><?php echo PHOTOS_PUBLIC_BASE_URL; ?>BadalingAncientGreatWall</samp>.</div>
            </div>

            <div class="mb-3">
                <label for="photoset_alt" class="form-label">Photo alt text (required)</label>
                <input type="text" class="form-control<?php
				if( !empty ($has_errors['photoset_alt']) ){
					echo " is-invalid";
				}
				?>" id="photoset_alt" name="photoset_alt" aria-describedby="photoset_alt_help" required maxlength="255" value="<?php echo $clean_post_data['photoset_alt']; ?>">
                <?php
				if( !empty ($has_errors['photoset_alt']) ){
					echo '<div class="invalid-feedback">'.$has_errors['photoset_alt'].'</div>';
				}
				?> 
                <div id="photoset_alt_help" class="form-text">e.g. Hikers on the ABC Great Wall.</div>
            </div>

            <div class="mb-3">
                <label for="photoset_alt_prefix" class="form-label">Photo alt prefix (optional)</label>
                <input type="text" class="form-control" id="photoset_alt_prefix" name="photoset_alt_prefix" aria-describedby="photoset_prefix_help" maxlength="255"  value="<?php echo $clean_post_data['photoset_alt_prefix']; ?>">
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
            foreach ( $img_srcset_tags_live as $tag ) {
                if( !empty($tag) AND $tag !== '' ) {
                    echo '<div class="mb-3">' . $tag . "</div>\n";
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

    <div class="col-8" id="formheader">
        <h2>Copy the HTML from here</h2>    
        <form id="copy-form">

            <?php

            // Is there a featured image srcset
            if( !empty( $img_srcset_tags_live['featured_img_srcset_tag'] != '' ) ) {
                ?>
                <div class="mb-3">
                    <label for="srcset_featured" class="form-label">Srcset for Featured Image</label>
                    <textarea onfocus="this.select()" onmouseup="event.preventDefault()" class="form-control form-control-sm" id="srcset_featured" name="srcset_featured"><?php echo htmlspecialchars($img_srcset_tags_live['featured_img_srcset_tag'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <?php
            }

            // Is there a list image srcset
            if( !empty( $img_srcset_tags_live['list_img_srcset_tag'] != '' ) ) {
                ?>
                <div class="mb-3">
                    <label for="srcset_list" class="form-label">Srcset for List Image</label>
                    <textarea onfocus="this.select()" onmouseup="event.preventDefault()" class="form-control form-control-sm" id="srcset_list" name="srcset_list"><?php echo htmlspecialchars($img_srcset_tags_live['list_img_srcset_tag'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <?php
            }

            // Is there a caption
            // $clean_photo_caption has already had htmlspecialchars applied
            if( !empty( $clean_photo_caption != '' ) ) {
                ?>
                <div class="mb-3">
                    <label for="srcset_caption" class="form-label">Caption for photos</label>
                    <input onfocus="this.select()" onmouseup="event.preventDefault()" type="text" class="form-control form-control-sm" id="srcset_caption" name="srcset_caption" value="<?php echo $clean_photo_caption;  ?>">
                </div>
                <?php
            }

            // Is there a 608px image
            if( !empty( $img_srcset_tags_live['608_img_tag'] != '' ) ) {
                ?>
                <div class="mb-3">
                    <label for="srcset_608" class="form-label">608px image</label>
                    <textarea onfocus="this.select()" onmouseup="event.preventDefault()" class="form-control form-control-sm" id="srcset_608" name="srcset_608"><?php echo htmlspecialchars($img_srcset_tags_live['608_img_tag'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <?php
            }

            // Is there a 192px image
            if( !empty( $img_srcset_tags_live['192_img_tag'] != '' ) ) {
                ?>
                <div class="mb-3">
                    <label for="srcset_192" class="form-label">192px image</label>
                    <textarea onfocus="this.select()" onmouseup="event.preventDefault()" class="form-control form-control-sm" id="srcset_192" name="srcset_192"><?php echo htmlspecialchars($img_srcset_tags_live['192_img_tag'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <?php
            }

            // Is there a 112px image
            if( !empty( $img_srcset_tags_live['112_img_tag'] != '' ) ) {
                ?>
                <div class="mb-3">
                    <label for="srcset_112" class="form-label">112px image</label>
                    <textarea onfocus="this.select()" onmouseup="event.preventDefault()" class="form-control form-control-sm" id="srcset_112" name="srcset_112"><?php echo htmlspecialchars($img_srcset_tags_live['112_img_tag'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <?php
            }

            // Is there a 1024px image
            if( !empty( $img_srcset_tags_live['1024_img_tag'] != '' ) ) {
                ?>
                <div class="mb-3">
                    <label for="srcset_1024" class="form-label">1024px image</label>
                    <textarea onfocus="this.select()" onmouseup="event.preventDefault()" class="form-control form-control-sm" id="srcset_1024" name="srcset_1024"><?php echo htmlspecialchars($img_srcset_tags_live['1024_img_tag'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <?php
            }

            // Is there a 720px image
            if( !empty( $img_srcset_tags_live['720_img_tag'] != '' ) ) {
                ?>
                <div class="mb-3">
                    <label for="srcset_720" class="form-label">720px image</label>
                    <textarea onfocus="this.select()" onmouseup="event.preventDefault()" class="form-control form-control-sm" id="srcset_720" name="srcset_720"><?php echo htmlspecialchars($img_srcset_tags_live['720_img_tag'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <?php
            }

            // Is there a 320px image
            if( !empty( $img_srcset_tags_live['320_img_tag'] != '' ) ) {
                ?>
                <div class="mb-3">
                    <label for="srcset_320" class="form-label">320px image</label>
                    <textarea onfocus="this.select()" onmouseup="event.preventDefault()" class="form-control form-control-sm" id="srcset_320" name="srcset_320"><?php echo htmlspecialchars($img_srcset_tags_live['320_img_tag'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <?php
            }

            // Is there a 720 figure image
            if( !empty( $img_srcset_tags_live['figure_img_tag'] != '' ) ) {
            ?>
            <div class="mb-3">
                <label for="srcset_figure_img" class="form-label">The 720 figure</label>
                <textarea onfocus="this.select()" onmouseup="event.preventDefault()" class="form-control form-control-sm" id="srcset_figure_img" name="srcset_figure_img"><?php echo htmlspecialchars($img_srcset_tags_live['figure_img_tag'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <?php
            }

            ?>

        </form>
    </div>

    <div class="back-to-top-wrapper">
        <div class="back-to-top-link-container border border-secondary rounded p-2 bg-light mb-2">
            <a href="#formheader" class="back-to-top-link btn btn-link text-nowrap" aria-label="Scroll to Form">Scroll to form</a><br>
            <a href="#top" class="back-to-top-link btn btn-link text-nowrap" aria-label="Scroll to Top">Back to top</a><br>
        </div>
    </div>

</main>

<script>
// Prevent form submission copy-form on Enter keypress or button click
document.getElementById('copy-form').addEventListener('submit', e => {
  e.preventDefault();
});
</script>
</body>
</html>