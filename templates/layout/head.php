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
    <link rel="stylesheet" type="text/css" href="<?= e($cssCommonUrl) ?>" />
    <link rel="stylesheet" type="text/css" href="<?= e($themeUrl) ?>" />
</head>
