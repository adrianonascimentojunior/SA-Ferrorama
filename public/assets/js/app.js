const root = document.body.dataset.base;
const page = document.body.dataset.page;
let csrf = document.body.dataset.csrf;
let user = JSON.parse(document.body.dataset.user || 'null');
const app = document.getElementById('app');
const state = {trainQ:'',trainStatus:'',period:'30d',origin:'',destination:'',report:null,notifTab:'all',routeFilters:{status:'',from:'',to:'',train_id:'',q:''},readingFilters:{sensor_id:'',train_id:''},alertFilters:{severity:'',type:''}};
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const date = v => v ? new Date(String(v).replace(' ', 'T') + (String(v).includes('Z') ? '' : 'Z')).toLocaleString('pt-BR') : '—';
const localDateTimeInput = v => {
  if (!v) return '';
  const d = new Date(String(v).replace(' ', 'T') + 'Z');
  const two = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${two(d.getMonth()+1)}-${two(d.getDate())}T${two(d.getHours())}:${two(d.getMinutes())}`;
};
const money = v => Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
const num = v => Number(v||0).toLocaleString('pt-BR');
const link = p => `${root}index.php?page=${p}`;
async function api(path, method='GET', data) {
  const headers = {};
  if (data !== undefined) headers['Content-Type']='application/json';
  if (method !== 'GET' && csrf) headers['X-CSRF-Token']=csrf;
  const response = await fetch(root+'api/'+path.replace(/^\//,''),{method,headers,body:data===undefined?undefined:JSON.stringify(data),cache:'no-store',credentials:'same-origin'});
  const result = await response.json().catch(()=>({}));
  if (!response.ok) throw new Error(result.error || `Erro ${response.status}`);
  return result;
}
function notice(message, error=false) { const el=document.getElementById('notice'); if(el){el.textContent=message;el.className='notice '+(error?'error':'success');el.hidden=false;} else alert(message); }
function head(title,subtitle='',action='') { return `<div class="page-head"><div><span class="eyebrow">A-TRAIN · OPERAÇÃO</span><h1>${esc(title)}</h1><p>${esc(subtitle)}</p></div>${action}</div><div id="notice" hidden></div>`; }
function card(title, body, extra='') { return `<section class="card"><div class="card-head"><h2>${esc(title)}</h2>${extra}</div>${body}</section>`; }
function empty(label='Nenhum registro encontrado.') { return `<p class="empty-state">${esc(label)}</p>`; }
function table(headers, rows) { return `<div class="table-wrap"><table><thead><tr>${headers.map(x=>`<th>${esc(x)}</th>`).join('')}</tr></thead><tbody>${rows.join('')||`<tr><td colspan="${headers.length}">${empty()}</td></tr>`}</tbody></table></div>`; }
function managementTable(headers, rows) { return `<div class="table-wrap"><table class="management-table"><thead><tr>${headers.map(x=>`<th>${esc(x)}</th>`).join('')}</tr></thead><tbody>${rows.map(cells=>`<tr>${cells.map((cell,i)=>`<td data-label="${esc(headers[i])}">${cell}</td>`).join('')}</tr>`).join('')||`<tr><td colspan="${headers.length}">${empty()}</td></tr>`}</tbody></table></div>`; }
function field(label,name,value='',type='text',extra='') { return `<label class="field"><span>${esc(label)}</span><input name="${esc(name)}" type="${type}" value="${esc(value)}" ${extra}></label>`; }
function select(label,name,options,value='') { return `<label class="field"><span>${esc(label)}</span><select name="${esc(name)}">${options.map(([v,t])=>`<option value="${esc(v)}" ${String(v)===String(value)?'selected':''}>${esc(t)}</option>`).join('')}</select></label>`; }
function formData(form) { return Object.fromEntries(new FormData(form).entries()); }
function onForm(id, fn) { document.getElementById(id)?.addEventListener('submit',async e=>{e.preventDefault();try{await fn(formData(e.currentTarget),e.currentTarget);}catch(err){notice(err.message,true);}}); }
const option = (a,label='name') => a.map(x=>[x.id,x[label]]);
const statuses = [['operating','Operando'],['maintenance','Manutenção'],['stopped','Parado'],['inactive','Inativo']];
const roleOptions=[['operator','Operador'],['manager','Gerente'],['super_admin','Super Admin']];
const statusBadge = v => `<span class="badge">${esc(v)}</span>`;
const managementBadge = (label,tone) => `<span class="badge management-badge tone-${tone}">${esc(label)}</span>`;
const trainStatusLabels = Object.fromEntries(statuses.map(([value,label])=>[value,label]));
const trainStatusTone = {operating:'normal',maintenance:'attention',stopped:'critical',inactive:'critical'};
async function auth() {
  const cfg={login:['Bem-vindo de volta','Entre para acompanhar sua operação.','auth/login'],cadastro:['Crie sua conta','Comece a monitorar a frota.','auth/register'],recuperar:['Recuperar acesso','Gere um código temporário de 6 dígitos.','auth/forgot'],redefinir:['Defina uma nova senha','Use o código temporário gerado.','auth/reset']}[page];
  app.innerHTML=`<h1>${cfg[0]}</h1><p class="auth-subtitle">${cfg[1]}</p><div id="notice" hidden></div><form id="auth-form" class="auth-form">
    ${page==='cadastro'?field('Nome completo','name','','text','required maxlength="120"'):''}
    ${field('E-mail','email',new URLSearchParams(location.search).get('email')||'','email','required autocomplete="email"')}
    ${page==='redefinir'?field('Código de 6 dígitos','code','','text','required inputmode="numeric" pattern="[0-9]{6}"'):''}
    ${['login','cadastro','redefinir'].includes(page)?field('Senha','password','','password','required minlength="12"'):''}
    ${['cadastro','redefinir'].includes(page)?field('Confirmar senha','confirm','','password','required minlength="12"'):''}
    <button class="button primary" type="submit">${page==='login'?'Entrar':page==='cadastro'?'Criar conta':page==='recuperar'?'Gerar código':'Redefinir senha'}</button></form>
    <p class="auth-foot"><a href="${link('login')}">Entrar</a> · <a href="${link('cadastro')}">Cadastrar</a> · <a href="${link('recuperar')}">Recuperar acesso</a> · <a href="${link('redefinir')}">Redefinir</a></p>`;
  onForm('auth-form',async d=>{if(d.confirm!==undefined && d.confirm!==d.password) throw Error('As senhas não coincidem.');delete d.confirm;const r=await api(cfg[2],'POST',d);if(page==='login'){csrf=r.csrf;location.href=root;return;}notice(r.message||'Operação concluída.');if(r.development_code)notice(`${r.message} Código de demonstração: ${r.development_code}`);});
}
async function dashboard() {
  const [d,map]=await Promise.all([api('dashboard?period='+state.period),api('map')]);
  app.innerHTML=head('Visão geral','Acompanhe a operação ferroviária em tempo quase real.',select('Período','period',[['7d','7 dias'],['30d','30 dias'],['90d','90 dias']],state.period))+
    `<div class="metrics-grid">${[['Trens operando',d.metrics.operating_trains],['Sensores ativos',d.metrics.active_sensors],['Manutenções hoje',d.metrics.maintenances_today],['Alertas ativos',d.metrics.active_alerts]].map(([t,v])=>`<div class="metric-card card"><span>${t}</span><strong>${num(v)}</strong></div>`).join('')}</div>`+
    `<div class="dashboard-grid">${card('Resumo da frota',`<div class="summary-grid"><div>Distância<br><strong>${num(d.summary.distance_km)} km</strong></div><div>Consumo<br><strong>${num(d.summary.consumption_l)} L</strong></div><div>Pontualidade<br><strong>${num(d.summary.punctuality_pct)}%</strong></div></div><h3>Status da frota</h3>${d.fleet.map(x=>`<p>${statusBadge(x.status)} ${num(x.total)} trens</p>`).join('')}<h3>Leituras no período</h3>${d.trends.length?d.trends.map(x=>`<p>${esc(x.day)}: ${num(x.readings)}</p>`).join(''):empty()}`)}${card('Mapa operacional',mapView(map))}</div>`+
    `<div class="lower-grid">${card('Próximas manutenções',d.maintenance.map(x=>`<p><strong>${esc(x.train_code)}</strong> ${esc(x.title)} · ${date(x.scheduled_at)}</p>`).join('')||empty())}${card('Alertas recentes',d.alerts.map(x=>`<p>${statusBadge(x.severity)} ${esc(x.title)}</p>`).join('')||empty())}</div>`;
  app.querySelector('[name=period]').onchange=e=>{state.period=e.target.value;dashboard();};
}
function mapView(map) {
  const ok = o => o.latitude != null && o.longitude != null && o.latitude !== '' && Number.isFinite(Number(o.latitude)) && Number.isFinite(Number(o.longitude));
  const stations = (map.stations || []).filter(ok), trains = (map.trains || []).filter(ok);
  if (!stations.length && !trains.length) return empty('Nenhuma estação ou trem com coordenadas cadastradas.');
  const W = 800, H = 440, P = 78, all = [...stations, ...trains];
  const meanLat = all.reduce((a, o) => a + Number(o.latitude), 0) / all.length;
  const k = Math.cos(meanLat * Math.PI / 180);
  const gx = o => Number(o.longitude) * k, gy = o => -Number(o.latitude);
  const xs = all.map(gx), ys = all.map(gy);
  const minX = Math.min(...xs), minY = Math.min(...ys);
  const spanX = Math.max(Math.max(...xs) - minX, .02), spanY = Math.max(Math.max(...ys) - minY, .02);
  const scale = Math.min((W - 2 * P) / spanX, (H - 2 * P) / spanY);
  const offX = (W - spanX * scale) / 2, offY = (H - spanY * scale) / 2;
  const pos = o => [offX + (gx(o) - minX) * scale, offY + (gy(o) - minY) * scale];
  // Linha: encadeia as estações pelo vizinho mais próximo, partindo da mais a oeste
  const left = [...stations].sort((a, b) => Number(a.longitude) - Number(b.longitude));
  const chain = left.length ? [left.shift()] : [];
  while (left.length) {
    const [cx, cy] = pos(chain[chain.length - 1]);
    left.sort((a, b) => Math.hypot(pos(a)[0] - cx, pos(a)[1] - cy) - Math.hypot(pos(b)[0] - cx, pos(b)[1] - cy));
    chain.push(left.shift());
  }
  const track = chain.map(s => pos(s).map(n => n.toFixed(1)).join(',')).join(' ');
  const st = { operating: ['Operando', '#34d399'], maintenance: ['Manutenção', '#fbbf24'], stopped: ['Parado', '#f87171'], inactive: ['Inativo', '#94a3b8'] };
  const stationSvg = stations.map(s => {
    const [x, y] = pos(s), w = String(s.name).length * 6.8 + 20, flip = x > W * .68, rx = flip ? x - 16 - w : x + 16;
    return `<g class="tm-station"><circle class="tm-halo" cx="${x}" cy="${y}" r="15"/><circle class="tm-dot" cx="${x}" cy="${y}" r="7"/><rect class="tm-label-bg" x="${rx}" y="${y - 12}" width="${w}" height="24" rx="12"/><text class="tm-label" x="${rx + w / 2}" y="${y + 4}" text-anchor="middle">${esc(s.name)}</text></g>`;
  }).join('');
  const used = {};
  const trainSvg = trains.map(t => {
    let [x, y] = pos(t);
    const near = stations.some(s => Math.hypot(pos(s)[0] - x, pos(s)[1] - y) < 18);
    const key = Math.round(x / 14) + ':' + Math.round(y / 14), n = used[key] = (used[key] ?? -1) + 1;
    if (near) y -= 28; x += n * 24;
    const [label, color] = st[t.status] || [t.status, '#94a3b8'];
    const text = `${t.code} · ${label}`, w = text.length * 6.6 + 20, below = y < 62, ty = below ? y + 16 : y - 16 - 26;
    const tx = Math.min(Math.max(x - w / 2, 6), W - w - 6);
    return `<g class="tm-train" tabindex="0" style="--c:${color}"><circle class="tm-pulse" cx="${x}" cy="${y}" r="10"/><circle class="tm-core" cx="${x}" cy="${y}" r="8.5"/><path class="tm-glyph" d="M${x - 3.2} ${y - 3.5}h6.4v5.2h-6.4z M${x - 3.2} ${y - .4}h6.4 M${x - 2.4} ${y + 4.4}l1-2.7 M${x + 2.4} ${y + 4.4}l-1-2.7"/><g class="tm-tip"><rect x="${tx}" y="${ty}" width="${w}" height="26" rx="8"/><text x="${tx + w / 2}" y="${ty + 17}" text-anchor="middle">${esc(text)}</text></g></g>`;
  }).join('');
  const counts = Object.entries(st).map(([k2, [label, color]]) => [label, color, trains.filter(t => t.status === k2).length]).filter(c => c[2]);
  const legend = `<li><i class="tm-sw station"></i>${stations.length} estações</li>` + counts.map(([l, c, n]) => `<li><i class="tm-sw" style="--c:${c}"></i>${n} ${esc(l.toLowerCase())}</li>`).join('');
  return `<div class="tm-map"><svg viewBox="0 0 ${W} ${H}" role="img" aria-label="Mapa esquemático de estações e trens"><defs><pattern id="tm-grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M40 0H0V40" fill="none" stroke="#fff" stroke-opacity=".05"/></pattern><radialGradient id="tm-glow" cx="50%" cy="45%" r="65%"><stop offset="0" stop-color="#7c3aed" stop-opacity=".35"/><stop offset="1" stop-color="#7c3aed" stop-opacity="0"/></radialGradient></defs><rect width="${W}" height="${H}" fill="url(#tm-grid)"/><rect width="${W}" height="${H}" fill="url(#tm-glow)"/>${chain.length > 1 ? `<polyline class="tm-rail" points="${track}"/><polyline class="tm-sleepers" points="${track}"/><polyline class="tm-flow" points="${track}"/>` : ''}${stationSvg}${trainSvg}</svg></div><ul class="tm-legend">${legend}</ul><p class="data-note">Posições baseadas nas coordenadas cadastradas; não representam GPS contínuo.</p>`;
}
async function mapPage() {
  const d = await api('map');
  app.innerHTML = head('Localização da frota', 'Mapa esquemático com estações e posições cadastradas.') +
    card('Mapa ferroviário', mapView(d)) +
    card('Estações e trens', table(['Código', 'Nome', 'Status', 'Estação'], d.trains.map(t =>
      `<tr><td>${esc(t.code)}</td><td>${esc(t.name)}</td><td>${statusBadge(t.status)}</td><td>${esc(d.stations.find(s=>s.id==t.station_id)?.name||'—')}</td></tr>`
    )));
}
async function trains() {
  const keepSearch = document.activeElement?.name === 'q';
  const d=await api('trains?q='+encodeURIComponent(state.trainQ)+'&status='+encodeURIComponent(state.trainStatus));
  app.innerHTML=head('Trens','Consulte e gerencie os trens cadastrados.',`<a class="button primary" href="${root}trens_form.php">Novo trem</a>`)+
  `<div class="toolbar">${field('Buscar prefixo ou modelo','q',state.trainQ,'search')}${select('Status','status',[['','Todos'],...statuses],state.trainStatus)}</div>`+
  card('Trens',managementTable(['Prefixo','Modelo','Ano','Status','Capacidade (t)','Inspeção','Sensores','Ações'],d.map(t=>[
    esc(t.code),esc(t.name),esc(t.model_year||'—'),managementBadge(trainStatusLabels[t.status]||t.status,trainStatusTone[t.status]||'normal'),
    t.capacity_tons==null?'—':esc(t.capacity_tons),esc(t.last_inspection||'—'),esc(t.sensor_count),
    `<button class="text-link" data-view="${t.id}">Detalhes</button> <a class="text-link" href="${root}trens_form.php?id=${t.id}">Editar</a> <form class="inline-form" data-delete="${t.id}"><button type="submit" class="text-link danger-link">Excluir</button></form>`
  ])))+`<div id="train-panel"></div>`;
  if (keepSearch) {
    const search = app.querySelector('[name=q]');
    search.focus();
    search.setSelectionRange(search.value.length, search.value.length);
  }
  app.querySelector('[name=q]').oninput = e => {
    state.trainQ = e.target.value;
    clearTimeout(state.searchTimer);
    state.searchTimer = setTimeout(trains, 350);
  };
  app.querySelector('[name=status]').onchange = e => { state.trainStatus = e.target.value; trains(); };
  app.querySelectorAll('[data-view]').forEach(b => b.onclick = () => {
    state.detailId = b.dataset.view;
    showTrainDetail(b.dataset.view);
  });
  app.querySelectorAll('[data-delete]').forEach(form => form.onsubmit = async e => {
    e.preventDefault();
    if (!confirm('Confirma a exclusão deste trem?')) return;
    try {
      await api('trains/'+form.dataset.delete+'/delete', 'POST');
      if (state.detailId == form.dataset.delete) state.detailId = null;
      await trains();
      notice('Trem excluído.');
    } catch (error) {
      notice(error.message, true);
    }
  });
  if (state.detailId) await showTrainDetail(state.detailId);
}
async function showTrainDetail(id) {
  const t = await api('trains/'+id);
  const histories = await Promise.all(t.sensors.map(s => api(`sensors/${s.id}/readings`)));
  const panel = document.getElementById('train-panel');
  if (panel) panel.innerHTML = card(`Detalhes · ${t.code}`,
    `<p>${esc(t.name)} · ${statusBadge(t.status)}</p><h3>Sensores e leituras recentes</h3>${t.sensors.map((s,i)=>`<details><summary>${esc(s.code)}: ${esc(s.latest_value??'—')} ${esc(s.unit)} · ${statusBadge(s.status)}</summary>${histories[i].map(r=>`<p>${date(r.recorded_at)} · ${esc(r.value)} ${esc(s.unit)} · ${esc(r.source)}</p>`).join('')||empty()}</details>`).join('')||empty()}<h3>Manutenções</h3>${t.maintenances.map(m=>`<p>${esc(m.title)} · ${date(m.scheduled_at)}</p>`).join('')||empty()}`
  );
}
async function trainFormPage() {
  const id = new URLSearchParams(location.search).get('id');
  if (id && !/^\d+$/.test(id)) throw Error('Trem inválido.');
  const t = id ? await api('trains/'+id) : null;
  app.innerHTML=head(t?'Editar trem':'Cadastrar trem','Preencha os dados do trem e confira antes de salvar.')+
    card(t?'Editar trem':'Novo trem',`<form id="train-form" class="form-stack"><div class="form-two">
      ${field('Prefixo','code',t?.code||'','text','required maxlength="32" pattern="TR-[0-9]{3,}" placeholder="TR-204"')}
      ${field('Modelo','name',t?.name||'','text','required maxlength="120"')}
      ${select('Tipo','type',[['locomotive','Locomotiva'],['composition','Composição']],t?.type||'composition')}
      ${field('Ano','model_year',t?.model_year||'','number','required min="1900" max="2100" step="1"')}
      ${select('Status','status',statuses,t?.status||'stopped')}
      ${field('Capacidade (toneladas)','capacity_tons',t?.capacity_tons||'','number','required min="0.01" max="999999.99" step="0.01"')}
      ${field('Última inspeção','last_inspection',t?.last_inspection||'','date')}
    </div><div class="toolbar"><button class="button primary">Salvar trem</button><a class="button secondary" href="${root}trens.php">Cancelar</a></div></form>`);
  onForm('train-form',async data=>{await api(t?'trains/'+t.id:'trains',t?'PATCH':'POST',data);location.href=root+'trens.php'});
}
const sensorTypes = ['Temperatura','Velocidade','Consumo','Localização'];
const sensorIndicators = [['normal','Normal'],['attention','Atenção'],['critical','Crítico']];
const sensorIndicatorLabels = Object.fromEntries(sensorIndicators.map(([value,label])=>[value,label]));
const sensorIndicatorTone = {normal:'normal',attention:'attention',critical:'critical'};
async function sensorsPage() {
  const [all,filtered] = await Promise.all([api('sensors'),api('sensors?type='+encodeURIComponent(state.sensorType||''))]);
  const types = [...new Set([...sensorTypes,...all.map(s=>s.type)])];
  app.innerHTML=head('Sensores','Cada sensor pertence a um trem.',`<a class="button primary" href="${root}sensores_form.php">Novo sensor</a>`)+
    `<div class="toolbar">${select('Filtrar por tipo','type',[['','Todos'],...types.map(type=>[type,type])],state.sensorType||'')}</div>`+
    card('Sensores',managementTable(['Código','Tipo','Trem','Localização','Segmento','Última leitura','Ações'],filtered.map(s=>[
      esc(s.code),esc(s.type),`${esc(s.train_code)} · ${esc(s.train_name)}`,esc(s.location||'—'),esc(s.segment||'—'),
      s.reading_indicator ? `${managementBadge(sensorIndicatorLabels[s.reading_indicator],sensorIndicatorTone[s.reading_indicator])}<br><small>${esc(s.latest_value)} ${esc(s.unit)} · ${date(s.latest_reading_at)}</small>` : '<span class="data-note">Sem leitura</span>',
      `<a class="text-link" href="${root}sensores_form.php?id=${s.id}">Editar</a> <form class="inline-form" data-sensor-delete="${s.id}"><button class="text-link danger-link" type="submit">Excluir</button></form>`
    ])));
  app.querySelector('[name=type]').onchange = e => { state.sensorType = e.target.value; sensorsPage(); };
  app.querySelectorAll('[data-sensor-delete]').forEach(form => form.onsubmit = async e => {
    e.preventDefault();
    if (!confirm('Confirma a exclusão deste sensor?')) return;
    try {
      await api('sensors/'+form.dataset.sensorDelete+'/delete', 'POST');
      await sensorsPage();
      notice('Sensor excluído.');
    } catch (error) {
      notice(error.message, true);
    }
  });
}
async function sensorFormPage() {
  const id = new URLSearchParams(location.search).get('id');
  if (id && !/^\d+$/.test(id)) throw Error('Sensor inválido.');
  const [trains,s] = await Promise.all([api('trains'),id?api('sensors/'+id):Promise.resolve(null)]);
  if (!trains.length) { app.innerHTML=head('Sensores','Cadastre um trem antes de adicionar sensores.')+`<a class="button primary" href="${root}trens_form.php">Cadastrar trem</a>`; return; }
  const types = [...new Set([...sensorTypes,...(s?[s.type]:[])])];
  const trainOptions = [['','Selecione um trem'],...trains.map(t=>[t.id,`${t.code} · ${t.name}`])];
  app.innerHTML=head(s?'Editar sensor':'Cadastrar sensor','Vincule o sensor a um trem existente.')+
    card(s?'Editar sensor':'Novo sensor',`<form id="sensor-form" class="form-stack"><div class="form-two">
      ${field('Código','code',s?.code||'','text','required maxlength="40" pattern="S-[A-Z]{2,10}-[0-9]{3,}" placeholder="S-TEMP-001"')}
      <label class="field"><span>Trem</span><select name="train_id" required>${trainOptions.map(([value,label])=>`<option value="${esc(value)}" ${String(value)===String(s?.train_id||'')?'selected':''}>${esc(label)}</option>`).join('')}</select></label>
      ${select('Tipo','type',types.map(type=>[type,type]),s?.type||'Temperatura')}
      ${field('Unidade','unit',s?.unit||'','text','required maxlength="20" placeholder="°C"')}
      ${select('Status','status',[['active','Ativo'],['warning','Atenção'],['offline','Sem conexão']],s?.status||'active')}
      ${field('Localização no trem','location',s?.location||'','text','maxlength="80" placeholder="Motor, eixo ou cabine"')}
      ${field('Segmento','segment',s?.segment||'','text','maxlength="80" placeholder="Trecho da malha"')}
    </div><p class="data-note">O indicador é calculado pela leitura mais recente do sensor.</p><div class="toolbar"><button class="button primary">Salvar sensor</button><a class="button secondary" href="${root}sensores.php">Cancelar</a></div></form>`);
  onForm('sensor-form',async data=>{await api(s?'sensors/'+s.id:'sensors',s?'PATCH':'POST',data);location.href=root+'sensores.php'});
}
async function tickets() {
  const [map, all] = await Promise.all([api('map'), api('schedules')]);
  const filtered = all.filter(s =>
    (!state.origin || s.origin_id == state.origin) &&
    (!state.destination || s.destination_id == state.destination)
  );
  app.innerHTML = head('Horários e bilhetes', 'Simule uma viagem. Nenhuma compra ou reserva é feita.') +
    card('Mapa ferroviário', mapView(map)) +
    card('Buscar horários',
      `<div class="form-two">${select('Origem','origin',[['','Todas'],...option(map.stations)],state.origin)}${select('Destino','destination',[['','Todos'],...option(map.stations)],state.destination)}</div>` +
      table(['Trem', 'Origem', 'Destino', 'Partida', 'Chegada', 'Tarifa'], filtered.map(s =>
        `<tr><td>${esc(s.train_code)}</td><td>${esc(s.origin_name)}</td><td>${esc(s.destination_name)}</td><td>${date(s.departure_at)}</td><td>${date(s.arrival_at)}</td><td>${money(s.base_price)}</td></tr>`
      ))
    ) +
    card('Simular passagem',
      `<form id="ticket-form" class="form-stack">${select('Ida','schedule_id',[['','Selecione'],...filtered.map(s=>[s.id,`${s.origin_name} → ${s.destination_name} · ${date(s.departure_at)} · ${money(s.base_price)}`])])}${select('Volta opcional','return_schedule_id',[['','Somente ida'],...all.map(s=>[s.id,`${s.origin_name} → ${s.destination_name} · ${date(s.departure_at)}`])])}${field('Passageiros (1 a 8)','passengers',1,'number','min="1" max="8" required')}<button class="button primary">Calcular e registrar simulação</button></form><p class="data-note">Esta simulação não compra nem reserva passagens.</p>`
    );
  app.querySelector('[name=origin]').onchange = e => { state.origin = e.target.value; tickets(); };
  app.querySelector('[name=destination]').onchange = e => { state.destination = e.target.value; tickets(); };
  onForm('ticket-form', async d => {
    const r = await api('tickets/simulate', 'POST', {
      schedule_id: Number(d.schedule_id),
      return_schedule_id: Number(d.return_schedule_id || 0),
      passengers: Number(d.passengers)
    });
    notice(`${r.message} Total estimado: ${money(r.total)}.`);
  });
}
async function reports() {
  const [trains, sensors] = await Promise.all([api('trains'), api('sensors')]);
  let rows = null;
  if (state.report) rows = await api('reports?'+new URLSearchParams(state.report));
  app.innerHTML = head('Relatórios', 'Filtre leituras e ocorrências por período, trem e sensor.',
      `<button class="button secondary" id="print">Imprimir / Salvar PDF</button><button class="button secondary" id="csv">Exportar CSV</button>`
    ) +
    card('Filtros',
      `<form id="report-form" class="report-filters">${field('De','from',state.report?.from||new Date(Date.now()-30*86400000).toISOString().slice(0,10),'date','required')}${field('Até','to',state.report?.to||new Date().toISOString().slice(0,10),'date','required')}${select('Trem','train_id',[['','Todos'],...trains.map(x=>[x.id,x.code])],state.report?.train_id||'')}${select('Sensor','sensor_id',[['','Todos'],...sensors.map(x=>[x.id,x.code])],state.report?.sensor_id||'')}<button class="button primary">Gerar relatório</button></form>`
    ) +
    card('Resultado', rows
      ? table(['Trem', 'Sensor', 'Tipo', 'Status', 'Última leitura', 'Data', 'Ocorrências'], rows.map(x =>
          `<tr><td>${esc(x.train_code)}</td><td>${esc(x.sensor_code)}</td><td>${esc(x.type)}</td><td>${statusBadge(x.status)}</td><td>${esc(x.latest_value??'—')} ${esc(x.unit)}</td><td>${date(x.latest_reading_at)}</td><td>${num(x.occurrences)}</td></tr>`
        ))
      : empty('Defina os filtros e gere o relatório.'));
  onForm('report-form', async d => { state.report = d; reports(); });
  document.getElementById('print').onclick = () => window.print();
  document.getElementById('csv').onclick = () => {
    if (!rows) { notice('Gere o relatório primeiro.', true); return; }
    const cols = ['train_code', 'sensor_code', 'type', 'status', 'latest_value', 'latest_reading_at', 'occurrences'];
    const cell = v => {
      const raw = String(v ?? '');
      const safe = /^[=+\-@]/.test(raw) ? "'"+raw : raw;
      return '"'+safe.replaceAll('"','""')+'"';
    };
    const csv = [cols.join(';'), ...rows.map(r => cols.map(k => cell(r[k])).join(';'))].join('\r\n');
    download('relatorio-atrain.csv', '\ufeff'+csv, 'text/csv;charset=utf-8');
  };
}
function download(name,content,type){const a=document.createElement('a'),u=URL.createObjectURL(new Blob([content],{type}));a.href=u;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(u),1000);}
async function notifications() {
  const [alerts, pref] = await Promise.all([api('notifications'), api('notifications/preferences')]);
  const filtered = alerts.filter(a =>
    state.notifTab === 'all' || (state.notifTab === 'unread' ? !Number(a.is_read) : a.severity === state.notifTab)
  );
  app.innerHTML = head('Notificações', 'Alertas da frota e preferências de contato.') +
    `<div class="toolbar">${select('Exibir','tab',[['all','Todas'],['unread','Não lidas'],['critical','Críticas'],['warning','Avisos']],state.notifTab)}</div>` +
    card('Central de notificações', filtered.map(a =>
      `<div class="notification-row"><div>${statusBadge(a.severity)} <strong>${esc(a.title)}</strong><p>${esc(a.message)}</p><small>${date(a.created_at)} · ${esc(a.train_code||'Sistema')}</small></div>${Number(a.is_read)?'<span>Lida</span>':`<button class="button secondary" data-read="${a.id}">Marcar como lida</button>`}</div>`
    ).join('') || empty()) +
    card('Preferências de entrega (integrações simuladas)',
      `<form id="pref-form" class="form-stack">${[['email_enabled','E-mail'],['push_enabled','Push'],['sms_enabled','SMS']].map(([k,l])=>`<label class="check"><input type="checkbox" name="${k}" ${Number(pref[k])?'checked':''}> ${l}</label>`).join('')}<button class="button primary">Salvar preferências</button></form><p class="data-note">Estas opções são salvas; envios externos ainda são simulações.</p>`
    );
  app.querySelector('[name=tab]').onchange = e => { state.notifTab = e.target.value; notifications(); };
  app.querySelectorAll('[data-read]').forEach(b => b.onclick = async () => {
    await api(`notifications/${b.dataset.read}/read`, 'POST');
    notifications();
  });
  onForm('pref-form', async (d, f) => {
    await api('notifications/preferences', 'PATCH',
      Object.fromEntries(['email_enabled', 'push_enabled', 'sms_enabled'].map(k => [k, f.elements[k].checked]))
    );
    notice('Preferências salvas.');
  });
}
async function alertsPage() {
  const d = await api('notifications');
  app.innerHTML = head('Alertas operacionais', 'Acompanhe ocorrências registradas pela frota.') +
    card('Alertas', table(['Título', 'Trem', 'Severidade', 'Status', 'Criado em'], d.map(x =>
      `<tr><td>${esc(x.title)}<br><small>${esc(x.message)}</small></td><td>${esc(x.train_code||'—')}</td><td>${statusBadge(x.severity)}</td><td>${statusBadge(x.status)}</td><td>${date(x.created_at)}</td></tr>`
    )));
}
async function profile() {
  const d = await api('profile');
  app.innerHTML = head('Meu perfil', 'Dados da sua conta e função na operação.') +
    card('Informações da conta',
      `<p><strong>${esc(d.name)}</strong><br>${esc(d.email)}<br>${statusBadge((roleOptions.find(r=>r[0]===d.role)||[])[1]||d.role)} ${statusBadge(d.status)}<br><small>${({operator:'Consulta painel, alertas, leituras e relatórios.',manager:'Consulta tudo e gerencia trens, sensores, rotas e simulador.',super_admin:'Acesso total, incluindo Painel Admin, usuários e configurações do sistema.'})[d.role]||''}</small><br>Criado em ${date(d.created_at)}</p><form id="profile-form" class="form-stack narrow">${field('Nome','name',d.name,'text','required maxlength="120"')}${field('Cargo','job_title',d.job_title,'text','required maxlength="120"')}<button class="button primary">Salvar perfil</button></form>`
    );
  onForm('profile-form', async v => {
    await api('profile', 'PATCH', v);
    notice('Perfil atualizado.');
  });
}
async function settings() {
  app.innerHTML = head('Configurações', 'Gerencie segurança e dados da sua conta.') +
    card('Alterar senha',
      `<form id="password-form" class="form-stack narrow">${field('Senha atual','old_password','','password','required')}${field('Nova senha','new_password','','password','required minlength="12"')}${field('Confirmar nova senha','confirm','','password','required minlength="12"')}<button class="button primary">Alterar senha</button></form>`
    ) +
    card('Dados da conta',
      `<button class="button secondary" id="export">Exportar dados JSON</button><p class="data-note">Arquivo pessoal para consulta e portabilidade.</p>`
    ) +
    card('Encerrar conta',
      `<form id="account-form" class="form-stack narrow">${field('Confirme sua senha','password','','password','required')}<div class="toolbar"><button type="button" class="button secondary" id="deactivate">Desativar conta</button><button type="button" class="button danger" id="delete">Excluir conta</button></div></form>`
    ) +
    card('Idioma e pagamentos',
      `<label class="field"><span>Idioma preferido</span><select id="language"><option value="pt-BR">Português</option><option value="en-US">English</option></select></label><p class="data-note">A preferência de idioma é local. Pagamentos são apenas ilustrativos.</p>`
    );
  onForm('password-form', async d => {
    if (d.new_password !== d.confirm) throw Error('As senhas não coincidem.');
    delete d.confirm;
    await api('profile/password', 'POST', d);
    notice('Senha alterada.');
  });
  document.getElementById('export').onclick = async () =>
    download('atrain-dados.json', JSON.stringify(await api('profile/export'), null, 2), 'application/json');
  for (const mode of ['deactivate', 'delete']) {
    document.getElementById(mode).onclick = async () => {
      if (!confirm(mode === 'delete' ? 'Excluir sua conta permanentemente?' : 'Desativar sua conta?')) return;
      try {
        await api('profile/'+mode, 'POST', { password: document.querySelector('#account-form [name=password]').value });
        location.href = link('login');
      } catch (e) {
        notice(e.message, true);
      }
    };
  }
  const lang = document.getElementById('language');
  lang.value = localStorage.getItem('atrain.language') || 'pt-BR';
  lang.onchange = () => {
    localStorage.setItem('atrain.language', lang.value);
    notice('Preferência salva neste navegador.');
  };
}
async function help() {
  app.innerHTML = head('Ajuda e suporte', 'Encontre respostas e envie uma solicitação.') +
    `<div class="help-grid">${
      card('Perguntas frequentes',
        `<details><summary>Os dados são atualizados automaticamente?</summary><p>Sim, as páginas operacionais consultam a API a cada cinco segundos quando visíveis.</p></details><details><summary>Uma simulação reserva passagem?</summary><p>Não. A simulação apenas calcula e registra uma estimativa.</p></details><details><summary>Como salvar um relatório em PDF?</summary><p>Use Imprimir / Salvar PDF na página de relatórios.</p></details>`
      )
    }${
      card('Fale com o suporte',
        `<form id="support-form" class="form-stack">${field('Assunto','subject','','text','required maxlength="160"')}<label class="field"><span>Mensagem</span><textarea name="message" required maxlength="3000" rows="7"></textarea></label><button class="button primary">Enviar solicitação</button></form><p class="data-note">Sua solicitação será salva na sua conta.</p>`
      )
    }</div>`;
  onForm('support-form', async (d, f) => {
    await api('support', 'POST', d);
    f.reset();
    notice('Solicitação registrada.');
  });
}
/* ===================== PAINEL ADMIN =====================
   Regra de ouro desta tela: tudo o que é gravado no MySQL aparece escrito como
   "tabela.coluna: antes → depois" ANTES de salvar (confirmação), DEPOIS de salvar
   (resultado) e na Auditoria (histórico). Só as colunas alteradas são enviadas. */
const enumLabels = {
  role: Object.fromEntries(roleOptions),
  userStatus: {active:'Ativo',disabled:'Desativado',banned:'Banido'},
  trainStatus: trainStatusLabels,
  sensorStatus: {active:'Ativo',warning:'Atenção',offline:'Sem conexão'},
  maintStatus: {pending:'Pendente',in_progress:'Em andamento',completed:'Concluída',cancelled:'Cancelada'},
  alertStatus: {active:'Ativo',resolved:'Resolvido'},
  severity: {critical:'Crítico',warning:'Aviso',info:'Informação',system:'Sistema'}
};
const adminTables = {users:'users',trains:'trains',sensors:'sensors',maintenances:'maintenances',alerts:'alerts'};
const adminNames = {users:'Usuários',trains:'Trens',sensors:'Sensores',maintenances:'Manutenções',alerts:'Alertas'};
const adminStatusLabels = {users:enumLabels.userStatus,trains:enumLabels.trainStatus,sensors:enumLabels.sensorStatus,maintenances:enumLabels.maintStatus,alerts:enumLabels.alertStatus};
const adminTabs = [['dados','Dados'],['config','Configurações'],['acesso','Acesso por papel'],['banco','Banco e conexão'],['auditoria','Auditoria']];
const actionLabels = {admin_update:'Edição no Painel Admin',update_setting:'Configuração do sistema'};
const settingMeta = {
  maintenance_mode:{label:'Modo de manutenção',kind:'toggle',help:'Quando ligado, Operadores e Gerentes recebem erro 503 em toda a plataforma. Só o Super Admin continua usando.'},
  db_statement_timeout_ms:{label:'Tempo máximo de consulta ao banco',kind:'number',unit:'ms',min:1000,max:30000,help:'Aplicado como max_execution_time do MySQL a cada requisição.'},
  alert_retention_days:{label:'Retenção de alertas',kind:'number',unit:'dias',min:7,max:3650,help:'Por quantos dias os alertas são mantidos.'}
};
const tableOwners = {users:'Painel Admin / Perfil',trains:'Painel Admin / Gerente',sensors:'Painel Admin / Gerente',routes:'Gerente',maintenances:'Painel Admin',alerts:'Painel Admin / Simulador',app_settings:'Painel Admin',audit_logs:'Automático (somente leitura)',sensor_readings:'Simulador',stations:'Somente leitura',password_resets:'Recuperação de senha',notification_reads:'Notificações',notification_preferences:'Notificações',schedules:'Somente leitura',ticket_simulations:'Simulador de bilhetes',support_requests:'Ajuda e suporte'};
let adminEntity='users', adminTab='dados';

const rawTime = v => v ? new Date(String(v).replace(' ','T')+(String(v).includes('Z')?'':'Z')) : null;
function canon(k,v) { // forma canônica só para comparar "valor salvo" x "valor do formulário"
  if (v===null || v===undefined || v==='') return '';
  if (k==='scheduled_at') { const d=rawTime(v); return d && !isNaN(d) ? d.toISOString().slice(0,19) : String(v); }
  if (['capacity_tons','latitude','longitude'].includes(k)) return String(Number(v));
  if (k==='station_id') return String(Number(v));
  return String(v);
}
function adminFieldDefs(entity, stations) {
  const T = adminTables[entity];
  const defs = {
    users:[['name','Nome'],['job_title','Cargo'],['role','Papel',roleOptions],['status','Status',Object.entries(enumLabels.userStatus)]],
    trains:[['name','Modelo / nome'],['status','Status',statuses],['capacity_tons','Capacidade (toneladas)',null,'number','0.01'],['latitude','Latitude',null,'number','any'],['longitude','Longitude',null,'number','any'],['station_id','Estação atual',[['','Nenhuma'],...stations.map(s=>[s.id,s.name])]]],
    sensors:[['status','Status',Object.entries(enumLabels.sensorStatus)]],
    maintenances:[['status','Status',Object.entries(enumLabels.maintStatus)],['notes','Observações',null,'textarea'],['scheduled_at','Data programada',null,'datetime-local']],
    alerts:[['status','Status',Object.entries(enumLabels.alertStatus)],['severity','Severidade',Object.entries(enumLabels.severity)],['message','Mensagem',null,'textarea']]
  }[entity];
  return defs.map(([key,label,opts,type,step])=>({key,label,opts,type:type||'text',step,column:`${T}.${key}`}));
}
function adminFmt(entity, key, v, ctx) { // valor legível para o usuário
  if (v===null || v===undefined || v==='') return '—';
  if (key==='role') return enumLabels.role[v]||v;
  if (key==='severity') return enumLabels.severity[v]||v;
  if (key==='status') return (adminStatusLabels[entity]||{})[v]||v;
  if (key==='station_id') return ctx.stations.find(s=>s.id==v)?.name||`estação #${v}`;
  if (key==='scheduled_at') return date(v);
  if (key==='capacity_tons') return `${v} t`;
  return String(v);
}
function changesList(entity, id, changes, ctx) {
  return `<ul class="change-list">${changes.map(c=>`<li><code>${esc(adminTables[entity]||entity)}.${esc(c.field)}</code> <span class="chg-before">${esc(adminFmt(entity,c.field,c.before,ctx))}</span> <b>→</b> <span class="chg-after">${esc(adminFmt(entity,c.field,c.after,ctx))}</span></li>`).join('')}</ul>`;
}
function dbBanner(text) { return `<div class="db-banner"><strong>Banco de dados</strong><span>${text}</span></div>`; }

