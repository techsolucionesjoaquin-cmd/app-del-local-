<?php
namespace Kronos\Controllers;
use Kronos\Repositories\Db;
use Kronos\Middleware\Auth;
use Kronos\Support\{C,Problem};
final class Router {
    private array $routes=[];
    public function __construct(private Db $d,private ?array $user,private Auth $auth){}
    public function on(string $method,string $path,?string $permission,callable $handler,int $status=200,bool $once=false):void{$p=preg_replace('/\{[a-z_]+\}/','([1-9][0-9]{0,17})',$path);$this->routes[]=[$method,"~^$p$~D",$permission,$handler,$status,$once];}
    public function dispatch(string $method,string $path):never{
        $other=false;
        foreach($this->routes as [$m,$re,$permission,$h,$status,$once]){
            if(!preg_match($re,$path,$matches))continue;if($m!==$method){$other=true;continue;}$id=array_map('intval',array_slice($matches,1));
            if($permission!==null){C::check($this->user!==null,'Iniciá sesión para continuar.',401,'unauthenticated');if($permission!=='@')Auth::need($this->user,$permission);}
            if(!in_array($method,['GET','HEAD'],true))$this->auth->csrf();
            $sessionRoute=in_array($path,['/auth/login','/auth/logout','/auth/demo'],true);$csrf=$_SESSION['csrf'];
            if(!$sessionRoute){if($this->user!==null)$_SESSION['t']=time();session_write_close();}
            $body=[];
            if(!in_array($method,['GET','HEAD'],true) && str_starts_with($_SERVER['CONTENT_TYPE']??'','application/json')){
                C::check((int)($_SERVER['CONTENT_LENGTH']??0)<=200000,'Solicitud demasiado grande.',413);$raw=file_get_contents('php://input');
                try{$body=json_decode($raw?:'{}',true,48,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new Problem(400,'El formato JSON no es válido.','malformed_json');}
                C::check(is_array($body)&&(!$body||!array_is_list($body)),'Se esperaba un objeto JSON.',400);
            }elseif(!in_array($method,['GET','HEAD'],true)&&$_FILES===[]&&(int)($_SERVER['CONTENT_LENGTH']??0)>0)throw new Problem(415,'Formato de solicitud no admitido.','media_type');
            $fn=fn()=>$h($body,$id,$_GET,$csrf);
            $result=$once?$this->d->once((int)$this->user['id'],$method.':'.$path,$_SERVER['HTTP_IDEMPOTENCY_KEY']??'',$body,$fn):$fn();
            self::json($result,$status);
        }
        throw new Problem($other?405:404,$other?'Método no permitido.':'No se encontró la ruta.',$other?'method':'not_found');
    }
    public static function json(mixed $data,int $status=200,?string $msg=null,array $errors=[],?array $meta=null):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo C::json(['data'=>$data,'message'=>$msg,'errors'=>$errors,'meta'=>$meta]);exit;}
    public static function csv(string $name,array $header,array $rows):never{
        header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$name.'"');$f=fopen('php://output','w');fwrite($f,"\xEF\xBB\xBF");fputcsv($f,$header,',','"','');foreach($rows as $r){$v=array_map(fn($x)=>is_string($x)&&preg_match('/^[\s\x00-\x1F]*[=+\-@]/',$x)?"'".$x:$x,$r);fputcsv($f,$v,',','"','');}fclose($f);exit;
    }
}
