<?php
declare(strict_types=1);
use Kronos\Repositories\Db;
use Kronos\Middleware\Auth;
use Kronos\Controllers\Router;
use Kronos\Support\Problem;
require dirname(__DIR__).'/bootstrap.php';
ini_set('display_errors','0');
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
$requestId=bin2hex(random_bytes(8));header('X-Request-Id: '.$requestId);
try{
    $url=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);if(!is_string($url)||!str_starts_with($url,'/api/v1/'))throw new Problem(404,'Ruta no disponible.','not_found');
    $db=new Db();$auth=new Auth($db);$auth->start();$user=$auth->me();$r=new Router($db,$user,$auth);
    require dirname(__DIR__).'/routes/api.php';$r->dispatch($_SERVER['REQUEST_METHOD']??'GET',substr($url,7));
}catch(Problem $e){if($e->status===429)header('Retry-After: 900');Router::json(null,$e->status,$e->getMessage(),[['field'=>$e->field,'code'=>$e->reason,'detail'=>$e->getMessage()]]);}
catch(PDOException $e){$code=$e->errorInfo[1]??0;if($code===1062)Router::json(null,409,'El dato ya existe o la operación ya fue registrada.',[['code'=>'duplicate']]);if(in_array($code,[1451,1452,3819],true))Router::json(null,422,'La relación o los datos no cumplen las reglas del sistema.',[['code'=>'constraint']]);error_log("[kronos $requestId] SQLSTATE ".$e->getCode().' code '.$code.' '.$e->getFile().':'.$e->getLine());Router::json(null,503,'No se pudo completar la operación. Consultá con administración.',[['code'=>'database']]);}
catch(Throwable $e){error_log("[kronos $requestId] ".get_class($e).' '.$e->getMessage().' en '.$e->getFile().':'.$e->getLine());Router::json(null,500,'Ocurrió un error inesperado.',[['code'=>'internal']]);}
