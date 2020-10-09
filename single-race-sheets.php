<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= get_the_title() ?></title>
<link rel="stylesheet" type="text/css" href="<?= plugin_dir_url(__FILE__) ?>assets/race-results.css" />
</head>
<body>
<div id="page">
<div id="results-container">
<div id="results">
<?php
include_once __DIR__ . '/sheets.php';
while ( have_posts() ) : the_post();
printf('<h1>%s</h1>', get_the_title());
?>
<div class="results-data">
<?php
echo get_races_list(get_post_meta(get_the_ID(), 'spreadsheet_id', true), isset($_GET['show']) ? explode(',', $_GET['show']) : ['results']);
endwhile;
?>
</div></div></div></div>
<script type="text/javascript" src="<?= plugin_dir_url(__FILE__) ?>assets/jquery-3.2.1.min.js"></script>
<script type="text/javascript" src="<?= plugin_dir_url(__FILE__) ?>assets/race-results.js"></script>
<?php
if (isset($_GET['scroll'])) {
$scrollSpeed = isset($_GET['speed']) ? intval($_GET['speed']) : 80;
?>
<script type="text/javascript">
var scrollSpeed = <?= $scrollSpeed ?>; // Scroll speed in px/s
startAnimation();
</script>
<?php
}
?>
<?php if (isset($_GET['reload'])) {
$reloadCheckPeriod = isset($_GET['check']) ? intval($_GET['check']) : 120;
?>
<script type="text/javascript">
var wpPostId = '<?= get_the_ID() ?>', reloadCheckPeriod = <?= $reloadCheckPeriod ?>; // Period between checks in s
checkLastModified();
setInterval(checkLastModified, reloadCheckPeriod*1000);
</script>
<?php } ?>
</body>
</html>
