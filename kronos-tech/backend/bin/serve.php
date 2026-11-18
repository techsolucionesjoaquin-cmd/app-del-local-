<?php
// Development-only same-origin router. In production, route /api/v1/* to PHP-FPM.
$uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (is_string($uri)&&str_starts_with($uri,'/api/v1/')){require dirname(__DIR__).'/public/index.php';return true;}
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'");
$doc=realpath(dirname(__DIR__,2).'/frontend');$p=realpath($doc.($uri==='/'?'/index.html':$uri));
if(is_string($p)&&str_starts_with($p,$doc.'/')&&is_file($p)&&preg_match('/\.(html|js|css|woff2|svg)$/D',$p))return false;
http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'No se encontró esta ruta.';
