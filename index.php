<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Deliberately NOT `require_once __DIR__ . '/paths.php'` here, despite that
 * being this file's originally planned shape (Task 9 brief Step 2) - doing
 * so causes a real, confirmed-by-curl bug: every route 302-redirects to
 * setup.php?section=step0 because lib/preflight.lib.php's check_setup()
 * calls silently check table '' instead of 'baseline_mods' etc.
 *
 * Root cause: PHP's require executes the required file's top-level code in
 * the scope of the *line* that triggered it, not a universal global scope.
 * Every Bcoem\Legacy\* handler (LegacyPageHandler, LegacyProcessHandler,
 * LegacyFileHandler, LegacyBootstrap) requires its target legacy file (e.g.
 * legacy/index.php, qr.php) from WITHIN A METHOD - a function scope. Each
 * target file's own first line is require_once('paths.php'), which sets
 * $prefix/$database/$connection/etc as plain variables in whatever scope
 * that line runs in - normally the handler method's local scope, which is
 * exactly where the target file's *own* subsequent code (needing those
 * same variables) also runs, so it all lines up correctly. But if paths.php
 * had ALREADY been require_once'd once before - e.g. eagerly, right here,
 * at this file's true top-level/global scope - PHP's require_once sees it's
 * already loaded and no-ops on every later call, silently skipping
 * execution rather than re-running it in the new (method-local) scope. The
 * target file then proceeds using $prefix/$database as if they'd just been
 * set, but they're actually stuck as unrelated globals from this file's
 * scope, invisible inside the handler method without an explicit `global`
 * declaration neither this file nor any Legacy* class makes.
 *
 * Only the ROOT constant is needed before Slim dispatches to a matched
 * route handler - every Legacy* handler builds its target file's full path
 * as ROOT . $file before requiring it, so ROOT must already be defined by
 * then. Nothing else paths.php defines (LIB, CONFIG, the DB connection,
 * $prefix, the session, ...) is needed pre-dispatch: buildApp() and its
 * middleware pipeline (session/auth/authorization) don't reference any of
 * it, confirmed by grep. Defining ROOT to match paths.php's own formula
 * exactly (mirrors paths.php:13) lets paths.php's own require_once, when
 * a dispatched handler triggers it for the first and only time, run in the
 * correct scope and set everything else up exactly as before Task 9.
 */
if (!defined('ROOT')) {
    define('ROOT', __DIR__ . DIRECTORY_SEPARATOR);
}

require_once __DIR__ . '/src/Kernel/app.php';
require_once __DIR__ . '/src/Kernel/bootstrap_errors.php';

// Build the container once so the legacy warning/notice capture can be wired
// onto its Monolog `legacy` channel before any request is dispatched, then
// hand the same instance to buildApp(). container.php also makes mysqli's
// exception-throwing mode explicit (see its header comment).
$container = require __DIR__ . '/src/Kernel/container.php';
bcoem_register_error_logging($container->get('logger.legacy'));

        if ($section != "maintenance") {
            $redirect = $base_url."index.php?section=maintenance";
            $redirect = prep_redirect_link($redirect);
            $redirect_go_to = sprintf("Location: %s", $redirect);
            header($redirect_go_to);
            exit();
        }
        
    }

}

else {

    if ($section == "maintenance") {
        $redirect = $base_url;
        $redirect = prep_redirect_link($redirect);
        $redirect_go_to = sprintf("Location: %s", $redirect);
        header($redirect_go_to);
        exit();
    }

}

/**
 * ------------------------------
 * Admin Only Functions
 * ------------------------------
 */

if ($section == "admin") {

    // Redirect if non-admins try to access admin functions
    if (!$logged_in) {

        $redirect = $base_url."index.php?msg=0";
        $redirect = prep_redirect_link($redirect);
        $redirect_go_to = sprintf("Location: %s", $redirect);
        header($redirect_go_to);
        exit();

    }

    if (($logged_in) && ($_SESSION['userLevel'] > 1)) {
        
        $redirect = $base_url."index.php?msg=4";
        $redirect = prep_redirect_link($redirect);
        $redirect_go_to = sprintf("Location: %s", $redirect);
        header($redirect_go_to);
        exit();

    }

    require_once (LIB.'admin.lib.php');
    require_once (DB.'admin_common.db.php');
    require_once (DB.'judging_locations.db.php');
    require_once (DB.'stewarding.db.php');
    require_once (DB.'dropoff.db.php');
    require_once (DB.'contacts.db.php');

    if ($_SESSION['userLevel'] == 0) {
        // Cached for the session so this scan runs at most once per login, not on every
        // admin/default.admin.php hit. Cleared by cleanup_double_encoding.php after a live run.
        if (!isset($_SESSION['double_encoding_checked'])) {
            $_SESSION['double_encoding_detected'] = has_double_encoded_data($db_conn);
            $_SESSION['double_encoding_checked'] = true;
        }
        $double_encoding_detected = $_SESSION['double_encoding_detected'];
    }
}

