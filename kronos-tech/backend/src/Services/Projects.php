<?php
namespace Kronos\Services;
use Kronos\Repositories\Db;
use Kronos\Support\{C,Money};
use Kronos\Middleware\Auth;
final class Projects {
    public const NEXT=['solicitud'=>['cotizacion'],'cotizacion'=>[],'espera_cliente'=>['cotizacion'],'aprobado'=>['en_armado','cotizacion'],'en_armado'=>['pruebas','cotizacion'],'pruebas'=>['en_armado','listo_entrega'],'listo_entrega'=>['pruebas','entregado']];
    public function __construct(private Db $d,private array $u){}
    private function uid():int{return (int)$this->u['id'];}
    private function tech():bool{return $this->u['role']==='tecnico';}
    public function one(int $id,bool $lock=false):array{
        $q=$this->tech()?' AND technician_id=?':'';$a=$this->tech()?[$id,$this->uid()]:[$id];return C::exists($this->d->one('SELECT * FROM pc_projects WHERE id=?'.$q.($lock?' FOR UPDATE':''),$a));
    }
    public function listing(string $q):array{
        $s='%'.C::txt($q,120).'%';$args=[$s,$s];$sql=$this->tech()?' AND p.technician_id=?':'';if($sql)$args[]=$this->uid();
        return $this->d->all('SELECT p.*,c.name AS customer,u.name AS technician,(SELECT COUNT(*) FROM pc_project_items i WHERE i.project_id=p.id) AS item_count FROM pc_projects p JOIN customers c ON c.id=p.customer_id LEFT JOIN users u ON u.id=p.technician_id WHERE (p.title LIKE ? OR c.name LIKE ?)'.$sql.' ORDER BY p.archived_at IS NOT NULL,p.id DESC LIMIT 150',$args);
    }
    public function create(array $v):array{return $this->d->tx(function()use($v){$c=C::id($v['customer_id']??null);C::exists($this->d->one('SELECT id FROM customers WHERE id=?',[$c]));$type=C::pick($v['type']??'nuevo',['nuevo','mejora']);$dev=C::idOrNull($v['device_id']??null);
        if($type==='mejora')C::check($dev!==null&&$this->d->val('SELECT 1 FROM devices WHERE id=? AND customer_id=?',[$dev,$c]),'Seleccioná el equipo que se va a mejorar.');else $dev=null;
        $id=$this->d->add('INSERT INTO pc_projects(customer_id,technician_id,device_id,title,type,usage_notes) VALUES(?,?,?,?,?,?)',[$c,(new Desk($this->d,$this->u))->technician($v['technician_id']??null),$dev,C::txt($v['title']??null,150,true,'nombre del proyecto'),$type,C::txt($v['usage_notes']??null,2200)]);$this->d->audit($this->uid(),'armado.creado','project_id',$id);return ['id'=>$id];});}
    public function detail(int $id):array{
        $o=$this->one($id);$o['number']=C::number('PC',$id);$p=$this->tech()?'':',COALESCE(p.price,s.price) AS price';$o['customer']=$this->d->val('SELECT name FROM customers WHERE id=?',[$o['customer_id']]);$o['technician']=$o['technician_id']?$this->d->val('SELECT name FROM users WHERE id=?',[$o['technician_id']]):null;
        $o['items']=$this->d->all('SELECT i.*,COALESCE(p.name,s.name) AS label,p.sku'.$p.' FROM pc_project_items i LEFT JOIN products p ON p.id=i.product_id LEFT JOIN service_catalog s ON s.id=i.service_id WHERE i.project_id=? ORDER BY i.id',[$id]);
        $o['updates']=$this->d->all('SELECT x.*,u.name FROM pc_project_updates x JOIN users u ON u.id=x.actor_id WHERE project_id=? ORDER BY x.id DESC',[$id]);$o['compatibility']=$this->compat($id);
        $o['reservations']=(new Inventory($this->d,$this->u))->reservations('project',$id);$o['quotes']=$this->d->all($this->tech()?'SELECT id,state,revision FROM quotes WHERE project_id=? ORDER BY id DESC':'SELECT id,state,total,revision,valid_until FROM quotes WHERE project_id=? ORDER BY id DESC',[$id]);
        if(!$this->tech()){$c=new Commerce($this->d,$this->u);$o['account']=$c->account('project',$id);$o['payments']=$o['account']['id']?$c->payments((int)$o['account']['id']):[];}
        $o['next']=self::NEXT[$o['state']]??[];if($this->u['role']==='admin'&&!C::reached($o['state']))$o['next'][]='cancelado';return $o;
    }
    public function item(int $id,array $v,?int $iid=null,bool $remove=false):array{return $this->d->tx(function()use($id,$v,$iid,$remove){$root=$this->one($id,true);C::check(in_array($root['state'],['solicitud','cotizacion'],true),'Volvé a cotización antes de modificar componentes.',409);if($iid){C::exists($this->d->one('SELECT id FROM pc_project_items WHERE id=? AND project_id=? FOR UPDATE',[$iid,$id]));C::check(!$this->d->val('SELECT 1 FROM stock_reservations WHERE project_item_id=? LIMIT 1',[$iid]),'Este componente tiene historial de reservas.',409);if($remove)$this->d->exec('DELETE FROM pc_project_items WHERE id=?',[$iid]);else $this->d->exec('UPDATE pc_project_items SET quantity=? WHERE id=?',[C::int($v['quantity']??null,1,500),$iid]);}
        else{$p=C::idOrNull($v['product_id']??null);$s=C::idOrNull($v['service_id']??null);C::check(($p===null)!==($s===null),'Elegí un producto o servicio.');$iid=$this->d->add('INSERT INTO pc_project_items(project_id,product_id,service_id,quantity) VALUES(?,?,?,?)',[$id,$p,$s,C::int($v['quantity']??null,1,500)]);}
        $this->d->exec("UPDATE quotes SET state='superseded' WHERE project_id=? AND state='draft'",[$id]);$this->d->audit($this->uid(),'armado.componentes','project_id',$id);return ['id'=>$iid];});}
    public function update(int $id,array $v):array{return $this->d->tx(function()use($id,$v){$o=$this->one($id,true);C::check(!C::reached($o['state']),'El armado ya cerró.',409);$n=$this->d->add('INSERT INTO pc_project_updates(project_id,actor_id,kind,note) VALUES(?,?,?,?)',[$id,$this->uid(),C::pick($v['kind']??'avance',['avance','prueba']),C::txt($v['note']??null,3500,true,'avance')]);return ['id'=>$n];});}
    public function change(int $id,array $v):array{return $this->d->tx(function()use($id,$v){$o=$this->one($id,true);$t=C::pick($v['state']??null,['cotizacion','en_armado','pruebas','listo_entrega','entregado','cancelado']);$why=C::txt($v['reason']??null,500,in_array($t,['entregado','cancelado'],true),'motivo');
        C::check(in_array($t,self::NEXT[$o['state']]??[],true)||($t==='cancelado'&&!C::reached($o['state'])),'Transición no permitida.',409,'state');$c=new Commerce($this->d,$this->u);$inv=new Inventory($this->d,$this->u);
        if($t==='cancelado'){Auth::need($this->u,'admin');$c->canClose('project',$id,true);$inv->releaseAll('project',$id);}
        elseif($t==='entregado'){Auth::need($this->u,'projects.write');$c->canClose('project',$id);C::check(!$inv->hasActive('project',$id),'Conciliá las reservas del proyecto.',409);}
        elseif($t==='cotizacion'){Auth::need($this->u,'projects.write');C::check(!$inv->hasActive('project',$id),'Liberá las reservas antes de cambiar el alcance.',409);$this->d->exec("UPDATE quotes SET state='superseded' WHERE project_id=? AND state='sent'",[$id]);}
        else{Auth::need($this->u,'projects.tech');C::check($this->compat($id)['state']!=='incompatible','Revisá la incompatibilidad antes de avanzar.',409);if($t==='listo_entrega')C::check((bool)$this->d->val("SELECT 1 FROM pc_project_updates WHERE project_id=? AND kind='prueba' LIMIT 1",[$id]),'Registrá una prueba antes de marcar listo.',409);}
        $this->state($id,$o['state'],$t,$why);return ['id'=>$id,'state'=>$t];});}
    public function state(int $id,string $from,string $to,string $why=''):void{$this->d->exec('UPDATE pc_projects SET state=? WHERE id=?',[$to,$id]);$this->d->exec("INSERT INTO pc_project_updates(project_id,actor_id,kind,note) VALUES(?,?,'estado',?)",[$id,$this->uid(),$from.' → '.$to.($why!==''?' · '.$why:'')]);$this->d->audit($this->uid(),'armado.estado','project_id',$id,['from'=>$from,'to'=>$to]);}
    public function duplicate(int $id):array{return $this->d->tx(function()use($id){$o=$this->one($id,true);$n=$this->d->add('INSERT INTO pc_projects(customer_id,technician_id,device_id,title,type,usage_notes) VALUES(?,?,?,?,?,?)',[$o['customer_id'],$o['technician_id'],$o['device_id'],mb_substr($o['title'],0,140).' · copia',$o['type'],$o['usage_notes']]);$this->d->exec('INSERT INTO pc_project_items(project_id,product_id,service_id,quantity) SELECT ?,product_id,service_id,quantity FROM pc_project_items WHERE project_id=?',[$n,$id]);$this->d->audit($this->uid(),'armado.duplicado','project_id',$n,['source'=>$id]);return ['id'=>$n];});}
    public function archive(int $id):array{return $this->d->tx(function()use($id){$o=$this->one($id,true);C::check(C::reached($o['state']),'Solo se puede archivar un proyecto finalizado.',409);$this->d->exec('UPDATE pc_projects SET archived_at=UTC_TIMESTAMP(6) WHERE id=? AND archived_at IS NULL',[$id]);$this->d->audit($this->uid(),'armado.archivado','project_id',$id);return ['id'=>$id];});}
    public function firmware(int $id,array $v):array{return $this->d->tx(function()use($id,$v){$p=$this->one($id,true);C::check(!C::reached($p['state']),'El armado ya cerró.',409);
        $revision=C::txt($v['board_revision']??null,40);$bios=C::txt($v['bios_version']??null,40);C::check(($revision==='')===($bios===''),'Completá revisión y versión BIOS, o limpiá ambas.');
        $this->d->exec('UPDATE pc_projects SET board_revision=?,bios_version=? WHERE id=?',[$revision?:null,$bios?:null,$id]);$this->d->audit($this->uid(),'armado.bios','project_id',$id);return ['id'=>$id];});}
    public function compat(int $id):array{
        $rows=$this->d->all('SELECT i.quantity,s.* FROM pc_project_items i LEFT JOIN product_technical_specs s ON s.product_id=i.product_id WHERE i.project_id=? AND i.product_id IS NOT NULL',[$id]);$m=[];$unknown=false;$roles=['cpu','motherboard','ram','gpu','case','psu'];
        foreach($rows as $v){if(!$v['component_type'])$unknown=true;else $m[$v['component_type']]=$v;} $cpu=$m['cpu']??[];$mb=$m['motherboard']??[];$ram=$m['ram']??[];$gpu=$m['gpu']??[];$case=$m['case']??[];$psu=$m['psu']??[];$checks=[];
        $add=function($name,$value)use(&$checks){$checks[]=['label'=>$name,'state'=>$value===null?'verify':($value?'compatible':'incompatible')];};
        $add('Socket CPU / placa',isset($cpu['socket'],$mb['socket'])?strcasecmp($cpu['socket'],$mb['socket'])===0:null);
        $add('Generación de memoria',isset($mb['ram_type'],$ram['ram_type'])?strcasecmp($mb['ram_type'],$ram['ram_type'])===0:null);
        $add('Capacidad y ranuras RAM',isset($mb['slots'],$mb['max_ram_gb'],$ram['modules'],$ram['module_gb'])?$ram['quantity']*$ram['modules']<=$mb['slots']&&$ram['quantity']*$ram['modules']*$ram['module_gb']<=$mb['max_ram_gb']:null);
        $formats=json_decode($case['supported_formats']??'[]',true)?:[];$add('Formato de placa y gabinete',isset($mb['form_factor'])&&$formats?in_array(strtoupper($mb['form_factor']),array_map('strtoupper',$formats),true):null);
        $add('Espacio para GPU',isset($gpu['length_mm'],$case['clearance_mm'])?$gpu['length_mm']<=$case['clearance_mm']:null);
        $power=0;$powerKnown=isset($psu['power_w'],$cpu['power_w'],$gpu['power_w'],$mb['power_w'])&&!$unknown;
        foreach($rows as $r)if(($r['component_type']??'')!=='psu'&&($r['component_type']??'')!=='case'){if($r['power_w']===null)$powerKnown=false;else $power+=(int)$r['power_w']*(int)$r['quantity'];}
        $add('Fuente, potencia estimada y margen 25 %',$powerKnown?$power*5<=(int)$psu['power_w']*4:null);
        $conn=fn($v)=>json_decode($v['connectors']??'[]',true)?:[];$need=$conn($mb);foreach($conn($gpu) as $k=>$q)$need[$k]=($need[$k]??0)+$q;$has=$conn($psu);$fits=null;
        if($conn($mb)&&$conn($gpu)&&$has){$fits=true;foreach($need as $k=>$n)if(($has[$k]??0)<$n)$fits=false;}$add('Conectores de fuente, placa y GPU',$fits);
        $f=$this->d->one('SELECT board_revision,bios_version FROM pc_projects WHERE id=?',[$id]);$bios=null;
        if(isset($cpu['product_id'],$mb['product_id'],$f['board_revision'],$f['bios_version']))$bios=$this->d->one('SELECT compatible,source FROM product_bios_support WHERE motherboard_id=? AND cpu_id=? AND board_revision=? AND bios_version=?',[$mb['product_id'],$cpu['product_id'],$f['board_revision'],$f['bios_version']]);
        $add('Compatibilidad de BIOS',$bios?(bool)$bios['compatible']:null); if($bios)$checks[count($checks)-1]['source']=$bios['source'];
        if($unknown)$add('Fichas de cada componente',null);$states=array_column($checks,'state');return ['state'=>in_array('incompatible',$states,true)?'incompatible':(in_array('verify',$states,true)?'verify':'compatible'),'checks'=>$checks];
    }
}
