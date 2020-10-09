var lastModified = null;

function startAnimation() {
  var resultsHeight = $('#results').height(), screenHeight = window.innerHeight;
  if (resultsHeight > window.innerHeight) {
    // Set results to be just off the page and make them visible
    $('#results').css({
      paddingTop: '' + screenHeight + 'px',
      animationDuration: '' + ((resultsHeight + screenHeight) / scrollSpeed) + 's'
    });
    $('#results-data').css({
      visibility: 'visible'
    });
    // Start scrolling
    $('#page').addClass('scrolling');
    // Hard-code the container height to avoid messing up the percentage-based position on the child
    $('#results-container').css('height', '' + (resultsHeight + screenHeight) + 'px');
  } else {
    $('#results-data').css({
      visibility: 'visible'
    });
  }
  $('#results').addClass('scrolling-results');
}

function stopAnimation() {
  $('#results').removeClass('scrolling-results');
}

function checkLastModified() {
  $.post(
    '/wp-admin/admin-ajax.php',
    {
      'action': 'race_sheets_last_modified',
      'post_id':   wpPostId
    },
    function(response) {
      if (lastModified === null) {
        lastModified = response.modifiedTime;
      } else if (lastModified !== response.modifiedTime) {
        reloadPage();
      }
    },
    'json'
  );
}

function reloadPage() {
  $('#results').fadeOut(900, function() {
    window.location.reload();
  });
}
