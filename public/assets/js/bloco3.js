const routeStatuses = [['scheduled','Programada'],['in_progress','Em andamento'],['completed','Concluída'],['cancelled','Cancelada']];
const routeStatusLabels = Object.fromEntries(routeStatuses);

async function routesPage() {
  const [trains, rows] = await Promise.all([api('trains'), api('routes?'+new URLSearchParams(state.routeFilters))]);
  const f = state.routeFilters;
  app.innerHTML = head('Rotas e viagens programadas','Consulte e organize as viagens da frota.',`<a class="button primary" href="${root}rotas_form.php">Nova rota</a>`)+
    card('Filtros',`<form id="route-filters" class="form-stack"><div class="form-two">
      ${select('Status','status',[['','Todos'],...routeStatuses],f.status)}
      ${field('Data inicial','from',f.from,'date')}${field('Data final','to',f.to,'date')}
      ${select('Trem','train_id',[['','Todos'],...trains.map(t=>[t.id,t.code])],f.train_id)}
      ${field('Rota (origem ou destino)','q',f.q,'search','maxlength="80"')}
    </div><button class="button secondary">Filtrar</button></form>`)+
    card('Viagens',rows.length ? managementTable(['Rota','Trem','Data','Partida','Chegada','Status','Ações'],rows.map(r=>[
      `${esc(r.origin)} → ${esc(r.destination)}`,`${esc(r.train_code)} · ${esc(r.train_name)}`,esc(r.travel_date),
      esc(r.departure_time.slice(0,5)),r.arrival_time ? esc(r.arrival_time.slice(0,5)) : '—',
      managementBadge(routeStatusLabels[r.status]||r.status,r.status==='cancelled'?'critical':r.status==='in_progress'?'attention':'normal'),
      `<a class="text-link" href="${root}rotas_form.php?id=${r.id}">Editar</a> <form class="inline-form" data-route-delete="${r.id}"><button class="text-link danger-link" type="submit">Excluir</button></form>`
    ])) : empty('Nenhuma rota encontrada para os filtros selecionados.'));
  onForm('route-filters',async d=>{state.routeFilters=d;await routesPage()});
  app.querySelectorAll('[data-route-delete]').forEach(form=>form.onsubmit=async e=>{
    e.preventDefault(); if(!confirm('Confirma a exclusão desta rota?')) return;
    try { await api(`routes/${form.dataset.routeDelete}/delete`,'POST'); await routesPage(); notice('Rota excluída.'); }
    catch(error) { notice(error.message,true); }
  });
}

async function routeFormPage() {
  const id=new URLSearchParams(location.search).get('id');
  if(id && !/^[1-9]\d*$/.test(id)) throw Error('Rota inválida.');
  const [trains,r]=await Promise.all([api('trains'),id?api('routes/'+id):Promise.resolve(null)]);
  if(!trains.length) { app.innerHTML=head('Rotas','Cadastre um trem antes de criar uma rota.')+`<a class="button primary" href="${root}trens_form.php">Cadastrar trem</a>`; return; }
  app.innerHTML=head(r?'Editar rota':'Cadastrar rota','A rota representa o trajeto e a viagem programada.')+
    card(r?'Editar rota':'Nova rota',`<form id="route-form" class="form-stack"><div class="form-two">
      ${select('Trem','train_id',[['','Selecione'],...trains.map(t=>[t.id,`${t.code} · ${t.name}`])],r?.train_id||'')}
      ${field('Origem','origin',r?.origin||'','text','required maxlength="80"')}
      ${field('Destino','destination',r?.destination||'','text','required maxlength="80"')}
      ${field('Data da viagem','travel_date',r?.travel_date||'','date','required')}
      ${field('Hora da partida','departure_time',r?.departure_time?.slice(0,5)||'','time','required')}
      ${field('Hora da chegada','arrival_time',r?.arrival_time?.slice(0,5)||'','time')}
      ${select('Status','status',routeStatuses,r?.status||'scheduled')}
    </div><p class="data-note">A chegada informada deve ser no mesmo dia e após a partida.</p><div class="toolbar"><button class="button primary">Salvar rota</button><a class="button secondary" href="${root}rotas.php">Cancelar</a></div></form>`);
  app.querySelector('[name=train_id]').required=true;
  onForm('route-form',async d=>{await api(r?'routes/'+r.id:'routes',r?'PATCH':'POST',d);location.href=root+'rotas.php'});
}