async function admin() {
  const onlyUsers = page==='usuarios';
  if (onlyUsers) { adminEntity='users'; adminTab='dados'; }
  const [users, overview, map] = await Promise.all([api('users'), api('admin/overview'), api('map')]);
  const ctx = {stations: map.stations};
  app.innerHTML = head(
      onlyUsers ? 'Gestão de usuários' : 'Painel Admin',
      onlyUsers ? 'Altere papel e status de contas. Cada alteração é gravada na tabela users e registrada na auditoria.' : 'Área exclusiva do Super Admin. Tudo que você salvar aqui é gravado no MySQL e fica registrado na aba Auditoria.'
    ) +
    `<div class="metrics-grid">${[['Usuários',overview.users],['Trens',overview.trains],['Sensores',overview.sensors],['Alertas ativos',overview.alerts]].map(([k,v])=>`<div class="card metric-card"><span>${k}</span><strong>${num(v)}</strong></div>`).join('')}</div>` +
    (onlyUsers ? '' : `<div class="admin-tabs toolbar" role="tablist">${adminTabs.map(([k,v])=>`<button class="button ${k===adminTab?'primary':'secondary'}" role="tab" aria-selected="${k===adminTab}" data-admin-tab="${k}">${v}</button>`).join('')}</div>`) +
    `<div id="admin-body"></div>`;
  app.querySelectorAll('[data-admin-tab]').forEach(b => b.onclick = () => { state.pauseRefresh=false; adminTab=b.dataset.adminTab; admin(); });
  const body = document.getElementById('admin-body');
  if (adminTab==='dados') await adminDataTab(body, {users, overview, trains:null, ctx, onlyUsers});
  else if (adminTab==='config') await adminSettingsTab(body);
  else if (adminTab==='acesso') await adminAccessTab(body);
  else if (adminTab==='banco') await adminSystemTab(body);
  else adminAuditTab(body, overview, ctx);
}

