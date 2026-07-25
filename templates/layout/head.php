<?php
/**
 * Layout chrome: <head>. Available variables (set by LayoutRenderer::wrap()):
 * - $title: string
 * - $cssCommonUrl: string
 * - $themeUrl: string
 * - $isPublic: bool
 * - $isLanding: bool, only for the typed landing page
 */
?>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> - Brew Competition Online Entry &amp; Management</title>
    <?php if ($isPublic): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/gh/livecanvas-team/ninjabootstrap@fb1907ca0aa3f96f14518c890190833091479769/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-afmQT9IhigxzVFs6SNsbkppczaXyBIBXRyMHBsFKNkqVrstfKlHpPPUAX1/nJf+q" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" integrity="sha384-Gu3KVV2H9d+yA4QDpVB7VcOyhJlAVrcXd0thEjr4KznfaFPLe0xQJyonVxONa4ZC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha384-nRgPTkuX86pH8yjPJUAFuASXQSSl2/bBUiNV47vSYpKFxHJhbcrGnmlYpYJMeD7a" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/v4-shims.min.css" integrity="sha384-npPMK6zwqNmU3qyCCxEcWJkLBNYxEFM1nGgSoAWuCCXqVVz0cvwKEMfyTNkOxM2N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" integrity="sha384-piG3EtH1fBnPi68q4spy+Qgpb0dHK1D1dwk0GaHwFkvmUxYi526bBlk3xJcjEBsD" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js" integrity="sha384-cnROoUgVILyibe3J0zhzWoJ9p2WmdnK7j/BOTSWqVDbC1pVw2d+i6Q/1ESKJKCYf" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/zxcvbn/4.4.2/zxcvbn.js" integrity="sha384-jhGcGHNZytnBnH1wbEM3KxJYyRDy9Q0QLKjE65xk+aMqXFCdvFuYIjzMWAAWBBtR" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pwstrength-bootstrap/3.1.3/pwstrength-bootstrap.min.js" integrity="sha384-jLFJ0kDo8prvK0+rtsbrBYTnWc3wcfk6JiUSE1h00h+C6c3iY2erhSusYO/HAarY" crossorigin="anonymous"></script>
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