async function readingsPage() {
  const [trains,sensors,rows]=await Promise.all([api('trains'),api('sensors'),api('readings?'+new URLSearchParams(state.readingFilters))]);
  const f=state.readingFilters;
  const action=['manager','super_admin'].includes(user?.role)?`<a class="button primary" href="${root}simulador.php">Abrir simulador</a>`:'';
  app.innerHTML=head('Leituras dos sensores','Até 100 leituras, da mais recente para a mais antiga.',action)+
    `<div class="toolbar">${select('Trem','train_id',[['','Todos'],...trains.map(t=>[t.id,t.code])],f.train_id)}${select('Sensor','sensor_id',[['','Todos'],...sensors.map(s=>[s.id,s.code])],f.sensor_id)}</div>`+
    card('Leituras recebidas',rows.length?managementTable(['Data e hora','Trem','Sensor','Tipo','Valor'],rows.map(r=>[
      date(r.recorded_at),esc(r.train_code),esc(r.sensor_code),esc(r.sensor_type),`${esc(r.value)} ${esc(r.unit)}`
    ])):empty('Ainda não há leituras para os filtros selecionados.'));
  app.querySelectorAll('[name=train_id],[name=sensor_id]').forEach(el=>el.onchange=e=>{state.readingFilters[e.target.name]=e.target.value;readingsPage()});
}

async function simulatorPage() {
  const [sensors,types]=await Promise.all([api('sensors'),api('sensor-profiles')]);
  const available=sensors.filter(s=>types.includes(s.type));
  app.innerHTML=head('Simulador de leituras','Gere valores compatíveis com o tipo de sensor escolhido.')+
    card('Gerar leituras',available.length?`<form id="simulator-form" class="form-stack"><div class="form-two">
      ${select('Sensor','sensor_id',[['','Selecione'],...available.map(s=>[s.id,`${s.code} · ${s.type} · ${s.train_code}`])])}
      ${field('Quantidade (1 a 100)','quantity',1,'number','required min="1" max="100" step="1"')}
    </div><label class="check"><input type="checkbox" name="outside_limit"> Gerar valores acima do limite para testar alertas</label>
    <button class="button primary">Gerar leituras</button></form>`:empty('Cadastre um sensor de tipo compatível para usar o simulador.'));
  if(available.length) {
    app.querySelector('[name=sensor_id]').required=true;
    onForm('simulator-form',async(d,form)=>{
      const result=await api('simulator','POST',{sensor_id:d.sensor_id,quantity:d.quantity,outside_limit:form.elements.outside_limit.checked});
      notice(`${result.readings} leituras e ${result.alerts} alertas gravados.`);
    });
  }
}

async function operationalAlertsPage() {
  const [rows,sensors]=await Promise.all([api('operational-alerts?'+new URLSearchParams(state.alertFilters)),api('sensors')]);
  const types=[...new Set(sensors.map(s=>s.type))].sort(); const f=state.alertFilters;
  app.innerHTML=head('Central de alertas','Leituras fora do limite aparecem automaticamente nesta central.')+
    `<div class="toolbar">${select('Severidade','severity',[['','Todas'],['warning','Atenção'],['critical','Crítica']],f.severity)}${select('Tipo de sensor','type',[['','Todos'],...types.map(t=>[t,t])],f.type)}</div>`+
    card('Alertas gerados',rows.length?managementTable(['Data e hora','Severidade','Tipo','Trem','Sensor','Leitura','Ocorrência'],rows.map(a=>[
      date(a.created_at),`<span class="alert-emphasis ${a.severity==='critical'?'critical':''}">${managementBadge(a.severity==='critical'?'Crítica':'Atenção',a.severity==='critical'?'critical':'attention')}</span>`,
      esc(a.sensor_type),esc(a.train_code),esc(a.sensor_code),`${esc(a.value)} ${esc(a.unit)}`,esc(a.message)
    ])):empty('Nenhum alerta para os filtros selecionados.'));
  app.querySelectorAll('[name=severity],[name=type]').forEach(el=>el.onchange=e=>{state.alertFilters[e.target.name]=e.target.value;operationalAlertsPage()});
}
