(() => {
  const sidebar = document.getElementById('sidebar');
  if (!sidebar) return;
  const root = document.documentElement;
  const menuBtn = document.getElementById('menu');
  const overlay = document.getElementById('nav-overlay');
  const collapseBtn = document.getElementById('nav-collapse');
  const pill = document.getElementById('live-pill');
  const mobile = window.matchMedia('(max-width: 900px)');

  const safe = fn => { try { return fn(); } catch (e) { return null; } };

  // Recolher/expandir (desktop), lembrado no navegador
  const syncCollapse = () => {
    const collapsed = root.classList.contains('nav-collapsed');
    collapseBtn?.setAttribute('aria-pressed', String(collapsed));
    collapseBtn?.setAttribute('aria-label', collapsed ? 'Expandir menu' : 'Recolher menu');
  };
  collapseBtn?.addEventListener('click', () => {
    root.classList.toggle('nav-collapsed');
    safe(() => localStorage.setItem('atrain.nav', root.classList.contains('nav-collapsed') ? 'collapsed' : 'open'));
    syncCollapse();
  });
  syncCollapse();

  // Mobile: o toggle de #menu é feito em app.js; aqui só sincronizamos estado/acessibilidade
  const closeMenu = () => sidebar.classList.remove('is-open');
  const syncOpen = () => {
    const open = sidebar.classList.contains('is-open');
    menuBtn?.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open && mobile.matches ? 'hidden' : '';
  };
  new MutationObserver(syncOpen).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
  overlay?.addEventListener('click', closeMenu);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });
  sidebar.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));
  mobile.addEventListener?.('change', () => { closeMenu(); syncOpen(); });

  // Indicador de conexão
  const setOnline = online => {
    if (!pill) return;
    pill.classList.toggle('offline', !online);
    const label = pill.querySelector('b');
    if (label) label.textContent = online ? 'Dados atualizados' : 'Sem conexão';
  };
  window.addEventListener('online', () => setOnline(true));
  window.addEventListener('offline', () => setOnline(false));
  setOnline(navigator.onLine);

  // Mantém o item ativo visível no menu
  sidebar.querySelector('.nav-link.active')?.scrollIntoView({ block: 'nearest' });
})();