/**
 * ------------------------------
 * Other Top-Level Constants
 * ------------------------------
 */

require_once (INCLUDES.'constants_post_lang.inc.php');

// Hosted installations only
if (HOSTED) require_once (LIB.'hosted.lib.php');

// Pay modal is defined here to make sure it's top-level
// Otherwise, the modal does not render correctly.
$pay_modal = "";
$pay_modal .= "\n<!-- PayPal Confirmation Modal -->\n";
$pay_modal .= "<div class=\"modal modal-lg fade\" id=\"confirm-submit\" tabindex=\"-1\" role=\"dialog\" aria-hidden=\"true\">\n";
$pay_modal .= "\t<div class=\"modal-dialog\">\n";
$pay_modal .= "\t\t<div class=\"modal-content\">\n";
$pay_modal .= "\t\t\t<div class=\"modal-header\">\n";
if ((isset($_SESSION['prefsPaypalIPN'])) && ($_SESSION['prefsPaypalIPN'] == 1)) $pay_modal .= sprintf("\t\t\t\t<h4 class=\"modal-title\">%s</h4>\n",$pay_text_031);
else $pay_modal .= sprintf("\t\t\t\t<h4 class=\"modal-title\">%s</h4>\n",$pay_text_022);
$pay_modal .= "\t\t\t\t<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"Close\"></button>\n";
$pay_modal .= "\t\t\t</div>\n";
if ((isset($_SESSION['prefsPaypalIPN'])) && ($_SESSION['prefsPaypalIPN'] == 1)) $pay_modal .= sprintf("\t\t\t<div class=\"modal-body\">\n\t\t\t\t<p>%s</p>\n",$pay_text_030);
else $pay_modal .= sprintf("\t\t\t<div class=\"modal-body\">\n\t\t\t\t<p>%s</p>\n",$pay_text_021);
$pay_modal .= "\t\t\t</div>\n";
$pay_modal .= "\t\t\t<div class=\"modal-footer\">\n";
$pay_modal .= sprintf("\t\t\t\t<button type=\"button\" class=\"btn btn-danger\" data-bs-dismiss=\"modal\">%s</button>\n",$label_cancel);
$pay_modal .= sprintf("\t\t\t\t<a href=\"#\" id=\"submit\" class=\"btn btn-primary\">%s</a>\n",$label_understand);
$pay_modal .= "\t\t\t</div>\n";
$pay_modal .= "\t\t</div>\n";
$pay_modal .= "\t</div>\n";
$pay_modal .= "</div>\n";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    
    <meta charset="utf-8">
    <meta http-equiv="Content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $_SESSION['contestName']; ?> - Brew Competition Online Entry &amp; Management</title>

<?php   
if ($section == "admin") include (INCLUDES.'load_cdn_libraries_admin.inc.php');
else include (INCLUDES.'load_cdn_libraries_public.inc.php');
?>

    <script src="https://cdn.jsdelivr.net/npm/js-cookie@3.0.5/dist/js.cookie.min.js"></script>

    <!-- Load BCOE&M Custom CSS - Contains Bootstrap overrides and custom classes common to all BCOE&M themes -->
    <link rel="stylesheet" type="text/css" href="<?php echo $css_common_url; ?>" />
    <link rel="stylesheet" type="text/css" href="<?php echo $theme; ?>" />

    <script type="text/javascript">
        var username_url = "<?php echo $ajax_url; ?>username.ajax.php";
        var email_url="<?php echo $ajax_url; ?>valid_email.ajax.php";
        var user_agent_msg = "<?php echo $alert_text_086; ?>";
        var setup = 0;
    </script>
    
    <!-- Open Graph Implementation -->
<?php if (!empty($_SESSION['contestName'])) { ?>
    <meta property="og:title" content="<?php echo $_SESSION['contestName']?>" />
<?php } ?>
<?php if (!empty($_SESSION['contestLogo'])) { ?>
    <meta property="og:image" content="<?php echo $base_url."user_images/".$_SESSION['contestLogo']; ?>" />
<?php } ?>
    <meta property="og:url" content="<?php echo "http".((!empty($_SERVER['HTTPS'])) ? "s://" : "://").$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']; ?>" />

</head>

<?php
if (($section == "admin") || ($admin != "default")) require ('index.legacy.php');
else require ('index.pub.php');
if (($section == "list") || ($section == "pay")) echo $pay_modal;
mysqli_close($connection); 
?>
</html>