async function adminDataTab(body, {users, overview, ctx, onlyUsers}) {
  const [trains, sensors] = await Promise.all([api('trains'), api('sensors')]);
  const groups = {users, trains, sensors, maintenances: overview.maintenances, alerts: overview.alerts_list};
  const cols = {
    users:[['ID',x=>x.id],['Nome',x=>esc(x.name)],['E-mail',x=>esc(x.email)],['Papel',x=>esc(enumLabels.role[x.role]||x.role)],['Status',x=>statusPill('users',x.status)]],
    trains:[['ID',x=>x.id],['Prefixo',x=>esc(x.code)],['Modelo',x=>esc(x.name)],['Status',x=>statusPill('trains',x.status)],['Estação',x=>esc(x.station_name||'—')]],
    sensors:[['ID',x=>x.id],['Código',x=>esc(x.code)],['Tipo',x=>esc(x.type)],['Trem',x=>esc(x.train_code)],['Status',x=>statusPill('sensors',x.status)]],
    maintenances:[['ID',x=>x.id],['Trem',x=>esc(x.train_code)],['Título',x=>esc(x.title)],['Programada',x=>date(x.scheduled_at)],['Status',x=>statusPill('maintenances',x.status)]],
    alerts:[['ID',x=>x.id],['Título',x=>esc(x.title)],['Severidade',x=>esc(enumLabels.severity[x.severity]||x.severity)],['Status',x=>statusPill('alerts',x.status)]]
  };
  const entities = onlyUsers ? ['users'] : Object.keys(adminNames);
  const c = cols[adminEntity];
  body.innerHTML =
    (onlyUsers ? '' : `<div class="admin-tabs toolbar">${entities.map(k=>`<button class="button ${k===adminEntity?'primary':'secondary'}" data-entity="${k}">${adminNames[k]}</button>`).join('')}</div>`) +
    dbBanner(`Editar um registro abaixo altera a tabela <code>${adminTables[adminEntity]}</code>. Campos editáveis: ${adminFieldDefs(adminEntity, ctx.stations).map(f=>`<code>${f.key}</code>`).join(', ')}. Os demais campos são somente leitura.`) +
    card(adminNames[adminEntity], managementTable([...c.map(x=>x[0]),'Ação'], groups[adminEntity].map(x => [...c.map(([,fn])=>fn(x)), `<button class="text-link" data-admin-edit="${x.id}">Editar</button>`])), `<span class="table-tag">tabela ${adminTables[adminEntity]}</span>`) +
    `<div id="admin-editor"></div>`;
  body.querySelectorAll('[data-entity]').forEach(b => b.onclick = () => { state.pauseRefresh=false; adminEntity=b.dataset.entity; admin(); });
  body.querySelectorAll('[data-admin-edit]').forEach(b => b.onclick = () =>
    adminEdit(adminEntity, groups[adminEntity].find(x => x.id == b.dataset.adminEdit), ctx)
  );
}
function statusPill(entity, v) {
  const tone = {operating:'normal',active:'normal',completed:'normal',resolved:'normal',maintenance:'attention',warning:'attention',pending:'attention',in_progress:'attention',stopped:'critical',inactive:'critical',offline:'critical',disabled:'critical',banned:'critical',cancelled:'critical'}[v]||'normal';
  return managementBadge((adminStatusLabels[entity]||{})[v]||v||'—', tone);
}

