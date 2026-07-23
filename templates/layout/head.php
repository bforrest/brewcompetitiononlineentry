<?php
/**
 * Layout chrome: <head>. Available variables (set by LayoutRenderer::wrap()):
 * - $title: string
 * - $cssCommonUrl: string
 * - $themeUrl: string
 */
?>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - Brew Competition Online Entry &amp; Management</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" type="text/css" href="<?= e($cssCommonUrl) ?>" />
    <link rel="stylesheet" type="text/css" href="<?= e($themeUrl) ?>" />
    <link rel="stylesheet" type="text/css" href="/css/registration-public.css" />
</head>
