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
 * 4. Use the list of photos to create the html tags for the required img and srcset and collection of figure tags
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


    // 2. Validate the data (e.g. not empty, the folder location is readable), set in $has_errors or set $did_validate = "Y"

    // 2a. Photoset title is not empty
    if( empty( $clean_post_data['photoset_title'] ) )
    {
        $has_errors['photoset_title'] = "Photo set title is required.";
        $did_validate = "N";
    }

    // 2a. Photoset intro is not empty
    if( empty( $clean_post_data['photoset_intro'] ) )
    {
        $has_errors['photoset_intro'] = "Photo set brief intro is required.";
        $did_validate = "N";
    }

    // 2c. Photoset folder variable is not empty
    if( empty( $clean_post_data['photoset_folder'] ) )
    {
        $has_errors['photoset_folder'] = "Photo set folder location is required.";
        $did_validate = "N";
    }

    // 2d. The photos folder exists and we can open it, a trailing slash on PHOTOS_PRINT_LOCAL_PATH is assumed
    // The location is PHOTOS_PRINT_LOCAL_PATH/$clean_post_data['photoset_folder']    
    if (!is_dir(PHOTOS_PRINT_LOCAL_PATH.$clean_post_data['photoset_folder']) || !is_readable(PHOTOS_PRINT_LOCAL_PATH.$clean_post_data['photoset_folder'])) {
        $has_errors['photoset_dir'] = "The <code>".$clean_post_data['photoset_folder']."</code> folder was not found in the <code>PHOTOS_PRINT_LOCAL_PATH</code> location set in <code>env.php</code>, or it was not readable.";
        $did_validate = "N";
    }
    else 
    {
        
        try {
            foreach (new DirectoryIterator(PHOTOS_PRINT_LOCAL_PATH.$clean_post_data['photoset_folder']) as $fileInfo) {
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
            $has_errors['photoset_dir'] = "Failed to read <code>PHOTOS_PRINT_LOCAL_PATH" . $clean_post_data['photoset_folder'] . "</code>: " . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $did_validate = "N";
        }
    }

    // 2e. We were able to build the list of photos
    if( empty($photos_list) )
    {
        $has_errors['photoset_dir'] = "No photos were found in the <code>".$clean_post_data['photoset_folder']."</code> folder in the <code>PHOTOS_PRINT_LOCAL_PATH</code> location set in <code>env.php</code>.";
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


    // 4. Use the list of photos to create the html tags for the required img and srcset and collection of figure tags

    /**  
     * What do we want?
     * A standalone image tag for each of the allowed sizes, with `class="img"` added for legacy images
     * - linking to the location in `PHOTOS_PUBLIC_BASE_URL/$clean_post_data['photoset_folder']/`
     * - with width, height, and an alt tag made out of $clean_post_data['photoset_alt'] (and $clean_post_data['photoset_alt_prefix'], if present)
     * The src attribute for the 1024 and 720 and 320px sizes, linking to the location in `PHOTOS_PUBLIC_BASE_URL/$clean_post_data['photoset_folder']/`
     * to use in a srcset
     * For all the images without the _1024x576 etc suffixes, create a figure tag with a plain image inside and add to $photo_set_figures array;
     * 
     */

    $image_template = '<img src="%s" alt="%s" width="%d" height="%d"%s>';
    $figure_template = "<figure>\n\t".$image_template."\n</figure>\n\n";
    $image_alt = $clean_post_data['photoset_title'];
    $photo_set_figures_count = 1;

    foreach( $photos_list as $image ) 
    {
        // 1024 image
        if( strpos( $image['filename'], '_1024x576' ) !== FALSE )
        {
            // The standalone image
            $img_srcset_tags_live['1024_img_tag'] = sprintf(
                $image_template,
                PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'],
                $image_alt,
                $image['width'],
                $image['height'],
                '' // No class added
            );

            // The src attribute
            $img_srcset_tag_srcs['1024_src'] = PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'];

            continue; // Skip to the next $image in the array, don't process other code in this iteration of the loop
            
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

            continue; // Skip to the next $image in the array, don't process other code in this iteration of the loop
        }

        // 608 image
        if( strpos( $image['filename'], '_608x344' ) !== FALSE )
        {
            // The standalone image
            $img_srcset_tags_live['608_img_tag'] = sprintf(
                $image_template,
                PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'],
                $image_alt,
                $image['width'],
                $image['height'],
                ' class="img"' // Class added to legacy sizes
            );

            continue; // Skip to the next $image in the array, don't process other code in this iteration of the loop
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

            continue; // Skip to the next $image in the array, don't process other code in this iteration of the loop
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

            continue; // Skip to the next $image in the array, don't process other code in this iteration of the loop
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

            continue; // Skip to the next $image in the array, don't process other code in this iteration of the loop
        }

        // It's an image without a suffix, add it to the collection of figures
        // only gets here if the image doesn't have one of the above suffixes
        $photo_set_figures[] = sprintf(
            $figure_template,
            PHOTOS_PUBLIC_BASE_URL.$clean_post_data['photoset_folder'].'/'.$image['filename'],
                $image_alt.' - photo #'.$photo_set_figures_count,
                $image['width'],
                $image['height'],
                '' // No extra class for these
        );
        $photo_set_figures_count++; // Increment the count of figures
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
            $image_alt
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

        <?php

// Any messages to print?
// Were there any errors?

// Fields failed validation
if( !empty ( $has_errors ) )
{
    echo '<div class="alert alert-danger mb-4" role="alert">Please check for missing information</div>';
}

// Couldn't read the PHOTOS_SRCSET_RELATIVE_PATH/$clean_post_data['photoset_folder'] folder
if( !empty ($has_errors['photoset_dir']) )
{
    echo '<div class="alert alert-danger mb-4" role="alert">'.$has_errors['photoset_dir'].'</div>';
}



// No errors and passed validation
else if( $did_validate === "Y" )
{
    echo '<div class="alert alert-success mb-3" role="alert">See below for the preview and HTML</div>';
}
?>

    </div>

    <div class="col-8" id="formheader">
        
        <form action="./index.php" method="post">

            <div class="mb-3">
                <label for="photoset_title" class="form-label">Title of photo set</label>
                <input type="text" class="form-control<?php
				if( !empty ($has_errors['photoset_title']) ){
					echo " is-invalid";
				}
				?>" id="photoset_title" name="photoset_title" aria-describedby="photoset_title_help" required maxlength="255" value="<?php echo $clean_post_data['photoset_title']; ?>">
                <?php
				if( !empty ($has_errors['photoset_title']) ){
					echo '<div class="invalid-feedback">'.$has_errors['photoset_title'].'</div>';
				}
				?>
                <div id="photoset_title_help" class="form-text">e.g. Switchback Great Wall Camping, 2017/10/06</div>
            </div>

            <div class="mb-3">
                <label for="photoset_intro" class="form-label">Brief intro of photo set</label>
                <textarea class="form-control<?php
				if( !empty ($has_errors['photoset_intro']) ){
					echo " is-invalid";
				}
				?>" id="photoset_intro" name="photoset_intro" aria-describedby="photoset_intro_help" required><?php echo $clean_post_data['photoset_intro']; ?></textarea>
                <?php
				if( !empty ($has_errors['photoset_intro']) ){
					echo '<div class="invalid-feedback">'.$has_errors['photoset_intro'].'</div>';
				}
				?>
                <div id="photoset_intro_help" class="form-text">A description of what’s in the set of photos e.g. 18 photos from a hike &hellip;</div>
            </div>

            <label for="photoset_folder" class="form-label">FTP folder name</label>
                <div class="input-group mb-3 has-validation">
                <span class="input-group-text" id="photoset_folder_tip"><?php echo PHOTOS_PUBLIC_BASE_URL; ?></span>
                <input type="text" class="form-control<?php
				if( !empty ($has_errors['photoset_folder']) ){
					echo " is-invalid";
				}
				?>" id="photoset_folder" name="photoset_folder" aria-describedby="photoset_folder_tip" required pattern="^[\d\w\-]*" title="Only letters or numbers or dashes" value="<?php echo $clean_post_data['photoset_folder']; ?>">
                <?php
				if( !empty ($has_errors['photoset_folder']) ){
					echo '<div class="invalid-feedback">'.$has_errors['photoset_folder'].'</div>';
				}
				?>
                <div id="photoset_folder_help" class="form-text">The name of the folder where you uploaded the photos e.g. <samp>20171006-SwitchbackGreatWallCamping</samp></div>
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
            // First the srcset and standalone
            $had_srcset_tags = "N"; // Write a message if this is still N at the end of the loop
            foreach ( $img_srcset_tags_live as $tag ) {
                if( !empty($tag) AND $tag !== '' ) {
                    echo '<div class="mb-3">' . $tag . "</div>\n";
                    $had_srcset_tags = "Y";
                }
            }
            
            // Write a message if there were no HTML tags to preview
            if( $had_srcset_tags === 'N') {
                ?>
                <div class="alert alert-warning mb-3" role="alert">
                    No srcset tags to print. (Yet?)
                </div>
                <?php
            }

            // Second the group of figures
            if(!empty( $photo_set_figures ))
            {
                echo '<div class="mb-3">'.implode("", $photo_set_figures).'</div>';
            }
            else
            {
                ?><div class="alert alert-warning mb-3" role="alert">
                    No figure tags to print. (Yet?)
                </div>
                <?php
            }

        ?>



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