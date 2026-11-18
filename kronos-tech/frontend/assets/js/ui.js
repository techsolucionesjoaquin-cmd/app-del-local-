const paths={
 grid:'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',list:'<path d="M9 6h11M9 12h11M9 18h11M4 6h1M4 12h1M4 18h1"/>',users:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/><circle cx="9" cy="7" r="4"/>',box:'<path d="m21 8-9 5-9-5m9 5v8m9-13v8a2 2 0 0 1-1 1.7l-7 4a2 2 0 0 1-2 0l-7-4A2 2 0 0 1 3 16V8a2 2 0 0 1 1-1.7l7-4a2 2 0 0 1 2 0l7 4A2 2 0 0 1 21 8ZM7.5 4.3l9 5.1"/>',cpu:'<rect x="5" y="5" width="14" height="14" rx="2"/><rect x="9" y="9" width="6" height="6" rx="1"/><path d="M9 2v3m6-3v3m-6 14v3m6-3v3M2 9h3m-3 6h3m14-6h3m-3 6h3"/>',cart:'<path d="M2 3h3l2.4 11a2 2 0 0 0 2 1.5h8.8a2 2 0 0 0 2-1.6L22 6H6"/><circle cx="10" cy="20" r="1"/><circle cx="19" cy="20" r="1"/>',wallet:'<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18m-6 5h2m-7 0H6M5 6l12-3"/>',calendar:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4m-5 3h18m-13 5h2m4 0h2"/>',shield:'<path d="M12 22s7-3.5 7-10V5l-7-3-7 3v7c0 6.5 7 10 7 10Z"/><path d="m9 12 2 2 4-4"/>',arrow:'<path d="M4 12h15m-5-5 5 5-5 5"/>',upRight:'<path d="M7 17 17 7M7 7h10v10"/>',chev:'<path d="m9 18 6-6-6-6"/>',down:'<path d="m6 9 6 6 6-6"/>',plus:'<path d="M12 5v14M5 12h14"/>',search:'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>',bell:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12a2 2 0 0 0 4 0"/>',wrench:'<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0L21 6a6 6 0 0 1-7.9 7.9l-6.8 6.8a2.1 2.1 0 0 1-3-3l6.8-6.8A6 6 0 0 1 18 3Z"/>',triangle:'<path d="M10.3 4 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4a2 2 0 0 0-3.4 0ZM12 9v4m0 3h.01"/>',file:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Zm0 0v6h6M8 13h8m-8 4h5"/>',clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',phone:'<path d="M22 16.9v3a2 2 0 0 1-2.2 2 18 18 0 0 1-7.9-2.8 18 18 0 0 1-5.5-5.5 18 18 0 0 1-2.8-8A2 2 0 0 1 5.6 3h3a2 2 0 0 1 2 1.7l.5 2.5a2 2 0 0 1-.6 2.1l-1.3 1.3a14 14 0 0 0 4.2 4.2l1.3-1.3a2 2 0 0 1 2.1-.6l2.5.5a2 2 0 0 1 2.7 3.5Z"/>',deliver:'<path d="M2 5h13v12H2zM15 9h3l4 4v4h-7M18 9v4h4"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>',computer:'<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8m-4-4v4"/>',panel:'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/>',menu:'<path d="M4 6h16M4 12h16M4 18h16"/>',x:'<path d="m6 6 12 12M18 6 6 18"/>',check:'<path d="m5 12 4 4L19 6"/>',download:'<path d="M12 3v12m-4-4 4 4 4-4M4 17v4h16v-4"/>',upload:'<path d="M12 15V3m-4 4 4-4 4 4M4 17v4h16v-4"/>',back:'<path d="M20 12H5m5-5-5 5 5 5"/>',edit:'<path d="m15 5 4 4M4 20l4-.8L20 7l-3-3L5 16l-1 4Z"/>',print:'<path d="M6 9V3h12v6M6 17H3V9h18v8h-3m-12-4h12v8H6z"/>',filter:'<path d="M4 5h16M7 12h10m-7 7h4"/>',logout:'<path d="M10 17v3H4V4h6v3m3 9 4-4-4-4m4 4H8"/>',key:'<path d="M21 2l-8 8m4-4 3 3m-6-1 3 3"/><circle cx="7" cy="16" r="5"/>',refresh:'<path d="M20 11a8 8 0 0 0-14-5L3 9m0-5v5h5m-4 4a8 8 0 0 0 14 5l3-3m0 5v-5h-5"/>',history:'<path d="M3 12a9 9 0 1 1 3 6.7M3 5v7h7m2-5v5l3 2"/>',sheet:'<rect x="4" y="4" width="16" height="17" rx="2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M8 11h8m-8 4h8"/>',dots:'<circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>',copy:'<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 5V4H4v12h1"/>',trash:'<path d="M3 6h18M8 6V3h8v3m3 0-1 15H6L5 6m5 4v7m4-7v7"/>',photo:'<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m4 17 4-4 3 3 4-5 6 7"/>',info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-8h.01"/>',activity:'<path d="M3 12h4l3-6 4 12 3-6h4"/>'};
