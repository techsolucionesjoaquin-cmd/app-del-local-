<?php
namespace Kronos\Repositories;
use Kronos\Support\{C,Problem};
final class Db {
    public \PDO $pdo;
    public function __construct(){
        $this->pdo=new \PDO(C::env('DB_DSN'),C::env('DB_USER'),C::env('DB_PASSWORD'),[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false]);
        $this->pdo->exec("SET time_zone='+00:00'"); $this->pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
    }
    private function statement(string $s,array $p=[]):\PDOStatement{$st=$this->pdo->prepare($s);$st->execute($p);return $st;}
    public function all(string $s,array $p=[]):array{return $this->statement($s,$p)->fetchAll();}
    public function one(string $s,array $p=[]):?array{return $this->statement($s,$p)->fetch()?:null;}
    public function val(string $s,array $p=[]):mixed{return $this->statement($s,$p)->fetchColumn();}
    public function exec(string $s,array $p=[]):int{return $this->statement($s,$p)->rowCount();}
    public function add(string $s,array $p=[]):int{$this->statement($s,$p);return (int)$this->pdo->lastInsertId();}
    public function tx(callable $f):mixed{
        if($this->pdo->inTransaction()) return $f();
        for($i=0;;$i++){
            $this->pdo->beginTransaction();
            try{$r=$f();$this->pdo->commit();return $r;}
            catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();if($e instanceof \PDOException && in_array($e->errorInfo[1]??0,[1205,1213],true)){if($i<2){usleep(random_int(10000,45000));continue;}throw new Problem(503,'Operación ocupada. Volvé a intentar con la misma clave.','write_busy');}throw $e;}
        }
    }
    public function audit(int $actor,string $action,string $subject,int $id,array $detail=[]):void{
        if(!in_array($subject,['order_id','project_id','product_id','sale_id','session_id','customer_id','quote_id','appointment_id','subject_user_id'],true))throw new \LogicException('Audit subject');
        $this->exec("INSERT INTO audit_logs(actor_id,action,$subject,detail) VALUES(?,?,?,?)",[$actor,$action,$id,C::json($detail)]);
    }
    public function once(int $actor,string $scope,string $key,array $body,callable $fn):mixed{
        C::check(preg_match('/^[0-9A-Za-z_-]{16,96}$/D',$key)===1,'Falta Idempotency-Key válida.',400,'idempotency_required');
        $canonical=function(mixed $v)use(&$canonical):mixed{if(!is_array($v))return $v;if(!array_is_list($v))ksort($v);return array_map($canonical,$v);};
        $h=hash_hmac('sha256',C::json($canonical($body)),C::key());
        return $this->tx(function() use($actor,$scope,$key,$h,$fn){
            $args=[$actor,$scope,$key];
            $this->exec('INSERT INTO idempotency_keys(actor_id,scope_key,client_key,payload_hash) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE payload_hash=payload_hash',[...$args,$h]);
            $r=$this->one('SELECT payload_hash,result FROM idempotency_keys WHERE actor_id=? AND scope_key=? AND client_key=? FOR UPDATE',$args);
            C::check(hash_equals($r['payload_hash'],$h),'La misma clave ya se usó para otra solicitud.',409,'idempotency_conflict');
            if($r['result']!==null){if(PHP_SAPI!=='cli')header('Idempotency-Replayed: true');return json_decode($r['result'],true,512,JSON_THROW_ON_ERROR);}
            $result=$fn();$this->exec('UPDATE idempotency_keys SET result=? WHERE actor_id=? AND scope_key=? AND client_key=?',[C::json($result),...$args]);return $result;
        });
    }
}
