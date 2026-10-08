<?php
// Rebuild the static brand preview. No listing or private data is used.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$image = imagecreatetruecolor(1200, 630);
$paper = imagecolorallocate($image, 246, 246, 238);
$green = imagecolorallocate($image, 25, 61, 50);
$white = imagecolorallocate($image, 255, 255, 255);
imagefill($image, 0, 0, $paper);
imagefilledrectangle($image, 0, 0, 1200, 25, $green);
imagefilledellipse($image, 200, 245, 200, 200, $green);
$bold = $root . '/assets/fonts/AtkinsonHyperlegible-Bold.ttf';
$regular = $root . '/assets/fonts/AtkinsonHyperlegible-Regular.ttf';
imagettftext($image, 100, 0, 164, 283, $white, $bold, 'P');
imagettftext($image, 100, 0, 350, 285, $green, $bold, 'Perro.');
imagettftext($image, 42, 0, 100, 430, $green, $regular, 'Adopción responsable en Paraguay');
imagettftext($image, 30, 0, 100, 515, $green, $regular, 'perro.com.py  ·  Avisos gratuitos y revisados');
imagejpeg($image, $root . '/assets/images/default-preview.jpg', 86);
imagedestroy($image);
