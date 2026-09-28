const root = document.body.dataset.base;
const page = document.body.dataset.page;
let csrf = document.body.dataset.csrf;
let user = JSON.parse(document.body.dataset.user || 'null');
const app = document.getElementById('app');
const state = {trainQ:'',trainStatus:'',period:'30d',origin:'',destination:'',report:null,notifTab:'all'};
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
function field(label,name,value='',type='text',extra='') { return `<label class="field"><span>${esc(label)}</span><input name="${esc(name)}" type="${type}" value="${esc(value)}" ${extra}></label>`; }
function select(label,name,options,value='') { return `<label class="field"><span>${esc(label)}</span><select name="${esc(name)}">${options.map(([v,t])=>`<option value="${esc(v)}" ${String(v)===String(value)?'selected':''}>${esc(t)}</option>`).join('')}</select></label>`; }
function formData(form) { return Object.fromEntries(new FormData(form).entries()); }
function onForm(id, fn) { document.getElementById(id)?.addEventListener('submit',async e=>{e.preventDefault();try{await fn(formData(e.currentTarget),e.currentTarget);}catch(err){notice(err.message,true);}}); }
const option = (a,label='name') => a.map(x=>[x.id,x[label]]);
const statuses = [['operating','Operando'],['maintenance','Manutenção'],['stopped','Parado'],['inactive','Inativo']];
const roleOptions=[['operator','Operador'],['manager','Gerente'],['super_admin','Super Admin']];
const statusBadge = v => `<span class="badge">${esc(v)}</span>`;
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
const routes={};
async function load(){try{if(['login','cadastro','recuperar','redefinir'].includes(page))await auth();else if(routes[page])await routes[page]();else app.innerHTML=head('Página indisponível','Confira o endereço ou suas permissões.');}catch(e){app.innerHTML=head('Não foi possível carregar',e.message);}}
document.getElementById('logout')?.addEventListener('click',async()=>{try{await api('auth/logout','POST');location.href=link('login')}catch(e){notice(e.message,true)}});
document.getElementById('menu')?.addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('is-open'));
load();
if(routes[page]){setInterval(async()=>{if(document.visibilityState!=='visible'||!navigator.onLine||state.pauseRefresh||Date.now()-(state.lastActivity||0)<10000||document.activeElement?.matches('input,textarea,select'))return;try{const session=await api('auth/me');csrf=session.csrf;user=session.user;if(['admin','usuarios'].includes(page)&&user.role!=='super_admin'){location.href=root;return;}await routes[page]()}catch(e){if(e.message.includes('Sessão'))location.href=link('login')}},5000);window.addEventListener('focus',()=>{if(!state.pauseRefresh&&!document.activeElement?.matches('input,textarea,select'))load()});document.addEventListener('pointerdown',()=>{state.lastActivity=Date.now()});document.addEventListener('keydown',()=>{state.lastActivity=Date.now()});}
