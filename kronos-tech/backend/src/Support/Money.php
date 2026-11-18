<?php
namespace Kronos\Support;
final class Money {
    public static function parse(mixed $v):int{
        $s=C::scale(); C::check(is_string($v)||is_int($v),'Ingresá un importe válido.'); $v=(string)$v;
        C::check((bool)preg_match('/^(0|[1-9][0-9]{0,9})(?:\.[0-9]{1,'.max(1,$s).'})?$/D',$v) && ($s>0||!str_contains($v,'.')),'Importe o precisión inválidos.'); return self::fromDb($v);
    }
    public static function fromDb(mixed $v):int{return (int)bcmul((string)($v??'0'),(string)(10**C::scale()),0);}
    public static function decimal(int $v):string{return bcdiv((string)$v,(string)(10**C::scale()),C::scale());}
    public static function halfUp(int|string $n,int $d):int{return (int)bcdiv(bcadd(bcmul((string)$n,'2'),(string)$d),bcmul((string)$d,'2'),0);}
    public static function inclusive(int $total,int $bps):array{$net=self::halfUp(bcmul((string)$total,'10000'),10000+$bps);return [$net,$total-$net];}
}