export const ic=(n,small='')=>`<svg class="ic ${small}" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">${paths[n]||paths.info}</svg>`;
export const esc=(v)=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
export const $=(q,root=document)=>root.querySelector(q);
export const $$=(q,root=document)=>Array.from(root.querySelectorAll(q));
export const on=(q,event,fn,root=document)=>{const el=$(q,root);if(el)el.addEventListener(event,fn);};
export const onAll=(q,event,fn,root=document)=>$$(q,root).forEach(el=>el.addEventListener(event,e=>fn(e,el)));
export const state={user:null,shift:null,csrf:null,demo:false,currency:'CLP',scale:0,tax:1900,timezone:'America/Santiago',alerts:[],counts:null};
export const can=(p)=>state.user?.permissions?.includes(p)??false;
export function requestKey(){if(globalThis.crypto?.randomUUID)return crypto.randomUUID();const a=crypto.getRandomValues(new Uint8Array(18));return Array.from(a,b=>b.toString(16).padStart(2,'0')).join('');}
const pendingKeys=new Map();
export async function api(path,{method='GET',body,key,quiet=false}={}){
 const headers={Accept:'application/json'};const opts={method,headers,credentials:'same-origin'};let pending;
 if(method!=='GET'){headers['X-CSRF-Token']=state.csrf||'';if(body instanceof FormData)opts.body=body;else{headers['Content-Type']='application/json';opts.body=JSON.stringify(body??{});}if(key===true){pending=`${state.user?.id??''}:${method}:${path}:${opts.body}`;if(pendingKeys.size>=50&&!pendingKeys.has(pending))pendingKeys.delete(pendingKeys.keys().next().value);if(!pendingKeys.has(pending))pendingKeys.set(pending,requestKey());headers['Idempotency-Key']=pendingKeys.get(pending);}else if(key)headers['Idempotency-Key']=key;}
 let res;try{res=await fetch('/api/v1'+path,opts);}catch{throw new Error('No se pudo conectar con TechSoluciones.');}
 const payload=await res.json().catch(()=>null);if(pending&&res.status<500&&payload)pendingKeys.delete(pending);
 if(!res.ok||!payload){const er=new Error(payload?.message||'No se pudo completar la solicitud.');er.status=res.status;er.reason=payload?.errors?.[0]?.code;er.field=payload?.errors?.[0]?.field;if(res.status===401&&!quiet)window.dispatchEvent(new Event('kronos-expired'));throw er;}
 return payload.data;
}
export const money=(n)=>n===null||n===undefined||n===''?'—':new Intl.NumberFormat('es-CL',{style:'currency',currency:state.currency,minimumFractionDigits:state.scale,maximumFractionDigits:state.scale}).format(Number(n));
export const rawMoney=(n)=>n===null||n===undefined||n===''?'':Number(n).toFixed(state.scale);
export const num=(n)=>new Intl.NumberFormat('es-CL').format(n??0);
export const pretty=(s='')=>String(s??'').replaceAll('_',' ').replace(/^./,c=>c.toUpperCase());
export const number=(p,id)=>`${p}-${String(id).padStart(4,'0')}`;
export const ini=(s='')=>String(s??'').trim().split(/\s+/).map(v=>v[0]??'').slice(0,2).join('').toUpperCase();
const parse=(s)=>s?new Date(/^\d{4}-\d{2}-\d{2}$/.test(s)?`${s}T12:00:00Z`:/[zZ]|[+-]\d\d:\d\d$/.test(s)?s:s.replace(' ','T')+'Z'):null;
export function date(s,opts={day:'2-digit',month:'short'}){try{const d=parse(s);return d?new Intl.DateTimeFormat('es-CL',{timeZone:state.timezone,...opts}).format(d):'—';}catch{return '—';}}
export const time=(s)=>date(s,{hour:'2-digit',minute:'2-digit',hourCycle:'h23'});
export const today=()=>new Intl.DateTimeFormat('sv-SE',{timeZone:state.timezone,year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
export function plusDays(s,n){const d=parse(s);d.setUTCDate(d.getUTCDate()+n);return d.toISOString().slice(0,10);}
export const qs=(obj)=>{const p=new URLSearchParams();for(const [k,v] of Object.entries(obj))if(v!==null&&v!==undefined&&v!=='')p.set(k,v);const q=p.toString();return q?'?'+q:'';};
export const opt=(value,label,selected)=>`<option value="${esc(value)}" ${String(value)===String(selected??'')?'selected':''}>${esc(label)}</option>`;
export const opts=(items,sel,empty)=>`${empty!==undefined?opt('',empty,sel):''}${items.map(v=>Array.isArray(v)?opt(v[0],v[1],sel):opt(v.id,v.name??v.label,sel)).join('')}`;
export const field=(label,html,cls='')=>`<label class="field ${cls}"><span>${esc(label)}</span>${html}</label>`;
export const input=(name,value='',attrs='')=>`<input name="${esc(name)}" value="${esc(value)}" ${attrs}>`;
export const area=(name,value='',attrs='')=>`<textarea name="${esc(name)}" ${attrs}>${esc(value)}</textarea>`;
export const select=(name,html,attrs='')=>`<select name="${esc(name)}" ${attrs}>${html}</select>`;
export const grid=(html,cls='')=>`<div class="form-grid ${cls}">${html}</div>`;
export const fd=(form)=>Object.fromEntries(new FormData(form));
export const check=(name,label,checked=false,required=false)=>`<label class="field inline"><input type="checkbox" name="${esc(name)}" ${checked?'checked':''} ${required?'required':''}><span>${esc(label)}</span></label>`;
export const moneyAttrs=()=>`inputmode="${state.scale?'decimal':'numeric'}" type="number" min="0" step="${state.scale?1/10**state.scale:1}"`;
export const badge=(text,tone='slate')=>`<span class="badge tone-${tone}"><i></i>${esc(text)}</span>`;
export const statusMap={borrador:['Borrador','slate'],recibido:['Recibido','blue'],diagnostico:['Diagnóstico','purple'],presupuesto_enviado:['Presupuesto enviado','blue'],espera_aprobacion:['Espera aprobación','amber'],espera_repuesto:['Espera repuesto','amber'],en_reparacion:['En reparación','purple'],listo_entrega:['Listo para entrega','teal'],entregado:['Entregado','teal'],cancelado:['Cancelado','red'],solicitud:['Solicitud','blue'],cotizacion:['Cotización','purple'],espera_cliente:['Espera cliente','amber'],aprobado:['Aprobado','teal'],en_armado:['En armado','purple'],pruebas:['Pruebas','blue'],draft:['Borrador','slate'],sent:['En espera','amber'],approved:['Aprobado','teal'],rejected:['Rechazado','red'],superseded:['Reemplazado','slate'],verify:['Requiere verificación','amber'],compatible:['Compatible según datos disponibles','teal'],incompatible:['Incompatible','red'],pendiente:['Pendiente','amber'],realizada:['Realizada','teal'],cancelada:['Cancelada','red']};
export const status=(x)=>badge(...(statusMap[x]??[pretty(x),'slate']));
export const priority=(x)=>`<span class="priority ${esc(x)}"><i></i>${esc(pretty(x))}</span>`;
export const methodLabel={cash:'Efectivo',debit:'Débito',transfer:'Transferencia'};
export const methodOptions=Object.entries(methodLabel);
export function btn(text,{id,icon,cls='',href,data,disabled=false,type='button',label}={}){
 const attrs=`class="btn ${cls}"${id?` id="${esc(id)}"`:''}${data?` data-id="${esc(data)}"`:''}${label?` aria-label="${esc(label)}" title="${esc(label)}"`:''}`;const body=(icon?ic(icon):'')+(text?esc(text):'');
 return href?`<a ${attrs} href="${esc(href)}">${body}</a>`:`<button ${attrs} type="${type}" ${disabled?'disabled':''}>${body}</button>`;
}
export const link=(text,href,i='arrow')=>`<a class="section-link" href="${esc(href)}">${esc(text)}${ic(i)}</a>`;
export const panel=(title,sub,body,action='',cls='')=>`<section class="panel ${cls}"><div class="panel-head"><div><h2>${esc(title)}</h2>${sub?`<p>${esc(sub)}</p>`:''}</div>${action}</div><div class="panel-body">${body}</div></section>`;
export const head=(k,h,d,a='')=>`<header class="page-head"><div><div class="kicker">${esc(k)}</div><h1>${esc(h)}</h1><p>${esc(d)}</p></div>${a?`<div class="head-actions">${a}</div>`:''}</header>`;
export const empty=(text='No hay datos para mostrar.',icon='box')=>`<div class="empty">${ic(icon)}${esc(text)}</div>`;
export const facts=(entries)=>`<dl class="facts">${entries.map(([k,v,w])=>`<div class="${w?'wide':''}"><dt>${esc(k)}</dt><dd>${v}</dd></div>`).join('')}</dl>`;
export function table(headers,rows,{cards=true,blank='No hay resultados.'}={}){
 if(!rows.length)return empty(blank,'list');
 return `<div class="table-wrap"><table class="data-table" ${cards?'data-cards':''}><thead><tr>${headers.map(h=>`<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(r=>`<tr>${r.map((v,i)=>`<td data-label="${esc(headers[i])}" ${Array.isArray(v)?`class="${esc(v[1])}"`:''}>${Array.isArray(v)?v[0]:v}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
}
export function toast(text,error=false){const e=document.createElement('div');e.className='toast'+(error?' error':'');e.textContent=text;$('#toasts').append(e);setTimeout(()=>e.remove(),3900);}
export function dialog(title,body,desc='',cls=''){
 const d=$('#modal');if(d.open)d.close();d.className=cls;d.innerHTML=`<header class="modal-head"><div><h2 id="modal-title">${esc(title)}</h2>${desc?`<p>${esc(desc)}</p>`:''}</div><button type="button" class="tool-icon" id="dismiss-modal" aria-label="Cerrar">${ic('x')}</button></header><div class="modal-body">${body}</div>`;
 on('#dismiss-modal','click',()=>d.close(),d);d.showModal();return d;
}
export function formModal(title,fields,onSave,{desc='',submit='Guardar',danger=false}={}){
 const m=dialog(title,`<form class="stack" id="modal-form">${fields}<div class="modal-error alert error" role="alert"></div><div class="modal-foot">${btn('Cancelar',{id:'modal-cancel'})}${btn(submit,{type:'submit',cls:danger?'danger':'primary'})}</div></form>`,desc);const f=$('#modal-form',m);
 on('#modal-cancel','click',()=>m.close(),m);
 f.addEventListener('submit',async e=>{e.preventDefault();const button=$('[type=submit]',f),error=$('.modal-error',f);button.disabled=true;error.classList.remove('show');try{const r=await onSave(fd(f),f,m);if(r!==false&&f.isConnected)m.close();}catch(x){error.textContent=x.message;error.classList.add('show');const key=x.field;if(key&&f.elements.namedItem(key))f.elements.namedItem(key)?.focus();}finally{button.disabled=false;}});return m;
}
export const confirmAction=(title,text,action)=>formModal(title,`<p class="alert">${esc(text)}</p>`,action,{submit:'Confirmar',danger:true});
export function printView(title,body){const d=dialog(title,`<div class="print-paper"><h3>KRONOS TECH</h3>${body}<p class="muted section-space">Documento interno · No es un comprobante tributario.</p></div><div class="modal-foot">${btn('Imprimir',{icon:'print',id:'print-now',cls:'primary'})}</div>`);on('#print-now','click',()=>window.print(),d);}
export function safe(fn){return async(e,...a)=>{try{await fn(e,...a);}catch(x){toast(x.message,true);}};}
export function debounce(fn,ms=240){let timer;return (...a)=>{clearTimeout(timer);timer=setTimeout(()=>fn(...a),ms);};}
export function uppercase(s){return s?String(s).charAt(0).toUpperCase()+String(s).slice(1):'';}
