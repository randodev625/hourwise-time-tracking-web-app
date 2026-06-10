<?php
$statusCode = (int)current_route_option('status_code', 500);
http_response_code($statusCode);

$page_title = current_route_title();
$page_message = page_message();

if ($page_title === '') {
    $page_title = 'Server Error';
}

if ($page_message === '') {
    $page_message = 'Something went wrong, but no account details or server information were exposed.';
}

render_layout_header('error');
?>
<div class="text-center">
    <a href="/" class="btn btn-primary">Return to HourWise</a>
</div>
<?php render_layout_footer('error'); ?>
