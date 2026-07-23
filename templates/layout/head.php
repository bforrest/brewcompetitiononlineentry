<?php
/**
 * Layout chrome: <head>. Available variables (set by LayoutRenderer::wrap()):
 * - $title: string
 * - $cssCommonUrl: string
 * - $themeUrl: string
 * - $isPublic: bool
 */
?>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - Brew Competition Online Entry &amp; Management</title>
    <?php if ($isPublic): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/gh/livecanvas-team/ninjabootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/v4-shims.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pwstrength-bootstrap/3.1.3/pwstrength-bootstrap.min.js"></script>
    <link rel="stylesheet" type="text/css" href="/css/common-3.min.css">
    <link rel="stylesheet" type="text/css" href="/css/default-3.min.css">
    <?php else: ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?= e($cssCommonUrl) ?>" />
    <link rel="stylesheet" type="text/css" href="<?= e($themeUrl) ?>" />
    <?php endif; ?>
    <link rel="stylesheet" type="text/css" href="/css/registration-public.css" />
</head>
