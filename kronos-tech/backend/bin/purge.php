<?php
// CLI / cron: retirar credenciales temporales vencidas e intentos de login antiguos.
use Kronos\Repositories\Db;
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/bootstrap.php';
$d=new Db();
$a=$d->exec('DELETE FROM service_order_secrets WHERE expires_at<=UTC_TIMESTAMP(6)');
$b=$d->exec('DELETE FROM login_limits WHERE started_at<UTC_TIMESTAMP(6)-INTERVAL 24 HOUR');
printf("Credenciales eliminadas: %d. Contadores expirados: %d.\n",$a,$b);
