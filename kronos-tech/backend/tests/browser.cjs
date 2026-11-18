// Requiere Playwright instalado fuera del runtime; usar solo un entorno demo desechable.
const assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.KRONOS_TEST_URL;
if(!base)throw Error('Definí KRONOS_TEST_URL con una demo desechable.');
(async()=>{
 const b=await chromium.launch({headless:true,args:['--no-sandbox']});
 try{
  const ctx=await b.newContext({viewport:{width:1440,height:900}}),p=await ctx.newPage();p.setDefaultTimeout(7000);const errors=[];p.on('pageerror',e=>errors.push(e.message));
  const h=(name)=>p.getByRole('heading',{name,exact:true});const button=(name)=>p.getByRole('button',{name,exact:true});
  await p.goto(base,{waitUntil:'networkidle'});await h('Panel general').waitFor();
  await p.locator('#chart-days').selectOption('14');await p.locator('#chart-main circle').nth(13).waitFor();
  for(const [name,title] of [['Órdenes','Órdenes de servicio'],['Clientes','Clientes'],['Agenda','Agenda'],['Inventario','Inventario'],['Armados de PC','Armados de PC'],['Presupuestos','Presupuestos'],['Ventas','Ventas'],['Caja','Caja'],['Auditoría','Auditoría'],['Usuarios','Usuarios y acceso']]){
   await p.locator('#side-links').getByRole('link',{name,exact:false}).first().click();await h(title).waitFor();
  }
  await p.goto(base+'/#nueva-orden');await p.getByLabel('Cliente existente').selectOption('1');await button('Continuar').click();
  await p.getByLabel('Equipo registrado').selectOption('1');await p.getByLabel('Accesorios (uno por línea)').fill('Cargador de prueba');await button('Continuar').click();
  await p.getByLabel('Falla reportada').fill('Falla registrada en prueba de navegador');await p.getByLabel('Presupuesto preliminar').fill('19900');await button('Continuar').click();
  for(const text of ['El cliente autoriza el diagnóstico.','Se verificaron equipo y accesorios recibidos.','Conformidad con el tratamiento de datos para este servicio.'])await p.getByLabel(text).check();
  await p.getByLabel('Nombre de quien da conformidad (opcional)').fill('Firma de prueba');
  const pad=await p.locator('#signature-pad').boundingBox();await p.mouse.move(pad.x+18,pad.y+22);await p.mouse.down();await p.mouse.move(pad.x+60,pad.y+37,{steps:8});await p.mouse.move(pad.x+97,pad.y+13,{steps:8});await p.mouse.up();
  await button('Confirmar recepción').click();await p.waitForURL(/#orden\/\d+/);
  await button('Comprobante').click();const sign=p.getByAltText('Firma de recepción');await sign.waitFor();await p.waitForFunction(()=>{const x=document.querySelector('.receipt-sign');return !!x?.naturalWidth;});
  await button('Cerrar').click();
  await p.goto(base+'/#ventas');await p.getByRole('button',{name:/SSD Kingston NV2/}).first().click();await button('Ir al cobro').click();await p.getByLabel('Efectivo recibido').first().fill('60000');await button('Confirmar venta').click();await p.waitForURL(/#ventas\?tab=historial&id=\d+/);await p.getByText('Comprobante interno. No es boleta electrónica.').waitFor();
  assert.deepEqual(errors,[]);console.log('OK navegador: navegación, período del gráfico, recepción con firma y venta completa.');
  const mobile=await b.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true});const page=await mobile.newPage();page.setDefaultTimeout(7000);await page.goto(base,{waitUntil:'networkidle'});await page.getByRole('heading',{name:'Panel general',exact:true}).waitFor();
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=390));await page.getByRole('button',{name:'Abrir navegación'}).click();await page.locator('#side-links').getByRole('link',{name:'Inventario',exact:true}).click();await page.getByRole('heading',{name:'Inventario',exact:true}).waitFor();console.log('OK navegador: viewport móvil 390 px sin desplazamiento general horizontal y menú táctil.');
 }finally{await b.close();}
})().catch(e=>{console.error(e);process.exit(1);});
