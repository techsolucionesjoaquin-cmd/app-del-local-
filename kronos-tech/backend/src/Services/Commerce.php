<?php
namespace Kronos\Services;
use Kronos\Repositories\Db;
use Kronos\Support\{C,Money};
use Kronos\Middleware\Auth;
final class Commerce {
    public function __construct(private Db $d,private array $u){}
    private function uid():int{return (int)$this->u['id'];}
    private function w(int $v):string{return Money::decimal($v);}
    public function account(string $t,int $id,bool $lock=false):array{
        $key=match($t){'order'=>'order_id','project'=>'project_id','sale'=>'sale_id'};
        $r=$this->d->one("SELECT * FROM receivables WHERE $key=?".($lock?' FOR UPDATE':''),[$id]);
        if(!$r)return ['id'=>null,'kind'=>null,'total'=>null,'paid'=>$this->w(0),'balance'=>null,'credit'=>$this->w(0)];
        $x=$this->d->one('SELECT kind,amount FROM receivable_terms WHERE receivable_id=? ORDER BY id DESC LIMIT 1',[$r['id']]);
        $paid=$this->paid((int)$r['id']);$sum=$x?Money::fromDb($x['amount'])- $paid:null;
        return ['id'=>$r['id'],'kind'=>$x['kind']??null,'total'=>$x['amount']??null,'paid'=>$this->w($paid),'balance'=>$sum!==null?$this->w(max(0,$sum)):null,'credit'=>$sum!==null?$this->w(max(0,-$sum)):$this->w(0)];
    }
    private function paid(int $r):int{return Money::fromDb($this->d->val("SELECT COALESCE(SUM(IF(p.kind='capture',p.amount,-p.amount)),0) FROM payments p JOIN payment_allocations a ON a.payment_id=p.id WHERE a.receivable_id=?",[$r]));}
    public function payments(int $r):array{return $this->d->all('SELECT p.id,p.method,p.kind,p.amount,p.tendered,p.change_due,p.reference,p.created_at,p.refund_of_id FROM payments p JOIN payment_allocations a ON a.payment_id=p.id WHERE a.receivable_id=? ORDER BY p.id DESC',[$r]);}
    public function canClose(string $t,int $id,bool $cancel=false):void{
        $a=$this->account($t,$id,true);$paid=Money::fromDb($a['paid']);
        if($cancel){C::check($paid===0,'Devolvé los pagos antes de cancelar.',409,'unsettled');if($a['id'])$this->d->exec("INSERT INTO receivable_terms(receivable_id,kind,amount) VALUES(?,'void',0)",[$a['id']]);return;}
        C::check($a['kind']==='firm'&&Money::fromDb($a['total'])===$paid,'Se requiere presupuesto firme y saldo liquidado.',409,'unsettled');
    }
    public function current():?array{return $this->d->one('SELECT s.*,r.name AS register,r.code FROM cash_sessions s JOIN cash_registers r ON r.id=s.register_id WHERE s.open_operator=?',[$this->uid()]);}
    public function lockSession(int $id,?string $override=null):array{
        $r=C::exists($this->d->one('SELECT id,register_id FROM cash_sessions WHERE id=?',[$id]),'No existe ese turno.');
        $this->d->one('SELECT id FROM cash_registers WHERE id=? FOR UPDATE',[$r['register_id']]);
        $s=C::exists($this->d->one('SELECT * FROM cash_sessions WHERE id=? FOR UPDATE',[$id]));C::check($s['closed_at']===null,'El turno de caja ya cerró.',409,'cash_closed');
        if((int)$s['operator_id']!==$this->uid()){Auth::need($this->u,'admin');$why=C::txt($override,250,true,'motivo de intervención');$this->d->audit($this->uid(),'caja.intervencion','session_id',$id,['reason'=>$why]);}return $s;
    }
    public function registers():array{return $this->d->all('SELECT r.id,r.code,r.name,s.id AS active_session FROM cash_registers r LEFT JOIN cash_sessions s ON s.open_register=r.id WHERE r.active=1 ORDER BY r.id');}
    public function open(array $v):array{
        return $this->d->tx(function()use($v){$reg=C::id($v['register_id']??null);C::exists($this->d->one('SELECT id FROM cash_registers WHERE id=? AND active=1 FOR UPDATE',[$reg]));C::check(!$this->current()&&!$this->d->val('SELECT id FROM cash_sessions WHERE open_register=?',[$reg]),'Ya hay un turno abierto para esa caja o responsable.',409,'cash_open');
            $id=$this->d->add('INSERT INTO cash_sessions(register_id,operator_id,opening) VALUES(?,?,?)',[$reg,$this->uid(),$this->w(Money::parse($v['opening']??null))]);$this->d->audit($this->uid(),'caja.apertura','session_id',$id);return ['id'=>$id];});
    }
    public function summary(int $s):array{
        $amount=Money::fromDb($this->d->val('SELECT opening FROM cash_sessions WHERE id=?',[$s]));$res=[];$cashNet=0;
        foreach(['cash','debit','transfer'] as $m){$p=$this->d->one("SELECT COALESCE(SUM(IF(kind='capture',amount,0)),0) AS captured,COALESCE(SUM(IF(kind='refund',amount,0)),0) AS refunded FROM payments WHERE session_id=? AND method=?",[$s,$m]);$a=Money::fromDb($p['captured']);$b=Money::fromDb($p['refunded']);$res[$m]=['captured'=>$this->w($a),'refunded'=>$this->w($b),'net'=>$this->w($a-$b)];if($m==='cash')$cashNet=$a-$b;}
        $m=$this->d->one("SELECT COALESCE(SUM(IF(direction='in',amount,0)),0) AS credit,COALESCE(SUM(IF(direction='out',amount,0)),0) AS debit FROM cash_movements WHERE session_id=?",[$s]);$in=Money::fromDb($m['credit']);$out=Money::fromDb($m['debit']);
        return ['opening'=>$this->w($amount),'manual_in'=>$this->w($in),'manual_out'=>$this->w($out),'expected'=>$this->w($amount+$cashNet+$in-$out),'methods'=>$res];
    }
    public function cashView(?int $id=null):array{
        $s=$id?C::exists($this->d->one('SELECT s.*,r.name AS register,r.code FROM cash_sessions s JOIN cash_registers r ON r.id=s.register_id WHERE s.id=?',[$id])):$this->current();
        $args=$this->u['role']==='admin'?[]:[$this->uid()];$where=$args?' WHERE s.operator_id=?':'';
        $hist=$this->d->all('SELECT s.id,s.opened_at,s.closed_at,r.name AS register,u.name AS operator,c.expected,c.counted,c.difference FROM cash_sessions s JOIN cash_registers r ON r.id=s.register_id JOIN users u ON u.id=s.operator_id LEFT JOIN cash_counts c ON c.final_session=s.id'.$where.' ORDER BY s.id DESC LIMIT 40',$args);
        if(!$s)return ['session'=>null,'history'=>$hist,'registers'=>$this->registers()];
        C::exists($this->u['role']==='admin'||(int)$s['operator_id']===$this->uid()?$s:null);$x=(int)$s['id'];
        $events=$this->d->all("SELECT created_at,method,kind,amount,COALESCE(reference,'') AS description FROM payments WHERE session_id=? UNION ALL SELECT created_at,'cash',direction,amount,reason FROM cash_movements WHERE session_id=? ORDER BY created_at DESC LIMIT 100",[$x,$x]);
        return ['session'=>$s,'summary'=>$this->summary($x),'events'=>$events,'history'=>$hist,'counts'=>$this->d->all('SELECT id,kind,expected,counted,difference,notes,created_at FROM cash_counts WHERE session_id=? ORDER BY id DESC',[$x])];
    }
    public function movement(int $id,array $v):array{
        return $this->d->tx(function()use($id,$v){$this->lockSession($id,$v['override_reason']??null);$dir=C::pick($v['direction']??null,['in','out']);$n=Money::parse($v['amount']??null);C::check($n>0,'El importe debe ser mayor a cero.');
            if($dir==='out')$this->sufficientCash($id,$n);$m=$this->d->add('INSERT INTO cash_movements(session_id,actor_id,direction,amount,reason) VALUES(?,?,?,?,?)',[$id,$this->uid(),$dir,$this->w($n),C::txt($v['reason']??null,255,true,'motivo')]);$this->d->audit($this->uid(),'caja.movimiento','session_id',$id,['direction'=>$dir,'amount'=>$this->w($n)]);return ['id'=>$m];});
    }
    private function sufficientCash(int $id,int $amt):void{C::check($amt<=Money::fromDb($this->summary($id)['expected']),'El turno no tiene efectivo suficiente.',409,'cash_insufficient');}
    public function count(int $id,array $v,bool $close):array{
        return $this->d->tx(function()use($id,$v,$close){$this->lockSession($id,$v['override_reason']??null);$tot=$this->summary($id);$count=Money::parse($v['counted']??null);$diff=$count-Money::fromDb($tot['expected']);$notes=C::txt($v['notes']??null,500,$close&&$diff!==0,'observaciones');
            $s=$this->d->add('INSERT INTO cash_counts(session_id,actor_id,kind,expected,counted,difference,summary,notes) VALUES(?,?,?,?,?,?,?,?)',[$id,$this->uid(),$close?'close':'partial',$tot['expected'],$this->w($count),$this->w($diff),C::json($tot),$notes]);
            if($close)$this->d->exec('UPDATE cash_sessions SET closed_at=UTC_TIMESTAMP(6),closed_by=? WHERE id=?',[$this->uid(),$id]);$this->d->audit($this->uid(),$close?'caja.cierre':'caja.arqueo','session_id',$id,['difference'=>$this->w($diff)]);return ['id'=>$s,'expected'=>$tot['expected'],'counted'=>$this->w($count),'difference'=>$this->w($diff)];});
    }
    public function capture(int $session,int $r,mixed $rows,int $max,bool $exact):array{
        C::check(is_array($rows)&&array_is_list($rows)&&count($rows)>0&&count($rows)<=3,'Seleccioná uno o más medios de pago.');$a=[];$used=[];$sum=0;
        foreach($rows as $p){C::check(is_array($p),'Pago no válido.');$m=C::pick($p['method']??null,['cash','debit','transfer']);C::check(!isset($used[$m]),'No repitas medios de pago.');$used[$m]=true;$amount=Money::parse($p['amount']??null);C::check($amount>0,'El pago debe ser mayor que cero.');$sum+=$amount;
            $tender=$m==='cash'?Money::parse($p['tendered']??$p['amount']):null;C::check($tender===null||$tender>=$amount,'El efectivo recibido no alcanza.');$ref=$m==='cash'?null:C::txt($p['reference']??null,155,true,'referencia externa');$a[]=[$m,$amount,$tender,$ref];}
        C::check($sum<=$max&&(!$exact||$sum===$max),$exact?'Los pagos deben coincidir con el total.':'El pago excede el saldo disponible.',422,'payment_total');$out=[];
        foreach($a as [$m,$amt,$t,$ref]){$id=$this->d->add("INSERT INTO payments(session_id,actor_id,method,kind,amount,tendered,change_due,reference) VALUES(?,?,?,'capture',?,?,?,?)",[$session,$this->uid(),$m,$this->w($amt),$t===null?null:$this->w($t),$t===null?null:$this->w($t-$amt),$ref]);$this->d->exec('INSERT INTO payment_allocations VALUES(?,?)',[$id,$r]);$out[]=['id'=>$id,'method'=>$m,'amount'=>$this->w($amt),'change'=>$t===null?null:$this->w($t-$amt)];}
        $this->d->audit($this->uid(),'caja.cobro','session_id',$session,['account'=>$r,'amount'=>$this->w($sum)]);return $out;
    }
    public function postPayment(array $v):array{
        return $this->d->tx(function()use($v){$r=C::exists($this->d->one('SELECT * FROM receivables WHERE id=?',[C::id($v['receivable_id']??null)]));$type=$r['order_id']?'order':'project';$id=(int)($r['order_id']??$r['project_id']);C::check($r['sale_id']===null,'Cobrá ventas desde el punto de venta.');
            $parent=$type==='order'?(new Desk($this->d,$this->u))->order($id,true):(new Projects($this->d,$this->u))->one($id,true);C::check(!C::reached($parent['state']),'El trabajo ya cerró.',409);
            $s=$this->lockSession(C::id($v['session_id']??null),$v['override_reason']??null);$a=$this->account($type,$id,true);C::check($a['kind']==='firm','Para un nuevo cobro necesitás un presupuesto aprobado.',409,'approval_required');
            $this->capture((int)$s['id'],(int)$r['id'],$v['payments']??[],Money::fromDb($a['balance']),false);return $this->account($type,$id);});
    }
    private function appendRefund(int $s,int $r,array $p,int $amount,?string $ref):int{
        $returned=Money::fromDb($this->d->val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE refund_of_id=?',[$p['id']]));C::check($amount>0&&$amount+$returned<=Money::fromDb($p['amount']),'El reintegro supera el pago disponible.',409,'refund_amount');
        if($p['method']==='cash')$this->sufficientCash($s,$amount);else $ref=C::txt($ref,155,true,'referencia del reintegro');
        $id=$this->d->add("INSERT INTO payments(session_id,actor_id,method,kind,amount,reference,refund_of_id) VALUES(?,?,?,'refund',?,?,?)",[$s,$this->uid(),$p['method'],$this->w($amount),$ref,$p['id']]);$this->d->exec('INSERT INTO payment_allocations VALUES(?,?)',[$id,$r]);return $id;
    }
    public function refund(int $pid,array $v):array{
        return $this->d->tx(function()use($pid,$v){$p=C::exists($this->d->one("SELECT p.*,r.id AS account_id,r.order_id,r.project_id,r.sale_id FROM payments p JOIN payment_allocations a ON a.payment_id=p.id JOIN receivables r ON r.id=a.receivable_id WHERE p.id=? AND p.kind='capture'",[$pid]));C::check(!$p['sale_id'],'Usá la anulación de venta para este pago.',409);
            $t=$p['order_id']?'order':'project';$id=(int)($p['order_id']??$p['project_id']);$root=$t==='order'?(new Desk($this->d,$this->u))->order($id,true):(new Projects($this->d,$this->u))->one($id,true);C::check(!C::reached($root['state']),'El trabajo ya cerró.',409);
            $s=$this->lockSession(C::id($v['session_id']??null),$v['override_reason']??null);$this->account($t,$id,true);$this->d->one('SELECT id FROM payments WHERE id=? FOR UPDATE',[$pid]);$reason=C::txt($v['reason']??null,255,true,'motivo');
            $n=$this->appendRefund((int)$s['id'],(int)$p['account_id'],$p,Money::parse($v['amount']??null),$v['reference']??null);$this->d->audit($this->uid(),'pago.reintegro',$t.'_id',$id,['reason'=>$reason,'payment_id'=>$n]);return ['id'=>$n];});
    }
    public function prices(array $v,bool $lock=false):array{
        $lines=$v['items']??[];C::check(is_array($lines)&&array_is_list($lines)&&count($lines)>0&&count($lines)<=50,'Agregá entre 1 y 50 líneas.');$keys=[];
        foreach($lines as $l){C::check(is_array($l)&&isset($l['product_id'])!==isset($l['service_id']),'La línea debe ser producto o servicio.');$t=isset($l['product_id'])?'p':'s';$n=C::id($l['product_id']??$l['service_id']);$key=$t.':'.str_pad((string)$n,20,'0',STR_PAD_LEFT);C::check(!isset($keys[$key]),'No repitas líneas del mismo ítem.');$keys[$key]=[$t,$n,C::int($l['quantity']??null,1,1000)];}
        ksort($keys);$a=[];$gross=0;
        foreach($keys as [$t,$n,$q]){$table=$t==='p'?'products':'service_catalog';$p=C::exists($this->d->one("SELECT * FROM $table WHERE id=? AND active=1".($lock?' FOR UPDATE':''),[$n]),'Hay un ítem que ya no está disponible.');$price=Money::fromDb($p['price']);$g=$price*$q;C::check($g<100000000000000,'Total fuera de rango.');$gross+=$g;
            $a[]=['product_id'=>$t==='p'?$n:null,'service_id'=>$t==='s'?$n:null,'location_id'=>$p['location_id']??null,'label'=>$p['name'],'quantity'=>$q,'price'=>$this->w($price),'cost'=>$p['cost'],'tax_bps'=>(int)$p['tax_bps'],'gross'=>$g];}
        $discount=Money::parse($v['discount']??'0');if($discount){Auth::need($this->u,'discount');C::txt($v['discount_reason']??null,255,true,'motivo de descuento');}
        C::check($discount<=$gross,'El descuento supera el total.');$alloc=[];$rem=[];$remain=$discount;
        foreach($a as $i=>$l){$product=bcmul((string)$discount,(string)$l['gross']);$alloc[$i]=$gross?(int)bcdiv($product,(string)$gross,0):0;$remain-=$alloc[$i];$rem[$i]=$gross?(int)bcmod($product,(string)$gross):0;}
        arsort($rem,SORT_NUMERIC);foreach(array_keys($rem) as $i){if($remain<=0)break;$alloc[$i]++;$remain--;}
        $net=$tax=0;foreach($a as $i=>&$l){$g=$l['gross']-$alloc[$i];[$n,$t]=Money::inclusive($g,$l['tax_bps']);$net+=$n;$tax+=$t;unset($l['gross']);$l['net']=$this->w($n);$l['tax']=$this->w($t);$l['total']=$this->w($g);$l['discount']=$this->w($alloc[$i]);}unset($l);
        $public=array_map(function($l){unset($l['cost']);return $l;},$a);
        return ['items'=>$lock?$a:$public,'subtotal'=>$this->w($net),'tax'=>$this->w($tax),'total'=>$this->w($net+$tax),'discount'=>$this->w($discount),'fingerprint'=>hash_hmac('sha256',C::json($public),C::key())];
    }
    public function sale(array $v):array{
        return $this->d->tx(function()use($v){$s=$this->lockSession(C::id($v['session_id']??null),$v['override_reason']??null);$price=$this->prices($v,true);$p=Money::fromDb($price['total']);C::check($p>0,'La venta debe tener un total positivo.');
            C::check(hash_equals($price['fingerprint'],(string)($v['fingerprint']??'')),'El precio cambió. Actualizá la revisión de compra.',409,'price_changed');
            $cid=C::idOrNull($v['customer_id']??null);if($cid)C::exists($this->d->one('SELECT id FROM customers WHERE id=?',[$cid]));
            $id=$this->d->add('INSERT INTO sales(customer_id,session_id,actor_id,subtotal,tax,discount,total) VALUES(?,?,?,?,?,?,?)',[$cid,$s['id'],$this->uid(),$price['subtotal'],$price['tax'],$price['discount'],$price['total']]);
            $r=$this->d->add('INSERT INTO receivables(sale_id) VALUES(?)',[$id]);$this->d->exec("INSERT INTO receivable_terms(receivable_id,kind,amount) VALUES(?,'firm',?)",[$r,$price['total']]);
            $payments=$this->capture((int)$s['id'],$r,$v['payments']??[],$p,true);$i=new Inventory($this->d,$this->u);
            foreach($price['items'] as $l){$product=$l['product_id'];$location=$l['location_id'];if($product)$i->delta($product,(int)$location,-$l['quantity']);
                $row=$this->d->add('INSERT INTO sale_items(sale_id,product_id,service_id,location_id,label,quantity,price,cost,discount,net,tax,total,tax_bps) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[$id,$product,$l['service_id'],$location,$l['label'],$l['quantity'],$l['price'],$l['cost'],$l['discount'],$l['net'],$l['tax'],$l['total'],$l['tax_bps']]);
                if($product)$i->append($product,(int)$location,'salida_venta',-$l['quantity'],'Venta '.C::number('VTA',$id),$row);
            }
            $this->d->audit($this->uid(),'venta.confirmada','sale_id',$id,['discount'=>$price['discount'],'discount_reason'=>C::txt($v['discount_reason']??null,255)]);return ['id'=>$id,'number'=>C::number('VTA',$id),'total'=>$price['total'],'payments'=>$payments];});
    }
    public function sales():array{return $this->d->all('SELECT s.id,s.total,s.created_at,s.voided_at,c.name AS customer,u.name AS actor FROM sales s LEFT JOIN customers c ON c.id=s.customer_id JOIN users u ON u.id=s.actor_id ORDER BY s.id DESC LIMIT 100');}
    public function saleView(int $id):array{
        $r=C::exists($this->d->one('SELECT s.*,c.name AS customer,u.name AS actor FROM sales s LEFT JOIN customers c ON c.id=s.customer_id JOIN users u ON u.id=s.actor_id WHERE s.id=?',[$id]));$r['items']=$this->d->all('SELECT label,quantity,price,discount,net,tax,total FROM sale_items WHERE sale_id=? ORDER BY id',[$id]);$r['payments']=$this->payments((int)$this->account('sale',$id)['id']);$r['number']=C::number('VTA',$id);return $r;
    }
    public function void(int $id,array $v):array{
        return $this->d->tx(function()use($id,$v){$sale=C::exists($this->d->one('SELECT * FROM sales WHERE id=? FOR UPDATE',[$id]));C::check(!$sale['voided_at'],'La venta ya se anuló.',409,'voided');$s=$this->lockSession(C::id($v['session_id']??null),$v['override_reason']??null);$a=$this->account('sale',$id,true);$why=C::txt($v['reason']??null,255,true,'motivo');
            $m=$this->d->all('SELECT m.* FROM stock_movements m JOIN sale_items i ON i.id=m.sale_item_id WHERE i.sale_id=? ORDER BY m.product_id,m.location_id',[$id]);C::check(!$m||($v['return_confirmed']??false)===true,'Confirmá la devolución física de los productos.');
            foreach(array_reverse($this->payments((int)$a['id'])) as $p){C::check($p['kind']==='capture','El pago ya fue revertido.',409);$this->appendRefund((int)$s['id'],(int)$a['id'],$p,Money::fromDb($p['amount']),$v['references'][$p['method']]??null);}
            $i=new Inventory($this->d,$this->u);foreach($m as $r){$p=(int)$r['product_id'];$l=(int)$r['location_id'];$q=-(int)$r['delta'];$i->delta($p,$l,$q);$i->append($p,$l,'reversion',$q,$why,null,null,(int)$r['id']);}
            $this->d->exec('UPDATE sales SET voided_at=UTC_TIMESTAMP(6),void_reason=? WHERE id=?',[$why,$id]);$this->d->exec("INSERT INTO receivable_terms(receivable_id,kind,amount) VALUES(?,'void',0)",[$a['id']]);$this->d->audit($this->uid(),'venta.anulada','sale_id',$id,['reason'=>$why]);return ['id'=>$id,'voided'=>true];});
    }
}
