<?php
namespace Kronos\Services;
use Kronos\Repositories\Db;
use Kronos\Support\{C,Money};
use Kronos\Middleware\Auth;
final class Desk {
    public const STATES=['borrador','recibido','diagnostico','presupuesto_enviado','espera_aprobacion','espera_repuesto','en_reparacion','listo_entrega','entregado','cancelado'];
    public const NEXT=['recibido'=>['diagnostico'],'diagnostico'=>['espera_repuesto','en_reparacion'],'presupuesto_enviado'=>['espera_aprobacion','diagnostico','espera_repuesto','en_reparacion'],'espera_aprobacion'=>['diagnostico','espera_repuesto','en_reparacion'],'espera_repuesto'=>['diagnostico','en_reparacion'],'en_reparacion'=>['diagnostico','espera_repuesto','listo_entrega'],'listo_entrega'=>['en_reparacion','entregado']];
    public function __construct(private Db $d,private array $u){}
    private function uid():int{return (int)$this->u['id'];}
    private function tech():bool{return $this->u['role']==='tecnico';}
    public function technicians():array{return $this->d->all("SELECT DISTINCT u.id,u.name FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE u.active=1 AND r.code IN ('tecnico','admin') ORDER BY u.name");}
    public function technician(mixed $v):?int{$id=C::idOrNull($v);if($id)C::check(in_array($id,array_map('intval',array_column($this->technicians(),'id')),true),'Técnico no habilitado.');return $id;}
    public static function rut(string $s):?string{
        $s=strtoupper(str_replace(['.','-',' '],'',$s));if(!preg_match('/^([1-9][0-9]{5,7})([0-9K])$/D',$s,$m))return null;
        $sum=0;$factor=2;foreach(str_split(strrev($m[1])) as $n){$sum+=(int)$n*$factor;$factor=$factor===7?2:$factor+1;}$r=11-$sum%11;$dv=$r===11?'0':($r===10?'K':(string)$r);return $m[2]===$dv?$m[1].'-'.$dv:null;
    }
    public function customers(string $q):array{
        $p='%'.mb_substr($q,0,100).'%';return $this->d->all('SELECT c.*,(SELECT COUNT(*) FROM devices d WHERE d.customer_id=c.id) AS device_count FROM customers c WHERE c.name LIKE ? OR c.document LIKE ? OR c.phone LIKE ? OR c.email LIKE ? ORDER BY c.name LIMIT 200',[$p,$p,$p,$p]);
    }
    public function customer(int $id):array{
        $c=C::exists($this->d->one('SELECT * FROM customers WHERE id=?',[$id]));$c['devices']=$this->devices($id);
        $c['orders']=$this->orders(['customer_id'=>$id])['items'];
        $c['projects']=$this->d->all('SELECT id,title,state FROM pc_projects WHERE customer_id=? ORDER BY id DESC LIMIT 40',[$id]);
        $c['sales']=$this->d->all('SELECT id,total,created_at,voided_at FROM sales WHERE customer_id=? ORDER BY id DESC LIMIT 40',[$id]);return $c;
    }
    public function saveCustomer(array $v,?int $id=null):array{
        return $this->d->tx(function()use($v,$id){
            $name=C::txt($v['name']??null,140,true,'nombre');$doc=C::txt($v['document']??null,40);$type=$doc!==''?C::pick($v['document_type']??'otro',['rut','pasaporte','otro']):null;
            if($type==='rut'){$doc=self::rut($doc);C::check($doc!==null,'El RUT no es válido.',422,'rut','document');}else $doc=$doc?mb_strtoupper($doc):null;
            $phone=C::txt($v['phone']??null,32);$phone=preg_replace('/[\s().-]/','',$phone);if($phone!=='' && preg_match('/^\d{9}$/D',$phone))$phone='+56'.$phone; C::check($phone===''||preg_match('/^\+?[0-9]{6,18}$/D',$phone)===1,'El teléfono no tiene un formato válido.');
            $email=mb_strtolower(C::txt($v['email']??null,150));C::check($email===''||filter_var($email,FILTER_VALIDATE_EMAIL)!==false,'El correo no es válido.');
            $data=[$name,$type,$doc,$phone,$email,C::txt($v['address']??null,180),C::txt($v['district']??null,100),C::txt($v['notes']??null,2500)];
            $dups=$this->d->all("SELECT id,name FROM customers WHERE id<>? AND (name=? OR (phone<>'' AND phone=?) OR (email<>'' AND email=?)) LIMIT 5",[$id??0,$name,$phone,$email]);
            if($dups&&!($v['allow_possible_duplicate']??false))return ['duplicates'=>$dups,'needs_review'=>true];
            if($id){C::exists($this->d->one('SELECT id FROM customers WHERE id=? FOR UPDATE',[$id]));$this->d->exec('UPDATE customers SET name=?,document_type=?,document=?,phone=?,email=?,address=?,district=?,notes=? WHERE id=?',[...$data,$id]);}
            else $id=$this->d->add('INSERT INTO customers(name,document_type,document,phone,email,address,district,notes) VALUES(?,?,?,?,?,?,?,?)',$data);
            $this->d->audit($this->uid(),'cliente.guardado','customer_id',$id);return $this->d->one('SELECT * FROM customers WHERE id=?',[$id]);
        });
    }
    public function devices(int $customer):array{return $this->d->all('SELECT * FROM devices WHERE customer_id=? ORDER BY id DESC',[$customer]);}
    public function saveDevice(array $v,?int $id=null):array{
        return $this->d->tx(function()use($v,$id){
            $customer=C::id($v['customer_id']??null);C::exists($this->d->one('SELECT id FROM customers WHERE id=?',[$customer]));
            $vals=[C::txt($v['type']??null,70,true,'tipo de equipo'),C::txt($v['brand']??null,70),C::txt($v['model']??null,85),C::txt($v['serial']??null,100),C::txt($v['os']??null,70),C::txt($v['notes']??null,1700)];
            if($id){C::exists($this->d->one('SELECT id FROM devices WHERE id=? AND customer_id=? FOR UPDATE',[$id,$customer]));$this->d->exec('UPDATE devices SET type=?,brand=?,model=?,serial=?,os=?,notes=? WHERE id=?',[...$vals,$id]);}
            else $id=$this->d->add('INSERT INTO devices(customer_id,type,brand,model,serial,os,notes) VALUES(?,?,?,?,?,?,?)',[$customer,...$vals]);
            $this->d->audit($this->uid(),'equipo.guardado','customer_id',$customer,['device_id'=>$id]);return $this->d->one('SELECT * FROM devices WHERE id=?',[$id]);
        });
    }
    private const SELECT="SELECT o.*, c.name AS customer,c.phone,c.email, d.type AS device_type,d.brand,d.model,d.serial,u.name AS technician,
        r.id AS receivable_id,(SELECT amount FROM receivable_terms WHERE receivable_id=r.id ORDER BY id DESC LIMIT 1) AS budget,
        (SELECT kind FROM receivable_terms WHERE receivable_id=r.id ORDER BY id DESC LIMIT 1) AS term_kind,
        COALESCE((SELECT SUM(IF(p.kind='capture',p.amount,-p.amount)) FROM payment_allocations a JOIN payments p ON p.id=a.payment_id WHERE a.receivable_id=r.id),0) AS paid
        FROM service_orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN devices d ON d.id=o.device_id LEFT JOIN users u ON u.id=o.technician_id LEFT JOIN receivables r ON r.order_id=o.id";
    private function shape(array $o):array{
        $o['number']=$o['state']==='borrador'?'BOR-'.$o['id']:C::number('OT',$o['id']);$o['device']=trim(($o['brand']??'').' '.($o['model']??''))?:($o['device_type']??'');
        $balance=Money::fromDb($o['budget'])-Money::fromDb($o['paid']);$o['balance']=$o['budget']!==null?Money::decimal(max($balance,0)):null;
        $o['credit']=$o['budget']!==null?Money::decimal(max(-$balance,0)):null;
        if($this->tech())foreach(['phone','email','receivable_id','budget','term_kind','paid','balance','preliminary','credit','created_by'] as $k)unset($o[$k]);
        return $o;
    }
    public function orders(array $q,bool $export=false):array{
        $a=[];$w=[];
        if($this->tech()){$w[]="o.technician_id=? AND o.state<>'borrador'";$a[]=$this->uid();}
        elseif($this->u['role']!=='admin'){$w[]="(o.state<>'borrador' OR o.created_by=?)";$a[]=$this->uid();}
        if(($s=$q['state']??'')!==''){if($s==='open')$w[]="o.state NOT IN ('borrador','entregado','cancelado')";else {$w[]='o.state=?';$a[]=C::pick($s,self::STATES);}}
        if(($v=$q['priority']??'')!==''){$w[]='o.priority=?';$a[]=C::pick($v,['alta','media','baja']);}
        foreach(['technician_id','customer_id'] as $f)if(($v=$q[$f]??'')!==''){$w[]="o.$f=?";$a[]=C::id($v);}
        foreach(['from'=>'>=','to'=>'<'] as $f=>$op)if(($v=$q[$f]??'')!==''){$day=new \DateTimeImmutable(C::date($v),C::tz());$w[]="o.received_at $op ?";$a[]=C::utc($f==='to'?$day->modify('+1 day'):$day);}
        if(($str=trim(C::txt($q['q']??'',100)))!==''){$m='%'.$str.'%';$w[]="(c.name LIKE ? OR CONCAT_WS(' ',d.type,d.brand,d.model) LIKE ? OR d.serial LIKE ? OR CONCAT('OT-',LPAD(o.id,GREATEST(4,LENGTH(o.id)),'0')) LIKE ? OR CAST(o.id AS CHAR)=?)";array_push($a,$m,$m,$m,$m,$str);}
        $filter=$w?' WHERE '.implode(' AND ',$w):'';$size=$export?5000:min(100,max(1,(int)($q['per_page']??15)));$page=max(1,min(10000,(int)($q['page']??1)));$offset=($page-1)*$size;
        $count=(int)$this->d->val('SELECT COUNT(*) FROM service_orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN devices d ON d.id=o.device_id'.$filter,$a);
        return ['items'=>array_map($this->shape(...),$this->d->all(self::SELECT.$filter." ORDER BY COALESCE(o.received_at,o.created_at) DESC,o.id DESC LIMIT $size OFFSET $offset",$a)),'total'=>$count,'page'=>$page,'pages'=>(int)max(1,ceil($count/$size))];
    }
    public function order(int $id,bool $lock=false):array{
        $r=C::exists($this->d->one('SELECT * FROM service_orders WHERE id=?'.($lock?' FOR UPDATE':''),[$id]));
        C::exists((($this->tech()&&((int)$r['technician_id']!==$this->uid()||$r['state']==='borrador'))||($r['state']==='borrador'&&$this->u['role']!=='admin'&&(int)$r['created_by']!==$this->uid()))?null:$r);return $r;
    }
    public function detail(int $id):array{
        $base=$this->order($id);$o=$this->shape($this->d->one(self::SELECT.' WHERE o.id=?',[$id]));
        $o['accessories']=array_column($this->d->all('SELECT label FROM service_order_accessories WHERE order_id=? ORDER BY id',[$id]),'label');
        $o['tasks']=$this->d->all('SELECT id,label,progress FROM service_order_tasks WHERE order_id=? ORDER BY id',[$id]);
        $o['notes']=$this->d->all('SELECT n.id,n.note,n.created_at,u.name FROM service_order_notes n JOIN users u ON u.id=n.actor_id WHERE order_id=? ORDER BY n.id DESC',[$id]);
        $o['photos']=$this->d->all('SELECT id,created_at FROM service_order_photos WHERE order_id=? ORDER BY id',[$id]);
        $o['history']=$this->d->all('SELECT h.*,u.name FROM service_order_status_history h JOIN users u ON u.id=h.actor_id WHERE order_id=? ORDER BY h.id DESC',[$id]);
        $o['reservations']=(new Inventory($this->d,$this->u))->reservations('order',$id);
        $o['quotes']=$this->d->all($this->tech()?'SELECT id,state,revision FROM quotes WHERE order_id=? ORDER BY id DESC':'SELECT id,state,revision,total,valid_until FROM quotes WHERE order_id=? ORDER BY id DESC',[$id]);
        if(!$this->tech()&&$o['receivable_id'])$o['payments']=(new Commerce($this->d,$this->u))->payments((int)$o['receivable_id']);
        $o['next']=self::NEXT[$base['state']]??[];if($this->u['role']==='admin'&&$o['next'])$o['next'][]='cancelado';
        return $o;
    }
    private function requireVersion(array $o,array $v):void{C::check(isset($v['version']),'Falta la versión del borrador.',428,'version');C::check((int)$v['version']===(int)$o['version'],'Otra persona guardó cambios. Recargá la orden.',412,'version');}
    public function draft(array $v,?int $id=null):array{
        return $this->d->tx(function()use($v,$id){
            $old=$id?$this->order($id,true):null;
            if($old){C::check($old['state']==='borrador','La orden ya fue confirmada.',409,'state');$this->requireVersion($old,$v);}
            $c=C::idOrNull($v['customer_id']??null);$dev=C::idOrNull($v['device_id']??null);if($c)C::exists($this->d->one('SELECT id FROM customers WHERE id=?',[$c]));
            if($dev)C::check($c && $this->d->val('SELECT 1 FROM devices WHERE id=? AND customer_id=?',[$dev,$c]),'Seleccioná un equipo de ese cliente.');
            $p=$v['preliminary']??'';$p=$p===''||$p===null?null:Money::decimal(Money::parse($p));
            $values=[$c,$dev,$this->technician($v['technician_id']??null),C::pick($v['priority']??'media',['alta','media','baja']),C::txt($v['fault']??null,3000),C::txt($v['diagnosis']??null,3000),C::txt($v['condition_notes']??null,2800),$p,C::date($v['due_date']??null)];
            if($id)$this->d->exec('UPDATE service_orders SET customer_id=?,device_id=?,technician_id=?,priority=?,fault=?,diagnosis=?,condition_notes=?,preliminary=?,due_date=?,version=version+1 WHERE id=?',[...$values,$id]);
            else $id=$this->d->add('INSERT INTO service_orders(customer_id,device_id,technician_id,priority,fault,diagnosis,condition_notes,preliminary,due_date,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',[...$values,$this->uid()]);
            if(array_key_exists('accessories',$v)){
                C::check(is_array($v['accessories'])&&count($v['accessories'])<=24,'Accesorios no válidos.');
                $this->d->exec('DELETE FROM service_order_accessories WHERE order_id=?',[$id]);
                foreach($v['accessories'] as $label){$label=C::txt($label,160);if($label!=='')$this->d->exec('INSERT INTO service_order_accessories(order_id,label) VALUES(?,?)',[$id,$label]);}
            }
            $ret=$this->order($id);return ['id'=>$id,'version'=>$ret['version'],'state'=>'borrador'];
        });
    }
    public function confirm(int $id,array $v):array{
        return $this->d->tx(function()use($id,$v){
            $o=$this->order($id,true);C::check($o['state']==='borrador','La orden ya fue confirmada.',409,'state');$this->requireVersion($o,$v);
            C::check($o['customer_id']&&$o['device_id']&&trim($o['fault']??'')!=='','Completá cliente, equipo y falla reportada.');
            foreach(['diagnosis_authorized','accessories_verified','policy_accepted'] as $k)C::check(($v[$k]??false)===true,'Confirmá las conformidades de recepción.');
            $signature=C::txt($v['signature_name']??null,140);$signaturePng=null;
            if(($svg=$v['signature_png']??'')!==''){
                C::check(is_string($svg)&&strlen($svg)<=120000&&str_starts_with($svg,'data:image/png;base64,'),'Firma no admitida.');$blob=base64_decode(substr($svg,22),true);$size=$blob===false?false:@getimagesizefromstring($blob);
                C::check($size && $size[2]===IMAGETYPE_PNG && $size[0]<=850 && $size[1]<=300,'Imagen de firma inválida.');$img=@imagecreatefromstring($blob);C::check($img!==false,'No se pudo leer la firma.');
                ob_start();imagepng($img);$signaturePng=ob_get_clean();
            }
            $c=new Commerce($this->d,$this->u);$session=null;$advance=$v['advance']??null;
            if($advance){Auth::need($this->u,'cash');C::check($o['preliminary']!==null&&($v['advance_accepted']??false)===true,'El anticipo requiere un estimado aceptado.');$session=$c->lockSession(C::id($advance['session_id']??null));}
            $this->d->exec("UPDATE service_orders SET state='recibido',received_at=UTC_TIMESTAMP(6),version=version+1 WHERE id=?",[$id]);
            $this->d->exec('INSERT INTO service_order_consents(order_id,policy_version,diagnosis_authorized,accessories_verified,policy_accepted,signature_name,signature_png) VALUES(?,\'1.0\',1,1,1,?,?)',[$id,$signature,$signaturePng]);
            $d=$this->shape($this->d->one(self::SELECT.' WHERE o.id=?',[$id]));
            $snapshot=array_intersect_key($d,array_flip(['id','number','customer','device','serial','fault','condition_notes','received_at','due_date']));$snapshot['accessories']=array_column($this->d->all('SELECT label FROM service_order_accessories WHERE order_id=? ORDER BY id',[$id]),'label');$snapshot['signature_name']=$signature;$snapshot['has_signature']=$signaturePng!==null;
            $this->d->exec('INSERT INTO service_order_receipts VALUES(?,?)',[$id,C::json($snapshot)]);
            $rid=$this->d->add('INSERT INTO receivables(order_id) VALUES(?)',[$id]);
            if($o['preliminary']!==null)$this->d->exec("INSERT INTO receivable_terms(receivable_id,kind,amount) VALUES(?,'estimate',?)",[$rid,$o['preliminary']]);
            if($session)$c->capture((int)$session['id'],$rid,$advance['payments']??[],Money::fromDb($o['preliminary']),false);
            $secret=C::txt($v['temporary_access']??null,100);
            if($secret!==''){$nonce=random_bytes(24);$enc=sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($secret,'order:'.$id,$nonce,C::key());$this->d->exec('INSERT INTO service_order_secrets VALUES(?,?,?,UTC_TIMESTAMP(6)+INTERVAL 7 DAY)',[$id,$enc,$nonce]);}
            $this->history($id,'borrador','recibido','Recepción confirmada');return ['id'=>$id,'number'=>C::number('OT',$id),'state'=>'recibido'];
        });
    }
    public function history(int $id,string $from,string $to,string $why):void{
        $this->d->exec('INSERT INTO service_order_status_history(order_id,actor_id,from_state,to_state,reason) VALUES(?,?,?,?,?)',[$id,$this->uid(),$from,$to,$why]);
        $this->d->audit($this->uid(),'orden.estado','order_id',$id,['from'=>$from,'to'=>$to]);
    }
    public function edit(int $id,array $v):array{
        $o=$this->order($id);if($o['state']==='borrador'){Auth::need($this->u,'orders.write');return $this->draft($v,$id);}
        return $this->d->tx(function()use($id,$v){
            $o=$this->order($id,true);$this->requireVersion($o,$v);C::check(!C::reached($o['state']),'La orden está cerrada.',409,'state');
            if(array_key_exists('diagnosis',$v)){Auth::need($this->u,'orders.tech');$this->d->exec('UPDATE service_orders SET diagnosis=?,version=version+1 WHERE id=?',[C::txt($v['diagnosis'],3000),$id]);}
            else{Auth::need($this->u,'orders.write');$this->d->exec('UPDATE service_orders SET technician_id=?,priority=?,due_date=?,version=version+1 WHERE id=?',[$this->technician($v['technician_id']??$o['technician_id']),C::pick($v['priority']??$o['priority'],['alta','media','baja']),C::date($v['due_date']??$o['due_date']),$id]);}
            $this->d->audit($this->uid(),'orden.edicion','order_id',$id,['field'=>isset($v['diagnosis'])?'diagnosis':'assignment']);return $this->detail($id);
        });
    }
    public function transition(int $id,array $v):array{
        return $this->d->tx(function()use($id,$v){
            $o=$this->order($id,true);$from=$o['state'];$to=C::pick($v['state']??null,self::STATES);$why=C::txt($v['reason']??null,500,in_array($to,['cancelado','entregado'],true),'motivo o constancia');
            C::check(in_array($to,self::NEXT[$from]??[],true)||($to==='cancelado'&&isset(self::NEXT[$from])),'Esa transición no está permitida.',409,'state');
            $c=new Commerce($this->d,$this->u);
            if($to==='cancelado'){Auth::need($this->u,'admin');$c->canClose('order',$id,true);(new Inventory($this->d,$this->u))->releaseAll('order',$id);}
            elseif($to==='entregado'){Auth::need($this->u,'orders.write');$c->canClose('order',$id);C::check(!(new Inventory($this->d,$this->u))->hasActive('order',$id),'Primero conciliá las reservas.',409,'active_reservations');}
            elseif($to==='espera_aprobacion')Auth::need($this->u,'orders.write');
            else {Auth::need($this->u,'orders.tech');if($to==='en_reparacion')C::check(trim($o['diagnosis']??'')!=='' && $c->account('order',$id)['kind']==='firm','Se requiere diagnóstico y presupuesto aprobado.',409,'approval_required');if($to==='listo_entrega')C::check(!$this->d->val('SELECT 1 FROM service_order_tasks WHERE order_id=? AND progress<100 LIMIT 1',[$id]),'Hay tareas sin completar.',409,'tasks_open');}
            $this->d->exec('UPDATE service_orders SET state=?,delivered_at=IF(?=\'entregado\',UTC_TIMESTAMP(6),delivered_at),version=version+1 WHERE id=?',[$to,$to,$id]);
            if(C::reached($to))$this->d->exec('DELETE FROM service_order_secrets WHERE order_id=?',[$id]);
            $this->history($id,$from,$to,$why);return ['id'=>$id,'state'=>$to];
        });
    }
    public function receipt(int $id):array{$this->order($id);$r=C::exists($this->d->one('SELECT snapshot FROM service_order_receipts WHERE order_id=?',[$id]),'Esta recepción aún no tiene comprobante.');return json_decode($r['snapshot'],true,512,JSON_THROW_ON_ERROR);}
    public function note(int $id,array $v):array{return $this->d->tx(function()use($id,$v){$o=$this->order($id,true);C::check($o['state']!=='borrador'&&!C::reached($o['state']),'La orden no está en curso.',409,'state');$n=$this->d->add('INSERT INTO service_order_notes(order_id,actor_id,note) VALUES(?,?,?)',[$id,$this->uid(),C::txt($v['note']??null,3800,true,'nota')]);return ['id'=>$n];});}
    public function task(int $id,array $v,?int $task=null):array{return $this->d->tx(function()use($id,$v,$task){$o=$this->order($id,true);C::check($o['state']!=='borrador'&&!C::reached($o['state']),'La orden no está en curso.',409);$p=C::int($v['progress']??0,0,100);if($task){C::exists($this->d->one('SELECT id FROM service_order_tasks WHERE id=? AND order_id=?',[$task,$id]));$this->d->exec('UPDATE service_order_tasks SET progress=? WHERE id=?',[$p,$task]);return ['id'=>$task];}return ['id'=>$this->d->add('INSERT INTO service_order_tasks(order_id,label,progress) VALUES(?,?,?)',[$id,C::txt($v['label']??null,200,true,'tarea'),$p])];});}
    public function photo(int $id,array $file):array{
        $o=$this->order($id);C::check(!C::reached($o['state']),'La orden está cerrada.',409);C::check(($file['error']??1)===UPLOAD_ERR_OK && ($file['size']??0)>0 && $file['size']<=5000000,'La foto no se pudo cargar o supera 5 MB.',422,'photo_size');
        $path=$file['tmp_name'];$types=['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp']];$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        C::check(isset($types[$mime])&&in_array(strtolower(pathinfo($file['name'],PATHINFO_EXTENSION)),$types[$mime],true),'Subí una foto JPEG, PNG o WebP válida.',422,'photo_type');
        $size=@getimagesize($path);C::check($size && $size[0]*$size[1]<=15000000,'La imagen supera el tamaño permitido.');
        $im=@imagecreatefromstring(file_get_contents($path));C::check($im!==false,'No se pudo leer la imagen.');
        $name=bin2hex(random_bytes(24)).'.jpg';$target=dirname(__DIR__,2).'/storage/uploads/'.$name;
        C::check(imagejpeg($im,$target,88),'No se pudo guardar la foto.',503);chmod($target,0600);
        try{return $this->d->tx(function()use($id,$name){$o=$this->order($id,true);C::check(!C::reached($o['state']),'La orden está cerrada.',409);C::check((int)$this->d->val('SELECT COUNT(*) FROM service_order_photos WHERE order_id=?',[$id])<24,'La orden alcanzó el máximo de 24 fotos.',409);$n=$this->d->add('INSERT INTO service_order_photos(order_id,actor_id,path) VALUES(?,?,?)',[$id,$this->uid(),$name]);$this->d->audit($this->uid(),'orden.foto','order_id',$id);return ['id'=>$n];});}catch(\Throwable $e){@unlink($target);throw $e;}
    }
    public function reveal(int $id,array $v):array{
        C::check(in_array($this->u['role'],['admin','tecnico'],true),'No tenés permiso para ver la credencial.',403);$this->order($id);
        $why=C::txt($v['reason']??null,250,true,'motivo');$hash=$this->d->val('SELECT password_hash FROM users WHERE id=?',[$this->uid()]);
        C::check(password_verify(C::txt($v['password']??null,200,true,'contraseña'),$hash),'Contraseña incorrecta.',403);
        $r=C::exists($this->d->one('SELECT encrypted,nonce FROM service_order_secrets WHERE order_id=? AND expires_at>UTC_TIMESTAMP(6)',[$id]),'No hay credencial temporal vigente.');
        $secret=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($r['encrypted'],'order:'.$id,$r['nonce'],C::key());C::check($secret!==false,'La credencial no está disponible.',503);
        $this->d->audit($this->uid(),'orden.acceso_temporal','order_id',$id);return ['secret'=>$secret];
    }
}
