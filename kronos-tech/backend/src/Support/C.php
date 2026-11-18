<?php
namespace Kronos\Support;
final class C {
    public static function env(string $key,string $default=''):string { $v=getenv($key); return $v===false?$default:$v; }
    public static function demo():bool{return self::env('APP_ENV')==='development' && self::env('APP_DEMO')==='1';}
    public static function tz():\DateTimeZone{return new \DateTimeZone(self::env('APP_TIMEZONE','America/Santiago'));}
    public static function now():\DateTimeImmutable{return new \DateTimeImmutable('now',self::tz());}
    public static function utc(\DateTimeInterface $t):string{return \DateTimeImmutable::createFromInterface($t)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');}
    public static function currency():string{return self::env('APP_CURRENCY','CLP');}
    public static function tax():int{return max(0,min(10000,(int)self::env('APP_TAX_BPS','1900')));}
    public static function scale():int{return max(0,min(4,(int)self::env('APP_SCALE','0')));}
    public static function json(mixed $a):string{return json_encode($a,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    public static function number(string $p,int|string $id):string{return $p.'-'.str_pad((string)$id,4,'0',STR_PAD_LEFT);}
    public static function check(bool $ok,string $msg,int $code=422,string $reason='invalid',?string $field=null):void{if(!$ok)throw new Problem($code,$msg,$reason,$field);}
    public static function key():string{ $raw=base64_decode(self::env('APP_KEY'),true); self::check(is_string($raw)&&strlen($raw)===32,'Configuración de seguridad no disponible.',503); return $raw; }
    public static function txt(mixed $v,int $max=255,bool $required=false,string $label='campo'):string{
        self::check($v===null||is_string($v),'Formato incorrecto para '.$label.'.',422,'invalid',$label);
        $v=trim($v??''); self::check(mb_strlen($v)<=$max && (!$required||$v!==''),$required?'Completá '.$label.' (máximo '.$max.' caracteres).':'Texto demasiado largo en '.$label.'.',422,'invalid',$label); return $v;
    }
    public static function int(mixed $v,int $min=1,int $max=100000000):int{
        self::check((is_int($v)||(is_string($v)&&preg_match('/^[0-9]+$/D',$v))) && (int)$v>=$min && (int)$v<=$max,'Dato numérico fuera de rango.'); return (int)$v;
    }
    public static function id(mixed $v):int{ return self::int($v,1,PHP_INT_MAX); }
    public static function idOrNull(mixed $v):?int{return $v===null||$v===''?null:self::id($v);}
    public static function pick(mixed $v,array $values,string $msg='Valor no permitido.'):string{self::check(is_string($v)&&in_array($v,$values,true),$msg);return $v;}
    public static function date(mixed $v):?string{if($v===null||$v==='')return null; self::check(is_string($v)&&preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)&&($d=\DateTimeImmutable::createFromFormat('!Y-m-d',$v))&&$d->format('Y-m-d')===$v,'La fecha no es válida.'); return $v;}
    public static function exists(mixed $row,string $msg='No se encontró el recurso.'):array{self::check(is_array($row),$msg,404,'not_found');return $row;}
    public static function reached(string $state):bool{return in_array($state,['entregado','cancelado'],true);}
}