function adminEdit(entity, item, ctx) {
  state.pauseRefresh = true;
  const panel = document.getElementById('admin-editor');
  const defs = adminFieldDefs(entity, ctx.stations);
  const who = item.name||item.code||item.title||`#${item.id}`;
  const input = d => {
    const cur = d.key==='scheduled_at' ? localDateTimeInput(item[d.key]) : (item[d.key]??'');
    const hint = `<small class="col-hint">grava em <code>${esc(d.column)}</code></small>`;
    if (d.opts) return `<label class="field"><span>${esc(d.label)}</span><select name="${d.key}">${d.opts.map(([v,t])=>`<option value="${esc(v)}" ${String(v)===String(cur)?'selected':''}>${esc(t)}</option>`).join('')}</select>${hint}</label>`;
    if (d.type==='textarea') return `<label class="field"><span>${esc(d.label)}</span><textarea name="${d.key}" rows="3" maxlength="3000">${esc(cur)}</textarea>${hint}</label>`;
    return `<label class="field"><span>${esc(d.label)}</span><input name="${d.key}" type="${d.type}" value="${esc(cur)}" ${d.type==='number'?`step="${d.step||'any'}"`:''}>${hint}</label>`;
  };
  panel.innerHTML = card(`Editando ${adminNames[entity].toLowerCase()} · ${who}`,
    `<p class="data-note">Registro <code>${adminTables[entity]}</code> id ${item.id}. Altere o que precisar: só as colunas modificadas serão gravadas.</p>
     <form id="admin-form" class="form-stack"><div class="form-two">${defs.map(input).join('')}</div>
     <div class="toolbar"><button class="button primary">Revisar alterações</button><button type="button" class="button secondary" id="cancel-admin">Cancelar</button></div></form><div id="admin-review"></div>`
  );
  panel.scrollIntoView({ behavior: 'smooth' });
  const close = () => { state.pauseRefresh = false; panel.innerHTML = ''; };
  document.getElementById('cancel-admin').onclick = close;
  onForm('admin-form', async (d, form) => {
    const payload = {}, changes = [];
    for (const f of defs) {
      let v = d[f.key] ?? '';
      if (f.key==='scheduled_at') {
        if (!v) throw Error('Informe a data programada.');
        v = new Date(v).toISOString().slice(0,19).replace('T',' ');
      } else if (f.type==='number') v = v==='' ? null : Number(v);
      else if (f.key==='station_id') v = v==='' ? null : Number(v);
      if (canon(f.key, v) === canon(f.key, item[f.key])) continue;
      payload[f.key] = v;
      changes.push({field:f.key, before:item[f.key]??null, after:v});
    }
    const review = document.getElementById('admin-review');
    if (!changes.length) { review.innerHTML = `<p class="empty-state">Nada foi alterado. Nenhuma gravação será feita no banco.</p>`; return; }
    review.innerHTML = `<div class="review-box"><h3>Confirme o que será gravado no banco</h3><p class="data-note">Tabela <code>${adminTables[entity]}</code>, registro id ${item.id}:</p>${changesList(entity,item.id,changes,ctx)}<div class="toolbar"><button class="button primary" id="confirm-admin">Confirmar e gravar</button><button class="button secondary" id="back-admin" type="button">Voltar</button></div></div>`;
    document.getElementById('back-admin').onclick = () => { review.innerHTML = ''; };
    document.getElementById('confirm-admin').onclick = async e => {
      e.target.disabled = true;
      try {
        const r = await api(`admin/${entity}/${item.id}`, 'PATCH', payload);
        state.pauseRefresh = false;
        await admin();
        showSaved(r, entity, ctx);
      } catch (err) { e.target.disabled = false; notice(err.message, true); }
    };
  });
}
function showSaved(r, entity, ctx) {
  const el = document.getElementById('notice'); if (!el) return;
  if (!r.changes?.length) { notice(r.message); return; }
  el.className = 'notice success';
  el.innerHTML = `<strong>${esc(r.message)}</strong> Gravado em <code>${esc(r.table)}</code>${r.id?` (id ${r.id})`:''}:${changesList(entity, r.id, r.changes, ctx)}`;
  el.hidden = false;
  window.scrollTo({top:0,behavior:'smooth'});
}

