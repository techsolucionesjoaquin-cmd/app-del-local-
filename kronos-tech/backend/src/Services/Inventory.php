<?php
namespace Kronos\Services;
use Kronos\Repositories\Db;
use Kronos\Support\{C,Money};
final class Inventory {
    public function __construct(private Db $d,private array $u){}
    private function uid():int{return (int)$this->u['id'];}
    public function list(array $v):array{
        $a=[];$w=[];$q='%'.C::txt($v['q']??'',120).'%';$w[]='(p.name LIKE ? OR p.sku LIKE ?)';$a=[$q,$q];
        if(($cat=$v['category']??'')!==''){$w[]='p.category_id=?';$a[]=C::id($cat);}if(!empty($v['only_active']))$w[]='p.active=1';
        if(!empty($v['critical']))$w[]='p.active=1 AND COALESCE(b.physical-b.reserved,0)<=p.minimum';
        $price=$this->u['role']==='tecnico'?'':',p.price,p.tax_bps';$cost=$this->u['role']==='admin'?',p.cost':'';
        return $this->d->all("SELECT p.id,p.sku,p.name,p.description,p.category_id,p.supplier_id,p.location_id,p.minimum,p.active,c.name AS category,l.name AS location, COALESCE(b.physical,0) AS physical,COALESCE(b.reserved,0) AS reserved,COALESCE(b.physical-b.reserved,0) AS available $price $cost FROM products p LEFT JOIN categories c ON c.id=p.category_id JOIN locations l ON l.id=p.location_id LEFT JOIN inventory_balances b ON b.product_id=p.id AND b.location_id=p.location_id WHERE ".implode(' AND ',$w).' ORDER BY p.name LIMIT 600',$a);
    }
    public function catalog():array{
        $price=$this->u['role']==='tecnico'?'':',price,tax_bps';
        return ['categories'=>$this->d->all('SELECT id,name FROM categories ORDER BY name'),'locations'=>$this->d->all('SELECT id,name FROM locations ORDER BY id'),'suppliers'=>$this->d->all('SELECT id,name FROM suppliers ORDER BY name'),'services'=>$this->d->all('SELECT id,code,name'.$price.' FROM service_catalog WHERE active=1 ORDER BY name')];
    }
    public function configs():array{
        $result=[];foreach(['categories','locations','suppliers','service_catalog'] as $table)$result[$table]=$this->d->all("SELECT * FROM $table ORDER BY name");return $result;
    }
    public function saveConfig(string $table,array $v,?int $id):array{
        C::check(in_array($table,['categories','suppliers','locations','service_catalog'],true),'Catálogo no válido.');
        return $this->d->tx(function()use($table,$v,$id){$c=['name'=>C::txt($v['name']??null,90,true,'nombre')];
            if(in_array($table,['locations','service_catalog'],true))$c['code']=strtoupper(C::txt($v['code']??null,30,true,'código'));
            if($table==='service_catalog')$c+=['price'=>Money::decimal(Money::parse($v['price']??null)),'cost'=>Money::decimal(Money::parse($v['cost']??'0')),'tax_bps'=>C::int($v['tax_bps']??C::tax(),0,10000),'active'=>($v['active']??true)?1:0];
            $fields=array_keys($c);if($id){C::exists($this->d->one("SELECT id FROM $table WHERE id=? FOR UPDATE",[$id]));$assign=implode(',',array_map(fn($k)=>"$k=?",$fields));$this->d->exec("UPDATE $table SET $assign WHERE id=?",[...array_values($c),$id]);}
            else{$names=implode(',',$fields);$mark=implode(',',array_fill(0,count($fields),'?'));$id=$this->d->add("INSERT INTO $table($names) VALUES($mark)",array_values($c));}
            $this->d->exec("INSERT INTO audit_logs(actor_id,action,detail) VALUES(?,'catalogo.guardado',?)",[$this->uid(),C::json(['catalog'=>$table,'record'=>$id])]);return ['id'=>$id];});
    }
    public function changePurchase(int $id,array $v):array{return $this->d->tx(function()use($id,$v){$r=C::exists($this->d->one('SELECT state FROM purchase_requests WHERE id=? FOR UPDATE',[$id]));$state=C::pick($v['state']??null,['aprobada','cerrada']);C::check($r['state']!=='cerrada'&&($state==='cerrada'||$r['state']==='pendiente'),'No se puede repetir o reabrir una solicitud.',409);$this->d->exec('UPDATE purchase_requests SET state=? WHERE id=?',[$state,$id]);$p=(int)$this->d->val('SELECT MIN(product_id) FROM purchase_request_items WHERE request_id=?',[$id]);$this->d->audit($this->uid(),'compra.estado','product_id',$p,['request_id'=>$id,'state'=>$state]);return ['id'=>$id];});}
    public function product(int $id):array{
        $p=C::exists($this->d->one('SELECT * FROM products WHERE id=?',[$id]));if($this->u['role']!=='admin')unset($p['cost']);if($this->u['role']==='tecnico')unset($p['price'],$p['tax_bps']);
        $p['spec']=$this->d->one('SELECT * FROM product_technical_specs WHERE product_id=?',[$id]);return $p;
    }
    public function save(array $v,?int $id=null):array{
        return $this->d->tx(function()use($id,$v){
            $sku=mb_strtoupper(C::txt($v['sku']??null,60,true,'SKU'));$location=C::id($v['location_id']??1);C::exists($this->d->one('SELECT id FROM locations WHERE id=?',[$location]));
            $data=[$sku,C::txt($v['name']??null,150,true,'nombre'),C::txt($v['description']??null,2000),C::idOrNull($v['category_id']??null),C::idOrNull($v['supplier_id']??null),$location,Money::decimal(Money::parse($v['cost']??'0')),Money::decimal(Money::parse($v['price']??null)),C::int($v['tax_bps']??C::tax(),0,10000),C::int($v['minimum']??0,0,100000),($v['active']??true)?1:0];
            if($id){$p=C::exists($this->d->one('SELECT * FROM products WHERE id=? FOR UPDATE',[$id]));$b=$this->position($id,(int)$p['location_id']);C::check(($location===(int)$p['location_id']||(int)$b['physical']===0)&&($data[10]||(int)$b['reserved']===0),'Conciliá existencias o reservas antes de cambiar ubicación o estado.',409);$this->d->exec('UPDATE products SET sku=?,name=?,description=?,category_id=?,supplier_id=?,location_id=?,cost=?,price=?,tax_bps=?,minimum=?,active=? WHERE id=?',[...$data,$id]);}
            else $id=$this->d->add('INSERT INTO products(sku,name,description,category_id,supplier_id,location_id,cost,price,tax_bps,minimum,active) VALUES(?,?,?,?,?,?,?,?,?,?,?)',$data);
            $this->d->exec('INSERT INTO inventory_balances(product_id,location_id) VALUES(?,?) ON DUPLICATE KEY UPDATE physical=physical',[$id,$location]);$this->d->audit($this->uid(),'inventario.producto','product_id',$id);return ['id'=>$id];
        });
    }
    public function spec(int $id,array $v):array{
        return $this->d->tx(function()use($id,$v){C::exists($this->d->one('SELECT id FROM products WHERE id=? FOR UPDATE',[$id]));
            $t=C::pick($v['component_type']??null,['cpu','motherboard','ram','gpu','case','psu','other']);$f=[];
            foreach(['socket','ram_type','form_factor','source','bios_status'] as $k)$f[$k]=C::txt($v[$k]??null,$k==='source'?220:25)?:null;
            foreach(['slots','max_ram_gb','module_gb','modules','length_mm','clearance_mm','power_w'] as $k)$f[$k]=($v[$k]??'')===''?null:C::int($v[$k],1,100000);
            $f['supported_formats']=C::json(array_values(array_filter(array_map('trim',explode(',',C::txt($v['supported_formats']??null,180))))));
            $conn=[];foreach(explode(',',C::txt($v['connectors']??null,200)) as $c){if(trim($c)==='')continue;$p=explode(':',trim($c),2);C::check(count($p)===2,'Conectores con formato tipo:cantidad.');$conn[mb_strtoupper(trim($p[0]))]=C::int(trim($p[1]),1,100);}$f['connectors']=C::json($conn);
            $keys=array_keys($f);$names=implode(',',$keys);$set=implode(',',array_map(fn($k)=>"$k=VALUES($k)",$keys));$ph=implode(',',array_fill(0,count($keys)+2,'?'));
            $this->d->exec("INSERT INTO product_technical_specs(product_id,component_type,$names) VALUES($ph) ON DUPLICATE KEY UPDATE component_type=VALUES(component_type),$set",[$id,$t,...array_values($f)]);$this->d->audit($this->uid(),'inventario.ficha','product_id',$id);return ['id'=>$id];
        });
    }
    public function biosSupport(int $board,array $v):array{
        return $this->d->tx(function()use($board,$v){$cpu=C::id($v['cpu_id']??null);C::check($this->d->val('SELECT component_type FROM product_technical_specs WHERE product_id=?',[$board])==='motherboard' && $this->d->val('SELECT component_type FROM product_technical_specs WHERE product_id=?',[$cpu])==='cpu','Seleccioná una placa y un CPU con sus fichas técnicas.');
        $rev=C::txt($v['board_revision']??null,40,true,'revisión de placa');$bios=C::txt($v['bios_version']??null,40,true,'versión BIOS');$link=C::txt($v['source']??null,240,true,'fuente oficial');C::check(filter_var($link,FILTER_VALIDATE_URL)!==false && parse_url($link,PHP_URL_SCHEME)==='https','Indicá una URL HTTPS de la fuente verificable.');$status=C::pick($v['compatibility']??null,['yes','no']);
        $this->d->exec('INSERT INTO product_bios_support VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE compatible=VALUES(compatible),source=VALUES(source)',[$board,$cpu,$rev,$bios,$status==='yes'?1:0,$link]);$this->d->audit($this->uid(),'inventario.bios','product_id',$board,['cpu_id'=>$cpu]);return ['id'=>$board];});
    }
    public function position(int $p,int $l):array{return C::exists($this->d->one('SELECT * FROM inventory_balances WHERE product_id=? AND location_id=? FOR UPDATE',[$p,$l]),'No existe posición para este producto.');}
    public function delta(int $p,int $l,int $q,int $reserve=0):void{
        $s=$this->position($p,$l);$on=(int)$s['physical']+$q;$held=(int)$s['reserved']+$reserve;
        C::check($on>=$held&&$held>=0&&$on<=10000000,'No hay stock disponible para esa cantidad.',409,'insufficient_stock');
        $this->d->exec('UPDATE inventory_balances SET physical=?,reserved=? WHERE product_id=? AND location_id=?',[$on,$held,$p,$l]);
    }
    public function append(int $p,int $l,string $kind,int $delta,string $why,?int $sale=null,?int $event=null,?int $reverse=null,?int $returned=null):int{
        return $this->d->add('INSERT INTO stock_movements(product_id,location_id,actor_id,kind,delta,reason,sale_item_id,reservation_event_id,reversal_of_id,returned_from_id) VALUES(?,?,?,?,?,?,?,?,?,?)',[$p,$l,$this->uid(),$kind,$delta,$why,$sale,$event,$reverse,$returned]);
    }
    public function adjust(array $v):array{
        return $this->d->tx(function()use($v){$id=C::id($v['product_id']??null);$p=C::exists($this->d->one('SELECT * FROM products WHERE id=? FOR UPDATE',[$id]));$loc=(int)$p['location_id'];$this->position($id,$loc);
            $kind=C::pick($v['kind']??null,['entrada','ajuste','devolucion']);$qty=C::int($v['quantity']??null,1,100000);$why=C::txt($v['reason']??null,255,true,'motivo');$orig=null;
            if($kind==='ajuste'&&($v['direction']??'in')==='out')$qty=-$qty;
            if($kind==='devolucion'){$orig=C::id($v['returned_from_id']??null);$m=C::exists($this->d->one("SELECT * FROM stock_movements WHERE id=? AND product_id=? AND location_id=? AND kind='consumo' FOR UPDATE",[$orig,$id,$loc]));$returned=(int)$this->d->val('SELECT COALESCE(SUM(delta),0) FROM stock_movements WHERE returned_from_id=?',[$orig]);C::check($qty+$returned<=-(int)$m['delta'],'La devolución excede el consumo original.',409);}
            $this->delta($id,$loc,$qty);$n=$this->append($id,$loc,$kind,$qty,$why,null,null,null,$orig);$this->d->audit($this->uid(),'inventario.movimiento','product_id',$id,['delta'=>$qty,'kind'=>$kind]);return ['id'=>$n];});
    }
    public function movements(?int $id):array{
        return $this->d->all('SELECT m.id,m.kind,m.delta,m.reason,m.created_at,p.name,p.sku,u.name AS actor,l.name AS location FROM stock_movements m JOIN products p ON p.id=m.product_id JOIN users u ON u.id=m.actor_id JOIN locations l ON l.id=m.location_id'.($id?' WHERE m.product_id=?':'').' ORDER BY m.id DESC LIMIT 120',$id?[$id]:[]);
    }
    private function parts(string $kind):array{return $kind==='order'?['service_order_items','order_id','order_item_id']:['pc_project_items','project_id','project_item_id'];}
    private function parent(string $kind,int $id):array{return $kind==='order'?(new Desk($this->d,$this->u))->order($id,true):(new Projects($this->d,$this->u))->one($id,true);}
    public function reservations(string $t,int $id):array{
        [$table,$root,$key]=$this->parts($t);return $this->d->all("SELECT s.id,s.active_qty,s.released_qty,s.consumed_qty,s.initial_qty,s.product_id,p.name,p.sku,l.name AS location FROM stock_reservations s JOIN $table i ON i.id=s.$key JOIN products p ON p.id=s.product_id JOIN locations l ON l.id=s.location_id WHERE i.$root=? ORDER BY s.id DESC",[$id]);
    }
    public function hasActive(string $t,int $id):bool{return array_sum(array_column($this->reservations($t,$id),'active_qty'))>0;}
    public function reserve(array $v):array{
        return $this->d->tx(function()use($v){$t=C::pick($v['target']??null,['order','project']);$id=C::id($v['target_id']??null);$p=C::id($v['product_id']??null);$q=C::int($v['quantity']??null,1,500);$o=$this->parent($t,$id);
            C::check($t==='order'?!C::reached($o['state'])&&$o['state']!=='borrador':in_array($o['state'],['aprobado','en_armado','pruebas','listo_entrega'],true),'El destino aún no admite reservas.',409);
            $prod=C::exists($this->d->one('SELECT id,location_id FROM products WHERE id=? AND active=1 FOR UPDATE',[$p]));$l=(int)$prod['location_id'];[$table,$root,$key]=$this->parts($t);
            $item=$this->d->one("SELECT * FROM $table WHERE $root=? AND product_id=? FOR UPDATE",[$id,$p]);
            if(!$item) C::check($t==='order','Agregá el componente al proyecto primero.',409);
            if(!$item)$iid=$this->d->add("INSERT INTO $table($root,product_id,quantity) VALUES(?,?,?)",[$id,$p,$q]);
            else{$iid=(int)$item['id'];$held=(int)$this->d->val("SELECT COALESCE(SUM(active_qty+consumed_qty),0) FROM stock_reservations WHERE $key=?",[$iid]);if($t==='project')C::check($held+$q<=(int)$item['quantity'],'Excede la cantidad del componente.',409);elseif($held+$q>(int)$item['quantity'])$this->d->exec("UPDATE $table SET quantity=? WHERE id=?",[$held+$q,$iid]);}
            $this->delta($p,$l,0,$q);$s=$this->d->add("INSERT INTO stock_reservations($key,product_id,location_id,initial_qty,active_qty) VALUES(?,?,?,?,?)",[$iid,$p,$l,$q,$q]);
            $this->d->exec("INSERT INTO stock_reservation_events(reservation_id,actor_id,kind,quantity) VALUES(?,?,'created',?)",[$s,$this->uid(),$q]);$this->d->audit($this->uid(),'reserva.creada',$root,$id,['reservation'=>$s,'quantity'=>$q]);return ['id'=>$s];});
    }
    private function event(array $res,int $q,bool $consume,string $reason):int{
        C::check($q>0&&$q<=(int)$res['active_qty'],'La cantidad supera la reserva activa.',409);$p=(int)$res['product_id'];$l=(int)$res['location_id'];
        $this->delta($p,$l,$consume?-$q:0,-$q);$col=$consume?'consumed_qty':'released_qty';
        $this->d->exec("UPDATE stock_reservations SET active_qty=active_qty-?,$col=$col+? WHERE id=?",[$q,$q,$res['id']]);
        $e=$this->d->add('INSERT INTO stock_reservation_events(reservation_id,actor_id,kind,quantity,reason) VALUES(?,?,?,?,?)',[$res['id'],$this->uid(),$consume?'consumed':'released',$q,$reason]);
        if($consume)$this->append($p,$l,'consumo',-$q,$reason,null,$e);return $e;
    }
    public function change(int $id,array $v,bool $consume):array{
        return $this->d->tx(function()use($id,$v,$consume){$r=C::exists($this->d->one('SELECT s.*,i.order_id,p.project_id FROM stock_reservations s LEFT JOIN service_order_items i ON i.id=s.order_item_id LEFT JOIN pc_project_items p ON p.id=s.project_item_id WHERE s.id=?',[$id]));
            $t=$r['order_id']?'order':'project';$oid=(int)($r['order_id']??$r['project_id']);$o=$this->parent($t,$oid);
            C::check(!C::reached($o['state']),'El trabajo está cerrado.',409);if($consume)C::check(in_array($o['state'],['en_reparacion','en_armado','pruebas'],true),'El trabajo no está en reparación o armado.',409);
            $this->position((int)$r['product_id'],(int)$r['location_id']);$r=C::exists($this->d->one('SELECT * FROM stock_reservations WHERE id=? FOR UPDATE',[$id]));
            $event=$this->event($r,C::int($v['quantity']??null,1,500),$consume,C::txt($v['reason']??null,255,true,'motivo'));$this->d->audit($this->uid(),$consume?'reserva.consumida':'reserva.liberada',$t.'_id',$oid,['event'=>$event]);return ['id'=>$event];});
    }
    public function releaseAll(string $t,int $id):void{
        [$table,$root,$key]=$this->parts($t);$r=$this->d->all("SELECT s.* FROM stock_reservations s JOIN $table i ON i.id=s.$key WHERE i.$root=? AND s.active_qty>0 ORDER BY s.product_id,s.location_id,s.id",[$id]);
        foreach($r as $s)$this->event($s,(int)$s['active_qty'],false,'Cancelación de trabajo');
    }
    public function purchase(array $v):array{return $this->d->tx(function()use($v){$pid=C::id($v['product_id']??null);C::exists($this->d->one('SELECT id FROM products WHERE id=?',[$pid]));$id=$this->d->add('INSERT INTO purchase_requests(actor_id,reason) VALUES(?,?)',[$this->uid(),C::txt($v['reason']??null,255,true,'motivo')]);$this->d->exec('INSERT INTO purchase_request_items VALUES(?,?,?)',[$id,$pid,C::int($v['quantity']??null,1,100000)]);$this->d->audit($this->uid(),'compra.solicitada','product_id',$pid,['request_id'=>$id]);return ['id'=>$id];});}
    public function purchases():array{return $this->d->all('SELECT r.*,i.quantity,p.name FROM purchase_requests r JOIN purchase_request_items i ON r.id=i.request_id JOIN products p ON p.id=i.product_id ORDER BY r.id DESC LIMIT 100');}
    public function import(array $v,bool $commit):array{
        $text=C::txt($v['csv']??null,100000,true,'CSV');$f=fopen('php://temp','r+');fwrite($f,ltrim($text,"\xEF\xBB\xBF"));rewind($f);$headers=fgetcsv($f,0,',','"','');
        C::check(is_array($headers)&&array_slice($headers,0,3)===['sku','name','price']&&count($headers)===count(array_unique($headers))&&!array_diff($headers,['sku','name','price','cost','minimum']),'Encabezados: sku,name,price y opcionales cost,minimum.');$rows=[];$valid=[];$used=[];$i=1;
        while(($cols=fgetcsv($f,0,',','"',''))!==false){$i++;C::check($i<=301,'Máximo 300 filas por lote.');if($cols===[null])continue;
            try{C::check(count($cols)===count($headers),'Columnas incompletas.');$x=array_combine($headers,$cols);$sku=mb_strtoupper(C::txt($x['sku'],60,true,'SKU'));C::check(!isset($used[$sku]),'SKU repetido en el archivo.');$used[$sku]=true;C::check(!$this->d->val('SELECT id FROM products WHERE sku=?',[$sku]),'El SKU ya existe.');C::txt($x['name'],150,true,'nombre');Money::parse($x['price']);Money::parse($x['cost']??'0');C::int($x['minimum']??0,0,100000);$x['location_id']=1;$valid[]=$x;$rows[]=['row'=>$i,'name'=>$x['name'],'outcome'=>'válida'];}catch(\Kronos\Support\Problem $e){$rows[]=['row'=>$i,'outcome'=>$e->getMessage()];}}
        if($commit){C::check(count($rows)===count($valid)&&$valid,'Corregí los errores antes de importar.',422);return $this->d->tx(function()use($valid){foreach($valid as $x)$this->save($x);return ['imported'=>count($valid)];});}return ['rows'=>$rows,'valid'=>count($valid),'total'=>count($rows)];
    }
}
