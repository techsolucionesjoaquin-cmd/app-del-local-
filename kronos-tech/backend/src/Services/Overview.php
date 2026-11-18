<?php
namespace Kronos\Services;
use Kronos\Repositories\Db;
use Kronos\Support\{C,Money};
final class Overview {
    public function __construct(private Db $d,private array $u){}
    private function tech():bool{return $this->u['role']==='tecnico';}
    private function uid():int{return (int)$this->u['id'];}
    public function dashboard(int $days):array{
        $days=$days===14?14:7;$day=C::now()->setTime(0,0);$next=$day->modify('+1 day');$time=[C::utc($day),C::utc($next)];$st=$this->tech()?' AND technician_id=?':'';$ua=$this->tech()?[$this->uid()]:[];$inv=new Inventory($this->d,$this->u);
        $orders=(int)$this->d->val("SELECT COUNT(*) FROM service_orders WHERE state NOT IN ('borrador','entregado','cancelado')".$st,$ua);$r=(int)$this->d->val("SELECT COUNT(*) FROM service_orders WHERE state='en_reparacion'".$st,$ua);
        $chart=[];for($i=$days-1;$i>=0;$i--){$from=$day->modify('-'.$i.' day');$to=$from->modify('+1 day');$range=[C::utc($from),C::utc($to)];
            $chart[]=['day'=>$from->format('Y-m-d'),'received'=>(int)$this->d->val('SELECT COUNT(*) FROM service_orders WHERE received_at>=? AND received_at<?'.$st,[...$range,...$ua]),'delivered'=>(int)$this->d->val('SELECT COUNT(*) FROM service_orders WHERE delivered_at>=? AND delivered_at<?'.$st,[...$range,...$ua])];}
        $critical=$inv->list(['critical'=>1]);$metrics=['orders'=>$orders,'repair'=>$r,'critical'=>count($critical),'sales'=>null,'sale_count'=>null,'pending'=>null];
        if(!$this->tech()){$s=$this->d->one('SELECT COALESCE(SUM(total),0) AS total,COUNT(*) AS qty FROM sales WHERE voided_at IS NULL AND created_at>=? AND created_at<?',$time);$metrics['sales']=$s['total'];$metrics['sale_count']=$s['qty'];
            $metrics['pending']=$this->d->val("SELECT COALESCE(SUM(GREATEST(t.amount-COALESCE((SELECT SUM(IF(p.kind='capture',p.amount,-p.amount)) FROM payment_allocations a JOIN payments p ON p.id=a.payment_id WHERE a.receivable_id=r.id),0),0)),0) FROM receivables r JOIN receivable_terms t ON t.id=(SELECT MAX(x.id) FROM receivable_terms x WHERE x.receivable_id=r.id) WHERE r.sale_id IS NULL AND t.kind='firm'");}
        $alerts=[];
        if($critical){usort($critical,fn($x,$y)=>$x['available']<=>$y['available']);$p=$critical[0];$alerts[]=['tone'=>'amber','icon'=>'triangle','title'=>'Stock crítico','text'=>$p['name'].($p['available']?' · '.$p['available'].' disp.':' · sin disponibilidad'),'count'=>count($critical),'href'=>'#inventario?critical=1'];}
        if(!$this->tech()){$q=(int)$this->d->val("SELECT COUNT(*) FROM quotes WHERE state='sent'");if($q)$alerts[]=['tone'=>'purple','icon'=>'file','title'=>'Presupuestos pendientes','text'=>$q.' sin respuesta del cliente','count'=>$q,'href'=>'#presupuestos'];}
        $due=(int)$this->d->val("SELECT COUNT(*) FROM service_orders WHERE due_date<=? AND state NOT IN ('borrador','entregado','cancelado')".$st,[$day->modify('+1 day')->format('Y-m-d'),...$ua]);
        if($due)$alerts[]=['tone'=>'blue','icon'=>'clock','title'=>'Entregas próximas','text'=>$due.' órdenes para hoy o mañana, incl. vencidas','count'=>$due,'href'=>'#ordenes?state=open'];
        $recent=(new Desk($this->d,$this->u))->orders(['per_page'=>5,'state'=>'open'])['items'];
        $data=['date'=>$day->format('Y-m-d'),'timezone'=>C::tz()->getName(),'currency'=>C::currency(),'scale'=>C::scale(),'metrics'=>$metrics,'series'=>$chart,'agenda'=>$this->agenda($day->format('Y-m-d')),'alerts'=>$alerts,'recent'=>$recent];
        if($this->u['role']==='admin')$data['activity']=$this->audit(5);return $data;
    }
    public function agenda(string $day):array{
        $from=new \DateTimeImmutable(C::date($day)??C::now()->format('Y-m-d'),C::tz());$a=[C::utc($from),C::utc($from->modify('+1 day'))];$s=$this->tech()?' AND o.technician_id=?':'';if($s)$a[]=$this->uid();
        return $this->d->all("SELECT a.*,c.name AS customer,CONCAT('OT-',LPAD(o.id,GREATEST(4,LENGTH(o.id)),'0')) AS order_number FROM appointments a JOIN customers c ON c.id=a.customer_id LEFT JOIN service_orders o ON o.id=a.order_id WHERE scheduled_at>=? AND scheduled_at<?".$s.' ORDER BY scheduled_at,id',$a);
    }
    public function appointment(array $v,?int $id=null):array{return $this->d->tx(function()use($v,$id){
        if($id){$old=C::exists($this->d->one('SELECT * FROM appointments WHERE id=? FOR UPDATE',[$id]));$state=C::pick($v['state']??$old['state'],['pendiente','realizada','cancelada']);$this->d->exec('UPDATE appointments SET state=? WHERE id=?',[$state,$id]);}
        else{$c=C::id($v['customer_id']??null);$o=C::idOrNull($v['order_id']??null);C::exists($this->d->one('SELECT id FROM customers WHERE id=?',[$c]));if($o)C::check((bool)$this->d->val("SELECT 1 FROM service_orders WHERE id=? AND customer_id=? AND state<>'borrador'",[$o,$c]),'La orden no pertenece al cliente.');
            $day=C::date($v['day']??null);$time=C::txt($v['time']??null,5,true,'hora');C::check($day!==null&&preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$time)===1,'Fecha u hora inválida.');$dt=new \DateTimeImmutable($day.' '.$time,C::tz());C::check($dt->format('Y-m-d H:i')===$day.' '.$time,'Horario no disponible por cambio de hora.');
            $id=$this->d->add('INSERT INTO appointments(customer_id,order_id,actor_id,kind,title,scheduled_at) VALUES(?,?,?,?,?,?)',[$c,$o,$this->uid(),C::pick($v['kind']??null,['recepcion','entrega','llamada']),C::txt($v['title']??null,180,true,'título'),C::utc($dt)]);}
        $this->d->audit($this->uid(),'agenda.guardada','appointment_id',$id);return ['id'=>$id];});}
    public function audit(int $limit=100):array{$limit=max(1,min(250,$limit));return $this->d->all("SELECT a.*,u.name AS actor FROM audit_logs a JOIN users u ON u.id=a.actor_id ORDER BY a.id DESC LIMIT $limit");}
    public function pendingQuotes():array{return $this->d->all("SELECT q.id,q.order_id,q.project_id,q.state,q.revision,q.valid_until,q.total,COALESCE(c.name,cp.name) AS customer FROM quotes q LEFT JOIN service_orders o ON o.id=q.order_id LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN pc_projects p ON p.id=q.project_id LEFT JOIN customers cp ON cp.id=p.customer_id ORDER BY q.id DESC LIMIT 120");}
    public function users():array{return $this->d->all("SELECT u.id,u.username,u.name,u.email,u.active,GROUP_CONCAT(r.code) AS roles FROM users u LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles r ON r.id=ur.role_id GROUP BY u.id ORDER BY u.name");}
    public function user(array $v,?int $id=null):array{return $this->d->tx(function()use($v,$id){
        $role=C::pick($v['role']??null,['admin','recepcion','tecnico']);$rid=(int)$this->d->val('SELECT id FROM roles WHERE code=? FOR UPDATE',[$role]);$name=C::txt($v['name']??null,100,true,'nombre');$login=mb_strtolower(C::txt($v['username']??null,50,true,'usuario'));C::check(preg_match('/^[a-z0-9._-]{3,50}$/D',$login)===1,'Usuario no válido.');$email=C::txt($v['email']??null,150);C::check(!$email||filter_var($email,FILTER_VALIDATE_EMAIL)!==false,'Correo no válido.');
        $pass=$v['password']??'';C::check(is_string($pass)&&strlen($pass)<=200&&(($id&&$pass==='')||strlen($pass)>=12),'Usá una contraseña de al menos 12 caracteres.');$active=($v['active']??true)?1:0;
        if($id){C::exists($this->d->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$id]));C::check($id!==$this->uid()||($role==='admin'&&$active),'No podés quitarte tu propio acceso administrador.',409);$this->d->exec('UPDATE users SET username=?,name=?,email=?,active=? WHERE id=?',[$login,$name,$email?:null,$active,$id]);if($pass!=='')$this->d->exec('UPDATE users SET password_hash=? WHERE id=?',[password_hash($pass,PASSWORD_DEFAULT),$id]);$this->d->exec('DELETE FROM user_roles WHERE user_id=?',[$id]);}
        else $id=$this->d->add('INSERT INTO users(username,name,email,password_hash,active) VALUES(?,?,?,?,?)',[$login,$name,$email?:null,password_hash($pass,PASSWORD_DEFAULT),$active]);
        $this->d->exec('INSERT INTO user_roles VALUES(?,?)',[$id,$rid]);$this->d->audit($this->uid(),'acceso.usuario','subject_user_id',$id,['role'=>$role,'active'=>$active]);return ['id'=>$id];});}
}