async function adminSettingsTab(body) {
  const settings = await api('admin/settings');
  const ctx = {stations:[]};
  body.innerHTML = dbBanner('Cada configuração abaixo é uma linha da tabela <code>app_settings</code>. Ao salvar, o valor muda imediatamente para todos os usuários.') +
    card('Configurações do sistema', settings.filter(s=>settingMeta[s.key]).map(s => {
      const m = settingMeta[s.key];
      if (m.kind==='toggle') {
        const on = s.value==='true';
        return `<form class="setting-editor" data-setting="${esc(s.key)}" data-kind="toggle"><div><strong>${m.label}</strong><small>${m.help}</small><small class="col-hint"><code>app_settings.${esc(s.key)}</code> · valor atual: <b>${on?'ligado':'desligado'}</b></small></div><label class="check"><input type="checkbox" name="value" ${on?'checked':''}> ${on?'Ligado':'Desligado'}</label><button class="button secondary">Salvar</button></form>`;
      }
      return `<form class="setting-editor" data-setting="${esc(s.key)}" data-kind="number"><div><strong>${m.label}</strong><small>${m.help}</small><small class="col-hint"><code>app_settings.${esc(s.key)}</code> · valor atual: <b>${esc(s.value)} ${m.unit}</b> · permitido ${num(m.min)} a ${num(m.max)}</small></div><span class="unit-input"><input name="value" type="number" min="${m.min}" max="${m.max}" step="1" value="${esc(s.value)}" aria-label="${m.label}" required> ${m.unit}</span><button class="button secondary">Salvar</button></form>`;
    }).join('') || empty());
  body.querySelectorAll('[data-setting]').forEach(f => f.onsubmit = async e => {
    e.preventDefault();
    const key = f.dataset.setting, toggle = f.dataset.kind==='toggle';
    const value = toggle ? String(f.elements.value.checked) : f.elements.value.value;
    if (key==='maintenance_mode' && value==='true' && !confirm('Ligar o modo de manutenção bloqueia Operadores e Gerentes em toda a plataforma. Só o Super Admin continua com acesso. Confirmar?')) return;
    try {
      const r = await api('admin/settings', 'PATCH', {key, value});
      await admin();
      const el = document.getElementById('notice');
      if (el && r.changes?.length) {
        el.className='notice success'; el.hidden=false;
        const c = r.changes[0], m = settingMeta[key];
        const fmt = v => key==='maintenance_mode' ? (v==='true'?'ligado':'desligado') : `${v} ${m.unit}`;
        el.innerHTML = `<strong>${esc(r.message)}</strong> <code>app_settings.${esc(key)}</code>: <span class="chg-before">${esc(fmt(c.before))}</span> <b>→</b> <span class="chg-after">${esc(fmt(c.after))}</span>`;
      } else notice(r.message);
    } catch (err) { notice(err.message, true); }
  });
}

