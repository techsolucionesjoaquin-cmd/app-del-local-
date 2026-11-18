<?php
namespace Kronos\Middleware;
use Kronos\Repositories\Db;
use Kronos\Support\C;
final class Auth {
    public function __construct(private Db $db){}
    private bool $preview=false;
    private function partitionCookie(bool $clear=false):void{
        if(!$this->preview)return;
        header_remove('Set-Cookie');
        header('Set-Cookie: '.session_name().'='.($clear?'':rawurlencode(session_id())).'; Path=/; Secure; HttpOnly; SameSite=None; Partitioned'.($clear?'; Max-Age=0':''));
    }
    public function start():void{
        $host=C::env('APP_PREVIEW_HOST'); $this->preview=C::env('APP_ENV')==='development' && $host!=='' && (strcasecmp($host,$_SERVER['HTTP_HOST']??'')===0 || strcasecmp($host,$_SERVER['HTTP_X_FORWARDED_HOST']??'')===0);
        $secure=$this->preview||C::env('APP_HTTPS')==='1'||(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
        session_name($secure?'__Host-kronos':'kronos_dev');
        ini_set('session.use_strict_mode','1');
        session_set_cookie_params(['path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);session_start();
        if(isset($_SESSION['uid'])&&(time()-($_SESSION['t']??0)>1800||time()-($_SESSION['first']??0)>28800))$_SESSION=[];
        $_SESSION['csrf']??=bin2hex(random_bytes(24));$this->partitionCookie();
    }
    public function csrf():void{
        $origin=$_SERVER['HTTP_ORIGIN']??'';$expected=$this->preview?'https://'.C::env('APP_PREVIEW_HOST'):C::env('APP_ORIGIN');
        if($origin!==''){
            if($expected!=='')C::check($origin===$expected,'Origen no permitido.',403,'origin');
            else C::check(parse_url($origin,PHP_URL_HOST)===parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST),'Origen no permitido.',403,'origin');
        }
        C::check(hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''),'La sesión del formulario venció. Volvé a cargar.',403,'csrf');
    }
    public function me():?array{
        $id=$_SESSION['uid']??null; if(!$id)return null;
        $u=$this->db->one('SELECT id,username,name FROM users WHERE id=? AND active=1',[$id]);if(!$u)return null;
        $r=$this->db->all('SELECT r.code,r.label FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? ORDER BY FIELD(r.code,\'admin\',\'recepcion\',\'tecnico\') DESC',[$id]);
        $u['roles']=array_column($r,'code');$u['role']=in_array('admin',$u['roles'])?'admin':(in_array('recepcion',$u['roles'])?'recepcion':'tecnico');
        $u['label']=['admin'=>'Administrador','recepcion'=>'Recepción / ventas','tecnico'=>'Técnico'][$u['role']];
        $u['permissions']=array_column($this->db->all('SELECT DISTINCT p.code FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=?',[$id]),'code');
        return $u;
    }
    public function login(array $v):array{
        $login=mb_strtolower(C::txt($v['login']??null,150,true,'usuario'));$pass=$v['password']??'';
        C::check(is_string($pass)&&strlen($pass)<=300,'Credenciales inválidas.',401,'login');
        $this->db->tx(function()use($login){foreach([['login:'.$login,6],['ip:'.($_SERVER['REMOTE_ADDR']??''),30]] as [$value,$limit]){
            $bucket=hash_hmac('sha256',$value,C::key());$this->db->exec('INSERT INTO login_limits VALUES(?,0,UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE attempts=attempts',[$bucket]);
            $r=$this->db->one('SELECT attempts,started_at FROM login_limits WHERE bucket=? FOR UPDATE',[$bucket]);
            if(strtotime($r['started_at'].' UTC')<time()-900)$this->db->exec('UPDATE login_limits SET attempts=1,started_at=UTC_TIMESTAMP(6) WHERE bucket=?',[$bucket]);
            else {C::check((int)$r['attempts']<$limit,'Demasiados intentos. Esperá unos minutos.',429,'rate_limit');$this->db->exec('UPDATE login_limits SET attempts=attempts+1 WHERE bucket=?',[$bucket]);}
        }});
        $u=$this->db->one('SELECT id,password_hash,active FROM users WHERE username=? OR email=? LIMIT 1',[$login,$login]);
        $correct=password_verify($pass,$u['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        C::check($u && $correct && (bool)$u['active'],'Usuario o contraseña incorrectos.',401,'login');
        if(password_needs_rehash($u['password_hash'],PASSWORD_DEFAULT))$this->db->exec('UPDATE users SET password_hash=? WHERE id=?',[password_hash($pass,PASSWORD_DEFAULT),$u['id']]);
        return $this->identify((int)$u['id']);
    }
    public function identify(int $id):array{
        session_regenerate_id(true);$this->partitionCookie();$_SESSION=['uid'=>$id,'t'=>time(),'first'=>time(),'csrf'=>bin2hex(random_bytes(24))];$u=$this->me();C::check($u!==null,'Usuario inactivo.',401);return ['user'=>$u,'csrf_token'=>$_SESSION['csrf']];
    }
    public function end():void{$_SESSION=[];$c=session_get_cookie_params();setcookie(session_name(),'', ['expires'=>time()-86400,'path'=>$c['path'],'secure'=>$c['secure'],'httponly'=>true,'samesite'=>'Lax']);session_destroy();$this->partitionCookie(true);}
    public static function need(array $u,string $p):void{C::check(in_array($p,$u['permissions'],true),'No tenés permiso para esta operación.',403,'forbidden');}
}
