<?php
declare(strict_types=1);
use Kronos\Repositories\Db;
use Kronos\Support\C;
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/bootstrap.php';
$db=new Db();
$login=mb_strtolower(C::txt($argv[1]??null,50,true,'usuario'));C::check(preg_match('/^[a-z0-9._-]{3,50}$/D',$login)===1,'Usuario inválido.');
$name=C::txt($argv[2]??null,100,true,'nombre');$pass=C::env('ADMIN_PASSWORD');
C::check(strlen($pass)>=12&&strlen($pass)<=72,'Definí ADMIN_PASSWORD de entre 12 y 72 bytes.');
$db->tx(function()use($db,$login,$name,$pass){
 $setup=require dirname(__DIR__,2).'/database/seeders/roles.php';[$roles]=$setup($db);
 C::check(!$db->val("SELECT 1 FROM user_roles WHERE role_id=? LIMIT 1",[$roles['admin']]),'Ya existe un administrador. Creá cuentas adicionales desde la aplicación.');
 $id=$db->add('INSERT INTO users(username,name,password_hash) VALUES(?,?,?)',[$login,$name,password_hash($pass,PASSWORD_DEFAULT)]);
 $db->exec('INSERT INTO user_roles VALUES(?,?)',[$id,$roles['admin']]);$db->exec("INSERT IGNORE INTO locations(code,name) VALUES('TALLER','Bodega principal')");$db->exec("INSERT IGNORE INTO cash_registers(code,name) VALUES('CAJA-01','Caja principal')");
 $db->audit($id,'acceso.bootstrap','subject_user_id',$id);
});
echo "Administrador inicial creado.\n";