async function adminAccessTab(body) {
  const a = await api('admin/access');
  const roles = Object.entries(a.roles);
  body.innerHTML = dbBanner('Esta aba é somente leitura: mostra o que cada papel pode fazer e quais tabelas cada ação altera. Para mudar o papel de alguém, use Dados → Usuários.') +
    card('Acesso por papel', `<div class="table-wrap"><table class="access-table"><thead><tr><th>O que a pessoa pode fazer</th>${roles.map(([k,v])=>`<th class="${k===a.current_role?'is-me':''}">${esc(v)}${k===a.current_role?' <small>(você)</small>':''}</th>`).join('')}<th>Tabelas afetadas</th></tr></thead><tbody>${a.matrix.map(([cap,nota,papeis,tabelas])=>`<tr><td><strong>${esc(cap)}</strong><br><small>${esc(nota)}</small></td>${roles.map(([k])=>`<td class="${papeis.includes(k)?'yes':'no'}" aria-label="${papeis.includes(k)?'Permitido':'Sem acesso'}">${papeis.includes(k)?'✔':'—'}</td>`).join('')}<td><code>${esc(tabelas)}</code></td></tr>`).join('')}</tbody></table></div>`) +
    `<p class="data-note">As regras vêm de <code>includes/permissao.php</code> (menu e páginas) e são reforçadas na API: nenhuma tela substitui a checagem do servidor.</p>`;
}

