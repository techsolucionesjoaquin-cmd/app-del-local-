<?php
use Kronos\Repositories\Db;
return static function(Db $db):array {
 $access=['admin'=>['dashboard','customers','orders.read','orders.write','orders.tech','inventory','projects.read','projects.write','projects.tech','quotes','sales','cash','discount','admin'],'recepcion'=>['dashboard','customers','orders.read','orders.write','inventory','projects.read','projects.write','quotes','sales','cash'],'tecnico'=>['dashboard','orders.read','orders.tech','inventory','projects.read','projects.tech']];
 $labels=['admin'=>'Administrador','recepcion'=>'Recepción / ventas','tecnico'=>'Técnico'];$roles=[];
 foreach($access as $code=>$entries){$db->exec('INSERT INTO roles(code,label) VALUES(?,?) ON DUPLICATE KEY UPDATE label=VALUES(label)',[$code,$labels[$code]]);$r=(int)$db->val('SELECT id FROM roles WHERE code=?',[$code]);$roles[$code]=$r;foreach($entries as $p){$db->exec('INSERT IGNORE INTO permissions(code) VALUES(?)',[$p]);$db->exec('INSERT IGNORE INTO role_permissions SELECT ?,id FROM permissions WHERE code=?',[$r,$p]);}}
 return [$roles,$access];
};
