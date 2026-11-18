<?php
namespace Kronos\Services;
use Kronos\Repositories\Db;
use Kronos\Support\{C,Money};
final class Quotes {
    public function __construct(private Db $d,private array $u){}
    private function uid():int{return (int)$this->u['id'];}
    private function root(string $t,int $id,bool $lock=true):array{return $t==='order'?(new Desk($this->d,$this->u))->order($id,$lock):(new Projects($this->d,$this->u))->one($id,$lock);}
    public function one(int $id):array{
        $q=C::exists($this->d->one('SELECT * FROM quotes WHERE id=?',[$id]));$o=$this->root($q['order_id']?'order':'project',(int)($q['order_id']??$q['project_id']),false);
        $q['items']=$this->d->all('SELECT label,quantity,price,discount,net,tax,total,tax_bps FROM quote_items WHERE quote_id=? ORDER BY id',[$id]);
        if($this->u['role']==='admin'){
            $rows=$this->d->one('SELECT SUM(cost*quantity) AS total,SUM(cost IS NULL) AS missing FROM quote_items WHERE quote_id=?',[$id]);
            if($rows && $rows['total']!==null && (int)$rows['missing']===0){$cost=Money::fromDb($rows['total']);$net=Money::fromDb($q['subtotal']);$profit=$net-$cost;
                $ratio=fn($deriv)=>$deriv>0?(($profit<0?-1:1)*Money::halfUp(bcmul((string)abs($profit),'10000'),$deriv)):null;
                $q['internal']=['cost'=>Money::decimal($cost),'gross_profit'=>Money::decimal($profit),'margin_bps'=>$ratio($net),'markup_bps'=>$ratio($cost)];}
        }$q['customer']=$o['customer_id']?$this->d->val('SELECT name FROM customers WHERE id=?',[$o['customer_id']]):null;$q['approval']=$this->d->one('SELECT decision,channel,reference,created_at FROM quote_approvals WHERE quote_id=?',[$id]);return $q;
    }
    public function create(array $v):array{return $this->d->tx(function()use($v){$t=C::pick($v['target']??null,['order','project']);$id=C::id($v['target_id']??null);$o=$this->root($t,$id);$k=$t.'_id';
        C::check(in_array($o['state'],$t==='order'?['diagnostico','presupuesto_enviado','espera_aprobacion','espera_repuesto']:['cotizacion','espera_cliente'],true),'El estado del trabajo no permite emitir un presupuesto.',409);
        if($t==='project')$v['items']=array_map(fn($x)=>array_filter($x,fn($a)=>$a!==null),$this->d->all('SELECT product_id,service_id,quantity FROM pc_project_items WHERE project_id=? ORDER BY id',[$id]));
        $valid=C::date($v['valid_until']??null);C::check($valid!==null&&$valid>=C::now()->format('Y-m-d'),'Indicá una vigencia futura.');$p=(new Commerce($this->d,$this->u))->prices($v,true);
        $rev=(int)$this->d->val("SELECT COALESCE(MAX(revision),0)+1 FROM quotes WHERE $k=?",[$id]);
        $n=$this->d->add("INSERT INTO quotes($k,revision,valid_until,subtotal,tax,total,created_by) VALUES(?,?,?,?,?,?,?)",[$id,$rev,$valid,$p['subtotal'],$p['tax'],$p['total'],$this->uid()]);
        foreach($p['items'] as $l)$this->d->exec('INSERT INTO quote_items(quote_id,product_id,service_id,label,quantity,price,discount,net,tax,total,tax_bps,cost) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$n,$l['product_id'],$l['service_id'],$l['label'],$l['quantity'],$l['price'],$l['discount'],$l['net'],$l['tax'],$l['total'],$l['tax_bps'],$l['cost']]);$this->d->audit($this->uid(),'presupuesto.creado','quote_id',$n);return ['id'=>$n];});}
    private function lock(int $qid):array{$peek=C::exists($this->d->one('SELECT order_id,project_id FROM quotes WHERE id=?',[$qid]));$t=$peek['order_id']?'order':'project';$id=(int)($peek['order_id']??$peek['project_id']);$root=$this->root($t,$id);$q=C::exists($this->d->one('SELECT * FROM quotes WHERE id=? FOR UPDATE',[$qid]));return [$t,$id,$root,$q];}
    public function send(int $qid,array $v):array{return $this->d->tx(function()use($qid,$v){[$t,$id,$r,$q]=$this->lock($qid);C::check($q['state']==='draft'&&$q['valid_until']>=C::now()->format('Y-m-d'),'El presupuesto no es un borrador con vigencia.',409);$state=$r['state'];
        C::check(in_array($state,$t==='order'?['diagnostico','presupuesto_enviado','espera_aprobacion','espera_repuesto']:['cotizacion','espera_cliente'],true),'El trabajo no admite envíos.',409);
        $why=C::txt($v['reference']??null,255,true,'canal y referencia de envío real');$k=$t.'_id';$this->d->exec("UPDATE quotes SET state='superseded' WHERE $k=? AND state='sent'",[$id]);$this->d->exec("UPDATE quotes SET state='sent',sent_at=UTC_TIMESTAMP(6),send_reference=? WHERE id=?",[$why,$qid]);
        if($t==='order'&&$state!=='presupuesto_enviado'){$this->d->exec("UPDATE service_orders SET state='presupuesto_enviado',version=version+1 WHERE id=?",[$id]);(new Desk($this->d,$this->u))->history($id,$state,'presupuesto_enviado','Se registró el envío externo.');}elseif($t==='project'&&$state!=='espera_cliente')(new Projects($this->d,$this->u))->state($id,$state,'espera_cliente');
        $this->d->audit($this->uid(),'presupuesto.envio_registrado','quote_id',$qid);return ['id'=>$qid,'state'=>'sent'];});}
    public function decide(int $qid,array $v):array{return $this->d->tx(function()use($qid,$v){[$t,$id,$r,$q]=$this->lock($qid);C::check($q['state']==='sent'&&$q['valid_until']>=C::now()->format('Y-m-d')&&!C::reached($r['state']),'El presupuesto no está pendiente o ya venció.',409);$d=C::pick($v['decision']??null,['approved','rejected']);$why=C::txt($v['reference']??null,255,true,'evidencia de la decisión');$channel=C::txt($v['channel']??null,40,true,'canal');$p=new Projects($this->d,$this->u);
        if($d==='approved'&&$t==='project'){$compat=$p->compat($id);C::check($compat['state']!=='incompatible','Hay una incompatibilidad que impide aprobar.',409);if($compat['state']==='verify')C::check((bool)$this->d->val("SELECT 1 FROM pc_project_updates WHERE project_id=? AND kind='prueba' AND created_at>=? LIMIT 1",[$id,$q['created_at']]),'Un técnico debe documentar la verificación de los datos faltantes después de crear el presupuesto.',409);}
        $this->d->exec('UPDATE quotes SET state=? WHERE id=?',[$d,$qid]);$this->d->exec('INSERT INTO quote_approvals(quote_id,actor_id,decision,channel,reference) VALUES(?,?,?,?,?)',[$qid,$this->uid(),$d,$channel,$why]);
        if($d==='approved'){$a=(new Commerce($this->d,$this->u))->account($t,$id,true);$rid=$a['id']??$this->d->add('INSERT INTO receivables('.$t.'_id) VALUES(?)',[$id]);$this->d->exec("INSERT INTO receivable_terms(receivable_id,quote_id,kind,amount) VALUES(?,?,'firm',?)",[$rid,$qid,$q['total']]);}
        if($t==='project')$p->state($id,$r['state'],$d==='approved'?'aprobado':'cotizacion');$this->d->audit($this->uid(),'presupuesto.decision','quote_id',$qid,['decision'=>$d]);return ['id'=>$qid,'state'=>$d];});}
}