async function adminSystemTab(body) {
  const s = await api('admin/system');
  const d = s.database;
  const rows = [['Status da conexão', d.connected ? managementBadge('Conectado','normal') : managementBadge('Sem conexão','critical')],['Servidor MySQL', `<code>${esc(d.host)}</code>`],['Banco', `<code>${esc(d.name)}</code>`],['Usuário do banco', `<code>${esc(d.user)}</code> <small>(a senha nunca é exibida)</small>`],['Versão do MySQL', esc(d.version)],['Fuso do banco', esc(d.timezone)+' (a tela converte para o seu fuso)'],['Ambiente', esc(s.environment)],['Modo de manutenção', s.maintenance_mode ? managementBadge('Ligado','attention') : managementBadge('Desligado','normal')],['Hora do servidor (UTC)', date(s.server_time_utc)]];
  body.innerHTML = dbBanner('Conexão usada pela API (configurada em <code>config/local.php</code> ou variáveis de ambiente). Abaixo, quantas linhas cada tabela tem e quem pode alterá-la.') +
    card('Conexão com o banco', `<dl class="kv">${rows.map(([k,v])=>`<div><dt>${k}</dt><dd>${v}</dd></div>`).join('')}</dl>`) +
    card('Tabelas do banco', managementTable(['Tabela','Linhas','Quem altera'], s.tables.map(t=>[`<code>${esc(t.table)}</code>`, num(t.rows), esc(tableOwners[t.table]||'—')])));
}

