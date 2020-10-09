<?php
include_once __DIR__ . '/drive.php';
while ( have_posts() ) : the_post();
echo get_drive_html(get_post_meta(get_the_ID(), 'resource_id', true), $remove_styles=false);
endwhile;
?>