function adminAuditTab(body, overview, ctx) {
  const parse = v => { try { return typeof v==='string' ? JSON.parse(v) : v; } catch { return null; } };
  const detail = x => {
    const d = parse(x.details) || {};
    const entries = Object.entries(d.changes || {});
    if (entries.length) return `<ul class="change-list">${entries.map(([f,c])=>`<li><code>${esc(x.entity)}.${esc(f)}</code> <span class="chg-before">${esc(adminFmt(x.entity,f,c.before,ctx))}</span> <b>→</b> <span class="chg-after">${esc(adminFmt(x.entity,f,c.after,ctx))}</span></li>`).join('')}</ul>`;
    const flat = Object.keys(d).length ? JSON.stringify(d) : '';
    return flat ? `<small>${esc(flat.length>120?flat.slice(0,120)+'…':flat)}</small>` : '<small>—</small>';
  };
  body.innerHTML = dbBanner('Histórico automático de tudo que foi gravado (tabela <code>audit_logs</code>, somente leitura). Mostra as 40 ações mais recentes.') +
    card('Atividade recente', managementTable(['Data','Quem','Ação','Registro','O que mudou no banco'], overview.logs.map(x=>[
      date(x.created_at), esc(x.actor_name||'Sistema'), esc(actionLabels[x.action]||x.action), `<code>${esc(x.entity)}${x.entity_id?' #'+esc(x.entity_id):''}</code>`, detail(x)
    ])));
}

const routes={dashboard,trens:trains,trens_form:trainFormPage,sensores:sensorsPage,sensores_form:sensorFormPage,rotas:routesPage,rotas_form:routeFormPage,leituras:readingsPage,simulador:simulatorPage,notificacoes_alertas:operationalAlertsPage,alertas:alertsPage,localizacao:mapPage,bilhetes:tickets,relatorios:reports,notificacoes:notifications,perfil:profile,configuracoes:settings,ajuda:help,usuarios:admin,admin};
async function load() {
  try {
    if (['login', 'cadastro', 'recuperar', 'redefinir'].includes(page)) await auth();
    else if (routes[page]) await routes[page]();
    else if (page === 'sem-acesso') app.innerHTML = head('Sem acesso', `Seu papel (${(roleOptions.find(r=>r[0]===user?.role)||[])[1]||'—'}) não pode abrir esta página. Peça a um Super Admin para ajustar seu papel.`) + `<a class="button primary" href="${root}">Voltar ao início</a>`;
    else app.innerHTML = head('Página indisponível', 'Confira o endereço ou suas permissões.');
  } catch (e) {
    app.innerHTML = head('Não foi possível carregar', e.message);
  }
}
document.getElementById('logout')?.addEventListener('click', async () => {
  try {
    await api('auth/logout', 'POST');
    location.href = link('login');
  } catch (e) {
    notice(e.message, true);
  }
});
document.getElementById('menu')?.addEventListener('click', () =>
  document.getElementById('sidebar').classList.toggle('is-open')
);
load();
if (routes[page]) {
  setInterval(async () => {
    if (
      document.visibilityState !== 'visible' ||
      !navigator.onLine ||
      state.pauseRefresh ||
      Date.now() - (state.lastActivity || 0) < 10000 ||
      document.activeElement?.matches('input,textarea,select')
    ) return;
    try {
      const session = await api('auth/me');
      csrf = session.csrf;
      user = session.user;
      const restricted = JSON.parse(document.body.dataset.restricted || '{}');
      if (restricted[page] && !restricted[page].includes(user.role)) {
        location.href = root;
        return;
      }
      await routes[page]();
    } catch (e) {
      if (e.message.includes('Sessão')) location.href = link('login');
    }
  }, 5000);
  window.addEventListener('focus', () => {
    if (!state.pauseRefresh && !document.activeElement?.matches('input,textarea,select')) load();
  });
  document.addEventListener('pointerdown', () => { state.lastActivity = Date.now(); });
  document.addEventListener('keydown', () => { state.lastActivity = Date.now(); });
}
