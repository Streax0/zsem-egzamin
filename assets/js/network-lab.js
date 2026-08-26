/* ══════════════════════════════════════════
   STATE
══════════════════════════════════════════ */
var RS = {
  wan:  { type:'static', ip:'', mask:'', gw:'', dns1:'', dns2:'' },
  lan:  { ip:'', mask:'' },
  dhcp: { on:true, start:'', end:'', gw:'', dns:'', lease:'' },
  vlans: {1:'Default'}
};
var SS = {
  mgmt: { ip:'', mask:'', gw:'' },
  vlans: {1:'Default'},
  ports: {}
};
for (var _p=1;_p<=8;_p++) SS.ports[_p]={mode:'access',vlan:1};

var SESSIONS = {
  'cke': {
    label:'Przykład CKE',
    router:{
      wan_ip:'89.123.45.2', wan_mask:'255.255.255.252', wan_gw:'89.123.45.1', wan_dns1:'8.8.8.8',
      lan_ip:'192.168.1.1', lan_mask:'255.255.255.0',
      dhcp_on:true, dhcp_start:'192.168.1.100', dhcp_end:'192.168.1.200'
    },
    sw:{
      ip:'192.168.1.2', mask:'255.255.255.0', gw:'192.168.1.1',
      vlans:{10:'VLAN10',20:'VLAN20'},
      ports:{1:{mode:'trunk'},2:{mode:'access',vlan:10},3:{mode:'access',vlan:10},
             4:{mode:'access',vlan:10},5:{mode:'access',vlan:10},6:{mode:'access',vlan:10},
             7:{mode:'access',vlan:20},8:{mode:'access',vlan:20}}
    }
  },
  '2021-cze': {
    label:'2021 – sesja letnia',
    router:{
      wan_ip:'80.80.80.5', wan_mask:'255.255.255.248', wan_gw:'80.80.80.1', wan_dns1:'8.8.8.8',
      lan_ip:'172.20.0.1', lan_mask:'255.255.255.0',
      dhcp_on:true, dhcp_start:'172.20.0.100', dhcp_end:'172.20.0.149'
    },
    sw:{ ip:'172.20.0.2', mask:'255.255.255.0', gw:'172.20.0.1' }
  },
  '2022-sty': {
    label:'2022 – sesja zimowa',
    router:{
      wan_ip:'30.30.20.5', wan_mask:'255.255.255.248', wan_gw:'30.30.20.1', wan_dns1:'5.5.8.8',
      lan_ip:'192.168.10.1', lan_mask:'255.255.255.0',
      dhcp_on:true, dhcp_start:'192.168.10.14', dhcp_end:'192.168.10.34'
    },
    sw:{ ip:'192.168.10.2', mask:'255.255.255.0', gw:'192.168.10.1' }
  },
  '2022-cze': {
    label:'2022 – sesja letnia',
    router:{
      wan_ip:'90.90.90.1', wan_mask:'255.255.255.252', wan_gw:'90.90.90.2', wan_dns1:'8.8.8.8',
      lan_ip:'10.0.0.1', lan_mask:'255.255.255.0',
      dhcp_on:false
    },
    sw:null
  },
  '2023-sty': {
    label:'2023 – sesja zimowa',
    router:{
      wan_ip:'100.100.0.2', wan_mask:'255.255.255.224', wan_gw:'100.100.0.1', wan_dns1:'8.8.3.3',
      lan_ip:'192.168.0.1', lan_mask:'255.255.255.0',
      dhcp_on:true, dhcp_start:'192.168.0.79', dhcp_end:'192.168.0.99'
    },
    sw:{ ip:'192.168.0.3', mask:'255.255.255.0', gw:'192.168.0.1' }
  },
  '2023-cze': {
    label:'2023 – sesja letnia',
    router:{
      wan_ip:'20.20.20.2', wan_mask:'255.255.255.240', wan_gw:'20.20.20.1', wan_dns1:'8.8.8.8',
      lan_ip:'172.31.3.1', lan_mask:'255.255.255.0',
      dhcp_on:true, dhcp_start:'172.31.3.20', dhcp_end:'172.31.3.200'
    },
    sw:{
      ip:'172.31.3.2', mask:'255.255.255.0', gw:'172.31.3.1',
      vlans:{2:'VLAN2'},
      ports:{1:{mode:'access',vlan:2},2:{mode:'access',vlan:2},3:{mode:'access',vlan:2}}
    }
  },
  '2024-sty': {
    label:'2024 – sesja zimowa',
    router:{
      wan_ip:'72.16.31.1', wan_mask:'255.255.255.192', wan_gw:'72.16.31.62', wan_dns1:'6.6.9.9',
      lan_ip:'172.31.0.1', lan_mask:'255.255.255.0',
      dhcp_on:false
    },
    sw:{ ip:'10.100.100.2', mask:'255.255.255.0', gw:'10.100.100.1' }
  },
  '2024-cze': {
    label:'2024 – sesja letnia',
    router:{
      wan_ip:'75.75.75.1', wan_mask:'255.255.0.0', wan_gw:'75.75.75.2', wan_dns1:'5.5.7.7',
      lan_ip:'10.0.0.1', lan_mask:'255.0.0.0',
      dhcp_on:false,
      vlans:{1:{ip:'10.0.0.1',mask:'255.0.0.0'},2:{ip:'172.16.0.1',mask:'255.255.0.0'},3:{ip:'192.168.0.1',mask:'255.255.255.0'}}
    },
    sw:{
      ip:'10.0.0.2', mask:'255.0.0.0', gw:'10.0.0.1',
      vlans:{2:'VLAN2',3:'VLAN3'},
      ports:{1:{mode:'trunk'},2:{mode:'access',vlan:2},3:{mode:'access',vlan:3}}
    }
  },
  '2025-cze': {
    label:'2025 – sesja letnia',
    router:{
      wan_ip:'100.100.100.9', wan_mask:'255.255.255.240', wan_gw:'100.100.100.1', wan_dns1:'4.4.4.4',
      lan_ip:'172.16.0.1', lan_mask:'255.255.255.0',
      dhcp_on:false
    },
    sw:{
      ip:'172.16.0.2', mask:'255.255.255.0', gw:'172.16.0.1',
      vlans:{2:'VLAN2'},
      ports:{1:{mode:'access',vlan:2},2:{mode:'access',vlan:2},3:{mode:'access',vlan:2},4:{mode:'access',vlan:2}}
    }
  }
};
var currentSessionKey = '2025-cze';

/* ══════════════════════════════════════════
   PDF PANEL
══════════════════════════════════════════ */
var PDF_URLS = {
  'cke': 'sandbox_network_pdf.php?session=cke',
  '2025-cze': 'sandbox_network_pdf.php?session=2025-cze',
  '2024-cze': 'sandbox_network_pdf.php?session=2024-cze',
  '2024-sty': 'sandbox_network_pdf.php?session=2024-sty',
  '2023-cze': 'sandbox_network_pdf.php?session=2023-cze',
  '2023-sty': 'sandbox_network_pdf.php?session=2023-sty',
  '2022-cze': 'sandbox_network_pdf.php?session=2022-cze',
  '2022-sty': 'sandbox_network_pdf.php?session=2022-sty',
  '2021-cze': 'sandbox_network_pdf.php?session=2021-cze'
};
window.RS = RS;
window.SS = SS;
window.SESSIONS = SESSIONS;
window.PDF_URLS = PDF_URLS;
function resolveLabAssetUrl(path) {
  try {
    return new URL(path, window.location.href).href;
  } catch(e) {
    return path;
  }
}
function loadPDF(key) {
  currentSessionKey = key;
  var url = resolveLabAssetUrl(PDF_URLS[key] || PDF_URLS['2025-cze']);
  document.getElementById('pdf-loading').classList.remove('hidden');
  var frame = document.getElementById('pdf-frame');
  if (frame) {
    frame.onload = hidePdfLoading;
    frame.onerror = showPdfFallback;
    frame.src = url;
  }
  document.getElementById('pdf-ext').href = url;
  document.getElementById('pdf-fallback-btn').href = url;
  loadState(key);
}
function showPdfFallback() {
  var loading = document.getElementById('pdf-loading');
  if (loading) loading.classList.remove('hidden');
}
function hidePdfLoading() {
  var loading = document.getElementById('pdf-loading');
  if (loading) loading.classList.add('hidden');
}

function labEsc(value) {
  return String(value == null ? '' : value).replace(/[&<>"']/g, function(ch) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch];
  });
}
function showLabNotice(message, title) {
  var modal = document.getElementById('modal');
  var body = document.getElementById('modal-body');
  if (!modal || !body) return;
  body.innerHTML = '<div class="lab-dialog-message">'
    + '<strong>' + labEsc(title || 'Komunikat') + '</strong>'
    + '<p>' + labEsc(message) + '</p>'
    + '<button type="button" class="modal-close-btn" id="lab-notice-ok">Zamknij</button>'
    + '</div>';
  modal.classList.add('vis');
  document.getElementById('lab-notice-ok').addEventListener('click', function() {
    modal.classList.remove('vis');
  });
}
function showLabConfirm(message, onConfirm) {
  var modal = document.getElementById('modal');
  var body = document.getElementById('modal-body');
  if (!modal || !body) return;
  body.innerHTML = '<div class="lab-dialog-message">'
    + '<strong>Potwierdzenie</strong>'
    + '<p>' + labEsc(message) + '</p>'
    + '<div class="lab-dialog-actions">'
    + '<button type="button" class="cbtn" id="lab-reset-cancel">Anuluj</button>'
    + '<button type="button" class="cbtn cbtn-primary" id="lab-reset-confirm">Resetuj</button>'
    + '</div></div>';
  modal.classList.add('vis');
  document.getElementById('lab-reset-cancel').addEventListener('click', function() {
    modal.classList.remove('vis');
  });
  document.getElementById('lab-reset-confirm').addEventListener('click', function() {
    modal.classList.remove('vis');
    if (typeof onConfirm === 'function') onConfirm();
  });
}
function requestResetAll() {
  showLabConfirm('Wyczyścić całą wprowadzoną konfigurację dla tej sesji?', function() {
    window.__networkLabResetConfirmed = true;
    try {
      resetAll();
    } finally {
      window.__networkLabResetConfirmed = false;
    }
  });
}
function actionText(el) {
  return (el.getAttribute('aria-label') || el.getAttribute('title') || el.textContent || el.value || 'Opcja')
    .replace(/\s+/g, ' ')
    .trim();
}
function simulatedActionMessage(label) {
  var text = label.toLowerCase();
  if (/help|pomoc/.test(text)) return 'Pomoc tej funkcji jest częścią symulatora.';
  if (/about|informacje/.test(text)) return 'Informacje o urządzeniu są widoczne w nagłówku panelu.';
  if (/refresh|odświe/.test(text)) return 'Widok odświeżony.';
  if (/detect|wykry/.test(text)) return 'Automatyczne wykrywanie zasymulowane.';
  if (/generate|generuj/.test(text)) return 'Wartość wygenerowana w symulatorze.';
  if (/delete all|remove all|clear/.test(text)) return 'Lista jest pusta albo akcja została zasymulowana.';
  if (/add new|add|new/.test(text)) return 'Dodawanie zasymulowane dla tej opcji urządzenia.';
  if (/load/.test(text)) return 'Dane wczytane do symulowanego widoku.';
  if (/save|apply|ok/.test(text)) return 'Ustawienia zapisane w symulatorze.';
  return 'Symulacja: ' + label;
}
function isStaticLogoutAction(label) {
  return /^(log\s*out|logout|wyloguj|wylogowanie)$/i.test(String(label || '').trim());
}
function dynamicFieldStorageKey(el) {
  var key = '';
  if (el) {
    key = el.id || (el.type === 'radio' && el.name ? el.name + ':' + el.value : el.name);
  }
  if (!key) return '';
  return 'inf02_field_' + currentSessionKey + '_' + key;
}
function rememberDynamicField(el) {
  var key = dynamicFieldStorageKey(el);
  if (!key || el.readOnly || el.disabled) return;
  try {
    localStorage.setItem(key, (el.type === 'checkbox' || el.type === 'radio') ? (el.checked ? '1' : '0') : el.value);
  } catch(e) {}
}
function restoreDynamicFields(scope) {
  if (!scope) return;
  scope.querySelectorAll('input, select, textarea').forEach(function(el) {
    var key = dynamicFieldStorageKey(el);
    if (!key || el.readOnly) return;
    try {
      var saved = localStorage.getItem(key);
      if (saved == null) return;
      if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = saved === '1';
      } else {
        el.value = saved;
      }
    } catch(e) {}
  });
}
function bindDynamicFieldMemory() {
  var handle = function(e) {
    if (e.target && e.target.matches && e.target.matches('input, select, textarea')) {
      rememberDynamicField(e.target);
    }
  };
  document.addEventListener('input', handle);
  document.addEventListener('change', handle);
  ['ccontent-router','ccontent-switch','tpcontent','mt-wb-content','mt-wf-content'].forEach(function(id) {
    var root = document.getElementById(id);
    if (!root || !window.MutationObserver) return;
    new MutationObserver(function() {
      window.requestAnimationFrame(function() { restoreDynamicFields(root); });
    }).observe(root, {childList:true, subtree:true});
  });
}
function bindNetworkLabFallbackActions() {
  document.addEventListener('click', function(e) {
    var el = e.target.closest('button, a');
    if (!el || el.disabled) return;
    if (el.closest('#modal')) return;
    if (el.id || el.getAttribute('onclick')) return;
    var href = el.getAttribute('href');
    if (el.tagName === 'A' && href && href !== '#') return;
    if (el.classList.contains('dev-tab')) return;
    var label = actionText(el);
    if (isStaticLogoutAction(label)) return;
    e.preventDefault();
    var form = el.closest('form');
    if (form) form.querySelectorAll('input, select, textarea').forEach(rememberDynamicField);
    el.setAttribute('data-sim-action', label);
    toast(simulatedActionMessage(label));
  });
}

/* ── SAVE / LOAD STATE ── */
function saveState() {
  try {
    localStorage.setItem('inf02_'+currentSessionKey, JSON.stringify({
      w:RS.wan, l:RS.lan, d:RS.dhcp, rv:RS.vlans,
      sm:SS.mgmt, sv:SS.vlans, sp:SS.ports
    }));
  } catch(e) {}
}
function loadState(key) {
  try {
    var raw = localStorage.getItem('inf02_'+key);
    if (!raw) return;
    var s = JSON.parse(raw);
    if (s.w) RS.wan = s.w;
    if (s.l) RS.lan = s.l;
    if (s.d) RS.dhcp = s.d;
    if (s.rv) RS.vlans = s.rv;
    if (s.sm) SS.mgmt = s.sm;
    if (s.sv) SS.vlans = s.sv;
    if (s.sp) SS.ports = s.sp;
  } catch(e) {}
}

/* ── RESET ── */
function resetAll() {
  if (!window.__networkLabResetConfirmed) { requestResetAll(); return; }
  RS.wan = {type:'static',ip:'',mask:'',gw:'',dns1:'',dns2:''};
  RS.lan = {ip:'',mask:''};
  RS.dhcp = {on:true,start:'',end:'',gw:'',dns:'',lease:''};
  RS.vlans = {1:'Default'};
  SS.mgmt = {ip:'',mask:'',gw:''};
  SS.vlans = {1:'Default'};
  for (var rp = 1; rp <= 8; rp++) SS.ports[rp] = {mode:'access', vlan:1};
  TPRS.wan = {type:'static',ip:'',mask:'',gw:'',dns1:'',dns2:''};
  TPRS.lan = {ip:'',mask:''};
  TPRS.dhcp = {on:true,start:'',end:'',lease:'120'};
  TPRS.wireless = {ssid:'',channel:'6',mode:'bgn',password:''};
  MTRS.identity = 'MikroTik';
  MTRS.addresses = [{addr:'192.168.88.1/24',net:'192.168.88.0',iface:'bridge'},{addr:'0.0.0.0/0',net:'0.0.0.0',iface:'ether1'}];
  MTRS.dns = {s1:'8.8.8.8',s2:'8.8.4.4',remote:false};
  MTRS.dhcpServers = [{name:'defconf',iface:'bridge',pool:'default-dhcp',lease:'10m',auth:'yes'}];
  MTRS.ipPool = [{name:'default-dhcp',range:'192.168.88.10-192.168.88.254'}];
  MTRS.routes = [];
  MTRS.quickSet = {mode:'Router',acq:'Automatic (DHCP)',ip:'',mask:'/24 (255.255.255.0)',gw:'',dns:'8.8.8.8',lanip:'192.168.88.1',lanmask:'/24 (255.255.255.0)',ssid:'MikroTik',wpass:'',band:'2GHz-B/G/N',freq:'auto'};
  MTRS.switchPorts = [
    {name:'ether1',sw:'switch1',vlan:'1',mode:'fallback',flood:'yes',txRate:'unlimited',rxRate:'unlimited'},
    {name:'ether2',sw:'switch1',vlan:'1',mode:'fallback',flood:'yes',txRate:'unlimited',rxRate:'unlimited'},
    {name:'ether3',sw:'switch1',vlan:'1',mode:'fallback',flood:'yes',txRate:'unlimited',rxRate:'unlimited'},
    {name:'ether4',sw:'switch1',vlan:'1',mode:'fallback',flood:'yes',txRate:'unlimited',rxRate:'unlimited'},
    {name:'ether5',sw:'switch1',vlan:'1',mode:'fallback',flood:'yes',txRate:'unlimited',rxRate:'unlimited'}
  ];
  MTRS.switchVlans = [];
  MTRS.bridgeVlans = [];
  try { localStorage.removeItem('inf02_'+currentSessionKey); } catch(e) {}
  try { localStorage.removeItem('tp_'+currentSessionKey); } catch(e) {}
  try { localStorage.removeItem('mtrs_state'); } catch(e) {}
  if (currentRouterModel==='tplink') { renderTpPage('tp_status'); }
  else if (currentRouterModel==='mikrotik-wb'||currentRouterModel==='mikrotik-wf') { renderMtPage('quickset'); }
  else { renderRouterPage('getstart'); }
  renderSwitchPage('sw_sysinfo');
  toast('↺ Urządzenia zresetowane do stanu fabrycznego');
}

/* ── VLAN CLASS HELPER ── */
function vlanClass(v) {
  var m = {1:'p-unset', 2:'p-v2', 3:'p-v30', 10:'p-v10', 20:'p-v20', 30:'p-v30'};
  return m[v] || (v > 1 ? 'p-v10' : 'p-unset');
}

document.getElementById('exam-sel').addEventListener('change', function(){ loadPDF(this.value); });
loadPDF('2025-cze');

/* ══════════════════════════════════════════
   DEVICE TABS
══════════════════════════════════════════ */
document.getElementById('tab-router').addEventListener('click', function(){ switchDev('router'); });
document.getElementById('tab-switch').addEventListener('click', function(){ switchDev('switch'); });
function switchDev(d) {
  document.getElementById('tab-router').classList.toggle('active', d==='router');
  document.getElementById('tab-switch').classList.toggle('active', d==='switch');
  // przy powrocie do routera, przywróć aktywny model (cisco lub tplink)
  var model = currentRouterModel || 'cisco';
  document.getElementById('wrap-router').style.display  = (d==='router' && model==='cisco')   ? '' : 'none';
  document.getElementById('wrap-tplink').style.display  = (d==='router' && model==='tplink')  ? '' : 'none';
  document.getElementById('wrap-mikrotik-wb').style.display = (d==='router' && model==='mikrotik-wb') ? '' : 'none';
  document.getElementById('wrap-mikrotik-wf').style.display = (d==='router' && model==='mikrotik-wf') ? '' : 'none';
  document.getElementById('wrap-switch').style.display  = d==='switch' ? '' : 'none';
}

/* ══════════════════════════════════════════
   MENU DEFINITIONS
══════════════════════════════════════════ */
var RMENU = [
  {type:'top',label:'Getting Started',page:'getstart'},
  {type:'top',label:'Setup Wizard',page:'wizard'},
  {type:'section',label:'Status and Statistics',id:'sts',items:[
    {label:'Dashboard',page:'dashboard'},
    {label:'System Summary',page:'system'},
    {label:'TCP/IP Services',page:'tcpip_service'},
    {label:'Wireless Statistics',page:'status_wireless'},
    {label:'VPN Status',page:'status_vpn'},
    {label:'IPSec Status',page:'status_ipsec'},
    {label:'View Logs',page:'view_logs'},
    {label:'Connected Devices',page:'lan_host'},
    {label:'Port Statistics',page:'status_wide'},
    {label:'Mobile Network',page:'status_mobile'}
  ]},
  {type:'section',label:'Networking',id:'net',open:true,groups:[
    {grp:'WAN',items:[
      {label:'WAN Configuration',page:'wan',task:true},
      {label:'Mobile Network',page:'mobile'},
      {label:'WAN Failover',page:'failover'}
    ]},
    {grp:'LAN',items:[
      {label:'LAN Configuration',page:'lan',task:true},
      {label:'VLAN Membership',page:'vlan_membership',task:true},
      {label:'Static DHCP',page:'static_dhcp'},
      {label:'DHCP Leased Clients',page:'dhcp_leased_client'},
      {label:'DMZ Host',page:'dmz_host'},
      {label:'Port Management',page:'port_management'}
    ]},
    {grp:'Routing',items:[
      {label:'Basic Routing',page:'routing'},
      {label:'RIP',page:'rip_summary'}
    ]},
    {grp:'',items:[
      {label:'Routing Table',page:'routingtb'},
      {label:'Dynamic DNS',page:'ddns'},
      {label:'IP Mode',page:'ip_mode'}
    ]},
    {grp:'IPv6',items:[
      {label:'LAN Configuration',page:'lan_ipv6'},
      {label:'Static Routing',page:'ipv6_routing'},
      {label:'Routing (RIPng)',page:'ripng'},
      {label:'Router Advertisement',page:'router_ad'},
      {label:'Advertisement Prefixes',page:'adv_prefixes'}
    ]}
  ]},
  {type:'section',label:'Wireless',id:'wl',items:[
    {label:'Basic Settings',page:'wl_basic'},
    {label:'Advanced Settings',page:'wl_adv'},
    {label:'WPS',page:'wl_wps'}
  ]},
  {type:'section',label:'Firewall',id:'fw',items:[
    {label:'Basic Settings',page:'fw_basic'},
    {label:'Schedule Management',page:'fw_schedule'},
    {label:'Service Management',page:'fw_service'},
    {label:'Access Rules',page:'fw_acl'},
    {label:'Internet Access Policy',page:'fw_iap'},
    {label:'1-to-1 NAT',page:'fw_nat1to1'},
    {label:'Single Port Forwarding',page:'fw_single_fwd'},
    {label:'Port Range Forwarding',page:'fw_range_fwd'},
    {label:'Port Range Triggering',page:'fw_trigger'},
    {label:'Attack Protection',page:'fw_attack'},
    {label:'Session',page:'fw_session'}
  ]},
  {type:'section',label:'VPN',id:'vpn',groups:[
    {grp:'Site-to-Site',items:[
      {label:'Basic VPN Setup',page:'vpn_basic'},
      {label:'IPSec Policy',page:'vpn_ipsec'},
      {label:'Certificate Management',page:'vpn_cert'}
    ]},
    {grp:'',items:[
      {label:'VPN Client',page:'vpn_client'},
      {label:'VPN Passthrough',page:'vpn_pass'}
    ]}
  ]},
  {type:'section',label:'QoS',id:'qos',items:[
    {label:'Bandwidth Management',page:'qos_bw'},
    {label:'QoS Port-based Settings',page:'qos_port'},
    {label:'CoS Settings',page:'qos_cos'},
    {label:'DSCP Settings',page:'qos_dscp'}
  ]},
  {type:'section',label:'Administration',id:'adm',groups:[
    {grp:'',items:[
      {label:'Password Complexity',page:'adm_pwd'},
      {label:'Users',page:'adm_users'},
      {label:'Session Timeout',page:'adm_session'},
      {label:'Banner Text',page:'adm_banner'},
      {label:'TR-069 Settings',page:'adm_tr069'}
    ]},
    {grp:'Diagnostics',items:[
      {label:'Network Tools',page:'adm_nettools'},
      {label:'Port Mirror',page:'adm_mirror'},
      {label:'Remote Key',page:'adm_rkey'}
    ]},
    {grp:'Logging',items:[
      {label:'Syslog Settings',page:'adm_syslog'},
      {label:'Email Log Settings',page:'adm_email'}
    ]},
    {grp:'',items:[
      {label:'Bonjour',page:'adm_bonjour'},
      {label:'LLDP',page:'adm_lldp'},
      {label:'Time Settings',page:'adm_time'},
      {label:'Backup &amp; Restore',page:'adm_backup'},
      {label:'Firmware Upgrade',page:'adm_upgrade'},
      {label:'Reboot',page:'adm_reboot'}
    ]}
  ]}
];

var SMENU = [
  {type:'section',label:'System',id:'sw_sys',open:true,items:[
    {label:'System Info',page:'sw_sysinfo'},
    {label:'IP Setting',page:'sw_ipsetting',task:true},
    {label:'User Account',page:'sw_useracct'},
    {label:'Time Setting',page:'sw_time'},
    {label:'LED On/Off',page:'sw_led'}
  ]},
  {type:'section',label:'Switching',id:'sw_switching',items:[
    {label:'Port Setting',page:'sw_portsetting'},
    {label:'IGMP Snooping',page:'sw_igmp'},
    {label:'LAG',page:'sw_lag'},
    {label:'Port Mirror',page:'sw_mirror'}
  ]},
  {type:'section',label:'VLAN',id:'sw_vlan',open:true,items:[
    {label:'802.1Q VLAN',page:'sw_vlan8021q',task:true},
    {label:'MTU VLAN',page:'sw_mtuvlan'},
    {label:'Port Based VLAN',page:'sw_portbasedvlan'}
  ]},
  {type:'section',label:'QoS',id:'sw_qos',items:[
    {label:'Port Based QoS',page:'sw_qos_port'},
    {label:'802.1p/DSCP QoS',page:'sw_qos_dscp'}
  ]},
  {type:'section',label:'Monitoring',id:'sw_mon',items:[
    {label:'Port Statistics',page:'sw_portstats'},
    {label:'Port Mirror',page:'sw_portmirror'},
    {label:'Loop Prevention',page:'sw_loop'},
    {label:'Cable Test',page:'sw_cable'}
  ]}
];

/* ══════════════════════════════════════════
   BUILD SIDEBAR
══════════════════════════════════════════ */
function buildNav(menu, navEl, loadFn, initPage) {
  var h = '';
  menu.forEach(function(e) {
    if (e.type==='top') {
      h += '<button class="cnav-top" data-page="'+e.page+'">'+e.label+'</button>';
    } else {
      h += '<div class="cnav-s'+(e.open?' open':'')+'" id="ns-'+e.id+'">'
        +'<div class="cnav-s-hdr">'+e.label+'<span class="cnav-arr">▶</span></div>'
        +'<div class="cnav-s-body">';
      function renderItems(items) {
        items.forEach(function(it) {
          h += '<button class="cnav-item'+(it.task?' task-item':'')+'" data-page="'+it.page+'">'+it.label+'</button>';
        });
      }
      if (e.items) renderItems(e.items);
      if (e.groups) e.groups.forEach(function(g) {
        if (g.grp) h += '<div class="cnav-grp">'+g.grp+'</div>';
        renderItems(g.items);
      });
      h += '</div></div>';
    }
  });
  navEl.innerHTML = h;
  navEl.querySelectorAll('.cnav-s-hdr').forEach(function(hdr) {
    hdr.addEventListener('click', function(){ hdr.parentElement.classList.toggle('open'); });
  });
  navEl.querySelectorAll('[data-page]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      navEl.querySelectorAll('[data-page]').forEach(function(b){ b.classList.remove('sel'); });
      btn.classList.add('sel');
      /* open section */
      var s = btn.closest('.cnav-s');
      if (s) s.classList.add('open');
      loadFn(btn.getAttribute('data-page'));
    });
  });
  var init = navEl.querySelector('[data-page="'+initPage+'"]');
  if (init) { init.classList.add('sel'); loadFn(initPage); }
}

/* ══════════════════════════════════════════
   HELPERS
══════════════════════════════════════════ */
function toast(msg) {
  var t = document.createElement('div');
  t.className='toast'; t.textContent=msg;
  document.body.appendChild(t);
  setTimeout(function(){ t.remove(); }, 2600);
}
function fld(lbl,id,val,ph,w){
  w=w||200;
  return '<tr><td class="lbl">'+lbl+':</td><td><input type="text" id="'+id+'" value="'+(val||'')+'" placeholder="'+(ph||'')+'" style="width:'+w+'px"></td></tr>';
}
function sel(lbl,id,opts,cur){
  var o=opts.map(function(op){ return '<option value="'+op[0]+'"'+(op[0]===cur?' selected':'')+'>'+op[1]+'</option>'; }).join('');
  return '<tr><td class="lbl">'+lbl+':</td><td><select id="'+id+'">'+o+'</select></td></tr>';
}
function sep(){ return '<tr class="sep"><td colspan="2"></td></tr>'; }
function sysRow(l,v){ return '<table style="font-family:Arial;font-size:12px;margin-bottom:2px"><tr><td style="padding:3px 8px;color:#555;width:220px">'+l+':</td><td style="padding:3px 8px;font-weight:bold">'+v+'</td></tr></table>'; }
function tblRow(cells,cls){ return '<tr class="'+(cls||'')+'">'+cells.map(function(c){ return '<td>'+c+'</td>'; }).join('')+'</tr>'; }
function emptyTbl(msg){ return '<div class="ctable-empty">'+msg+'</div>'; }
function chkRow(lbl,id,checked){
  return '<tr class="chk-row"><td colspan="2"><label><input type="checkbox" id="'+id+'"'+(checked?' checked':'')+'>'+lbl+'</label></td></tr>';
}

/* ══════════════════════════════════════════
   ROUTER PAGES
══════════════════════════════════════════ */
function renderRouterPage(page) {
  var el = document.getElementById('ccontent-router');
  switch(page) {
    case 'getstart':       el.innerHTML = pageGetStart(); break;
    case 'wizard':         el.innerHTML = pageWizard(); break;
    case 'dashboard':      el.innerHTML = pageDashboard(); break;
    case 'system':         el.innerHTML = pageSystem(); break;
    case 'tcpip_service':  el.innerHTML = pageTcpSvc(); break;
    case 'status_wireless':el.innerHTML = pageWlStat(); break;
    case 'status_vpn':     el.innerHTML = pageVpnStat(); break;
    case 'status_ipsec':   el.innerHTML = pageIpsecStat(); break;
    case 'view_logs':      el.innerHTML = pageLogs(); break;
    case 'lan_host':       el.innerHTML = pageConnDev(); break;
    case 'status_wide':    el.innerHTML = pagePortStat(); break;
    case 'status_mobile':  el.innerHTML = pageMobileStat(); break;
    case 'wan':            el.innerHTML = pageWAN();         attachWAN(); break;
    case 'mobile':         el.innerHTML = pageMobile(); break;
    case 'failover':       el.innerHTML = pageFailover(); break;
    case 'lan':            el.innerHTML = pageLAN();         attachLAN(); break;
    case 'vlan_membership':el.innerHTML = pageVLAN();        attachVLAN(); break;
    case 'static_dhcp':    el.innerHTML = pageStaticDhcp(); break;
    case 'dhcp_leased_client': el.innerHTML = pageDhcpLeased(); break;
    case 'dmz_host':       el.innerHTML = pageDmz(); break;
    case 'port_management':el.innerHTML = pagePortMgmt(); break;
    case 'routing':        el.innerHTML = pageRouting(); break;
    case 'rip_summary':    el.innerHTML = pageRip(); break;
    case 'routingtb':      el.innerHTML = pageRoutingTbl(); break;
    case 'ddns':           el.innerHTML = pageDdns(); break;
    case 'ip_mode':        el.innerHTML = pageIpMode(); break;
    case 'lan_ipv6':       el.innerHTML = pageIpv6Lan(); break;
    case 'ipv6_routing':   el.innerHTML = pageIpv6Routes(); break;
    case 'ripng':          el.innerHTML = pageRipng(); break;
    case 'router_ad':      el.innerHTML = pageRouterAd(); break;
    case 'adv_prefixes':   el.innerHTML = pageAdvPrefixes(); break;
    case 'wl_basic':       el.innerHTML = pageWlBasic(); break;
    case 'wl_adv':         el.innerHTML = pageWlAdv(); break;
    case 'wl_wps':         el.innerHTML = pageWlWps(); break;
    case 'fw_basic':       el.innerHTML = pageFwBasic(); break;
    case 'fw_schedule':    el.innerHTML = pageFwSchedule(); break;
    case 'fw_service':     el.innerHTML = pageFwService(); break;
    case 'fw_acl':         el.innerHTML = pageFwAcl(); break;
    case 'fw_iap':         el.innerHTML = pageFwIap(); break;
    case 'fw_nat1to1':     el.innerHTML = pageFwNat(); break;
    case 'fw_single_fwd':  el.innerHTML = pageFwSingleFwd(); break;
    case 'fw_range_fwd':   el.innerHTML = pageFwRangeFwd(); break;
    case 'fw_trigger':     el.innerHTML = pageFwTrigger(); break;
    case 'fw_attack':      el.innerHTML = pageFwAttack(); break;
    case 'fw_session':     el.innerHTML = pageFwSession(); break;
    case 'vpn_basic':      el.innerHTML = pageVpnBasic(); break;
    case 'vpn_ipsec':      el.innerHTML = pageVpnIpsec(); break;
    case 'vpn_cert':       el.innerHTML = pageVpnCert(); break;
    case 'vpn_client':     el.innerHTML = pageVpnClient(); break;
    case 'vpn_pass':       el.innerHTML = pageVpnPass(); break;
    case 'qos_bw':         el.innerHTML = pageQosBw(); break;
    case 'qos_port':       el.innerHTML = pageQosPort(); break;
    case 'qos_cos':        el.innerHTML = pageQosCos(); break;
    case 'qos_dscp':       el.innerHTML = pageQosDscp(); break;
    case 'adm_pwd':        el.innerHTML = pageAdmPwd(); break;
    case 'adm_users':      el.innerHTML = pageAdmUsers(); break;
    case 'adm_session':    el.innerHTML = pageAdmSession(); break;
    case 'adm_banner':     el.innerHTML = pageAdmBanner(); break;
    case 'adm_tr069':      el.innerHTML = pageAdmTr069(); break;
    case 'adm_nettools':   el.innerHTML = pageAdmNettools(); break;
    case 'adm_mirror':     el.innerHTML = pageAdmMirror(); break;
    case 'adm_rkey':       el.innerHTML = pageAdmRkey(); break;
    case 'adm_syslog':     el.innerHTML = pageAdmSyslog(); break;
    case 'adm_email':      el.innerHTML = pageAdmEmail(); break;
    case 'adm_bonjour':    el.innerHTML = pageAdmBonjour(); break;
    case 'adm_lldp':       el.innerHTML = pageAdmLldp(); break;
    case 'adm_time':       el.innerHTML = pageAdmTime(); break;
    case 'adm_backup':     el.innerHTML = pageAdmBackup(); break;
    case 'adm_upgrade':    el.innerHTML = pageAdmUpgrade(); break;
    case 'adm_reboot':     el.innerHTML = pageAdmReboot(); break;
    default: el.innerHTML = '<h2 class="cisco-pg-title">'+labEsc(page)+'</h2>';
  }
}

/* ── Getting Started ── */
function pageGetStart(){
  return '<h2 class="cisco-pg-title">Getting Started</h2>'
    +'<div class="cnote">This page provides easy steps to configure your RV132W. Use the links below for quick access to key configuration pages.</div>'
    +'<div class="cpanel"><div class="cpanel-title">Initial Settings</div><div class="cpanel-body"><table style="font-family:Arial;font-size:12px;width:100%">'
    +'<tr><td style="padding:5px 8px;color:#224466">▶ Change Default Administrator Password</td><td><button class="cbtn" onclick="navTo(\'adm_pwd\')">Go</button></td></tr>'
    +'<tr><td style="padding:5px 8px;color:#224466">▶ Configure WAN Settings</td><td><button class="cbtn" onclick="navTo(\'wan\')">Go</button></td></tr>'
    +'<tr><td style="padding:5px 8px;color:#224466">▶ Configure LAN Settings</td><td><button class="cbtn" onclick="navTo(\'lan\')">Go</button></td></tr>'
    +'<tr><td style="padding:5px 8px;color:#224466">▶ Configure Wireless Settings</td><td><button class="cbtn" onclick="navTo(\'wl_basic\')">Go</button></td></tr>'
    +'<tr><td style="padding:5px 8px;color:#224466">▶ VLAN Membership</td><td><button class="cbtn" onclick="navTo(\'vlan_membership\')">Go</button></td></tr>'
    +'</table></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Device Status</div><div class="cpanel-body">'
    +sysRow('Firmware Version','1.0.1.14')+sysRow('Serial Number','PSZ194302XY')
    +sysRow('WAN Status','<span class="'+(RS.wan.ip?'st-up':'st-dn')+'">'+(RS.wan.ip?'Connected ('+RS.wan.ip+')':'Not Configured')+'</span>')
    +sysRow('LAN IP',RS.lan.ip||'192.168.1.1')
    +'</div></div>';
}

/* ── Setup Wizard ── */
function pageWizard(){
  return '<h2 class="cisco-pg-title">Setup Wizard</h2>'
    +'<div class="cnote">The Setup Wizard will guide you through basic configuration of the router. Click <b>Launch Setup Wizard</b> to begin.</div>'
    +'<div class="cpanel"><div class="cpanel-title">Setup Wizard</div><div class="cpanel-body">'
    +'<p style="font-family:Arial;font-size:12px;margin-bottom:10px">The wizard will help you configure:</p>'
    +'<ul style="font-family:Arial;font-size:12px;padding-left:20px;line-height:1.8">'
    +'<li>Internet (WAN) connection</li><li>LAN IP address</li><li>Wireless network</li><li>Administrator password</li></ul>'
    +'<div style="margin-top:14px"><button class="cbtn cbtn-primary">Launch Setup Wizard</button></div>'
    +'</div></div>';
}

/* ── Dashboard ── */
function pageDashboard(){
  var wanOk = !!RS.wan.ip;
  return '<h2 class="cisco-pg-title">Dashboard</h2>'
    +'<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">'
    +'<div class="cpanel"><div class="cpanel-title">WAN Status</div><div class="cpanel-body">'
    +sysRow('Status','<span class="'+(wanOk?'st-up':'st-dn')+'">'+(wanOk?'Connected':'Disconnected')+'</span>')
    +sysRow('IP Address',RS.wan.ip||'—')+sysRow('Subnet Mask',RS.wan.mask||'—')
    +sysRow('Default Gateway',RS.wan.gw||'—')+sysRow('DNS',RS.wan.dns1||'—')
    +'</div></div>'
    +'<div class="cpanel"><div class="cpanel-title">LAN Status</div><div class="cpanel-body">'
    +sysRow('IP Address',RS.lan.ip||'192.168.1.1')+sysRow('Subnet Mask',RS.lan.mask||'255.255.255.0')
    +sysRow('DHCP Server',RS.dhcp.on?'Enabled':'Disabled')
    +sysRow('DHCP Range',(RS.dhcp.start&&RS.dhcp.end)?RS.dhcp.start+' – '+RS.dhcp.end:'—')
    +'</div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Wireless Status</div><div class="cpanel-body">'
    +sysRow('Radio','Enabled')+sysRow('SSID','Cisco_RV132W')+sysRow('Security','WPA2-Personal')+sysRow('Channel','6 (Auto)')
    +'</div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Resource Utilization</div><div class="cpanel-body">'
    +sysRow('CPU Utilization','3%')+sysRow('Memory Utilization','28%')+sysRow('Uptime','0d 00:05:12')
    +'</div></div></div>';
}

/* ── System Summary ── */
function pageSystem(){
  return '<h2 class="cisco-pg-title">System Summary</h2>'
    +'<div class="cpanel"><div class="cpanel-title">WAN Settings</div><div class="cpanel-body">'
    +sysRow('Connection Type','Static IP')+sysRow('IP Address',RS.wan.ip||'—')
    +sysRow('Subnet Mask',RS.wan.mask||'—')+sysRow('Default Gateway',RS.wan.gw||'—')
    +sysRow('Primary DNS',RS.wan.dns1||'—')+sysRow('Secondary DNS',RS.wan.dns2||'—')
    +'</div></div>'
    +'<div class="cpanel"><div class="cpanel-title">LAN Settings</div><div class="cpanel-body">'
    +sysRow('IP Address',RS.lan.ip||'—')+sysRow('Subnet Mask',RS.lan.mask||'—')
    +'</div></div>'
    +'<div class="cpanel"><div class="cpanel-title">DHCP Server</div><div class="cpanel-body">'
    +sysRow('DHCP Server',RS.dhcp.on?'Enabled':'Disabled')
    +(RS.dhcp.on?sysRow('Range',(RS.dhcp.start||'—')+' – '+(RS.dhcp.end||'—'))+sysRow('Lease Time',(RS.dhcp.lease||'—')+' min'):'')
    +'</div></div>'
    +'<div class="cpanel"><div class="cpanel-title">VLAN Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>VLAN ID</th><th>Description</th></tr>'
    +Object.keys(RS.vlans).map(function(id,i){ return tblRow([id,RS.vlans[id]],i%2?'even':'odd'); }).join('')
    +'</table></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Device Information</div><div class="cpanel-body">'
    +sysRow('Model','RV132W')+sysRow('Serial Number','PSZ194302XY')+sysRow('Firmware','1.0.1.14')+sysRow('MAC Address','A4:93:4C:12:34:56')
    +'</div></div>';
}

/* ── TCP/IP Services ── */
function pageTcpSvc(){
  var rows=[['HTTP','TCP','80','Enabled'],['HTTPS','TCP','443','Enabled'],['SSH','TCP','22','Disabled'],['SNMP','UDP','161','Disabled'],['Telnet','TCP','23','Disabled']];
  return '<h2 class="cisco-pg-title">TCP/IP Services</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Active Service List</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Service</th><th>Protocol</th><th>Port</th><th>Status</th></tr>'
    +rows.map(function(r,i){ return tblRow([r[0],r[1],r[2],'<span class="'+(r[3]==='Enabled'?'st-up':'st-dn')+'">'+r[3]+'</span>'],i%2?'even':'odd'); }).join('')
    +'</table></div></div>';
}

/* ── Wireless Statistics ── */
function pageWlStat(){
  return '<h2 class="cisco-pg-title">Wireless Statistics</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Wireless Summary</div><div class="cpanel-body">'
    +sysRow('SSID','Cisco_RV132W')+sysRow('Band','2.4 GHz')+sysRow('Channel','6')+sysRow('Mode','B/G/N-Mixed')+sysRow('Security','WPA2-Personal')
    +'</div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Wireless Statistics</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Interface</th><th>Sent Packets</th><th>Recv Packets</th><th>Errors</th></tr>'
    +tblRow(['wl0','12485','9832','0'],'odd')+'</table></div></div>';
}

/* ── VPN Status ── */
function pageVpnStat(){
  return '<h2 class="cisco-pg-title">VPN Status</h2>'
    +'<div class="cpanel"><div class="cpanel-title">QuickVPN Users</div><div class="cpanel-body">'
    +sysRow('QuickVPN Connections Available','5')+sysRow('Connected QuickVPN Users','0')
    +'</div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Connected QuickVPN Users</div><div class="cpanel-body">'
    +emptyTbl('No QuickVPN users connected.')
    +'</div></div>';
}

/* ── IPSec Status ── */
function pageIpsecStat(){
  return '<h2 class="cisco-pg-title">IPSec Status</h2>'
    +'<div class="cpanel"><div class="cpanel-title">IPSec Tunnel Status</div><div class="cpanel-body">'
    +emptyTbl('No IPSec tunnels configured.')
    +'</div></div>';
}

/* ── View Logs ── */
function pageLogs(){
  var ts=new Date(); var ds=ts.toISOString().slice(0,19).replace('T',' ');
  var rows=[['INFO','System','Device started successfully.'],['INFO','WAN','Attempting WAN connection.'],['INFO','DHCP','DHCP server started on LAN interface.'],['INFO','Firewall','SPI Firewall enabled.']];
  return '<h2 class="cisco-pg-title">View Logs</h2>'
    +'<div class="cpanel"><div class="cpanel-title">System Log</div><div class="cpanel-body">'
    +'<div style="display:flex;gap:8px;margin-bottom:8px;font-family:Arial;font-size:12px"><button class="cbtn">Refresh</button><button class="cbtn">Clear Log</button></div>'
    +'<table class="ctable"><tr><th>Severity</th><th>Category</th><th>Message</th></tr>'
    +rows.map(function(r,i){ return tblRow(r,i%2?'even':'odd'); }).join('')
    +'</table></div></div>';
}

/* ── Connected Devices ── */
function pageConnDev(){
  return '<h2 class="cisco-pg-title">Connected Devices</h2>'
    +'<div class="cpanel"><div class="cpanel-title">IPv4 ARP Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>IP Address</th><th>MAC Address</th><th>Interface</th><th>Type</th></tr>'
    +tblRow(['192.168.1.1','A4:93:4C:12:34:56','LAN','Static'],'odd')
    +'</table></div></div>';
}

/* ── Port Statistics ── */
function pagePortStat(){
  var ifaces=[['WAN (eth0)','100 Mbps Full','Enabled','4582','12 MB','1024','3 MB'],['LAN 1 (eth1)','1 Gbps Full','Enabled','9821','28 MB','8432','24 MB'],['LAN 2 (eth2)','—','Disabled','0','0','0','0'],['LAN 3 (eth3)','—','Disabled','0','0','0','0'],['LAN 4 (eth4)','—','Disabled','0','0','0','0']];
  return '<h2 class="cisco-pg-title">Port Statistics</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Port Statistics Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Port</th><th>Link Status</th><th>State</th><th>Sent Pkts</th><th>Sent Bytes</th><th>Recv Pkts</th><th>Recv Bytes</th></tr>'
    +ifaces.map(function(r,i){ return tblRow(r,i%2?'even':'odd'); }).join('')
    +'</table><div style="margin-top:8px"><button class="cbtn">Refresh</button><button class="cbtn">Clear Count</button></div>'
    +'</div></div>';
}

/* ── Mobile Network Status ── */
function pageMobileStat(){
  return '<h2 class="cisco-pg-title">Mobile Network Status</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Mobile Network (3G/4G USB)</div><div class="cpanel-body">'
    +sysRow('Status','<span class="st-dn">No device connected</span>')
    +sysRow('Device','—')+sysRow('Signal Strength','—')+sysRow('IP Address','—')
    +'</div></div>';
}

/* ── WAN Configuration (TASK) ── */
function pageWAN(){
  function ph(val, fallback){ return fallback; }
  return '<h2 class="cisco-pg-title">WAN Configuration</h2>'
    +'<div class="cpanel"><div class="cpanel-title">WAN Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +sel('WAN Connection Type','w-type',[['static','Static IP'],['dhcp','Automatic Configuration - DHCP'],['pppoe','PPPoE'],['pptp','PPTP'],['l2tp','L2TP']],'static')
    +sep()
    +fld('Internet IP Address','w-ip',RS.wan.ip,ph('wan_ip','np. 89.123.45.2'))
    +fld('Subnet Mask','w-mask',RS.wan.mask,ph('wan_mask','np. 255.255.255.252'))
    +fld('Default Gateway','w-gw',RS.wan.gw,ph('wan_gw','np. 89.123.45.1'))
    +sep()
    +fld('Primary DNS Server','w-dns1',RS.wan.dns1,ph('wan_dns1','np. 8.8.8.8'))
    +fld('Secondary DNS Server','w-dns2',RS.wan.dns2,'np. 8.8.4.4')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn" id="w-cancel">Cancel</button><button class="cbtn cbtn-primary" id="w-save">Save</button></div>';
}
function attachWAN(){
  document.getElementById('w-save').addEventListener('click',function(){
    RS.wan.ip=document.getElementById('w-ip').value.trim();
    RS.wan.mask=document.getElementById('w-mask').value.trim();
    RS.wan.gw=document.getElementById('w-gw').value.trim();
    RS.wan.dns1=document.getElementById('w-dns1').value.trim();
    RS.wan.dns2=document.getElementById('w-dns2').value.trim();
    saveState();
    toast('✔ WAN settings saved');
  });
  document.getElementById('w-cancel').addEventListener('click',function(){ renderRouterPage('wan'); });
}

/* ── Mobile WAN ── */
function pageMobile(){
  return '<h2 class="cisco-pg-title">Mobile Network</h2>'
    +'<div class="cpanel"><div class="cpanel-title">3G/4G USB Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +sel('Connection Type','mob-type',[['auto','Auto Detect'],['manual','Manual']],'auto')
    +fld('APN','mob-apn','','internet')
    +fld('Username','mob-user','','optional')
    +fld('Password','mob-pwd','','optional')
    +sep()+sel('Modem','mob-dev',[['none','None detected']],'none')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── WAN Failover ── */
function pageFailover(){
  return '<h2 class="cisco-pg-title">WAN Failover</h2>'
    +'<div class="cpanel"><div class="cpanel-title">WAN Failover Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable WAN Failover','fo-en',false)
    +sep()+sel('Primary WAN','fo-pri',[['eth','Ethernet WAN'],['dsl','xDSL WAN']],'eth')
    +sel('Secondary WAN','fo-sec',[['mobile','Mobile Network'],['eth','Ethernet WAN']],'mobile')
    +fld('Reconnect Attempts','fo-att','3','')
    +fld('Reconnect Interval (sec)','fo-int','30','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── LAN Configuration (TASK) ── */
function pageLAN(){
  function ph(val, fallback){ return fallback; }
  return '<h2 class="cisco-pg-title">LAN Configuration</h2>'
    +'<div class="cpanel"><div class="cpanel-title">LAN TCP/IP Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +fld('Local IP Address','l-ip',RS.lan.ip,ph('lan_ip','np. 192.168.1.1'))
    +fld('Subnet Mask','l-mask',RS.lan.mask,ph('lan_mask','np. 255.255.255.0'))
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Server Settings (DHCP)</div><div class="cpanel-body"><div class="cform"><table>'
    +'<tr><td class="lbl">DHCP Server:</td><td>'
    +'<label><input type="radio" name="dhcp" id="dhcp-on" value="on"'+(RS.dhcp.on?' checked':'')+'>Enable</label>&nbsp;&nbsp;'
    +'<label><input type="radio" name="dhcp" id="dhcp-off" value="off"'+(!RS.dhcp.on?' checked':'')+'>Disable</label></td></tr>'
    +sep()
    +fld('Start IP Address','d-start',RS.dhcp.start,ph('dhcp_start','np. 192.168.1.100'))
    +fld('End IP Address','d-end',RS.dhcp.end,ph('dhcp_end','np. 192.168.1.200'))
    +fld('Default Gateway IP','d-gw',RS.dhcp.gw,'np. 192.168.1.1')
    +fld('DNS Server','d-dns',RS.dhcp.dns,'np. 8.8.8.8')
    +fld('Client Lease Time (min)','d-lease',RS.dhcp.lease,'1440',80)
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn" id="l-cancel">Cancel</button><button class="cbtn cbtn-primary" id="l-save">Save</button></div>';
}
function attachLAN(){
  document.getElementById('l-save').addEventListener('click',function(){
    RS.lan.ip=document.getElementById('l-ip').value.trim();
    RS.lan.mask=document.getElementById('l-mask').value.trim();
    RS.dhcp.on=document.getElementById('dhcp-on').checked;
    RS.dhcp.start=document.getElementById('d-start').value.trim();
    RS.dhcp.end=document.getElementById('d-end').value.trim();
    RS.dhcp.gw=document.getElementById('d-gw').value.trim();
    RS.dhcp.dns=document.getElementById('d-dns').value.trim();
    RS.dhcp.lease=document.getElementById('d-lease').value.trim();
    saveState();
    toast('✔ LAN & DHCP settings saved');
  });
  document.getElementById('l-cancel').addEventListener('click',function(){ renderRouterPage('lan'); });
}

/* ── VLAN Membership (TASK) ── */
function pageVLAN(){
  var rows=Object.keys(RS.vlans).map(function(id,i){
    var ro=id==='1'?' readonly style="background:#f0f0f0"':'';
    var del=id!=='1'?'<button class="cbtn cbtn-del vrow-del" data-vid="'+id+'">Delete</button>':'';
    return '<div class="vrow"><span class="vrow-id">'+id+'</span><input class="vrow-input" data-vid="'+id+'" value="'+RS.vlans[id]+'"'+ro+'>'+del+'</div>';
  }).join('');
  return '<h2 class="cisco-pg-title">VLAN Membership</h2>'
    +'<div class="cnote">Create VLANs and assign descriptions. Up to 5 VLANs total can be created.</div>'
    +'<div class="cpanel"><div class="cpanel-title">VLANs Setting Table</div><div class="cpanel-body">'
    +'<div style="font-family:Arial;font-size:11px;color:#555;margin-bottom:6px;display:flex;gap:8px"><span style="width:55px"><b>VLAN ID</b></span><span><b>Description</b></span></div>'
    +'<div id="vlan-rows">'+rows+'</div>'
    +'<div class="vadd-row"><input type="text" id="new-vid" maxlength="4" placeholder="ID"><input type="text" id="new-vname" maxlength="32" placeholder="Nazwa VLAN"><button id="vlan-add">+ Add VLAN</button></div>'
    +'</div></div>'
    +'<div class="cbtn-row"><button class="cbtn cbtn-primary" id="v-save">Save</button></div>';
}
function attachVLAN(){
  function rebind(){
    document.querySelectorAll('.vrow-del').forEach(function(b){
      b.addEventListener('click',function(){
        delete RS.vlans[b.getAttribute('data-vid')];
        renderRouterPage('vlan_membership'); attachVLAN();
      });
    });
  }
  rebind();
  document.getElementById('vlan-add').addEventListener('click',function(){
    var vid=document.getElementById('new-vid').value.trim();
    var vn=document.getElementById('new-vname').value.trim();
    if(!vid||!vn||isNaN(+vid)) return;
    var id=parseInt(vid);
    if(id<2||id>4094){showLabNotice('VLAN ID must be 2–4094');return;}
    RS.vlans[id]=vn;
    renderRouterPage('vlan_membership'); attachVLAN();
  });
  document.getElementById('v-save').addEventListener('click',function(){
    document.querySelectorAll('#vlan-rows .vrow-input').forEach(function(inp){
      var vid=inp.getAttribute('data-vid');
      if(vid!=='1') RS.vlans[vid]=inp.value.trim();
    });
    saveState();
    toast('✔ VLAN settings saved');
  });
}

/* ── Static DHCP ── */
function pageStaticDhcp(){
  return '<h2 class="cisco-pg-title">Static DHCP</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Static DHCP Client Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>No.</th><th>MAC Address</th><th>IP Address</th><th>Description</th><th>Action</th></tr></table>'
    +emptyTbl('No static DHCP entries. Click Add Row to add a new entry.')
    +'<div style="margin-top:8px"><button class="cbtn">Add Row</button></div>'
    +'</div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── DHCP Leased Clients ── */
function pageDhcpLeased(){
  return '<h2 class="cisco-pg-title">DHCP Leased Clients</h2>'
    +'<div class="cpanel"><div class="cpanel-title">DHCP Leased Client Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Client Name</th><th>IP Address</th><th>MAC Address</th><th>Expires In</th></tr></table>'
    +emptyTbl('No DHCP leases active.')
    +'<div style="margin-top:8px"><button class="cbtn">Refresh</button></div>'
    +'</div></div>';
}

/* ── DMZ Host ── */
function pageDmz(){
  return '<h2 class="cisco-pg-title">DMZ Host</h2>'
    +'<div class="cpanel"><div class="cpanel-title">DMZ Host Configuration</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable DMZ','dmz-en',false)
    +sep()+fld('DMZ Host IP Address','dmz-ip','','np. 192.168.1.50')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── Port Management ── */
function pagePortMgmt(){
  var ports=[['WAN','Auto','Auto','Enabled'],['LAN 1','Auto','1000 Mbps Full','Enabled'],['LAN 2','Auto','—','Enabled'],['LAN 3','Auto','—','Enabled'],['LAN 4','Auto','—','Enabled']];
  return '<h2 class="cisco-pg-title">Port Management</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Port Setting Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Port</th><th>Speed</th><th>Link Status</th><th>State</th></tr>'
    +ports.map(function(r,i){ return tblRow(r,i%2?'even':'odd'); }).join('')
    +'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── Basic Routing ── */
function pageRouting(){
  return '<h2 class="cisco-pg-title">Basic Routing</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Routing Table Entry List</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Destination</th><th>Subnet Mask</th><th>Gateway</th><th>Interface</th><th>Metric</th></tr>'
    +tblRow(['0.0.0.0','0.0.0.0',RS.wan.gw||'—','WAN','1'],'odd')
    +tblRow([RS.lan.ip?RS.lan.ip.replace(/\d+$/,'0'):'192.168.1.0','255.255.255.0','0.0.0.0','LAN','1'],'even')
    +'</table>'
    +'<div style="margin-top:8px"><button class="cbtn">Add Row</button></div>'
    +'</div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── RIP ── */
function pageRip(){
  return '<h2 class="cisco-pg-title">RIP</h2>'
    +'<div class="cpanel"><div class="cpanel-title">RIP Basic Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable RIP','rip-en',false)
    +sep()+sel('RIP Version','rip-ver',[['2','RIP Version 2'],['1','RIP Version 1'],['both','Both']],'2')
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">RIP Members</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Interface</th><th>Send</th><th>Receive</th><th>Auth</th></tr>'
    +tblRow(['LAN','V2','V2','None'],'odd')+tblRow(['WAN','V2','V2','None'],'even')
    +'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── Routing Table ── */
function pageRoutingTbl(){
  return '<h2 class="cisco-pg-title">Routing Table</h2>'
    +'<div class="cpanel"><div class="cpanel-title">IPv4 Routing Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Destination</th><th>Mask</th><th>Gateway</th><th>Interface</th><th>Protocol</th><th>Metric</th></tr>'
    +tblRow(['0.0.0.0','0.0.0.0',RS.wan.gw||'—','WAN','Static','1'],'odd')
    +tblRow([RS.lan.ip?RS.lan.ip.replace(/\d+$/,'0'):'192.168.1.0','255.255.255.0','0.0.0.0','LAN','Connected','0'],'even')
    +'</table><div style="margin-top:8px"><button class="cbtn">Refresh</button></div>'
    +'</div></div>';
}

/* ── Dynamic DNS ── */
function pageDdns(){
  return '<h2 class="cisco-pg-title">Dynamic DNS</h2>'
    +'<div class="cpanel"><div class="cpanel-title">DDNS Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable DDNS','ddns-en',false)
    +sep()+sel('DDNS Service','ddns-svc',[['dyndns','DynDNS'],['noip','No-IP'],['tzo','TZO']],'dyndns')
    +fld('Hostname','ddns-host','','myrouter.dyndns.org')
    +fld('Username','ddns-user','','')
    +fld('Password','ddns-pwd','','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── IP Mode ── */
function pageIpMode(){
  return '<h2 class="cisco-pg-title">IP Mode</h2>'
    +'<div class="cpanel"><div class="cpanel-title">IP Mode Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +sel('IP Mode','ipmode-sel',[['4','LAN:IPv4, WAN:IPv4'],['46','LAN:IPv4+IPv6, WAN:IPv4'],['46w46','LAN:IPv4+IPv6, WAN:IPv4+IPv6'],['6','LAN:IPv6, WAN:IPv4']],'4')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── IPv6 LAN ── */
function pageIpv6Lan(){
  return '<h2 class="cisco-pg-title">IPv6 LAN Configuration</h2>'
    +'<div class="cpanel"><div class="cpanel-title">LAN IPv6 Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +sel('IPv6 Configuration','ipv6-cfg',[['auto','Automatic Configuration – DHCPv6'],['static','Static IPv6'],['stateless','Stateless Address Auto Configuration']],'auto')
    +fld('IPv6 Address','ipv6-addr','','2001:db8::1')
    +fld('IPv6 Prefix Length','ipv6-pre','','64')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageIpv6Routes(){ return '<h2 class="cisco-pg-title">IPv6 Static Routing</h2><div class="cpanel"><div class="cpanel-title">IPv6 Static Route Table</div><div class="cpanel-body">'+emptyTbl('No IPv6 static routes configured.')+'<div style="margin-top:8px"><button class="cbtn">Add Row</button></div></div></div><div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>'; }
function pageRipng(){
  return '<h2 class="cisco-pg-title">Routing (RIPng)</h2>'
    +'<div class="cpanel"><div class="cpanel-title">RIPng Configuration</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable RIPng','ripng-en',false)
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageRouterAd(){
  return '<h2 class="cisco-pg-title">Router Advertisement</h2>'
    +'<div class="cpanel"><div class="cpanel-title">RADVD Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable RADVD','radvd-en',false)
    +sel('Advertise Mode','radvd-mode',[['unsolicited','Unsolicited Multicast'],['unicast','Unicast Only']],'unsolicited')
    +fld('Advertise Interval (sec)','radvd-int','30','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdvPrefixes(){
  return '<h2 class="cisco-pg-title">Advertisement Prefixes</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Prefixes to Advertise Table</div><div class="cpanel-body">'
    +emptyTbl('No advertisement prefixes configured.')
    +'<div style="margin-top:8px"><button class="cbtn">Add Row</button></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── Wireless ── */
function pageWlBasic(){
  return '<h2 class="cisco-pg-title">Wireless Basic Settings</h2>'
    +'<div class="cpanel"><div class="cpanel-title">2.4G Basic Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Radio','wl-radio',true)
    +fld('SSID','wl-ssid','Cisco_RV132W','')
    +chkRow('SSID Broadcast','wl-bc',true)
    +sep()+sel('Wireless Network Mode','wl-mode',[['bgn','B/G/N-Mixed'],['n','N-Only'],['g','G-Only']],'bgn')
    +sel('Wireless Channel Width','wl-bw',[['auto','Auto'],['20','20 MHz'],['40','40 MHz']],'auto')
    +sel('Wireless Channel','wl-ch',[['auto','Auto'],['1','1'],['6','6'],['11','11']],'auto')
    +sep()+sel('Security Mode','wl-sec',[['wpa2','WPA2-Personal'],['wpa','WPA-Personal'],['wep','WEP'],['none','None']],'wpa2')
    +fld('Passphrase','wl-pass','','min. 8 characters')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageWlAdv(){
  return '<h2 class="cisco-pg-title">Wireless Advanced Settings</h2>'
    +'<div class="cpanel"><div class="cpanel-title">2.4G Advanced Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +fld('Beacon Interval (ms)','wl-beacon','100','')
    +fld('DTIM Interval','wl-dtim','1','')
    +fld('Fragmentation Threshold','wl-frag','2346','')
    +fld('RTS Threshold','wl-rts','2347','')
    +chkRow('WMM (Wi-Fi Multimedia)','wl-wmm',true)
    +chkRow('Frame Burst','wl-burst',false)
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageWlWps(){
  return '<h2 class="cisco-pg-title">WPS</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Wi-Fi Protected Setup</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable WPS','wps-en',true)
    +sep()
    +'<tr><td class="lbl">Method 1 – Push Button:</td><td><button class="cbtn cbtn-primary">Connect</button></td></tr>'
    +fld('Method 2 – Client PIN','wps-pin','','Enter client PIN')
    +'<tr><td class="lbl">Router PIN:</td><td><span style="font-family:Arial;font-size:12px;font-weight:bold">12345678</span></td></tr>'
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── Firewall ── */
function pageFwBasic(){
  return '<h2 class="cisco-pg-title">Firewall – Basic Settings</h2>'
    +'<div class="cpanel"><div class="cpanel-title">General</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('SPI (Stateful Packet Inspection) Firewall','fw-spi',true)
    +chkRow('Block WAN Requests (Ping)','fw-ping',true)
    +chkRow('IPv4 Multicast Passthrough (IGMP Proxy)','fw-igmp',false)
    +chkRow('UPnP','fw-upnp',false)
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Web Access</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('HTTP Access','fw-http',true)
    +chkRow('HTTPS Access','fw-https',true)
    +chkRow('SSH Access','fw-ssh',false)
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Remote Management</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Remote Web Access','fw-rweb',false)
    +fld('Remote Management Port','fw-rport','8080','')
    +sel('Allowed Remote IP','fw-rip',[['any','Any IP Address'],['range','Specific IP']],'any')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageFwSchedule(){
  return '<h2 class="cisco-pg-title">Schedule Management</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Schedule Entries</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Name</th><th>Days</th><th>Start Time</th><th>End Time</th><th>Action</th></tr></table>'
    +emptyTbl('No schedules configured.')
    +'<div style="margin-top:8px"><button class="cbtn">Add</button></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageFwService(){
  var svcs=[['HTTP','TCP','80','80'],['HTTPS','TCP','443','443'],['FTP','TCP','21','21'],['SSH','TCP','22','22'],['Telnet','TCP','23','23'],['DNS','UDP','53','53'],['SMTP','TCP','25','25'],['POP3','TCP','110','110']];
  return '<h2 class="cisco-pg-title">Service Management</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Service Management Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Service Name</th><th>Protocol</th><th>Port Range Start</th><th>Port Range End</th></tr>'
    +svcs.map(function(r,i){ return tblRow(r,i%2?'even':'odd'); }).join('')
    +'</table><div style="margin-top:8px"><button class="cbtn">Add</button></div></div></div>';
}
function pageFwAcl(){
  return '<h2 class="cisco-pg-title">Access Rules</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Access Rule Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Priority</th><th>Action</th><th>Service</th><th>Source</th><th>Destination</th><th>Time</th></tr></table>'
    +emptyTbl('No access rules configured.')
    +'<div style="margin-top:8px"><button class="cbtn">Add Access Rule</button></div></div></div>';
}
function pageFwIap(){
  return '<h2 class="cisco-pg-title">Internet Access Policy</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Internet Access Policy</div><div class="cpanel-body"><div class="cform"><table>'
    +sel('Policy Number','iap-no',[['1','Policy 1'],['2','Policy 2'],['3','Policy 3']],'1')
    +chkRow('Enable this policy','iap-en',false)
    +fld('Policy Name','iap-name','','')
    +sep()+sel('Action','iap-act',[['deny','Deny Internet Access'],['allow','Allow Internet Access']],'deny')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageFwNat(){
  return '<h2 class="cisco-pg-title">1-to-1 NAT</h2>'
    +'<div class="cpanel"><div class="cpanel-title">1-to-1 NAT Table</div><div class="cpanel-body">'
    +emptyTbl('No 1-to-1 NAT rules configured.')
    +'<div style="margin-top:8px"><button class="cbtn">Add Row</button></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageFwSingleFwd(){
  return '<h2 class="cisco-pg-title">Single Port Forwarding</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Single Port Forwarding Rules</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Application</th><th>Ext Port</th><th>Int Port</th><th>Protocol</th><th>To IP Address</th><th>Enabled</th></tr></table>'
    +emptyTbl('No port forwarding rules.')
    +'<div style="margin-top:8px"><button class="cbtn">Add Row</button></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageFwRangeFwd(){
  return '<h2 class="cisco-pg-title">Port Range Forwarding</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Port Range Forwarding Rules</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Application</th><th>Start</th><th>End</th><th>Protocol</th><th>To IP Address</th><th>Enabled</th></tr></table>'
    +emptyTbl('No port range forwarding rules.')
    +'<div style="margin-top:8px"><button class="cbtn">Add Row</button></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageFwTrigger(){
  return '<h2 class="cisco-pg-title">Port Range Triggering</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Port Range Triggering Rules</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Application</th><th>Triggered Range</th><th>Forwarded Range</th><th>Enabled</th></tr></table>'
    +emptyTbl('No port triggering rules.')
    +'<div style="margin-top:8px"><button class="cbtn">Add Row</button></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageFwAttack(){
  return '<h2 class="cisco-pg-title">Attack Protection</h2>'
    +'<div class="cpanel"><div class="cpanel-title">DoS (Denial of Service) Protection</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable DoS Protection','dos-en',true)
    +chkRow('SYN Flood Protection','dos-syn',true)
    +chkRow('Echo Storm Protection','dos-echo',true)
    +chkRow('ICMP Flood Protection','dos-icmp',true)
    +chkRow('Port Scan Detection','dos-portscan',true)
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageFwSession(){
  return '<h2 class="cisco-pg-title">Session</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Firewall Session Timeout</div><div class="cpanel-body"><div class="cform"><table>'
    +fld('TCP Session Timeout (sec)','sess-tcp','1800','')
    +fld('UDP Session Timeout (sec)','sess-udp','30','')
    +fld('ICMP Session Timeout (sec)','sess-icmp','10','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── VPN ── */
function pageVpnBasic(){
  return '<h2 class="cisco-pg-title">Basic VPN Setup</h2>'
    +'<div class="cpanel"><div class="cpanel-title">VPN Tunnel Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>No.</th><th>Name</th><th>Status</th><th>Phase 1</th><th>Phase 2</th></tr></table>'
    +emptyTbl('No VPN tunnels configured.')
    +'<div style="margin-top:8px"><button class="cbtn">Add</button></div></div></div>';
}
function pageVpnIpsec(){
  return '<h2 class="cisco-pg-title">IPSec Policy</h2>'
    +'<div class="cpanel"><div class="cpanel-title">IKE Policy Table</div><div class="cpanel-body">'
    +emptyTbl('No IKE policies.')+'<div style="margin-top:8px"><button class="cbtn">Add</button></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">IPSec Policy Table</div><div class="cpanel-body">'
    +emptyTbl('No IPSec policies.')+'<div style="margin-top:8px"><button class="cbtn">Add</button></div></div></div>';
}
function pageVpnCert(){
  return '<h2 class="cisco-pg-title">Certificate Management</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Certificate Table</div><div class="cpanel-body">'
    +emptyTbl('No certificates imported.')+'<div style="margin-top:8px"><button class="cbtn">Import</button></div></div></div>';
}
function pageVpnClient(){
  return '<h2 class="cisco-pg-title">VPN Client</h2>'
    +'<div class="cpanel"><div class="cpanel-title">PPTP Server Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable PPTP Server','pptp-en',false)
    +fld('PPTP IP Range Start','pptp-s','192.168.1.200','')
    +fld('PPTP IP Range End','pptp-e','192.168.1.210','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageVpnPass(){
  return '<h2 class="cisco-pg-title">VPN Passthrough</h2>'
    +'<div class="cpanel"><div class="cpanel-title">VPN Passthrough Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('IPSec Passthrough','pt-ipsec',true)
    +chkRow('PPTP Passthrough','pt-pptp',true)
    +chkRow('L2TP Passthrough','pt-l2tp',true)
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── QoS ── */
function pageQosBw(){
  return '<h2 class="cisco-pg-title">Bandwidth Management</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Maximum Bandwidth from ISP</div><div class="cpanel-body"><div class="cform"><table>'
    +fld('Upstream Bandwidth (Kbit/s)','bw-up','1000','')
    +fld('Downstream Bandwidth (Kbit/s)','bw-down','10000','')
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Bandwidth Priority Table</div><div class="cpanel-body">'
    +emptyTbl('No bandwidth priority rules.')+'<div style="margin-top:8px"><button class="cbtn">Add</button></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageQosPort(){
  var ifaces=[['LAN 1','Port-based','4 (Normal)'],['LAN 2','Port-based','4 (Normal)'],['LAN 3','Port-based','4 (Normal)'],['LAN 4','Port-based','4 (Normal)'],['WAN','Port-based','4 (Normal)']];
  return '<h2 class="cisco-pg-title">QoS Port-based Settings</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Ethernet QoS Port Settings</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Port</th><th>Trust Mode</th><th>Default Traffic Queue</th></tr>'
    +ifaces.map(function(r,i){ return tblRow(r,i%2?'even':'odd'); }).join('')
    +'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageQosCos(){
  var items=[[0,'1 (lowest)'],[1,'1'],[2,'2'],[3,'2'],[4,'3'],[5,'3'],[6,'4'],[7,'4 (highest)']];
  return '<h2 class="cisco-pg-title">CoS Settings</h2>'
    +'<div class="cpanel"><div class="cpanel-title">CoS to Traffic Queue Mapping</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>CoS Priority</th><th>Traffic Forwarding Queue</th></tr>'
    +items.map(function(r,i){ return tblRow(r,i%2?'even':'odd'); }).join('')
    +'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageQosDscp(){
  return '<h2 class="cisco-pg-title">DSCP Settings</h2>'
    +'<div class="cpanel"><div class="cpanel-title">DSCP to Traffic Queue Mapping</div><div class="cpanel-body">'
    +'<div style="font-family:Arial;font-size:12px;color:#555;margin-bottom:8px">Maps DSCP values (0–63) to traffic forwarding queues (1–4).</div>'
    +'<table class="ctable"><tr><th>DSCP Range</th><th>Queue</th></tr>'
    +tblRow(['0 – 15','1 (Lowest)'],'odd')+tblRow(['16 – 31','2'],'even')+tblRow(['32 – 47','3'],'odd')+tblRow(['48 – 63','4 (Highest)'],'even')
    +'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}

/* ── Administration ── */
function pageAdmPwd(){
  return '<h2 class="cisco-pg-title">Password Complexity</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Password Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Password Complexity','pwd-complex',false)
    +fld('Minimum Password Length','pwd-minlen','8','')
    +chkRow('Require upper and lower case','pwd-case',false)
    +chkRow('Require numeric character','pwd-num',false)
    +chkRow('Require special character','pwd-spec',false)
    +sep()+'<tr><td class="lbl">Old Password:</td><td><input type="password" value="admin" style="width:160px"></td></tr>'
    +'<tr><td class="lbl">New Password:</td><td><input type="password" value="" style="width:160px"></td></tr>'
    +'<tr><td class="lbl">Confirm Password:</td><td><input type="password" value="" style="width:160px"></td></tr>'
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmUsers(){
  return '<h2 class="cisco-pg-title">Users</h2>'
    +'<div class="cpanel"><div class="cpanel-title">User Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Username</th><th>Group</th><th>Action</th></tr>'
    +tblRow(['admin','Administrator','<button class="cbtn">Edit</button>'],'odd')
    +tblRow(['guest','Guest','<button class="cbtn">Edit</button><button class="cbtn cbtn-del">Delete</button>'],'even')
    +'</table><div style="margin-top:8px"><button class="cbtn">Add User</button></div></div></div>';
}
function pageAdmSession(){
  return '<h2 class="cisco-pg-title">Session Timeout</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Session Timeout Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +fld('Session Timeout (minutes)','sess-to','5','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmBanner(){
  return '<h2 class="cisco-pg-title">Banner Text</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Login Banner</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Login Banner','banner-en',false)
    +'<tr><td class="lbl" style="vertical-align:top;padding-top:6px">Banner Text:</td><td><textarea style="font-family:Arial;font-size:12px;width:300px;height:80px;border:1px solid #999"></textarea></td></tr>'
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmTr069(){
  return '<h2 class="cisco-pg-title">TR-069 Settings</h2>'
    +'<div class="cpanel"><div class="cpanel-title">TR-069 / CWMP Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable TR-069','tr069-en',false)
    +fld('ACS URL','tr069-url','','http://acs.provider.com')
    +fld('ACS Username','tr069-user','','')
    +fld('ACS Password','tr069-pwd','','')
    +fld('Inform Interval (sec)','tr069-int','3600','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmNettools(){
  return '<h2 class="cisco-pg-title">Network Tools</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Ping Test</div><div class="cpanel-body"><div class="cform"><table>'
    +fld('Target IP / Hostname','ping-ip','','np. 8.8.8.8')
    +fld('Ping Size (bytes)','ping-size','32','')
    +fld('Ping Count','ping-cnt','4','')
    +'<tr><td></td><td><button class="cbtn cbtn-primary">Start Test</button></td></tr>'
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Traceroute</div><div class="cpanel-body"><div class="cform"><table>'
    +fld('Target IP / Hostname','tr-ip','','np. 8.8.8.8')
    +'<tr><td></td><td><button class="cbtn cbtn-primary">Start Traceroute</button></td></tr>'
    +'</table></div></div></div>';
}
function pageAdmMirror(){
  return '<h2 class="cisco-pg-title">Port Mirror</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Port Mirror Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Port Mirror','pm-en',false)
    +sel('Source Port','pm-src',[['wan','WAN'],['lan1','LAN 1'],['lan2','LAN 2'],['lan3','LAN 3'],['lan4','LAN 4']],'wan')
    +sel('Mirror Port','pm-dst',[['lan1','LAN 1'],['lan2','LAN 2'],['lan3','LAN 3'],['lan4','LAN 4']],'lan1')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmRkey(){
  return '<h2 class="cisco-pg-title">Remote Key</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Remote Key Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Remote Key','rkey-en',false)
    +fld('Remote Key','rkey-key','','enter remote key')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmSyslog(){
  return '<h2 class="cisco-pg-title">Syslog Settings</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Log Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Syslog','syslog-en',false)
    +fld('Syslog Server IP','syslog-ip','','np. 192.168.1.100')
    +fld('Syslog Server Port','syslog-port','514','')
    +sep()+sel('Log Level','syslog-lvl',[['err','Error'],['warn','Warning'],['info','Information'],['debug','Debug']],'warn')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmEmail(){
  return '<h2 class="cisco-pg-title">Email Log Settings</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Email Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Email Log','email-en',false)
    +fld('SMTP Server','email-smtp','','smtp.gmail.com')
    +fld('SMTP Port','email-port','587','')
    +fld('From Address','email-from','','router@example.com')
    +fld('To Address','email-to','','admin@example.com')
    +fld('Username','email-user','','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmBonjour(){
  return '<h2 class="cisco-pg-title">Bonjour</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Bonjour Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Bonjour (mDNS)','bonjour-en',true)
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmLldp(){
  return '<h2 class="cisco-pg-title">LLDP</h2>'
    +'<div class="cpanel"><div class="cpanel-title">LLDP Settings</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable LLDP','lldp-en',true)
    +fld('Transmit Interval (sec)','lldp-int','30','')
    +fld('Hold Multiplier','lldp-hold','4','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmTime(){
  return '<h2 class="cisco-pg-title">Time Settings</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Time Zone</div><div class="cpanel-body"><div class="cform"><table>'
    +sel('Time Zone','tz-sel',[['CET','(GMT+01:00) Warsaw'],['UTC','(GMT+00:00) UTC'],['EST','(GMT-05:00) New York']],'CET')
    +chkRow('Enable Daylight Saving Time','tz-dst',true)
    +sep()+chkRow('Enable NTP','tz-ntp',true)
    +fld('NTP Server 1','tz-ntp1','pool.ntp.org','')
    +fld('NTP Server 2','tz-ntp2','time.google.com','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Save</button></div>';
}
function pageAdmBackup(){
  return '<h2 class="cisco-pg-title">Backup &amp; Restore</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Backup Configuration</div><div class="cpanel-body">'
    +'<p style="font-family:Arial;font-size:12px;margin-bottom:10px">Click the button below to download the current configuration file.</p>'
    +'<button class="cbtn cbtn-primary">Backup</button></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Restore Configuration</div><div class="cpanel-body"><div class="cform"><table>'
    +'<tr><td class="lbl">Configuration File:</td><td><input type="file" style="font-family:Arial;font-size:12px"></td></tr>'
    +'<tr><td></td><td style="padding-top:6px"><button class="cbtn cbtn-primary">Restore</button></td></tr>'
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Restore Default</div><div class="cpanel-body">'
    +'<p style="font-family:Arial;font-size:12px;margin-bottom:10px">Restore the router to factory default settings. All configuration will be lost.</p>'
    +'<button class="cbtn cbtn-del">Restore Default</button></div></div>';
}
function pageAdmUpgrade(){
  return '<h2 class="cisco-pg-title">Firmware Upgrade</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Firmware Information</div><div class="cpanel-body">'
    +sysRow('Current Firmware Version','1.0.1.14')+sysRow('Model','RV132W')
    +'</div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Upgrade Firmware</div><div class="cpanel-body"><div class="cform"><table>'
    +'<tr><td class="lbl">Firmware File:</td><td><input type="file" style="font-family:Arial;font-size:12px"></td></tr>'
    +'<tr><td></td><td style="padding-top:6px"><button class="cbtn cbtn-primary">Upgrade</button></td></tr>'
    +'</table></div></div></div>';
}
function pageAdmReboot(){
  return '<h2 class="cisco-pg-title">Reboot</h2>'
    +'<div class="cnote-warn">Warning: Rebooting the router will disconnect all network connections. The process takes approximately 60 seconds.</div>'
    +'<div class="cpanel"><div class="cpanel-title">Reboot Device</div><div class="cpanel-body">'
    +'<p style="font-family:Arial;font-size:12px;margin-bottom:12px">Click <b>Reboot</b> to restart the router.</p>'
    +'<button class="cbtn cbtn-primary">Reboot</button></div></div>';
}

function navTo(page) {
  var btn = document.querySelector('#cnav-router [data-page="'+page+'"]');
  if (btn) btn.click();
}

/* ══════════════════════════════════════════
   SWITCH PAGES — TL-SG108E
══════════════════════════════════════════ */
function renderSwitchPage(page) {
  var el = document.getElementById('ccontent-switch');
  switch(page) {
    case 'sw_sysinfo':       el.innerHTML = pageSWsysinfo(); break;
    case 'sw_ipsetting':     el.innerHTML = pageSWipsetting(); attachSWipsetting(); break;
    case 'sw_useracct':      el.innerHTML = pageSWuseracct(); break;
    case 'sw_time':          el.innerHTML = pageSWtime(); break;
    case 'sw_led':           el.innerHTML = pageSWled(); break;
    case 'sw_portsetting':   el.innerHTML = pageSWportsetting(); attachSWportsetting(); break;
    case 'sw_igmp':          el.innerHTML = pageSWigmp(); break;
    case 'sw_lag':           el.innerHTML = pageSWlag(); break;
    case 'sw_mirror':        el.innerHTML = pageSWmirror_sw(); break;
    case 'sw_vlan8021q':     el.innerHTML = pageSWvlan8021q(); attachSWvlan8021q(); break;
    case 'sw_mtuvlan':       el.innerHTML = pageSWmtuvlan(); break;
    case 'sw_portbasedvlan': el.innerHTML = pageSWportbasedvlan(); break;
    case 'sw_qos_port':      el.innerHTML = pageSWqos_port(); break;
    case 'sw_qos_dscp':      el.innerHTML = pageSWqos_dscp(); break;
    case 'sw_portstats':     el.innerHTML = pageSWportstats(); break;
    case 'sw_portmirror':    el.innerHTML = pageSWportmirror(); break;
    case 'sw_loop':          el.innerHTML = pageSWloop(); break;
    case 'sw_cable':         el.innerHTML = pageSWcable(); attachSWcable(); break;
    default: el.innerHTML = '<h2 class="cisco-pg-title">'+labEsc(page)+'</h2>';
  }
}

/* ── TL-SG108E page functions ── */
/* ════ System ════ */
function pageSWsysinfo(){
  return '<h2 class="cisco-pg-title">System Info</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Device Information</div><div class="cpanel-body">'
    +sysRow('Device Description','8-Port Gigabit Easy Smart Switch')
    +sysRow('Device Name','TL-SG108E')
    +sysRow('Hardware Version','TL-SG108E 5.0')
    +sysRow('Firmware Version','1.3.5 Build 20191220 Rel.41590')
    +sysRow('MAC Address','B4-A9-FC-23-45-67')
    +sysRow('IP Address',SS.mgmt.ip||'192.168.0.1')
    +sysRow('Subnet Mask',SS.mgmt.mask||'255.255.255.0')
    +sysRow('Default Gateway',SS.mgmt.gw||'0.0.0.0')
    +'</div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Port Status</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Port</th><th>Status</th><th>Speed/Duplex</th><th>TxGoodPkt</th><th>RxGoodPkt</th></tr>'
    +Array.from({length:8},function(_,i){
      var up=i===0,pt=SS.ports[i+1]||{};
      var linked=up||pt.enabled!==false;
      return tblRow(['Port '+(i+1),linked?'<span style="color:#2d7a2d">Link Up</span>':'<span style="color:#999">Link Down</span>',linked?'1000M/Full':'—',linked?String(1000+i*37):'0',linked?String(800+i*29):'0'],i%2?'even':'odd');
    }).join('')
    +'</table></div></div>';
}
/* ════ IP Setting ════ */
function pageSWipsetting(){
  return '<h2 class="cisco-pg-title">IP Setting</h2>'
    +'<div class="cpanel"><div class="cpanel-title">IP Setting</div><div class="cpanel-body"><div class="cform"><table>'
    +'<tr><td class="lbl">IP Address:</td><td><input type="text" id="tl-ip" value="'+(SS.mgmt.ip||'')+'" placeholder="np. 192.168.0.1" style="width:180px"></td></tr>'
    +'<tr><td class="lbl">Subnet Mask:</td><td><input type="text" id="tl-mask" value="'+(SS.mgmt.mask||'')+'" placeholder="np. 255.255.255.0" style="width:180px"></td></tr>'
    +'<tr><td class="lbl">Default Gateway:</td><td><input type="text" id="tl-gw" value="'+(SS.mgmt.gw||'')+'" placeholder="np. 192.168.0.1" style="width:180px"></td></tr>'
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn" id="tl-ip-cancel">Cancel</button><button class="cbtn cbtn-primary" id="tl-ip-save">Apply</button></div>';
}
function attachSWipsetting(){
  document.getElementById('tl-ip-save').addEventListener('click',function(){
    var ip=document.getElementById('tl-ip').value.trim();
    var mask=document.getElementById('tl-mask').value.trim();
    var gw=document.getElementById('tl-gw').value.trim();
    if(!ip||!mask){toast('⚠ Podaj adres IP i maskę');return;}
    SS.mgmt.ip=ip; SS.mgmt.mask=mask; SS.mgmt.gw=gw;
    saveState(); toast('✔ IP Setting saved');
  });
  document.getElementById('tl-ip-cancel').addEventListener('click',function(){renderSwitchPage('sw_ipsetting');});
}
/* ════ User Account ════ */
function pageSWuseracct(){
  return '<h2 class="cisco-pg-title">User Account</h2>'
    +'<div class="cpanel"><div class="cpanel-title">User Account Management</div><div class="cpanel-body"><div class="cform"><table>'
    +fld('Old Password','ua-old','','')
    +fld('New Password','ua-new','','')
    +fld('Confirm New Password','ua-conf','','')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ Time Setting ════ */
function pageSWtime(){
  return '<h2 class="cisco-pg-title">Time Setting</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Time Setting</div><div class="cpanel-body"><div class="cform"><table>'
    +'<tr><td class="lbl">Get Time From:</td><td>'
    +'<label style="margin-right:12px"><input type="radio" name="tl-tmode" value="ntp" checked> NTP</label>'
    +'<label><input type="radio" name="tl-tmode" value="manual"> Manual</label>'
    +'</td></tr>'
    +fld('NTP Server 1','tl-ntp1','pool.ntp.org','')
    +fld('NTP Server 2','tl-ntp2','time.google.com','')
    +sel('Time Zone','tl-tz',[['UTC+1','(UTC+01:00) Warsaw / Prague'],['UTC','(UTC+00:00) UTC'],['UTC+2','(UTC+02:00) Helsinki / Kyiv']],'UTC+1')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ LED On/Off ════ */
function pageSWled(){
  return '<h2 class="cisco-pg-title">LED On/Off</h2>'
    +'<div class="cpanel"><div class="cpanel-title">LED Status</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable LED','tl-led',true)
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ Port Setting ════ */
function pageSWportsetting(){
  var rows='';
  for(var p=1;p<=8;p++){
    var pt=SS.ports[p]||{};
    rows+='<tr class="'+(p%2?'odd':'even')+'">'
      +'<td style="text-align:center">Port '+p+'</td>'
      +'<td><select id="ps-en-'+p+'" style="font-size:11px">'
      +'<option value="1"'+(pt.enabled===false?'':' selected')+'>Enable</option>'
      +'<option value="0"'+(pt.enabled===false?' selected':'')+'>Disable</option>'
      +'</select></td>'
      +'<td><select id="ps-spd-'+p+'" style="font-size:11px">'
      +['Auto','10M/Half','10M/Full','100M/Half','100M/Full','1000M/Full'].map(function(s){
        return '<option value="'+s+'"'+((pt.speed||'Auto')===s?' selected':'')+'>'+s+'</option>';
      }).join('')
      +'</select></td>'
      +'<td><select id="ps-fc-'+p+'" style="font-size:11px"><option>Disable</option><option>Enable</option></select></td>'
      +'</tr>';
  }
  return '<h2 class="cisco-pg-title">Port Setting</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Port Setting</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Port</th><th>Status</th><th>Speed/Duplex</th><th>Flow Ctrl</th></tr>'
    +rows+'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn" id="ps-cancel">Cancel</button><button class="cbtn cbtn-primary" id="ps-save">Apply</button></div>';
}
function attachSWportsetting(){
  document.getElementById('ps-save').addEventListener('click',function(){
    for(var p=1;p<=8;p++){
      var en=document.getElementById('ps-en-'+p).value==='1';
      var spd=document.getElementById('ps-spd-'+p).value;
      if(!SS.ports[p]) SS.ports[p]={mode:'access',vlan:1};
      SS.ports[p].enabled=en; SS.ports[p].speed=spd;
    }
    saveState(); toast('✔ Port settings saved');
  });
  document.getElementById('ps-cancel').addEventListener('click',function(){renderSwitchPage('sw_portsetting');});
}
/* ════ IGMP Snooping ════ */
function pageSWigmp(){
  return '<h2 class="cisco-pg-title">IGMP Snooping</h2>'
    +'<div class="cpanel"><div class="cpanel-title">IGMP Snooping</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable IGMP Snooping','tl-igmp',false)
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">IGMP Snooping VLAN Config</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>VLAN ID</th><th>IGMP Snooping Status</th><th>Router Port</th><th>Member Port</th></tr>'
    +Object.keys(SS.vlans).map(function(v,i){ return tblRow([v,'Disabled','—','All'],i%2?'even':'odd'); }).join('')
    +'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ LAG ════ */
function pageSWlag(){
  return '<h2 class="cisco-pg-title">LAG</h2>'
    +'<div class="cpanel"><div class="cpanel-title">LAG Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>LAG</th><th>Type</th><th>Ports</th><th>Active Ports</th></tr>'
    +tblRow(['LAG1','—','—','—'],'odd')+tblRow(['LAG2','—','—','—'],'even')
    +'</table></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">LAG Config</div><div class="cpanel-body"><div class="cform"><table>'
    +sel('LAG','lag-sel',[['LAG1','LAG1'],['LAG2','LAG2']],'LAG1')
    +sel('Type','lag-type',[['Static','Static'],['LACP','LACP']],'Static')
    +'<tr><td class="lbl">Member Ports:</td><td>'
    +Array.from({length:8},function(_,i){ return '<label style="margin-right:6px"><input type="checkbox" name="lag-p" value="'+(i+1)+'"> Port '+(i+1)+'</label>'; }).join('')
    +'</td></tr>'
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ Port Mirror (Switching) ════ */
function pageSWmirror_sw(){
  return '<h2 class="cisco-pg-title">Port Mirror</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Port Mirror</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Port Mirror','tl-mir-en',false)
    +sel('Mirroring Port (destination)','tl-mir-dst',Array.from({length:8},function(_,i){ return ['p'+(i+1),'Port '+(i+1)]; }),'p1')
    +'<tr><td class="lbl">Mirrored Ports:</td><td>'
    +Array.from({length:8},function(_,i){ return '<label style="margin-right:6px"><input type="checkbox" name="mir-src" value="'+(i+1)+'"> Port '+(i+1)+'</label>'; }).join('')
    +'</td></tr>'
    +sel('Mode','tl-mir-mode',[['ingress','Ingress'],['egress','Egress'],['both','Both']],'both')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ 802.1Q VLAN — functional ════ */
function pageSWvlan8021q(){
  var vids=Object.keys(SS.vlans).map(Number).sort(function(a,b){return a-b;});
  var vrows=vids.map(function(v,i){
    var mports=[],tports=[];
    for(var p=1;p<=8;p++){
      var pt=SS.ports[p]||{mode:'access',vlan:1};
      if(pt.mode==='trunk') tports.push(p);
      else if(pt.vlan===v) mports.push(p);
    }
    var del=v!==1?'<button class="cbtn cbtn-del" style="padding:1px 6px;font-size:10px" onclick="tlDelVlan('+v+')">Delete</button>':'';
    return '<tr class="'+(i%2?'even':'odd')+'" style="cursor:pointer" onclick="tlShowVlanCfg('+v+',event)" title="Kliknij aby skonfigurować porty">'
      +'<td style="text-align:center">'+v+'</td>'
      +'<td><input class="vl-name" data-vid="'+v+'" value="'+SS.vlans[v]+'"'+(v===1?' readonly style="background:#f0f0f0;width:120px;font-size:11px"':' style="width:120px;font-size:11px"')+'></td>'
      +'<td style="text-align:center">'+(mports.join(', ')||'—')+'</td>'
      +'<td style="text-align:center">'+(tports.join(', ')||'—')+'</td>'
      +'<td>'+del+'</td>'
      +'</tr>';
  }).join('');
  var portCfgHtml='<div id="tl-vlan-cfg" style="margin-top:12px;padding:10px;border:1px solid #4a7a4a;background:#f4fff4;display:none">'
    +'<div style="font-family:Arial;font-size:12px;margin-bottom:8px"><b>Configure VLAN <span id="tl-cfg-vid">—</span>: Port Membership</b> &nbsp;<span style="color:#888;font-size:10px">(T = Tagged, U = Untagged, N = Not Member)</span></div>'
    +'<table class="ctable"><tr><th></th>'
    +Array.from({length:8},function(_,i){ return '<th style="text-align:center">Port '+(i+1)+'</th>'; }).join('')
    +'</tr>'
    +['T','U','N'].map(function(val,ri){
      return '<tr class="'+(ri%2?'even':'odd')+'"><td style="font-weight:bold;padding:4px 8px">'+val+'</td>'
        +Array.from({length:8},function(_,i){
          return '<td style="text-align:center"><input type="radio" name="pm'+(i+1)+'" value="'+val+'" id="pm'+(i+1)+'-'+val+'"></td>';
        }).join('')
        +'</tr>';
    }).join('')
    +'</table>'
    +'<div style="margin-top:8px"><button class="cbtn" id="tl-cfg-cancel">Cancel</button>&nbsp;<button class="cbtn cbtn-primary" id="tl-cfg-apply">Apply</button></div>'
    +'</div>';
  return '<h2 class="cisco-pg-title">802.1Q VLAN</h2>'
    +'<div class="cpanel"><div class="cpanel-title">802.1Q VLAN</div><div class="cpanel-body">'
    +'<div style="font-family:Arial;font-size:11px;color:#555;margin-bottom:6px">Kliknij wiersz VLAN aby skonfigurować przypisanie portów.</div>'
    +'<table class="ctable" id="tl-vlan-tbl">'
    +'<tr><th>VLAN ID</th><th>VLAN Name</th><th>Member Ports</th><th>Tagged Ports</th><th>Action</th></tr>'
    +vrows+'</table>'
    +portCfgHtml
    +'<div style="margin-top:10px;padding:8px;border:1px solid #c8d8c8;background:#f0f8f0">'
    +'<b style="font-family:Arial;font-size:12px">Add VLAN</b>'
    +'<table style="margin-top:6px;font-family:Arial;font-size:12px">'
    +'<tr><td style="padding:3px 8px">VLAN ID (2–4094):</td><td><input type="text" id="tl-new-vid" style="width:80px" maxlength="4"></td></tr>'
    +'<tr><td style="padding:3px 8px">VLAN Name:</td><td><input type="text" id="tl-new-vname" style="width:140px" maxlength="32"></td></tr>'
    +'<tr><td></td><td><button class="cbtn cbtn-primary" id="tl-add-vlan" style="margin-top:4px">Add</button></td></tr>'
    +'</table></div>'
    +'</div></div>'
    +'<div class="cbtn-row"><button class="cbtn cbtn-primary" id="tl-vname-save">Save VLAN Names</button></div>';
}
function attachSWvlan8021q(){
  window.tlDelVlan=function(v){
    if(v===1){showLabNotice('Cannot delete VLAN 1');return;}
    delete SS.vlans[v];
    Object.keys(SS.ports).forEach(function(p){ if(SS.ports[p].vlan===v){SS.ports[p].vlan=1;} });
    saveState(); renderSwitchPage('sw_vlan8021q'); attachSWvlan8021q();
  };
  window.tlShowVlanCfg=function(vid,e){
    if(e&&(e.target.tagName==='BUTTON'||e.target.tagName==='INPUT')) return;
    document.getElementById('tl-cfg-vid').textContent=vid;
    for(var p=1;p<=8;p++){
      var pt=SS.ports[p]||{mode:'access',vlan:1};
      var val=(pt.mode==='trunk')?'T':(pt.vlan===vid?'U':'N');
      var r=document.getElementById('pm'+p+'-'+val);
      if(r) r.checked=true;
    }
    document.getElementById('tl-vlan-cfg').style.display='';
    document.getElementById('tl-vlan-cfg').scrollIntoView({behavior:'smooth',block:'nearest'});
  };
  document.getElementById('tl-cfg-cancel').addEventListener('click',function(){
    document.getElementById('tl-vlan-cfg').style.display='none';
  });
  document.getElementById('tl-cfg-apply').addEventListener('click',function(){
    var vid=parseInt(document.getElementById('tl-cfg-vid').textContent);
    if(isNaN(vid)) return;
    for(var p=1;p<=8;p++){
      var radios=document.getElementsByName('pm'+p);
      var val='N';
      for(var ri=0;ri<radios.length;ri++) if(radios[ri].checked){val=radios[ri].value;break;}
      if(!SS.ports[p]) SS.ports[p]={mode:'access',vlan:1};
      if(val==='T'){ SS.ports[p].mode='trunk'; SS.ports[p].vlan=null; }
      else if(val==='U'){ SS.ports[p].mode='access'; SS.ports[p].vlan=vid; }
      else if(val==='N' && SS.ports[p].vlan===vid){ SS.ports[p].vlan=1; }
    }
    saveState(); toast('✔ VLAN '+vid+' port membership saved');
    renderSwitchPage('sw_vlan8021q'); attachSWvlan8021q();
  });
  document.getElementById('tl-add-vlan').addEventListener('click',function(){
    var vid=parseInt(document.getElementById('tl-new-vid').value);
    var vn=document.getElementById('tl-new-vname').value.trim();
    if(isNaN(vid)||vid<2||vid>4094){showLabNotice('VLAN ID musi być 2–4094');return;}
    if(!vn){showLabNotice('Podaj nazwę VLAN');return;}
    SS.vlans[vid]=vn; saveState();
    renderSwitchPage('sw_vlan8021q'); attachSWvlan8021q();
  });
  document.getElementById('tl-vname-save').addEventListener('click',function(){
    document.querySelectorAll('.vl-name').forEach(function(inp){
      var vid=parseInt(inp.getAttribute('data-vid'));
      if(vid!==1) SS.vlans[vid]=inp.value.trim();
    });
    saveState(); toast('✔ VLAN names saved');
  });
}
/* ════ MTU VLAN ════ */
function pageSWmtuvlan(){
  return '<h2 class="cisco-pg-title">MTU VLAN</h2>'
    +'<div class="cnote">MTU VLAN (Uplink VLAN) — wyznacza jeden port uplink, który komunikuje się ze wszystkimi pozostałymi portami w tej samej sieci VLAN.</div>'
    +'<div class="cpanel"><div class="cpanel-title">MTU VLAN Config</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable MTU VLAN','tl-mtu-en',false)
    +sel('Uplink Port','tl-mtu-up',Array.from({length:8},function(_,i){ return ['p'+(i+1),'Port '+(i+1)]; }),'p1')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ Port Based VLAN ════ */
function pageSWportbasedvlan(){
  var rows='';
  for(var p=1;p<=8;p++){
    rows+='<tr class="'+(p%2?'odd':'even')+'"><td>Port '+p+'</td><td>'
      +Array.from({length:8},function(_,j){
        return '<label style="margin-right:4px"><input type="checkbox" name="pbv'+p+'" value="'+(j+1)+'"'+(p-1===j?'':' checked')+'> '+(j+1)+'</label>';
      }).join('')+'</td></tr>';
  }
  return '<h2 class="cisco-pg-title">Port Based VLAN</h2>'
    +'<div class="cnote">Port Based VLAN pozwala ograniczyć komunikację pomiędzy portami do wybranych grup.</div>'
    +'<div class="cpanel"><div class="cpanel-title">Port Based VLAN Config</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Port Based VLAN','tl-pbv-en',false)
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Port Based VLAN Table</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Port</th><th>VLAN Members</th></tr>'+rows+'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ Port Based QoS ════ */
function pageSWqos_port(){
  var rows='';
  for(var p=1;p<=8;p++){
    rows+='<tr class="'+(p%2?'odd':'even')+'"><td style="text-align:center">Port '+p+'</td><td style="text-align:center">'
      +'<select style="font-size:11px">'
      +[0,1,2,3,4,5,6,7].map(function(q){ return '<option value="'+q+'"'+(q===0?' selected':'')+'>'+q+(q===0?' (Lowest)':q===7?' (Highest)':'')+'</option>'; }).join('')
      +'</select></td></tr>';
  }
  return '<h2 class="cisco-pg-title">Port Based QoS</h2>'
    +'<div class="cpanel"><div class="cpanel-title">QoS Mode</div><div class="cpanel-body"><div class="cform"><table>'
    +sel('QoS Mode','tl-qmode',[['port','Port Based'],['1p','802.1p'],['dscp','DSCP'],['1p_dscp','802.1p/DSCP']],'port')
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Port Priority</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Port</th><th>Priority</th></tr>'+rows+'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ 802.1p/DSCP QoS ════ */
function pageSWqos_dscp(){
  return '<h2 class="cisco-pg-title">802.1p/DSCP QoS</h2>'
    +'<div class="cpanel"><div class="cpanel-title">802.1p Priority Mapping</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>802.1p Priority</th><th>Queue</th></tr>'
    +[['0','Queue 1 (Lowest)'],['1','Queue 1'],['2','Queue 2'],['3','Queue 2'],['4','Queue 3'],['5','Queue 3'],['6','Queue 4'],['7','Queue 4 (Highest)']].map(function(r,i){
      return tblRow(r,i%2?'even':'odd');
    }).join('')
    +'</table></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">DSCP Priority Mapping</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>DSCP Range</th><th>Queue</th></tr>'
    +tblRow(['0–15','Queue 1 (Lowest)'],'odd')+tblRow(['16–31','Queue 2'],'even')+tblRow(['32–47','Queue 3'],'odd')+tblRow(['48–63','Queue 4 (Highest)'],'even')
    +'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ Port Statistics ════ */
function pageSWportstats(){
  var rows='';
  for(var p=1;p<=8;p++){
    var pt=SS.ports[p]||{};
    var up=p===1||pt.enabled!==false;
    rows+=tblRow(['Port '+p,up?'<span style="color:#2d7a2d">Link Up</span>':'<span style="color:#999">Link Down</span>',up?'1000M/Full':'—',up?String(9000+p*37):'0',up?String(23000000+p*100000):'0',up?String(7000+p*29):'0',up?String(18000000+p*80000):'0'],p%2?'odd':'even');
  }
  return '<h2 class="cisco-pg-title">Port Statistics</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Port Statistics</div><div class="cpanel-body">'
    +'<div style="margin-bottom:8px"><button class="cbtn">Refresh</button>&nbsp;<button class="cbtn">Clear All</button></div>'
    +'<table class="ctable"><tr><th>Port</th><th>Status</th><th>Speed</th><th>TxGoodPkt</th><th>TxByte</th><th>RxGoodPkt</th><th>RxByte</th></tr>'
    +rows+'</table></div></div>';
}
/* ════ Port Mirror (Monitoring) ════ */
function pageSWportmirror(){
  return '<h2 class="cisco-pg-title">Port Mirror</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Port Mirror</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Port Mirror','tl-mir-en2',false)
    +sel('Mirroring Port (destination)','tl-mir-dst2',Array.from({length:8},function(_,i){ return ['p'+(i+1),'Port '+(i+1)]; }),'p1')
    +'<tr><td class="lbl">Mirrored Ports:</td><td>'
    +Array.from({length:8},function(_,i){ return '<label style="margin-right:6px"><input type="checkbox" name="mir-src2" value="'+(i+1)+'"> Port '+(i+1)+'</label>'; }).join('')
    +'</td></tr>'
    +sel('Mode','tl-mir-mode2',[['ingress','Ingress'],['egress','Egress'],['both','Both']],'both')
    +'</table></div></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ Loop Prevention ════ */
function pageSWloop(){
  return '<h2 class="cisco-pg-title">Loop Prevention</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Loop Prevention</div><div class="cpanel-body"><div class="cform"><table>'
    +chkRow('Enable Loop Prevention','tl-loop',true)
    +'</table></div></div></div>'
    +'<div class="cpanel"><div class="cpanel-title">Port Loop Prevention</div><div class="cpanel-body">'
    +'<table class="ctable"><tr><th>Port</th><th>Loop Prevention</th><th>Status</th></tr>'
    +Array.from({length:8},function(_,i){ return tblRow(['Port '+(i+1),'Enable','Normal'],i%2?'odd':'even'); }).join('')
    +'</table></div></div>'
    +'<div class="cbtn-row"><button class="cbtn">Cancel</button><button class="cbtn cbtn-primary">Apply</button></div>';
}
/* ════ Cable Test ════ */
function attachSWcable(){
  document.getElementById('tl-cable-test').addEventListener('click',function(){
    var res=document.getElementById('tl-cable-result');
    res.style.display='';
    res.querySelector('.cpanel-body').innerHTML=sysRow('Status','OK')+sysRow('Cable Length','approx. '+(Math.floor(Math.random()*80)+5)+' m');
  });
}
function pageSWcable(){
  return '<h2 class="cisco-pg-title">Cable Test</h2>'
    +'<div class="cpanel"><div class="cpanel-title">Cable Test</div><div class="cpanel-body"><div class="cform"><table>'
    +sel('Port','tl-cable-port',Array.from({length:8},function(_,i){ return ['p'+(i+1),'Port '+(i+1)]; }),'p1')
    +'<tr><td></td><td style="padding-top:6px"><button class="cbtn cbtn-primary" id="tl-cable-test">Test</button></td></tr>'
    +'</table></div></div></div>'
    +'<div class="cpanel" id="tl-cable-result" style="display:none"><div class="cpanel-title">Test Result</div><div class="cpanel-body">'
    +sysRow('Status','OK')+sysRow('Cable Length','approx. 20 m')
    +'</div></div>';
}

function navSW(page){
  var btn=document.querySelector('#cnav-switch [data-page="'+page+'"]');
  if(btn) btn.click();
}

/* ══════════════════════════════════════════
   VERIFY
══════════════════════════════════════════ */
document.getElementById('btn-verify').addEventListener('click', runVerify);
document.getElementById('btn-reset').addEventListener('click', resetAll);
document.getElementById('modal-close-x').addEventListener('click',function(){ document.getElementById('modal').classList.remove('vis'); });
document.getElementById('modal').addEventListener('click',function(e){ if(e.target===this) this.classList.remove('vis'); });

function chk(ok,label,got,exp){ return {ok:ok,label:label,got:got,exp:exp}; }
function runVerify(){
  var sess = SESSIONS[currentSessionKey];
  if (!sess) { showLabNotice('Brak danych weryfikacji dla wybranej sesji.'); return; }
  var r = sess.router, s = sess.sw;
  var checks = [], groups = [];

  if (currentRouterModel === 'tplink') {
    /* ── TP-Link WAN ── */
    var f = checks.length;
    checks.push(chk(TPRS.wan.ip===r.wan_ip,    'WAN: Adres IP',       TPRS.wan.ip||'—',   r.wan_ip));
    checks.push(chk(TPRS.wan.mask===r.wan_mask, 'WAN: Maska podsieci', TPRS.wan.mask||'—', r.wan_mask));
    checks.push(chk(TPRS.wan.gw===r.wan_gw,    'WAN: Brama domyślna', TPRS.wan.gw||'—',   r.wan_gw));
    checks.push(chk(TPRS.wan.dns1===r.wan_dns1,'WAN: Główny DNS',     TPRS.wan.dns1||'—', r.wan_dns1));
    groups.push({t:'TP-Link — WAN', f:f, e:checks.length});
    /* ── TP-Link LAN ── */
    f = checks.length;
    checks.push(chk(TPRS.lan.ip===r.lan_ip,    'LAN: Adres IP',       TPRS.lan.ip||'—',   r.lan_ip));
    checks.push(chk(TPRS.lan.mask===r.lan_mask, 'LAN: Maska podsieci', TPRS.lan.mask||'—', r.lan_mask));
    groups.push({t:'TP-Link — LAN', f:f, e:checks.length});
    /* ── TP-Link DHCP ── */
    f = checks.length;
    if (r.dhcp_on) {
      checks.push(chk(TPRS.dhcp.on===true,           'DHCP: Status',        TPRS.dhcp.on?'Włączony':'Wyłączony','Włączony'));
      checks.push(chk(TPRS.dhcp.start===r.dhcp_start,'DHCP: Początek puli', TPRS.dhcp.start||'—', r.dhcp_start));
      checks.push(chk(TPRS.dhcp.end===r.dhcp_end,   'DHCP: Koniec puli',   TPRS.dhcp.end||'—',   r.dhcp_end));
    } else {
      checks.push(chk(TPRS.dhcp.on===false,'DHCP: Status',TPRS.dhcp.on?'Włączony':'Wyłączony','Wyłączony'));
    }
    groups.push({t:'TP-Link — DHCP', f:f, e:checks.length});
  } else if (currentRouterModel==='mikrotik-wb'||currentRouterModel==='mikrotik-wf') {
  /* ── MikroTik — akceptuje QuickSet LUB ręczną konfigurację (IP > Addresses/Routes/DNS) ── */
  function mtMask(s){
    if(!s) return '';
    var m=s.match(/\(([0-9.]+)\)/); if(m) return m[1];        // "/24 (255.255.255.0)"
    var c=s.match(/\/?\s*(\d{1,2})\s*$/);                      // "/30" lub "30"
    if(c){ var n=parseInt(c[1],10); if(n>=0&&n<=32){ var mk=[]; for(var i=0;i<4;i++){var b=Math.max(0,Math.min(8,n-i*8)); mk.push(256-Math.pow(2,8-b));} return mk.join('.'); } }
    return s;
  }
  function mtAddr(iface){ var f=''; MTRS.addresses.forEach(function(a){ if(a.iface===iface && a.addr && a.addr.indexOf('0.0.0.0')!==0) f=a.addr; }); return f; }
  function mtIp(cidr){ return cidr ? cidr.split('/')[0] : ''; }
  function mtCidrMask(cidr){ return (cidr && cidr.indexOf('/')>=0) ? mtMask('/'+cidr.split('/')[1]) : ''; }
  function mtDefGw(){ var g=''; (MTRS.routes||[]).forEach(function(rt){ if((rt.dst==='0.0.0.0/0'||!rt.dst) && rt.gw) g=rt.gw; }); return g; }
  // wybiera wartość pasującą do oczekiwanej; inaczej pierwszą niepustą (QuickSet ma priorytet)
  function pk(a,b,exp){ if(a===exp) return a; if(b===exp) return b; return a||b||''; }
  var qs=MTRS.quickSet;
  var wanCidr=mtAddr('ether1'), lanCidr=mtAddr('bridge');
  var f=checks.length;
  var vWanIp  =pk(qs.ip,          mtIp(wanCidr),                r.wan_ip);
  var vWanMask=pk(mtMask(qs.mask),mtCidrMask(wanCidr),          r.wan_mask);
  var vWanGw  =pk(qs.gw,          mtDefGw(),                    r.wan_gw);
  var vWanDns =pk(qs.dns,         (MTRS.dns&&MTRS.dns.s1)||'',  r.wan_dns1);
  checks.push(chk(vWanIp===r.wan_ip,     'WAN: Adres IP',       vWanIp||'—',   r.wan_ip));
  checks.push(chk(vWanMask===r.wan_mask, 'WAN: Maska podsieci', vWanMask||'—', r.wan_mask));
  checks.push(chk(vWanGw===r.wan_gw,     'WAN: Brama domyślna', vWanGw||'—',   r.wan_gw));
  checks.push(chk(vWanDns===r.wan_dns1,  'WAN: Główny DNS',     vWanDns||'—',  r.wan_dns1));
  groups.push({t:'MikroTik — WAN', f:f, e:checks.length});
  f=checks.length;
  var vLanIp  =pk(qs.lanip,          mtIp(lanCidr),       r.lan_ip);
  var vLanMask=pk(mtMask(qs.lanmask), mtCidrMask(lanCidr), r.lan_mask);
  checks.push(chk(vLanIp===r.lan_ip,     'LAN: Adres IP',       vLanIp||'—',   r.lan_ip));
  checks.push(chk(vLanMask===r.lan_mask, 'LAN: Maska podsieci', vLanMask||'—', r.lan_mask));
  groups.push({t:'MikroTik — LAN', f:f, e:checks.length});
  if (r.dhcp_on!==undefined){
    f=checks.length;
    var mtDhcpOn=MTRS.dhcpServers.length>0;
    checks.push(chk(mtDhcpOn===r.dhcp_on,'DHCP: Status',mtDhcpOn?'Włączony':'Wyłączony',r.dhcp_on?'Włączony':'Wyłączony'));
    groups.push({t:'MikroTik — DHCP', f:f, e:checks.length});
  }
  } else {
  /* ── Router WAN (Cisco) ── */
  var f = checks.length;
  checks.push(chk(RS.wan.ip===r.wan_ip,    'WAN: Adres IP',       RS.wan.ip||'—',   r.wan_ip));
  checks.push(chk(RS.wan.mask===r.wan_mask, 'WAN: Maska podsieci', RS.wan.mask||'—', r.wan_mask));
  checks.push(chk(RS.wan.gw===r.wan_gw,    'WAN: Brama domyślna', RS.wan.gw||'—',   r.wan_gw));
  checks.push(chk(RS.wan.dns1===r.wan_dns1,'WAN: Główny DNS',     RS.wan.dns1||'—', r.wan_dns1));
  groups.push({t:'Router — WAN', f:f, e:checks.length});

  /* ── Router LAN ── */
  f = checks.length;
  checks.push(chk(RS.lan.ip===r.lan_ip,    'LAN: Adres IP',       RS.lan.ip||'—',   r.lan_ip));
  checks.push(chk(RS.lan.mask===r.lan_mask, 'LAN: Maska podsieci', RS.lan.mask||'—', r.lan_mask));
  groups.push({t:'Router — LAN', f:f, e:checks.length});

  /* ── Router DHCP ── */
  f = checks.length;
  if (r.dhcp_on) {
    checks.push(chk(RS.dhcp.on===true,          'DHCP: Status',        RS.dhcp.on?'Włączony':'Wyłączony','Włączony'));
    checks.push(chk(RS.dhcp.start===r.dhcp_start,'DHCP: Początek puli', RS.dhcp.start||'—', r.dhcp_start));
    checks.push(chk(RS.dhcp.end===r.dhcp_end,   'DHCP: Koniec puli',   RS.dhcp.end||'—',   r.dhcp_end));
  } else {
    checks.push(chk(RS.dhcp.on===false, 'DHCP: Status', RS.dhcp.on?'Włączony':'Wyłączony','Wyłączony'));
  }
  groups.push({t:'Router — DHCP', f:f, e:checks.length});

  /* ── Router VLANs (2024-cze) ── */
  if (r.vlans) {
    f = checks.length;
    Object.keys(r.vlans).forEach(function(vid){
      var vn=parseInt(vid), vd=r.vlans[vid];
      checks.push(chk(RS.vlans[vn]!==undefined,'Router VLAN '+vid+': istnieje',RS.vlans[vn]?'tak':'nie','tak'));
    });
    groups.push({t:'Router — VLAN', f:f, e:checks.length});
  }
  } // end Cisco else

  /* ── Switch ── */
  if (s) {
    f = checks.length;
    checks.push(chk(SS.mgmt.ip===s.ip,    'Przełącznik: Adres IP',  SS.mgmt.ip||'—',   s.ip));
    checks.push(chk(SS.mgmt.mask===s.mask, 'Przełącznik: Maska',     SS.mgmt.mask||'—', s.mask));
    if (s.gw) checks.push(chk(SS.mgmt.gw===s.gw,'Przełącznik: Brama domyślna',SS.mgmt.gw||'—',s.gw));
    groups.push({t:'Switch — Adres IP', f:f, e:checks.length});

    if (s.vlans && Object.keys(s.vlans).length) {
      f = checks.length;
      Object.keys(s.vlans).forEach(function(vid){
        var vn=parseInt(vid);
        checks.push(chk(SS.vlans[vn]!==undefined,'Switch VLAN '+vid+': istnieje',SS.vlans[vn]?'tak':'nie','tak'));
      });
      groups.push({t:'Switch — VLAN', f:f, e:checks.length});
    }

    if (s.ports && Object.keys(s.ports).length) {
      f = checks.length;
      Object.keys(s.ports).forEach(function(p){
        var pn=parseInt(p), ep=s.ports[p], ap=SS.ports[pn];
        if (ep.mode==='trunk') {
          checks.push(chk(ap&&ap.mode==='trunk','Switch Port GE'+p+': Trunk',ap?ap.mode:'—','trunk'));
        } else {
          checks.push(chk(ap&&ap.mode==='access'&&ap.vlan===ep.vlan,
            'Switch Port GE'+p+': Access VLAN'+ep.vlan,
            ap?(ap.mode+'/V'+ap.vlan):'—','access/V'+ep.vlan));
        }
      });
      groups.push({t:'Switch — Porty', f:f, e:checks.length});
    }
  }

  var total=checks.length, passed=checks.filter(function(c){return c.ok;}).length;
  var pct=Math.round(passed/total*100);
  var html='';
  groups.forEach(function(g){
    html+='<div class="chk-grp"><div class="chk-grp-ttl">'+g.t+'</div>';
    for(var i=g.f;i<g.e;i++){
      var c=checks[i];
      var icon=c.ok?'<span class="chk-ok">✔</span>':'<span class="chk-fail">✘</span>';
      var detail=c.ok?'':'<span style="color:#888;font-size:10px;margin-left:4px">Wpisano: <b>'+c.got+'</b> — oczekiwano: <b>'+c.exp+'</b></span>';
      html+='<div class="chk-item">'+icon+'<span>'+c.label+detail+'</span></div>';
    }
    html+='</div>';
  });
  html+='<div class="score-row"><div class="score-v '+(pct>=80?'score-pass':'score-fail')+'">'+passed+'/'+total+' ('+pct+'%)</div>'
    +'<div style="font-family:Arial;font-size:12px;margin-top:6px">'+(pct>=80?'Gratulacje! Konfiguracja jest poprawna.':'Sprawdź zaznaczone punkty i popraw błędy.')+'</div></div>'
    +'<button class="modal-close-btn" id="mcb">Zamknij</button>';
  document.getElementById('modal-body').innerHTML=html;
  document.getElementById('modal').classList.add('vis');
  document.getElementById('mcb').addEventListener('click',function(){ document.getElementById('modal').classList.remove('vis'); });
}

/* ══════════════════════════════════════════
   TP-LINK STATE
══════════════════════════════════════════ */
var TPRS = {
  wan: { type:'static', ip:'', mask:'', gw:'', dns1:'', dns2:'' },
  lan: { ip:'', mask:'' },
  dhcp: { on:true, start:'', end:'', lease:'' },
  wireless: { ssid:'', channel:'6', mode:'bgn', password:'' }
};
function saveTpState() {
  try { localStorage.setItem('tp_'+currentSessionKey, JSON.stringify(TPRS)); } catch(e) {}
}
function loadTpState(key) {
  try {
    var raw = localStorage.getItem('tp_'+key);
    if (!raw) return;
    var s = JSON.parse(raw);
    if (s.wan) TPRS.wan = s.wan;
    if (s.lan) TPRS.lan = s.lan;
    if (s.dhcp) TPRS.dhcp = s.dhcp;
    if (s.wireless) TPRS.wireless = s.wireless;
  } catch(e) {}
}

/* ── TP-Link router model switch ── */
var currentRouterModel = 'cisco';
function switchRouterModel(model) {
  currentRouterModel = model;
  document.getElementById('wrap-router').style.display = model === 'cisco' ? '' : 'none';
  document.getElementById('wrap-tplink').style.display = model === 'tplink' ? '' : 'none';
  document.getElementById('wrap-mikrotik-wb').style.display = model === 'mikrotik-wb' ? '' : 'none';
  document.getElementById('wrap-mikrotik-wf').style.display = model === 'mikrotik-wf' ? '' : 'none';
}
document.getElementById('router-model-sel').addEventListener('change', function() {
  switchRouterModel(this.value);
});
document.getElementById('tab-router').addEventListener('click', function() {
  document.getElementById('router-model-wrap').style.display = '';
});
document.getElementById('tab-switch').addEventListener('click', function() {
  document.getElementById('router-model-wrap').style.display = 'none';
});

/* ══════════════════════════════════════════
   TP-LINK NAV
══════════════════════════════════════════ */
var TPMENU = [
  {type:'top', label:'Status', page:'tp_status'},
  {type:'top', label:'Quick Setup', page:'tp_quicksetup'},
  {type:'top', label:'WPS', page:'tp_wps'},
  {type:'cat', label:'Network', id:'tp_net', open:false, items:[
    {label:'WAN', page:'tp_wan', task:true},
    {label:'MAC Clone', page:'tp_mac'},
    {label:'LAN', page:'tp_lan', task:true}
  ]},
  {type:'cat', label:'Wireless', id:'tp_wl', open:false, items:[
    {label:'Wireless Settings', page:'tp_wl_basic'},
    {label:'Wireless Security', page:'tp_wl_sec'},
    {label:'Wireless MAC Filtering', page:'tp_wl_mac'},
    {label:'Wireless Advanced', page:'tp_wl_adv'},
    {label:'Wireless Statistics', page:'tp_wl_stat'}
  ]},
  {type:'cat', label:'DHCP', id:'tp_dhcp', open:false, items:[
    {label:'DHCP Settings', page:'tp_dhcp_set', task:true},
    {label:'DHCP Clients List', page:'tp_dhcp_cli'},
    {label:'Address Reservation', page:'tp_dhcp_res'}
  ]},
  {type:'cat', label:'Forwarding', id:'tp_fwd', open:false, items:[
    {label:'Virtual Servers', page:'tp_fwd_vs'},
    {label:'Port Triggering', page:'tp_fwd_pt'},
    {label:'DMZ', page:'tp_fwd_dmz'},
    {label:'UPnP', page:'tp_fwd_upnp'}
  ]},
  {type:'cat', label:'Security', id:'tp_secat', open:false, items:[
    {label:'Basic Security', page:'tp_sec_basic'},
    {label:'Advanced Security', page:'tp_sec_adv'},
    {label:'Local Management', page:'tp_sec_local'},
    {label:'Remote Management', page:'tp_sec_rem'}
  ]},
  {type:'top', label:'Parental Control', page:'tp_parental'},
  {type:'cat', label:'Access Control', id:'tp_ac', open:false, items:[
    {label:'Rule', page:'tp_ac_rule'},
    {label:'Host', page:'tp_ac_host'},
    {label:'Target', page:'tp_ac_target'},
    {label:'Schedule', page:'tp_ac_sched'}
  ]},
  {type:'cat', label:'Advanced Routing', id:'tp_ar', open:false, items:[
    {label:'Static Routing List', page:'tp_ar_static'},
    {label:'System Routing Table', page:'tp_ar_sys'}
  ]},
  {type:'cat', label:'Bandwidth Control', id:'tp_bw', open:false, items:[
    {label:'Control Settings', page:'tp_bw_ctrl'},
    {label:'Rules List', page:'tp_bw_rules'}
  ]},
  {type:'cat', label:'IP & MAC Binding', id:'tp_imb', open:false, items:[
    {label:'Binding Settings', page:'tp_imb_set'},
    {label:'ARP List', page:'tp_imb_arp'}
  ]},
  {type:'top', label:'Dynamic DNS', page:'tp_ddns_set'},
  {type:'cat', label:'System Tools', id:'tp_systools', open:false, items:[
    {label:'Time Settings', page:'tp_sys_time'},
    {label:'Diagnostic', page:'tp_sys_diag'},
    {label:'Firmware Upgrade', page:'tp_sys_fw'},
    {label:'Factory Defaults', page:'tp_sys_factory'},
    {label:'Backup & Restore', page:'tp_sys_backup'},
    {label:'Reboot', page:'tp_sys_reboot'},
    {label:'Password', page:'tp_sys_pwd'},
    {label:'System Log', page:'tp_sys_log'},
    {label:'Statistics', page:'tp_sys_stats'}
  ]}
];

function buildTpNav(menu, navEl, loadFn, initPage) {
  var h = '';
  menu.forEach(function(e) {
    if (e.type === 'top') {
      h += '<button class="tpnav-top" data-page="'+e.page+'">'+e.label+'</button>';
    } else {
      h += '<div class="tpnav-cat'+(e.open?' open':'')+'" id="tns-'+e.id+'">'
        +'<div class="tpnav-cat-hdr">'+e.label+'<span class="tpnav-arr">▶</span></div>'
        +'<div class="tpnav-items">';
      e.items.forEach(function(it) {
        h += '<button class="tpnav-item'+(it.task?' task-item':'')+'" data-page="'+it.page+'">'+it.label+'</button>';
      });
      h += '</div></div>';
    }
  });
  navEl.innerHTML = h;
  navEl.querySelectorAll('.tpnav-cat-hdr').forEach(function(hdr) {
    hdr.addEventListener('click', function() { hdr.parentElement.classList.toggle('open'); });
  });
  navEl.querySelectorAll('[data-page]').forEach(function(btn) {
    btn.addEventListener('click', function() {
      navEl.querySelectorAll('[data-page]').forEach(function(b) { b.classList.remove('sel'); });
      btn.classList.add('sel');
      var c = btn.closest('.tpnav-cat');
      if (c) c.classList.add('open');
      loadFn(btn.getAttribute('data-page'));
    });
  });
  var init = navEl.querySelector('[data-page="'+initPage+'"]');
  if (init) { init.classList.add('sel'); loadFn(initPage); }
}

/* ══════════════════════════════════════════
   TP-LINK PAGE RENDERER
══════════════════════════════════════════ */
function renderTpPage(page) {
  var el = document.getElementById('tpcontent');
  var hp = document.getElementById('tphelp');
  switch(page) {
    case 'tp_status':     el.innerHTML = tpPageStatus(hp);        break;
    case 'tp_quicksetup': el.innerHTML = tpPageQuickSetup(hp);    break;
    case 'tp_wps':        el.innerHTML = tpPageWPS(hp);           break;
    case 'tp_wan':        el.innerHTML = tpPageWAN(hp);           tpAttachWAN(); break;
    case 'tp_mac':        el.innerHTML = tpPageMac(hp);           break;
    case 'tp_lan':        el.innerHTML = tpPageLAN(hp);           tpAttachLAN(); break;
    case 'tp_wl_basic':   el.innerHTML = tpPageWlBasic(hp);       tpAttachWlBasic(); break;
    case 'tp_wl_sec':     el.innerHTML = tpPageWlSec(hp);         tpAttachWlSec(); break;
    case 'tp_wl_mac':     el.innerHTML = tpPageWlMac(hp);         break;
    case 'tp_wl_adv':     el.innerHTML = tpPageWlAdv(hp);         break;
    case 'tp_wl_stat':    el.innerHTML = tpPageWlStat(hp);        break;
    case 'tp_dhcp_set':   el.innerHTML = tpPageDHCP(hp);          tpAttachDHCP(); break;
    case 'tp_dhcp_cli':   el.innerHTML = tpPageDhcpClients(hp);   break;
    case 'tp_dhcp_res':   el.innerHTML = tpPageDhcpRes(hp);       break;
    case 'tp_fwd_vs':     el.innerHTML = tpPageFwdVS(hp);         break;
    case 'tp_fwd_pt':     el.innerHTML = tpPageFwdPT(hp);         break;
    case 'tp_fwd_dmz':    el.innerHTML = tpPageFwdDMZ(hp);        break;
    case 'tp_fwd_upnp':   el.innerHTML = tpPageFwdUPnP(hp);       break;
    case 'tp_sec_basic':  el.innerHTML = tpPageSecBasic(hp);      break;
    case 'tp_sec_adv':    el.innerHTML = tpPageSecAdv(hp);        break;
    case 'tp_sec_local':  el.innerHTML = tpPageSecLocal(hp);      break;
    case 'tp_sec_rem':    el.innerHTML = tpPageSecRem(hp);        break;
    case 'tp_parental':   el.innerHTML = tpPageParental(hp);      break;
    case 'tp_ac_rule':    el.innerHTML = tpPageACRule(hp);        break;
    case 'tp_ac_host':    el.innerHTML = tpPageACHost(hp);        break;
    case 'tp_ac_target':  el.innerHTML = tpPageACTarget(hp);      break;
    case 'tp_ac_sched':   el.innerHTML = tpPageACSched(hp);       break;
    case 'tp_ar_static':  el.innerHTML = tpPageARStatic(hp);      break;
    case 'tp_ar_sys':     el.innerHTML = tpPageARSys(hp);         break;
    case 'tp_bw_ctrl':    el.innerHTML = tpPageBWCtrl(hp);        break;
    case 'tp_bw_rules':   el.innerHTML = tpPageBWRules(hp);       break;
    case 'tp_imb_set':    el.innerHTML = tpPageImbSet(hp);        break;
    case 'tp_imb_arp':    el.innerHTML = tpPageImbARP(hp);        break;
    case 'tp_ddns_set':   el.innerHTML = tpPageDDNS(hp);          break;
    case 'tp_sys_time':   el.innerHTML = tpPageSysTime(hp);       break;
    case 'tp_sys_diag':   el.innerHTML = tpPageSysDiag(hp);       tpAttachDiag(); break;
    case 'tp_sys_fw':     el.innerHTML = tpPageSysFW(hp);         break;
    case 'tp_sys_factory':el.innerHTML = tpPageSysFactory(hp);    break;
    case 'tp_sys_backup': el.innerHTML = tpPageSysBackup(hp);     break;
    case 'tp_sys_reboot': el.innerHTML = tpPageSysReboot(hp);     tpAttachReboot(); break;
    case 'tp_sys_pwd':    el.innerHTML = tpPageSysPwd(hp);        break;
    case 'tp_sys_log':    el.innerHTML = tpPageSysLog(hp);        break;
    case 'tp_sys_stats':  el.innerHTML = tpPageSysStats(hp);      break;
    default:              el.innerHTML = tpPageGeneric(page, hp); break;
  }
}

/* ── TP-Link helper builders ── */
function tpI(id, val, ph, w) {
  return '<input type="text" id="'+id+'" value="'+(val||'')+'" placeholder="'+(ph||'')+'"'+(w?' style="width:'+w+'px"':'')+' >';
}
function tpS(id, opts, cur) {
  return '<select id="'+id+'">'+opts.map(function(o){ return '<option value="'+o[0]+'"'+(o[0]===cur?' selected':'')+'>'+o[1]+'</option>'; }).join('')+'</select>';
}
function tpR(lbl, val) { return '<tr><td class="lbl">'+lbl+':</td><td class="val">'+val+'</td></tr>'; }
function tpSepR() { return '<tr class="tsep"><td colspan="2"></td></tr>'; }
function tpTitle(t) { return '<div class="tp-pg-title">'+t+'</div><div class="tp-pg-line"></div>'; }
function tpHelp(hp, title, html) { if (hp) hp.innerHTML = '<span class="tp-help-title">'+title+'</span>'+html; }

/* ── Status ── */
function tpPageStatus(hp) {
  tpHelp(hp,'Status','<p>The <b>Status</b> page displays the Router\'s current status and configuration, including LAN, Wireless, and WAN information.</p>');
  return tpTitle('Status')
    +'<div class="tp-st"><table>'
    +tpR('Firmware Version','3.13.18 Build 120522 Rel.31564n')
    +tpR('Hardware Version','WR841N v8 00000000')
    +'<tr><td colspan="2"><div style="height:1px;background:#c0c0c0;margin:6px 0 2px"></div></td></tr>'
    +'<tr><td colspan="2" class="tp-st-sec">LAN</td></tr>'
    +tpR('MAC Address','A4-2B-8C-12-34-55')
    +tpR('IP Address',TPRS.lan.ip||'192.168.0.1')
    +tpR('Subnet Mask',TPRS.lan.mask||'255.255.255.0')
    +'<tr><td colspan="2"><div style="height:1px;background:#c0c0c0;margin:6px 0 2px"></div></td></tr>'
    +'<tr><td colspan="2" class="tp-st-sec">Wireless</td></tr>'
    +tpR('Wireless Radio','Enable')
    +tpR('Name (SSID)',TPRS.wireless.ssid||'TP-LINK_1234')
    +tpR('Channel',TPRS.wireless.channel||'6')
    +tpR('Mode','11bgn mixed')
    +tpR('MAC Address','A4-2B-8C-12-34-57')
    +'<tr><td colspan="2"><div style="height:1px;background:#c0c0c0;margin:6px 0 2px"></div></td></tr>'
    +'<tr><td colspan="2" class="tp-st-sec">WAN</td></tr>'
    +tpR('MAC Address','A4-2B-8C-12-34-56')
    +tpR('IP Address',TPRS.wan.ip||'0.0.0.0')
    +tpR('Subnet Mask',TPRS.wan.mask||'0.0.0.0')
    +tpR('Default Gateway',TPRS.wan.gw||'0.0.0.0')
    +tpR('DNS Server',TPRS.wan.dns1||'0.0.0.0')
    +'</table>'
    +'<button class="tp-save-btn" style="padding:3px 16px" onclick="renderTpPage(\'tp_status\')">Refresh</button>'
    +'</div>';
}

/* ── Quick Setup ── */
function tpPageQuickSetup(hp) {
  tpHelp(hp,'Quick Setup','<p>The <b>Quick Setup</b> will guide you to configure the basic settings of your Router. Click <b>Next</b> to continue.</p>');
  return tpTitle('Quick Setup')
    +'<div class="tp-form">'
    +'<p style="font-size:12px;font-family:Arial;color:#333;margin:0 0 12px">Please select your connection type and click <b>Next</b>.</p>'
    +'<div style="font-size:12px;line-height:2.4">'
    +'<div><label><input type="radio" name="qs-type" value="pppoe"> PPPoE (ADSL)</label></div>'
    +'<div><label><input type="radio" name="qs-type" value="dhcp"> Dynamic IP</label></div>'
    +'<div><label><input type="radio" name="qs-type" value="static" checked> Static IP</label></div>'
    +'<div><label><input type="radio" name="qs-type" value="l2tp"> L2TP</label></div>'
    +'<div><label><input type="radio" name="qs-type" value="pptp"> PPTP</label></div>'
    +'</div>'
    +'<button class="tp-save-btn" style="margin-top:16px" onclick="renderTpPage(\'tp_wan\')">Next</button>'
    +'</div>';
}

/* ── Network > WAN ── */
function tpPageWAN(hp) {
  tpHelp(hp,'WAN','<p>Select <b>Static IP</b> if your ISP provides a fixed IP address. Enter the IP, Subnet Mask, Default Gateway, and DNS addresses provided by your ISP.</p>');
  return tpTitle('WAN')
    +'<div class="tp-form"><table>'
    +tpR('WAN Connection Type', tpS('tp-wan-type',[['static','Static IP'],['dhcp','Dynamic IP'],['pppoe','PPPoE'],['l2tp','L2TP'],['pptp','PPTP']],'static')
      +' <button class="tp-save-btn" style="display:inline-block;margin:0;padding:2px 10px;font-size:11px">Detect</button>')
    +tpSepR()
    +tpR('IP Address', tpI('tp-wan-ip',TPRS.wan.ip,'0.0.0.0'))
    +tpR('Subnet Mask', tpI('tp-wan-mask',TPRS.wan.mask,'0.0.0.0'))
    +tpR('Default Gateway', tpI('tp-wan-gw',TPRS.wan.gw,'0.0.0.0'))
    +tpSepR()
    +tpR('MTU Size (in bytes)', tpI('tp-wan-mtu','1500','',55)+' <span class="hint">(The default is 1500, do not change unless necessary.)</span>')
    +tpSepR()
    +tpR('Primary DNS', tpI('tp-wan-dns1',TPRS.wan.dns1,'0.0.0.0'))
    +tpR('Secondary DNS', tpI('tp-wan-dns2',TPRS.wan.dns2,'0.0.0.0')+' <span class="hint">(Optional)</span>')
    +'</table></div>'
    +'<button class="tp-save-btn" id="tp-wan-save">Save</button>';
}
function tpAttachWAN() {
  document.getElementById('tp-wan-save').addEventListener('click', function() {
    TPRS.wan.ip   = document.getElementById('tp-wan-ip').value.trim();
    TPRS.wan.mask = document.getElementById('tp-wan-mask').value.trim();
    TPRS.wan.gw   = document.getElementById('tp-wan-gw').value.trim();
    TPRS.wan.dns1 = document.getElementById('tp-wan-dns1').value.trim();
    TPRS.wan.dns2 = document.getElementById('tp-wan-dns2').value.trim();
    saveTpState(); toast('✔ WAN settings saved');
  });
}

/* ── Network > LAN ── */
function tpPageLAN(hp) {
  tpHelp(hp,'LAN','<p>The <b>LAN</b> page allows you to configure the Router\'s LAN IP address and subnet mask. The default IP is 192.168.0.1.</p>');
  return tpTitle('LAN')
    +'<div class="tp-form"><table>'
    +tpR('MAC Address','A4-2B-8C-12-34-55')
    +tpSepR()
    +tpR('IP Address', tpI('tp-lan-ip',TPRS.lan.ip,'np. x.x.x.x'))
    +tpR('Subnet Mask', tpI('tp-lan-mask',TPRS.lan.mask,'np. x.x.x.x'))
    +'</table></div>'
    +'<button class="tp-save-btn" id="tp-lan-save">Save</button>';
}
function tpAttachLAN() {
  document.getElementById('tp-lan-save').addEventListener('click', function() {
    TPRS.lan.ip   = document.getElementById('tp-lan-ip').value.trim();
    TPRS.lan.mask = document.getElementById('tp-lan-mask').value.trim();
    saveTpState(); toast('✔ LAN settings saved');
  });
}

/* ── Network > MAC Clone ── */
function tpPageMac(hp) {
  tpHelp(hp,'MAC Clone','<p>The <b>MAC Clone</b> feature allows you to copy your PC\'s MAC address to the Router\'s WAN port. This is useful if your ISP requires a specific MAC address.</p>');
  return tpTitle('MAC Clone')
    +'<div class="tp-form"><table>'
    +tpR('WAN MAC Address','A4-2B-8C-12-34-56')
    +tpSepR()
    +tpR("Your PC's MAC Address", tpI('tp-mac-pc','','XX-XX-XX-XX-XX-XX',165))
    +'</table></div>'
    +'<button class="tp-save-btn" id="tp-mac-clone">Clone MAC Address</button>';
}

/* ── Wireless > Wireless Settings ── */
function tpPageWlBasic(hp) {
  tpHelp(hp,'Wireless Settings','<p>You can configure the basic features of the wireless LAN interface, including the SSID, channel, and mode. Click <b>Save</b> to apply your settings.</p>');
  return tpTitle('Wireless Settings')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">Wireless Radio:</td><td class="val">'
    +'<label><input type="radio" name="tp-wl-en" value="en" checked> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-wl-en" value="dis"> Disable</label></td></tr>'
    +tpSepR()
    +tpR('Name (SSID)', tpI('tp-wl-ssid',TPRS.wireless.ssid,'TP-LINK_XXXX',165))
    +tpR('Region', tpS('tp-wl-region',[['pl','Poland'],['eu','Europe'],['us','United States'],['de','Germany']],'pl'))
    +tpR('Channel Width', tpS('tp-wl-bw',[['auto','Auto'],['20','20MHz'],['40','40MHz']],'auto'))
    +tpR('Channel', tpS('tp-wl-ch',[['auto','Auto'],['1','1'],['2','2'],['3','3'],['4','4'],['5','5'],['6','6'],['7','7'],['8','8'],['9','9'],['10','10'],['11','11'],['12','12'],['13','13']],TPRS.wireless.channel||'6'))
    +tpR('Mode', tpS('tp-wl-mode',[['bgn','11bgn mixed'],['bg','11bg mixed'],['n','11n only'],['b','11b only'],['g','11g only']],'bgn'))
    +tpSepR()
    +'<tr><td class="lbl">Enable WDS Bridging:</td><td class="val"><label><input type="checkbox"> Enable</label></td></tr>'
    +'</table></div>'
    +'<button class="tp-save-btn" id="tp-wl-save">Save</button>';
}
function tpAttachWlBasic() {
  document.getElementById('tp-wl-save').addEventListener('click', function() {
    TPRS.wireless.ssid    = document.getElementById('tp-wl-ssid').value.trim();
    TPRS.wireless.channel = document.getElementById('tp-wl-ch').value;
    TPRS.wireless.mode    = document.getElementById('tp-wl-mode').value;
    saveTpState(); toast('✔ Wireless settings saved');
  });
}

/* ── Wireless > Security ── */
function tpPageWlSec(hp) {
  tpHelp(hp,'Wireless Security','<p><b>WPA/WPA2-Personal</b> is the recommended security option. Choose a strong password of at least 8 characters. WEP uses an older, less secure algorithm.</p>');
  return tpTitle('Wireless Security')
    +'<div class="tp-form">'
    +'<div style="font-size:12px;margin-bottom:8px"><label><input type="radio" name="tp-sec-t" value="disable"> Disable Security</label></div>'
    +'<div style="font-size:12px;margin-bottom:4px"><label><input type="radio" name="tp-sec-t" value="wpa2" checked> WPA/WPA2 - Personal (Recommended)</label></div>'
    +'<table style="margin-left:20px">'
    +tpR('Version', tpS('tp-sec-ver',[['auto','Automatic'],['wpa','WPA'],['wpa2','WPA2']],'auto'))
    +tpR('Encryption', tpS('tp-sec-enc',[['auto','Automatic (TKIP/AES)'],['tkip','TKIP'],['aes','AES']],'auto'))
    +tpR('Wireless Password', tpI('tp-sec-pwd',TPRS.wireless.password,'At least 8 characters',180))
    +tpR('Group Key Update Period', tpI('tp-sec-gkup','0','',60)+' <span class="hint">Seconds (0 means no update)</span>')
    +'</table>'
    +'<div style="font-size:12px;margin-top:10px"><label><input type="radio" name="tp-sec-t" value="wpa-ent"> WPA/WPA2 - Enterprise</label></div>'
    +'<div style="font-size:12px;margin-top:6px"><label><input type="radio" name="tp-sec-t" value="wep"> WEP</label></div>'
    +'</div>'
    +'<button class="tp-save-btn" id="tp-sec-save">Save</button>';
}
function tpAttachWlSec() {
  document.getElementById('tp-sec-save').addEventListener('click', function() {
    TPRS.wireless.password = document.getElementById('tp-sec-pwd').value.trim();
    saveTpState(); toast('✔ Wireless security saved');
  });
}

/* ── DHCP > DHCP Settings ── */
function tpPageDHCP(hp) {
  tpHelp(hp,'DHCP Settings','<p>The Router acts as a DHCP server, automatically assigning IP addresses to computers on your LAN. Enter a range of IP addresses that will be assigned to clients.</p>');
  function ph(v, fb) { return fb; }
  return tpTitle('DHCP Settings')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">DHCP Server:</td><td class="val">'
    +'<label><input type="radio" name="tp-dhcp" id="tp-dhcp-en" value="en"'+(TPRS.dhcp.on?' checked':'')+'>Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-dhcp" id="tp-dhcp-dis" value="dis"'+(!TPRS.dhcp.on?' checked':'')+'>Disable</label></td></tr>'
    +tpSepR()
    +tpR('Start IP Address', tpI('tp-dhcp-start',TPRS.dhcp.start,'np. x.x.x.x'))
    +tpR('End IP Address', tpI('tp-dhcp-end',TPRS.dhcp.end,'np. x.x.x.x'))
    +tpR('Address Lease Time', tpI('tp-dhcp-lease',TPRS.dhcp.lease,'',60)+' <span class="hint">minutes (1~2880 minutes)</span>')
    +tpSepR()
    +tpR('Default Gateway', tpI('tp-dhcp-gw','',ph('lan_ip',''))+' <span class="hint">(Optional)</span>')
    +tpR('Default Domain', tpI('tp-dhcp-domain','','')+' <span class="hint">(Optional)</span>')
    +tpR('Primary DNS', tpI('tp-dhcp-dns1','',ph('wan_dns1',''))+' <span class="hint">(Optional)</span>')
    +tpR('Secondary DNS', tpI('tp-dhcp-dns2','','')+' <span class="hint">(Optional)</span>')
    +'</table></div>'
    +'<button class="tp-save-btn" id="tp-dhcp-save">Save</button>';
}
function tpAttachDHCP() {
  document.getElementById('tp-dhcp-save').addEventListener('click', function() {
    TPRS.dhcp.on    = document.getElementById('tp-dhcp-en').checked;
    TPRS.dhcp.start = document.getElementById('tp-dhcp-start').value.trim();
    TPRS.dhcp.end   = document.getElementById('tp-dhcp-end').value.trim();
    TPRS.dhcp.lease = document.getElementById('tp-dhcp-lease').value.trim();
    saveTpState(); toast('✔ DHCP settings saved');
  });
}

/* ── DHCP Clients List ── */
function tpPageDhcpClients(hp) {
  tpHelp(hp,'DHCP Clients List','<p>This page shows all devices that have been assigned an IP address by the Router\'s DHCP server, including their MAC addresses and lease times.</p>');
  return tpTitle('DHCP Clients List')
    +'<div style="padding:10px 14px">'
    +'<table class="tp-tbl"><tr><th>#</th><th>Client Name</th><th>MAC Address</th><th>Assigned IP</th><th>Lease Time</th></tr></table>'
    +'<p style="font-family:Arial;font-size:12px;color:#888;text-align:center;padding:10px 0">No DHCP clients connected.</p>'
    +'<button class="tp-save-btn" style="padding:3px 16px" onclick="renderTpPage(\'tp_dhcp_cli\')">Refresh</button>'
    +'</div>';
}

/* ── WPS ── */
function tpPageWPS(hp) {
  tpHelp(hp,'WPS','<p><b>WPS (Wi-Fi Protected Setup)</b> allows devices to quickly and securely connect to the wireless network using either a PIN or a Push Button.</p>');
  return tpTitle('WPS')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">WPS Status:</td><td class="val">'
    +'<label><input type="radio" name="tp-wps-en" value="en" checked> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-wps-en" value="dis"> Disable</label></td></tr>'
    +tpSepR()
    +tpR('Current PIN','12345678 &nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:2px 10px;font-size:11px">Generate</button>')
    +tpSepR()
    +'<tr><td class="lbl">Add a new device:</td><td class="val">'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:2px 14px">Add device</button>'
    +'</td></tr>'
    +'</table></div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Forwarding > Virtual Servers ── */
function tpPageFwdVS(hp) {
  tpHelp(hp,'Virtual Servers','<p>A virtual server defines a mapping between a service port on the WAN and an IP address and port on the LAN, allowing Internet users to access services on the local network.</p>');
  return tpTitle('Virtual Servers')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>Service Port</th><th>Internal Port</th><th>IP Address</th><th>Protocol</th><th>Status</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No virtual server entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>';
}

/* ── Forwarding > Port Triggering ── */
function tpPageFwdPT(hp) {
  tpHelp(hp,'Port Triggering','<p>Port Triggering allows the Router to open incoming ports for applications that require dynamic port forwarding. When a client sends data on the Trigger Port, the Incoming Port is opened.</p>');
  return tpTitle('Port Triggering')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>Trigger Port</th><th>Trigger Protocol</th><th>Incoming Port</th><th>Incoming Protocol</th><th>Status</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No port triggering entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>';
}

/* ── Forwarding > DMZ ── */
function tpPageFwdDMZ(hp) {
  tpHelp(hp,'DMZ','<p>A <b>DMZ</b> (Demilitarized Zone) host exposes one local computer to the Internet by forwarding all unrecognized ports to it. This is useful for hosting servers but reduces that computer\'s security.</p>');
  return tpTitle('DMZ')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">DMZ Status:</td><td class="val">'
    +'<label><input type="radio" name="tp-dmz" value="en"> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-dmz" value="dis" checked> Disable</label></td></tr>'
    +tpSepR()
    +tpR('DMZ Host IP Address', tpI('tp-dmz-ip','','0.0.0.0'))
    +'</table></div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Forwarding > UPnP ── */
function tpPageFwdUPnP(hp) {
  tpHelp(hp,'UPnP','<p><b>UPnP</b> (Universal Plug and Play) allows applications to automatically open ports they need in the Router. Applications like file sharing or online games may use this feature.</p>');
  return tpTitle('UPnP')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">UPnP:</td><td class="val">'
    +'<label><input type="radio" name="tp-upnp" value="en" checked> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-upnp" value="dis"> Disable</label></td></tr>'
    +'</table></div>'
    +'<div style="padding:4px 14px 8px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>App Description</th><th>External Port</th><th>Protocol</th><th>Internal Port</th><th>IP Address</th><th>Status</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No UPnP entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px" onclick="renderTpPage(\'tp_fwd_upnp\')">Refresh</button>'
    +'</div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Security > Basic Security ── */
function tpPageSecBasic(hp) {
  tpHelp(hp,'Basic Security','<p>The <b>SPI Firewall</b> monitors incoming and outgoing connections and blocks potentially dangerous packets. <b>VPN Passthrough</b> allows VPN tunnel traffic through the Router.</p>');
  return tpTitle('Basic Security')
    +'<div class="tp-form"><table>'
    +'<tr><td colspan="2" class="tp-st-sec" style="padding:8px 20px 4px;font-size:13px">Firewall</td></tr>'
    +'<tr><td class="lbl">SPI Firewall:</td><td class="val">'
    +'<label><input type="radio" name="tp-spi" value="en" checked> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-spi" value="dis"> Disable</label></td></tr>'
    +tpSepR()
    +'<tr><td colspan="2" class="tp-st-sec" style="padding:8px 20px 4px;font-size:13px">VPN</td></tr>'
    +'<tr><td class="lbl">PPTP Passthrough:</td><td class="val"><label><input type="checkbox" checked> Enable</label></td></tr>'
    +'<tr><td class="lbl">L2TP Passthrough:</td><td class="val"><label><input type="checkbox" checked> Enable</label></td></tr>'
    +'<tr><td class="lbl">IPSec Passthrough:</td><td class="val"><label><input type="checkbox" checked> Enable</label></td></tr>'
    +tpSepR()
    +'<tr><td colspan="2" class="tp-st-sec" style="padding:8px 20px 4px;font-size:13px">ALG</td></tr>'
    +'<tr><td class="lbl">FTP ALG:</td><td class="val"><label><input type="checkbox" checked> Enable</label></td></tr>'
    +'<tr><td class="lbl">TFTP ALG:</td><td class="val"><label><input type="checkbox" checked> Enable</label></td></tr>'
    +'<tr><td class="lbl">H323 ALG:</td><td class="val"><label><input type="checkbox" checked> Enable</label></td></tr>'
    +'<tr><td class="lbl">RTSP ALG:</td><td class="val"><label><input type="checkbox" checked> Enable</label></td></tr>'
    +'</table></div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Security > Advanced Security ── */
function tpPageSecAdv(hp) {
  tpHelp(hp,'Advanced Security','<p><b>DoS</b> (Denial of Service) protection detects and blocks flood attacks. Set thresholds per second for ICMP, UDP, and TCP SYN packets to prevent your network from being overloaded.</p>');
  return tpTitle('Advanced Security')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">DoS Protection:</td><td class="val">'
    +'<label><input type="radio" name="tp-dos" value="en"> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-dos" value="dis" checked> Disable</label></td></tr>'
    +tpSepR()
    +tpR('ICMP-FLOOD Attack Filtering', '<label><input type="checkbox"> Enable</label>')
    +tpR('ICMP-FLOOD Packets Threshold', tpI('tp-dos-icmp','50','',55)+' <span class="hint">(5~3600 packets/second)</span>')
    +tpR('UDP-FLOOD Attack Filtering', '<label><input type="checkbox"> Enable</label>')
    +tpR('UDP-FLOOD Packets Threshold', tpI('tp-dos-udp','500','',55)+' <span class="hint">(5~3600 packets/second)</span>')
    +tpR('TCP-SYN-FLOOD Attack Filtering', '<label><input type="checkbox"> Enable</label>')
    +tpR('TCP-SYN-FLOOD Packets Threshold', tpI('tp-dos-tcp','50','',55)+' <span class="hint">(5~3600 packets/second)</span>')
    +tpSepR()
    +'<tr><td class="lbl">Ignore Ping from WAN Port:</td><td class="val"><label><input type="checkbox"> Enable</label></td></tr>'
    +'<tr><td class="lbl">Forbid Ping from LAN Port:</td><td class="val"><label><input type="checkbox"> Enable</label></td></tr>'
    +'</table></div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Security > Local Management ── */
function tpPageSecLocal(hp) {
  tpHelp(hp,'Local Management','<p>By default, any PC on the LAN can manage the Router. You can restrict access to specific MAC addresses to prevent unauthorized changes to the Router settings.</p>');
  return tpTitle('Local Management')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">Local Management:</td><td class="val">'
    +'<div><label><input type="radio" name="tp-lm" value="all" checked> All PCs on the LAN can manage the Router</label></div>'
    +'<div style="margin-top:4px"><label><input type="radio" name="tp-lm" value="spec"> Only the PCs listed can browse the built-in web pages to perform Admin Tasks</label></div>'
    +'</td></tr>'
    +tpSepR()
    +tpR('MAC 1', tpI('tp-lm-mac1','','XX-XX-XX-XX-XX-XX',165))
    +tpR('MAC 2', tpI('tp-lm-mac2','','XX-XX-XX-XX-XX-XX',165))
    +tpR('MAC 3', tpI('tp-lm-mac3','','XX-XX-XX-XX-XX-XX',165))
    +tpR('MAC 4', tpI('tp-lm-mac4','','XX-XX-XX-XX-XX-XX',165))
    +'</table></div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Security > Remote Management ── */
function tpPageSecRem(hp) {
  tpHelp(hp,'Remote Management','<p>Remote Management allows access to the Router\'s web management page from the Internet. Enter your public IP address, or use 255.255.255.255 to allow access from any IP. Default is disabled (0.0.0.0).</p>');
  return tpTitle('Remote Management')
    +'<div class="tp-form"><table>'
    +tpR('Web Management Port', tpI('tp-rem-port','80','',55))
    +tpR('Remote Management IP Address', tpI('tp-rem-ip','0.0.0.0','',130)+' <span class="hint">(0.0.0.0 = disabled; 255.255.255.255 = all)</span>')
    +'</table></div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Parental Control ── */
function tpPageParental(hp) {
  tpHelp(hp,'Parental Control','<p><b>Parental Control</b> restricts Internet access for specific devices. The parental PC has unrestricted access. Child PCs can only access allowed websites during scheduled times.</p>');
  return tpTitle('Parental Control')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">Parental Control:</td><td class="val">'
    +'<label><input type="radio" name="tp-pc" value="en"> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-pc" value="dis" checked> Disable</label></td></tr>'
    +tpSepR()
    +tpR("Parental PC's MAC Address", tpI('tp-pc-mac','','XX-XX-XX-XX-XX-XX',165))
    +tpR("Your PC's MAC Address", '<span style="font-family:Arial;font-size:12px;color:#333">A4-2B-8C-12-34-50</span>'
      +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:2px 10px;font-size:11px">Copy To Above</button>')
    +'</table></div>'
    +'<div style="padding:4px 14px 8px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>ID</th><th>MAC Address</th><th>Website Description</th><th>Schedule</th><th>Status</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No parental control entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'</div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Access Control > Rule ── */
function tpPageACRule(hp) {
  tpHelp(hp,'Access Control','<p>Internet Access Control allows you to set up rules to control Internet access for specific hosts. Each rule can apply to a specific host, target, and schedule.</p>');
  return tpTitle('Access Control')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">Internet Access Control:</td><td class="val">'
    +'<label><input type="radio" name="tp-ac" value="en"> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-ac" value="dis" checked> Disable</label></td></tr>'
    +tpR('Default Filtering Rules', tpS('tp-ac-def',[['deny','Deny the packets not specified by any access control policy'],['allow','Allow the packets not specified by any access control policy']],'deny'))
    +'</table></div>'
    +'<div style="padding:4px 14px 8px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>Rule Name</th><th>Host</th><th>Target</th><th>Schedule</th><th>Action</th><th>Status</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No access control rules.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Access Control > Host ── */
function tpPageACHost(hp) {
  tpHelp(hp,'Access Control Host','<p>Create entries in the Host List to identify computers on your network. A host can be identified by its MAC address or an IP address range.</p>');
  return tpTitle('Host')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>ID</th><th>Host Description</th><th>Information</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No host entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>';
}

/* ── Access Control > Target ── */
function tpPageACTarget(hp) {
  tpHelp(hp,'Access Control Target','<p>Create entries in the Target List to specify websites or IP ranges that access control rules apply to. A target can be a domain name or an IP address with port range.</p>');
  return tpTitle('Target')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>ID</th><th>Target Description</th><th>Information</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No target entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>';
}

/* ── Access Control > Schedule ── */
function tpPageACSched(hp) {
  tpHelp(hp,'Access Control Schedule','<p>Create schedule entries to define the time periods when access control rules should take effect. You can specify days of the week and time ranges.</p>');
  return tpTitle('Schedule')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>ID</th><th>Schedule Description</th><th>Day(s)</th><th>Time</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No schedule entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>';
}

/* ── Advanced Routing > Static Routing ── */
function tpPageARStatic(hp) {
  tpHelp(hp,'Static Routing','<p>A static route is a manually configured path that the Router uses to direct traffic to a specific destination network. Use static routes when devices are not accessible through the default gateway.</p>');
  return tpTitle('Static Routing')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>ID</th><th>Destination Network</th><th>Subnet Mask</th><th>Default Gateway</th><th>Status</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No static routing entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>';
}

/* ── Advanced Routing > System Routing Table ── */
function tpPageARSys(hp) {
  tpHelp(hp,'System Routing Table','<p>The System Routing Table displays all routes currently configured on the Router, including default routes and any static routes you have added. This is a read-only view.</p>');
  return tpTitle('System Routing Table')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl">'
    +'<tr><th>ID</th><th>Destination Network</th><th>Subnet Mask</th><th>Gateway</th><th>Interface</th></tr>'
    +'<tr><td>1</td><td>'+(TPRS.lan.ip ? TPRS.lan.ip.replace(/\.\d+$/,'.0') : '192.168.0.0')+'</td><td>'+(TPRS.lan.mask||'255.255.255.0')+'</td><td>0.0.0.0</td><td>LAN &amp; WLAN</td></tr>'
    +'<tr><td>2</td><td>0.0.0.0</td><td>0.0.0.0</td><td>'+(TPRS.wan.gw||'0.0.0.0')+'</td><td>WAN</td></tr>'
    +'</table>'
    +'<button class="tp-save-btn" style="display:inline-block;margin-top:8px;padding:3px 16px" onclick="renderTpPage(\'tp_ar_sys\')">Refresh</button>'
    +'</div>';
}

/* ── Bandwidth Control Settings ── */
function tpPageBWCtrl(hp) {
  tpHelp(hp,'Bandwidth Control','<p><b>Bandwidth Control</b> lets you limit the total bandwidth available to WAN clients and define per-client allocation rules to ensure fair bandwidth usage.</p>');
  return tpTitle('Bandwidth Control')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">Enable Bandwidth Control:</td><td class="val">'
    +'<label><input type="radio" name="tp-bw" value="en"> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-bw" value="dis" checked> Disable</label></td></tr>'
    +tpSepR()
    +tpR('Egress Bandwidth', tpI('tp-bw-eg','','',80)+' <span class="hint">Kbps (total upload bandwidth from your ISP)</span>')
    +tpR('Ingress Bandwidth', tpI('tp-bw-in','','',80)+' <span class="hint">Kbps (total download bandwidth from your ISP)</span>')
    +'</table></div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Bandwidth Rules List ── */
function tpPageBWRules(hp) {
  tpHelp(hp,'Bandwidth Rules','<p>Create bandwidth control rules to allocate bandwidth to specific IP address ranges. Each rule specifies the IP range, port, protocol, and min/max bandwidth limits.</p>');
  return tpTitle('Bandwidth Rules List')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>ID</th><th>Description</th><th>IP Range</th><th>Port Range</th><th>Protocol</th><th>Priority</th><th>Egress (Kbps)</th><th>Ingress (Kbps)</th><th>Status</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No bandwidth rules.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>';
}

/* ── IP & MAC Binding Settings ── */
function tpPageImbSet(hp) {
  tpHelp(hp,'IP & MAC Binding','<p><b>ARP Binding</b> prevents IP address spoofing by linking a specific IP address to a specific MAC address. Only the bound MAC can use the assigned IP.</p>');
  return tpTitle('IP & MAC Binding Settings')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">ARP Binding:</td><td class="val">'
    +'<label><input type="radio" name="tp-arp" value="en"> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-arp" value="dis" checked> Disable</label></td></tr>'
    +'</table></div>'
    +'<div style="padding:4px 14px 8px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>ID</th><th>MAC Address</th><th>IP Address</th><th>Bound</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No ARP binding entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── IP & MAC Binding > ARP List ── */
function tpPageImbARP(hp) {
  tpHelp(hp,'ARP List','<p>The ARP List shows all current ARP (Address Resolution Protocol) entries known to the Router. Click <b>Load</b> to add an entry to the binding table.</p>');
  return tpTitle('ARP List')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl">'
    +'<tr><th>ID</th><th>MAC Address</th><th>IP Address</th><th>Bound</th><th>Load</th></tr>'
    +'<tr><td>1</td><td>A4-2B-8C-12-34-55</td><td>'+(TPRS.lan.ip||'192.168.0.1')+'</td><td>No</td><td><a href="#" style="color:#4CAF50;font-family:Arial;font-size:12px">Load</a></td></tr>'
    +'</table>'
    +'<button class="tp-save-btn" style="display:inline-block;margin-top:8px;padding:3px 16px" onclick="renderTpPage(\'tp_imb_arp\')">Refresh</button>'
    +'</div>';
}

/* ── Dynamic DNS ── */
function tpPageDDNS(hp) {
  tpHelp(hp,'Dynamic DNS','<p><b>Dynamic DNS</b> maps a fixed domain name to your Router\'s dynamic WAN IP address. This lets you access your home network by name even if your IP address changes.</p>');
  return tpTitle('Dynamic DNS')
    +'<div class="tp-form"><table>'
    +tpR('Service Provider', tpS('tp-ddns-prov',[['none','None'],['dyndns','DynDNS'],['noip','No-IP'],['tpddns','TP-LINK DDNS']],'none'))
    +tpSepR()
    +tpR('Username / Email', tpI('tp-ddns-user','','',165))
    +tpR('Password', '<input type="password" id="tp-ddns-pass" style="width:150px;font-family:Arial;font-size:12px;padding:1px 4px;border:1px solid #aaa">')
    +tpR('Domain Name', tpI('tp-ddns-domain','','yourdomain.dyndns.org',165))
    +tpSepR()
    +'<tr><td class="lbl">Connection Status:</td><td class="val" style="color:#888;font-style:italic">Not Connected</td></tr>'
    +'</table></div>'
    +'<button class="tp-save-btn">Login</button>';
}

/* ── Wireless > MAC Filtering ── */
function tpPageWlMac(hp) {
  tpHelp(hp,'Wireless MAC Filtering','<p>Wireless MAC Filtering controls which wireless stations can associate with the Router. Enable it and choose whether to allow or deny the listed MAC addresses.</p>');
  return tpTitle('Wireless MAC Filtering')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">Wireless MAC Filtering:</td><td class="val">'
    +'<label><input type="radio" name="tp-wmf" value="en"> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-wmf" value="dis" checked> Disable</label></td></tr>'
    +tpR('Filtering Rules', tpS('tp-wmf-rule',[['deny','Deny the stations specified by any enabled entries in the list to access'],['allow','Allow the stations specified by any enabled entries in the list to access']],'deny'))
    +'</table></div>'
    +'<div style="padding:4px 14px 8px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>ID</th><th>MAC Address</th><th>Status</th><th>Description</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No MAC filtering entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Wireless > Advanced ── */
function tpPageWlAdv(hp) {
  tpHelp(hp,'Wireless Advanced','<p>Advanced wireless settings control low-level radio behavior. The default values are recommended for most users. Change them only if you have a specific technical reason.</p>');
  return tpTitle('Wireless Advanced')
    +'<div class="tp-form"><table>'
    +tpR('Transmit Power', tpS('tp-wla-pwr',[['high','High'],['mid','Middle'],['low','Low']],'high'))
    +tpR('Beacon Interval', tpI('tp-wla-bcn','100','',55)+' <span class="hint">(40~1000 ms, the default is 100)</span>')
    +tpR('RTS Threshold', tpI('tp-wla-rts','2346','',55)+' <span class="hint">(256~2346, the default is 2346)</span>')
    +tpR('Fragmentation Threshold', tpI('tp-wla-frag','2346','',55)+' <span class="hint">(256~2346, the default is 2346)</span>')
    +tpR('DTIM Interval', tpI('tp-wla-dtim','1','',55)+' <span class="hint">(1~255, the default is 1)</span>')
    +tpSepR()
    +'<tr><td class="lbl">Enable WMM:</td><td class="val"><label><input type="checkbox" checked> Enable</label></td></tr>'
    +'<tr><td class="lbl">Enable Short GI:</td><td class="val"><label><input type="checkbox" checked> Enable</label></td></tr>'
    +'<tr><td class="lbl">Enable AP Isolation:</td><td class="val"><label><input type="checkbox"> Enable</label></td></tr>'
    +'</table></div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Wireless > Statistics ── */
function tpPageWlStat(hp) {
  tpHelp(hp,'Wireless Statistics','<p>This page shows the MAC address, connection status, and packet statistics for all wireless clients currently associated with the Router.</p>');
  return tpTitle('Wireless Statistics')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>MAC Address</th><th>Current Status</th><th>Received Packets</th><th>Sent Packets</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No wireless clients connected.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px" onclick="renderTpPage(\'tp_wl_stat\')">Refresh</button>'
    +'</div>';
}

/* ── DHCP > Address Reservation ── */
function tpPageDhcpRes(hp) {
  tpHelp(hp,'Address Reservation','<p><b>Address Reservation</b> ensures that a specific device always receives the same IP address from DHCP. The Router assigns the reserved IP to the device based on its MAC address.</p>');
  return tpTitle('Address Reservation')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>ID</th><th>MAC Address</th><th>Reserved IP Address</th><th>Status</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No address reservation entries.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Add New...</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 16px">Delete All</button>'
    +'</div>';
}

/* ── System Tools > Time Settings ── */
function tpPageSysTime(hp) {
  tpHelp(hp,'Time Settings','<p>Set the correct time zone and NTP servers for accurate timestamps in system logs and for time-based features such as Access Control Schedules.</p>');
  var now = new Date();
  var ts = now.toLocaleTimeString('pl-PL')+' '+now.toLocaleDateString('pl-PL');
  return tpTitle('Time Settings')
    +'<div class="tp-form"><table>'
    +tpR('Current Time','<span style="color:#333;font-family:monospace">'+ts+'</span>')
    +tpSepR()
    +tpR('Time Zone', tpS('tp-tz',[
      ['UTC+1','(UTC+01:00) Warsaw, Prague, Budapest'],
      ['UTC','(UTC+00:00) Greenwich Mean Time, Dublin, London'],
      ['UTC+2','(UTC+02:00) Athens, Bucharest, Helsinki'],
      ['UTC+3','(UTC+03:00) Moscow, St. Petersburg, Volgograd'],
      ['UTC-5','(UTC-05:00) Eastern Time (US & Canada)'],
      ['UTC-6','(UTC-06:00) Central Time (US & Canada)'],
      ['UTC-7','(UTC-07:00) Mountain Time (US & Canada)'],
      ['UTC-8','(UTC-08:00) Pacific Time (US & Canada)']
    ],'UTC+1'))
    +tpSepR()
    +'<tr><td class="lbl">Get time from NTP Server automatically:</td><td class="val"><label><input type="checkbox" checked> Enable</label></td></tr>'
    +tpR('NTP Server I', tpI('tp-ntp1','ntp1.tp-link.com','',200))
    +tpR('NTP Server II', tpI('tp-ntp2','ntp2.tp-link.com','',200)+' <span class="hint">(Optional)</span>')
    +'</table></div>'
    +'<button class="tp-save-btn">Get GMT &nbsp;/&nbsp; Save</button>';
}

/* ── System Tools > Diagnostic ── */
function tpPageSysDiag(hp) {
  tpHelp(hp,'Diagnostic','<p>Use <b>Ping</b> to test connectivity to a remote host, or <b>Traceroute</b> to identify the path and any delays along the route to the destination.</p>');
  return tpTitle('Diagnostic')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">Diagnostic Tool:</td><td class="val">'
    +'<label><input type="radio" name="tp-diag-tool" value="ping" id="tp-diag-ping" checked> Ping</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-diag-tool" value="trace" id="tp-diag-trace"> Traceroute</label></td></tr>'
    +tpR('IP Address / Domain Name', tpI('tp-diag-host','','e.g. www.tp-link.com',200))
    +tpR('Ping Count', tpI('tp-diag-cnt','4','',40)+' <span class="hint">(1~50)</span>')
    +tpR('Ping Packet Size', tpI('tp-diag-size','64','',40)+' <span class="hint">(4~1472 bytes)</span>')
    +'</table></div>'
    +'<button class="tp-save-btn" id="tp-diag-start">Start</button>'
    +'<div style="padding:0 14px 14px">'
    +'<textarea id="tp-diag-out" readonly style="width:100%;height:160px;font-family:monospace;font-size:11px;border:1px solid #aaa;padding:4px;box-sizing:border-box;background:#f8f8f8;color:#333;resize:vertical"></textarea>'
    +'</div>';
}
function tpAttachDiag() {
  document.getElementById('tp-diag-start').addEventListener('click', function() {
    var host = document.getElementById('tp-diag-host').value.trim();
    var out  = document.getElementById('tp-diag-out');
    if (!host) { out.value = 'Error: Please enter a valid IP address or domain name.'; return; }
    var isPing = document.getElementById('tp-diag-ping').checked;
    if (isPing) {
      out.value = 'Pinging '+host+' with 32 bytes of data:\n\nRequest timed out.\nRequest timed out.\nRequest timed out.\nRequest timed out.\n\nPing statistics for '+host+':\n    Packets: Sent = 4, Received = 0, Lost = 4 (100% loss)';
    } else {
      out.value = 'Traceroute to '+host+', 30 hops max, 60 byte packets:\n\n  1  *  *  *  Request timed out.\n  2  *  *  *  Request timed out.\n  3  *  *  *  Request timed out.\n\nTrace complete.';
    }
  });
}

/* ── System Tools > Firmware Upgrade ── */
function tpPageSysFW(hp) {
  tpHelp(hp,'Firmware Upgrade','<p>Upgrading the firmware may fix bugs and provide new features. Download the correct firmware file for your hardware version from TP-LINK\'s website before upgrading.</p>');
  return tpTitle('Firmware Upgrade')
    +'<div class="tp-form"><table>'
    +tpR('Firmware Version','3.13.18 Build 120522 Rel.31564n')
    +tpR('Hardware Version','WR841N v8 00000000')
    +tpSepR()
    +'<tr><td class="lbl">New Firmware File:</td><td class="val">'
    +'<input type="file" id="tp-fw-file" accept=".bin" style="font-family:Arial;font-size:12px"></td></tr>'
    +'</table></div>'
    +'<div style="padding:0 14px 10px;font-family:Arial;font-size:11px;color:#c00">'
    +'<b>Note:</b> Do not close the browser or turn off the router during the upgrade. The Router will reboot automatically after the upgrade is complete.'
    +'</div>'
    +'<button class="tp-save-btn">Upgrade</button>';
}

/* ── System Tools > Factory Defaults ── */
function tpPageSysFactory(hp) {
  tpHelp(hp,'Factory Defaults','<p>Restoring factory defaults will erase all your configuration settings and revert the Router to its original state. The Router will reboot automatically after restoring.</p>');
  return tpTitle('Factory Defaults')
    +'<div style="padding:14px;font-family:Arial;font-size:12px;color:#333">'
    +'<p>Click the following button to reset all configuration settings to their default values.</p>'
    +'<p style="color:#c00"><b>Note:</b> All current settings will be lost and the Router will reboot automatically. The default management address is 192.168.0.1.</p>'
    +'<div style="text-align:center;margin-top:20px">'
    +'<button class="tp-save-btn" style="padding:5px 30px" id="tp-factory-btn">Restore</button>'
    +'</div></div>';
}

/* ── System Tools > Backup & Restore ── */
function tpPageSysBackup(hp) {
  tpHelp(hp,'Backup & Restore','<p>Save your current Router configuration to a file for backup. You can restore the configuration later using the Restore function. It is recommended to backup before making major changes.</p>');
  return tpTitle('Backup & Restore')
    +'<div style="padding:14px;font-family:Arial;font-size:12px;color:#333">'
    +'<p><b>Backup</b></p>'
    +'<p>Click the button below to save the current configuration to a file.</p>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:6px 0 18px">Backup</button>'
    +'<div style="height:1px;background:#e0e0e0;margin:4px 0 12px"></div>'
    +'<p><b>Restore</b></p>'
    +'<p>To restore configuration settings, select the backup file below and click Restore.</p>'
    +'<div style="margin:6px 0 8px"><input type="file" accept=".bin" style="font-family:Arial;font-size:12px"></div>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0">Restore</button>'
    +'</div>';
}

/* ── System Tools > Reboot ── */
function tpPageSysReboot(hp) {
  tpHelp(hp,'Reboot','<p>Click <b>Reboot</b> to restart the Router. The Router will temporarily lose its Internet connection during the reboot process, which takes about 20 seconds.</p>');
  return tpTitle('Reboot')
    +'<div style="padding:14px;font-family:Arial;font-size:12px;color:#333">'
    +'<p>Click the following button to reboot the Router.</p>'
    +'<div style="text-align:center;margin-top:20px">'
    +'<button class="tp-save-btn" style="padding:5px 30px" id="tp-reboot-btn">Reboot</button>'
    +'</div></div>';
}
function tpAttachReboot() {
  document.getElementById('tp-reboot-btn').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true; btn.textContent = 'Rebooting...';
    setTimeout(function() { btn.disabled = false; btn.textContent = 'Reboot'; toast('Router rebooted (simulator)'); }, 2000);
  });
}

/* ── System Tools > Password ── */
function tpPageSysPwd(hp) {
  tpHelp(hp,'Password','<p>Change the Router\'s login password here. Use a strong password between 1–14 characters. The default username is <b>admin</b> and the default password is <b>admin</b>.</p>');
  return tpTitle('Password')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">Old Password:</td><td class="val">'
    +'<input type="password" id="tp-pwd-old" style="width:150px;font-family:Arial;font-size:12px;padding:1px 4px;border:1px solid #aaa"></td></tr>'
    +'<tr><td class="lbl">New Password:</td><td class="val">'
    +'<input type="password" id="tp-pwd-new" style="width:150px;font-family:Arial;font-size:12px;padding:1px 4px;border:1px solid #aaa"></td></tr>'
    +'<tr><td class="lbl">Confirm New Password:</td><td class="val">'
    +'<input type="password" id="tp-pwd-cf" style="width:150px;font-family:Arial;font-size:12px;padding:1px 4px;border:1px solid #aaa"></td></tr>'
    +'</table></div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── System Tools > System Log ── */
function tpPageSysLog(hp) {
  tpHelp(hp,'System Log','<p>The <b>System Log</b> records Router activity such as connections, DHCP events, and errors. Use the log to monitor activity and diagnose network problems.</p>');
  return tpTitle('System Log')
    +'<div style="padding:8px 14px 4px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>Time</th><th>Type</th><th>Level</th><th>Log Content</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">No log entries.</p>'
    +'<div style="margin-top:4px">'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 14px" onclick="renderTpPage(\'tp_sys_log\')">Refresh</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 14px">Clear Log</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 14px">Save Log</button>'
    +'</div></div>';
}

/* ── System Tools > Statistics ── */
function tpPageSysStats(hp) {
  tpHelp(hp,'Statistics','<p>The <b>Statistics</b> page displays traffic statistics per IP address. Enable statistics to monitor bandwidth usage of each client on your network.</p>');
  return tpTitle('Statistics')
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">Statistics:</td><td class="val">'
    +'<label><input type="radio" name="tp-stats" value="en"> Enable</label>'
    +'&nbsp;&nbsp;<label><input type="radio" name="tp-stats" value="dis" checked> Disable</label></td></tr>'
    +'</table></div>'
    +'<div style="padding:4px 14px 8px;font-family:Arial;font-size:12px">'
    +'<table class="tp-tbl"><tr><th>IP Address</th><th>Pkts Sent</th><th>Bytes Sent</th><th>Pkts Rcvd</th><th>Bytes Rcvd</th><th>ICMP Tx</th><th>UDP Tx</th><th>SYN Tx</th><th>Modify</th></tr></table>'
    +'<p style="color:#888;text-align:center;padding:8px 0">Statistics disabled or no data available.</p>'
    +'<div>'
    +'<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 14px" onclick="renderTpPage(\'tp_sys_stats\')">Refresh</button>'
    +'&nbsp;<button class="tp-save-btn" style="display:inline-block;margin:0;padding:3px 14px">Reset Statistics</button>'
    +'</div></div>'
    +'<button class="tp-save-btn">Save</button>';
}

/* ── Generic fallback ── */
function tpPageGeneric(page, hp) {
  var title = String(page || 'Page').replace(/^tp_/, '').replace(/_/g, ' ');
  var safeId = String(page || 'page').replace(/[^a-z0-9_-]/gi, '_');
  tpHelp(hp, labEsc(title), '<p>This simulated TP-Link page accepts local configuration changes and stores them in this lab session.</p>');
  return tpTitle(labEsc(title))
    +'<div class="tp-form"><table>'
    +'<tr><td class="lbl">Status:</td><td><select id="tp-generic-status-'+safeId+'"><option>Enabled</option><option>Disabled</option></select></td></tr>'
    +'<tr><td class="lbl">Name:</td><td><input id="tp-generic-name-'+safeId+'" type="text" value="'+labEsc(title)+'"></td></tr>'
    +'<tr><td class="lbl">Value:</td><td><input id="tp-generic-value-'+safeId+'" type="text" value=""></td></tr>'
    +'</table><button class="tp-save-btn">Save</button></div>';
}

/* ── Update resetAll to also reset TPRS ── */
var _origResetAll = resetAll;
resetAll = function() {
  if (!window.__networkLabResetConfirmed) { requestResetAll(); return; }
  RS.wan = {type:'static',ip:'',mask:'',gw:'',dns1:'',dns2:''};
  RS.lan = {ip:'',mask:''};
  RS.dhcp = {on:true,start:'',end:'',gw:'',dns:'',lease:''};
  RS.vlans = {1:'Default'};
  SS.mgmt = {ip:'',mask:'',gw:''};
  SS.vlans = {1:'Default'};
  for (var rp = 1; rp <= 8; rp++) SS.ports[rp] = {mode:'access', vlan:1};
  TPRS.wan = {type:'static',ip:'',mask:'',gw:'',dns1:'',dns2:''};
  TPRS.lan = {ip:'',mask:''};
  TPRS.dhcp = {on:true,start:'',end:'',lease:'120'};
  TPRS.wireless = {ssid:'',channel:'6',mode:'bgn',password:''};
  try { localStorage.removeItem('inf02_'+currentSessionKey); } catch(e) {}
  try { localStorage.removeItem('tp_'+currentSessionKey); } catch(e) {}
  toast('↺ Urządzenia zresetowane do stanu fabrycznego');
};

/* ── Update loadPDF to also load TP-Link state ── */
var _origLoadPDF = loadPDF;
loadPDF = function(key) {
  currentSessionKey = key;
  var url = resolveLabAssetUrl(PDF_URLS[key] || PDF_URLS['2025-cze']);
  document.getElementById('pdf-loading').classList.remove('hidden');
  var frame = document.getElementById('pdf-frame');
  if (frame) {
    frame.onload = hidePdfLoading;
    frame.onerror = showPdfFallback;
    frame.src = url;
  }
  document.getElementById('pdf-ext').href = url;
  document.getElementById('pdf-fallback-btn').href = url;
  loadState(key);
  loadTpState(key);
};

/* ══════════════════════════════════════════
   INIT
══════════════════════════════════════════ */
bindDynamicFieldMemory();
buildNav(RMENU, document.getElementById('cnav-router'), renderRouterPage, 'getstart');
buildNav(SMENU, document.getElementById('cnav-switch'), renderSwitchPage, 'sw_sysinfo');
buildTpNav(TPMENU, document.getElementById('tpnav'), renderTpPage, 'tp_status');
bindNetworkLabFallbackActions();

/* ── DRAG-TO-RESIZE SPLITTER ── */
(function(){
  var sp = document.getElementById('splitter');
  var tp = document.querySelector('.task-panel');
  var layout = document.querySelector('.layout');
  var dragging = false, startX = 0, startW = 0;

  sp.addEventListener('mousedown', function(e) {
    dragging = true;
    startX = e.clientX;
    startW = tp.offsetWidth;
    sp.classList.add('dragging');
    document.body.style.cursor = 'col-resize';
    document.body.style.userSelect = 'none';
    e.preventDefault();
  });

  document.addEventListener('mousemove', function(e) {
    if (!dragging) return;
    var dx = e.clientX - startX;
    var total = layout.offsetWidth;
    var nw = Math.max(260, Math.min(total - 360, startW + dx));
    tp.style.flex = 'none';
    tp.style.width = nw + 'px';
  });

  document.addEventListener('mouseup', function() {
    if (!dragging) return;
    dragging = false;
    sp.classList.remove('dragging');
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
  });
})();

/* ══════════════════════════════════════════
   MIKROTIK EMULATOR
══════════════════════════════════════════ */

var MTRS = {
  identity: 'MikroTik',
  addresses: [
    {addr:'192.168.88.1/24', net:'192.168.88.0', iface:'bridge'},
    {addr:'0.0.0.0/0', net:'0.0.0.0', iface:'ether1'}
  ],
  dns: { s1:'8.8.8.8', s2:'8.8.4.4', remote:false },
  dhcpServers: [
    {name:'defconf', iface:'bridge', pool:'default-dhcp', lease:'10m', auth:'yes'}
  ],
  ipPool: [
    {name:'default-dhcp', range:'192.168.88.10-192.168.88.254'}
  ],
  routes: [],
  quickSet: {
    mode:'Router', acq:'Automatic (DHCP)', ip:'', mask:'/24 (255.255.255.0)', gw:'', dns:'8.8.8.8',
    lanip:'192.168.88.1', lanmask:'/24 (255.255.255.0)',
    ssid:'MikroTik', wpass:'', band:'2GHz-B/G/N', freq:'auto'
  },
  switchPorts: [
    {name:'ether1',sw:'switch1',vlan:'1',mode:'fallback',flood:'yes',txRate:'unlimited',rxRate:'unlimited'},
    {name:'ether2',sw:'switch1',vlan:'1',mode:'fallback',flood:'yes',txRate:'unlimited',rxRate:'unlimited'},
    {name:'ether3',sw:'switch1',vlan:'1',mode:'fallback',flood:'yes',txRate:'unlimited',rxRate:'unlimited'},
    {name:'ether4',sw:'switch1',vlan:'1',mode:'fallback',flood:'yes',txRate:'unlimited',rxRate:'unlimited'},
    {name:'ether5',sw:'switch1',vlan:'1',mode:'fallback',flood:'yes',txRate:'unlimited',rxRate:'unlimited'}
  ]
};

function mtSave() {
  try { localStorage.setItem('mtrs_state', JSON.stringify(MTRS)); } catch (e) {}
}
function mtLoad() {
  try {
    var raw = localStorage.getItem('mtrs_state');
    if (!raw) return;
    var saved = JSON.parse(raw);
    // płytkie scalenie — zachowaj domyślne klucze, nadpisz zapisanymi
    for (var k in saved) { if (saved.hasOwnProperty(k)) MTRS[k] = saved[k]; }
  } catch (e) {}
}

// Zwraca aktywny widok ('wb' lub 'wf') na podstawie tego, który wrapper jest widoczny
function mtActiveView() {
  var wb = document.getElementById('wrap-mikrotik-wb');
  return (wb && wb.style.display !== 'none') ? 'wb' : 'wf';
}
// Pobiera pole z aktualnie widocznego widoku (WinBox lub WebFig)
function mtGet(wbId, wfId) {
  return document.getElementById(mtActiveView() === 'wb' ? wbId : wfId);
}

function mtApplyIdentity() {
  var n = mtGet('mt-wb-identity', 'mt-wf-identity-inp');
  if (n && n.value.trim()) MTRS.identity = n.value.trim();
  var el = document.getElementById('mt-wf-identity');
  if (el) el.textContent = MTRS.identity;
  mtSave();
  renderMtPage('sys_identity');
}

function mtApplyIPAddr() {
  var a = mtGet('mt-wb-addr-addr', 'mt-wf-addr');
  var net = mtGet('mt-wb-addr-net', 'mt-wf-addr-net');
  var iface = mtGet('mt-wb-addr-iface', 'mt-wf-addr-iface');
  if (!a || !a.value.trim()) return;
  MTRS.addresses.push({addr:a.value.trim(), net:(net?net.value.trim():''), iface:(iface?iface.value:'ether1')});
  mtSave();
  renderMtPage('ip_addresses');
}

function mtRemoveIPAddr() {
  var checks = document.querySelectorAll('#mt-wb-content input[type=checkbox]:checked, #mt-wf-content input[type=checkbox]:checked');
  var idxs = [];
  checks.forEach(function(c){ var i=parseInt(c.dataset.idx); if(!isNaN(i)) idxs.push(i); });
  MTRS.addresses = MTRS.addresses.filter(function(_,i){ return idxs.indexOf(i)===-1; });
  mtSave();
  renderMtPage('ip_addresses');
}

function mtApplyDNS() {
  var s1 = mtGet('mt-wb-dns1', 'mt-wf-dns1');
  var s2 = mtGet('mt-wb-dns2', 'mt-wf-dns2');
  var rem = mtGet('mt-wb-dns-remote', 'mt-wf-dns-remote');
  if (s1) MTRS.dns.s1 = s1.value.trim();
  if (s2) MTRS.dns.s2 = s2.value.trim();
  if (rem) MTRS.dns.remote = rem.checked;
  mtSave();
  renderMtPage('ip_dns');
  toast('✔ DNS zastosowany');
}

function mtApplyDHCP() {
  var name = mtGet('mt-wb-dhcps-name', 'mt-wf-dhcps-name');
  var iface = mtGet('mt-wb-dhcps-iface', 'mt-wf-dhcps-iface');
  var pool = mtGet('mt-wb-dhcps-pool', 'mt-wf-dhcps-pool');
  var lease = mtGet('mt-wb-dhcps-lease', 'mt-wf-dhcps-lease');
  var auth = mtGet('mt-wb-dhcps-auth', 'mt-wf-dhcps-auth');
  if (!name || !name.value.trim()) return;
  MTRS.dhcpServers.push({
    name:name.value.trim(),
    iface:iface?iface.value:'bridge',
    pool:pool?pool.value:'default-dhcp',
    lease:lease?lease.value:'10m',
    auth:auth?auth.value:'yes'
  });
  mtSave();
  renderMtPage('ip_dhcp_server');
}

function mtRemoveDHCP() {
  var checks = document.querySelectorAll('#mt-wb-content input[type=checkbox]:checked, #mt-wf-content input[type=checkbox]:checked');
  var idxs = [];
  checks.forEach(function(c){ var i=parseInt(c.dataset.idx); if(!isNaN(i)) idxs.push(i); });
  MTRS.dhcpServers = MTRS.dhcpServers.filter(function(_,i){ return idxs.indexOf(i)===-1; });
  mtSave();
  renderMtPage('ip_dhcp_server');
}

function mtApplyIPPool() {
  var name = mtGet('mt-wb-pool-name', 'mt-wf-pool-name');
  var range = mtGet('mt-wb-pool-range', 'mt-wf-pool-range');
  if (!name || !name.value.trim()) return;
  MTRS.ipPool.push({name:name.value.trim(), range:range?range.value.trim():''});
  mtSave();
  renderMtPage('ip_pool');
}

function mtRemoveIPPool() {
  var checks = document.querySelectorAll('#mt-wb-content input[type=checkbox]:checked, #mt-wf-content input[type=checkbox]:checked');
  var idxs = [];
  checks.forEach(function(c){ var i=parseInt(c.dataset.idx); if(!isNaN(i)) idxs.push(i); });
  MTRS.ipPool = MTRS.ipPool.filter(function(_,i){ return idxs.indexOf(i)===-1; });
  mtSave();
  renderMtPage('ip_pool');
}

function mtApplyRoute() {
  var dst = mtGet('mt-wb-route-dst', 'mt-wf-route-dst');
  var gw = mtGet('mt-wb-route-gw', 'mt-wf-route-gw');
  if (!gw || !gw.value.trim()) return;
  MTRS.routes.push({dst:(dst&&dst.value.trim())?dst.value.trim():'0.0.0.0/0', gw:gw.value.trim(), dist:'1'});
  mtSave();
  renderMtPage('ip_routes');
}

function mtRemoveRoute() {
  var checks = document.querySelectorAll('#mt-wb-content input[type=checkbox]:checked, #mt-wf-content input[type=checkbox]:checked');
  var idxs = [];
  checks.forEach(function(c){ var i=parseInt(c.dataset.idx); if(!isNaN(i)) idxs.push(i); });
  MTRS.routes = MTRS.routes.filter(function(_,i){ return idxs.indexOf(i)===-1; });
  mtSave();
  renderMtPage('ip_routes');
}

function mtApplySwitchVlan() {
  var sw=mtGet('mt-wb-swvl-sw','mt-wf-swvl-sw');
  var id=mtGet('mt-wb-swvl-id','mt-wf-swvl-id');
  if (!id||!id.value.trim()) return;
  if (!MTRS.switchVlans) MTRS.switchVlans=[];
  MTRS.switchVlans.push({sw:(sw?sw.value:'switch1'),id:id.value.trim(),ports:''});
  mtSave(); renderMtPage('switch_vlans');
}
function mtRemoveSwitchVlan() {
  var chks=document.querySelectorAll('[data-swvl]:checked');
  var idxs=Array.from(chks).map(function(c){return parseInt(c.dataset.swvl);});
  MTRS.switchVlans=(MTRS.switchVlans||[]).filter(function(_,i){return idxs.indexOf(i)===-1;});
  mtSave(); renderMtPage('switch_vlans');
}
function mtApplyBridgeVlan() {
  var vid=mtGet('mt-wb-brvl-vid','mt-wf-brvl-vid');
  var tag=mtGet('mt-wb-brvl-tag','mt-wf-brvl-tag');
  var untag=mtGet('mt-wb-brvl-untag','mt-wf-brvl-untag');
  if (!vid||!vid.value.trim()) return;
  if (!MTRS.bridgeVlans) MTRS.bridgeVlans=[];
  MTRS.bridgeVlans.push({bridge:'bridge',vids:vid.value.trim(),tagged:(tag?tag.value:''),untagged:(untag?untag.value:'bridge')});
  mtSave(); renderMtPage('bridge_vlans');
}
function mtRemoveBridgeVlan() {
  var chks=document.querySelectorAll('[data-brvl]:checked');
  var idxs=Array.from(chks).map(function(c){return parseInt(c.dataset.brvl);});
  MTRS.bridgeVlans=(MTRS.bridgeVlans||[]).filter(function(_,i){return idxs.indexOf(i)===-1;});
  mtSave(); renderMtPage('bridge_vlans');
}
function mtApplyWifi() {
  var ssid=mtGet('mt-wb-wif-ssid','mt-wf-wif-ssid');
  var band=mtGet('mt-wb-wif-band','mt-wf-wif-band');
  var freq=mtGet('mt-wb-wif-freq','mt-wf-wif-freq');
  if (ssid&&ssid.value.trim()) MTRS.quickSet.ssid=ssid.value.trim();
  if (band&&band.value) MTRS.quickSet.band=band.value;
  if (freq&&freq.value) MTRS.quickSet.freq=freq.value;
  mtSave(); renderMtPage('wifi_ifaces'); toast('WiFi zapisano');
}
function mtApplyWifiSec() {
  var pass=mtGet('mt-wb-wsec-wpa2','mt-wf-wsec-wpa2');
  if (pass&&pass.value) MTRS.quickSet.wpass=pass.value;
  mtSave(); renderMtPage('wifi_sec'); toast('Profil bezpieczeństwa zapisano');
}
function mtApplySwitchPort() {
  var name = document.getElementById('mt-wb-swp-name');
  var vlan = document.getElementById('mt-wb-swp-vlan');
  var mode = document.getElementById('mt-wb-swp-mode');
  if (!name || !name.value.trim()) return;
  var existing = MTRS.switchPorts.findIndex(function(p){ return p.name === name.value.trim(); });
  var entry = {name:name.value.trim(), sw:'switch1', vlan:vlan?vlan.value:'1', mode:mode?mode.value:'fallback', flood:'yes', txRate:'unlimited', rxRate:'unlimited'};
  if (existing >= 0) MTRS.switchPorts[existing] = entry;
  else MTRS.switchPorts.push(entry);
  mtSave();
  renderMtPage('switch_ports');
}

function mtRemoveSwitchPort() {
  var checks = document.querySelectorAll('#mt-wb-content input[type=checkbox]:checked, #mt-wf-content input[type=checkbox]:checked');
  var idxs = [];
  checks.forEach(function(c){ var i=parseInt(c.dataset.idx); if(!isNaN(i)) idxs.push(i); });
  MTRS.switchPorts = MTRS.switchPorts.filter(function(_,i){ return idxs.indexOf(i)===-1; });
  mtSave();
  renderMtPage('switch_ports');
}

function mtApplyQuickSet() {
  // czytaj z aktualnie widocznego widoku (WinBox lub WebFig); pozwól też wyczyścić pole
  function val(wbId, wfId){ var el = mtGet(wbId, wfId); return el ? el.value : ''; }
  var qs = MTRS.quickSet;
  qs.mode    = val('mt-qs-mode','mt-wf-qs-mode');
  qs.acq     = val('mt-qs-acq','mt-wf-qs-acq');
  qs.ip      = val('mt-qs-ip','mt-wf-qs-ip').trim();
  qs.mask    = val('mt-qs-mask','mt-wf-qs-mask');
  qs.gw      = val('mt-qs-gw','mt-wf-qs-gw').trim();
  qs.dns     = val('mt-qs-dns','mt-wf-qs-dns').trim();
  qs.lanip   = val('mt-qs-lanip','mt-wf-qs-lanip').trim();
  qs.lanmask = val('mt-qs-lanmask','mt-wf-qs-lanmask');
  qs.ssid    = val('mt-qs-ssid','mt-wf-qs-ssid');
  qs.wpass   = val('mt-qs-wpass','mt-wf-qs-wpass');
  qs.band    = val('mt-qs-band','mt-wf-qs-band');
  qs.freq    = val('mt-qs-freq','mt-wf-qs-freq');
  mtSave();
  renderMtPage('quickset');
  toast('✔ Quick Set zastosowany');
}

var MT_MENU = [
  {id:'quickset', label:'Quick Set', icon:'⚡'},
  {id:'wifi', label:'WiFi', icon:'📶', sub:[
    {id:'wifi_ifaces', label:'WiFi Interfaces'},
    {id:'wifi_sec', label:'Security Profiles'},
    {id:'wifi_access', label:'Access List'}
  ]},
  {id:'interfaces', label:'Interfaces', icon:'🔌'},
  {id:'wireguard', label:'WireGuard', icon:'🛡'},
  {id:'bridge', label:'Bridge', icon:'🌉', sub:[
    {id:'bridge_list', label:'Bridge'},
    {id:'bridge_ports', label:'Ports'},
    {id:'bridge_vlans', label:'VLANs'}
  ]},
  {id:'ppp', label:'PPP', icon:'📞', sub:[
    {id:'ppp_ifaces', label:'Interfaces'},
    {id:'ppp_profiles', label:'Profiles'},
    {id:'ppp_secrets', label:'Secrets'}
  ]},
  {id:'switch', label:'Switch', icon:'🔀', sub:[
    {id:'switch_main', label:'Switch'},
    {id:'switch_ports', label:'Ports'},
    {id:'switch_vlans', label:'VLANs'}
  ]},
  {id:'mesh', label:'Mesh', icon:'🕸'},
  {id:'ip', label:'IP', icon:'🌐', sub:[
    {id:'ip_addresses', label:'Addresses'},
    {id:'ip_routes', label:'Routes'},
    {id:'ip_arp', label:'ARP'},
    {id:'ip_dns', label:'DNS'},
    {id:'ip_dhcp_server', label:'DHCP Server'},
    {id:'ip_dhcp_client', label:'DHCP Client'},
    {id:'ip_firewall', label:'Firewall', sub:[
      {id:'ip_fw_nat', label:'NAT'},
      {id:'ip_fw_filter', label:'Filter Rules'},
      {id:'ip_fw_mangle', label:'Mangle'}
    ]},
    {id:'ip_pool', label:'Pool'},
    {id:'ip_settings', label:'Settings'}
  ]},
  {id:'ipv6', label:'IPv6', icon:'🌍', sub:[
    {id:'ipv6_addresses', label:'Addresses'},
    {id:'ipv6_routes', label:'Routes'},
    {id:'ipv6_firewall', label:'Firewall'}
  ]},
  {id:'routing', label:'Routing', icon:'🗺', sub:[
    {id:'routing_ospf', label:'OSPF'},
    {id:'routing_rip', label:'RIP'},
    {id:'routing_bgp', label:'BGP'},
    {id:'routing_filters', label:'Filters'},
    {id:'routing_rules', label:'Rules'},
    {id:'routing_tables', label:'Tables'}
  ]},
  {id:'system', label:'System', icon:'⚙️', sub:[
    {id:'sys_identity', label:'Identity'},
    {id:'sys_clock', label:'Clock'},
    {id:'sys_ntp', label:'NTP Client'},
    {id:'sys_password', label:'Password'},
    {id:'sys_users', label:'Users'},
    {id:'sys_resources', label:'Resources'},
    {id:'sys_routerboard', label:'RouterBoard'},
    {id:'sys_packages', label:'Packages'},
    {id:'sys_health', label:'Health'}
  ]},
  {id:'queues', label:'Queues', icon:'📊'},
  {id:'files', label:'Files', icon:'📁'},
  {id:'log', label:'Log', icon:'📋'},
  {id:'tools', label:'Tools', icon:'🔧', sub:[
    {id:'tools_ping', label:'Ping'},
    {id:'tools_trace', label:'Traceroute'},
    {id:'tools_btest', label:'Bandwidth Test'},
    {id:'tools_torch', label:'Torch'},
    {id:'tools_fetch', label:'Fetch'}
  ]}
];

// ── WinBox nav builder ─────────────────────
function buildMtWbNav(items, parentEl) {
  items.forEach(function(item) {
    var el = document.createElement('div');
    el.className = 'mt-wb-ni' + (item.id === _mtCurrentPage ? ' active' : '');
    el.innerHTML = '<span class="mt-wb-ni-icon">' + (item.icon||'') + '</span>'
      + '<span class="mt-wb-ni-text">' + item.label + '</span>'
      + (item.sub ? '<span class="mt-wb-ni-arr">▶</span>' : '');
    el.dataset.id = item.id;
    if (item.sub) {
      var sub = document.createElement('div');
      sub.className = 'mt-wb-sub';
      sub.id = 'mt-wb-sub-' + item.id;
      buildMtWbNav(item.sub, sub);
      el.addEventListener('click', function(e) {
        e.stopPropagation();
        var isOpen = sub.classList.contains('open');
        sub.classList.toggle('open', !isOpen);
        el.querySelector('.mt-wb-ni-arr').textContent = isOpen ? '▶' : '▼';
      });
      parentEl.appendChild(el);
      parentEl.appendChild(sub);
    } else {
      el.addEventListener('click', function() {
        renderMtPage(item.id);
      });
      parentEl.appendChild(el);
    }
  });
}

// ── WebFig nav builder ─────────────────────
function buildMtWfNav(items, parentEl) {
  items.forEach(function(item) {
    var el = document.createElement('div');
    el.className = 'mt-wf-ni' + (item.id === _mtCurrentPage ? ' active' : '');
    el.innerHTML = '<span class="mt-wf-ni-icon">' + (item.icon||'') + '</span>'
      + '<span class="mt-wf-ni-text">' + item.label + '</span>'
      + (item.sub ? '<span class="mt-wf-ni-arr">▶</span>' : '');
    el.dataset.id = item.id;
    if (item.sub) {
      var sub = document.createElement('div');
      sub.className = 'mt-wf-sub';
      sub.id = 'mt-wf-sub-' + item.id;
      buildMtWfNav(item.sub, sub);
      el.addEventListener('click', function(e) {
        e.stopPropagation();
        var isOpen = sub.classList.contains('open');
        sub.classList.toggle('open', !isOpen);
        el.querySelector('.mt-wf-ni-arr').textContent = isOpen ? '▼' : '▶';
      });
      parentEl.appendChild(el);
      parentEl.appendChild(sub);
    } else {
      el.addEventListener('click', function() {
        renderMtPage(item.id);
      });
      parentEl.appendChild(el);
    }
  });
}

var _mtCurrentPage = 'quickset';

function renderMtPage(pageId) {
  _mtCurrentPage = pageId;
  document.querySelectorAll('.mt-wb-ni, .mt-wf-ni').forEach(function(el) {
    el.classList.toggle('active', el.dataset.id === pageId);
  });
  var html = mtPageHtml(pageId);
  var wbC = document.getElementById('mt-wb-content');
  var wfC = document.getElementById('mt-wf-content');
  if (wbC) wbC.innerHTML = mtWrapWb(pageId, html);
  if (wfC) wfC.innerHTML = mtWrapWf(pageId, html);
}

function mtWrapWb(pageId, html) {
  var title = mtPageTitle(pageId);
  return '<div class="mt-wb-win">'
    + '<div class="mt-wb-win-title">' + title + '</div>'
    + '<div class="mt-wb-win-body">' + html.wb + '</div>'
    + '</div>';
}

function mtWrapWf(pageId, html) {
  var title = mtPageTitle(pageId);
  return '<div class="mt-wf-panel">'
    + '<div class="mt-wf-panel-title">' + title + '</div>'
    + '<div class="mt-wf-panel-body">' + html.wf + '</div>'
    + '</div>';
}

function mtPageTitle(pageId) {
  var titles = {
    quickset:'Quick Set', interfaces:'Interfaces', wifi:'WiFi',
    wifi_ifaces:'WiFi Interfaces', wifi_sec:'Security Profiles', wifi_access:'Access List',
    wireguard:'WireGuard', bridge_list:'Bridge', bridge_ports:'Bridge - Ports', bridge_vlans:'Bridge - VLANs',
    ppp_ifaces:'PPP Interfaces', ppp_profiles:'PPP Profiles', ppp_secrets:'PPP Secrets',
    ip_addresses:'IP Addresses', ip_routes:'Routes', ip_arp:'ARP', ip_dns:'DNS',
    ip_dhcp_server:'DHCP Server', ip_dhcp_client:'DHCP Client',
    ip_fw_nat:'Firewall - NAT Rules', ip_fw_filter:'Firewall - Filter Rules', ip_fw_mangle:'Firewall - Mangle',
    ip_pool:'IP Pool', ip_settings:'IP Settings',
    sys_identity:'System Identity', sys_clock:'Clock', sys_ntp:'NTP Client',
    sys_password:'Change Password', sys_users:'Users', sys_resources:'System Resources',
    sys_routerboard:'RouterBoard', sys_packages:'Packages', sys_health:'System Health',
    tools_ping:'Ping', tools_trace:'Traceroute', tools_btest:'Bandwidth Test',
    tools_torch:'Torch', tools_fetch:'Fetch', queues:'Queues', files:'Files', log:'Log'
  };
  return titles[pageId] || pageId;
}

function mtPageHtml(pageId) {
  switch(pageId) {
    case 'quickset': return mtPageQuickSet();
    case 'interfaces': return mtPageInterfaces();
    case 'ip_addresses': return mtPageIPAddresses();
    case 'ip_routes': return mtPageIPRoutes();
    case 'ip_dns': return mtPageIPDNS();
    case 'ip_dhcp_server': return mtPageDHCPServer();
    case 'ip_fw_nat': return mtPageNAT();
    case 'ip_pool': return mtPageIPPool();
    case 'bridge_list': return mtPageBridge();
    case 'bridge_ports': return mtPageBridgePorts();
    case 'sys_identity': return mtPageIdentity();
    case 'sys_password': return mtPagePassword();
    case 'sys_resources': return mtPageResources();
    case 'tools_ping': return mtPagePing();
    case 'wifi_ifaces': return mtPageWifiIfaces();
    case 'wifi_sec': return mtPageWifiSec();
    case 'wifi_access': return mtPageWifiAccess();
    case 'wireguard': return mtPageWireGuard();
    case 'bridge_vlans': return mtPageBridgeVlans();
    case 'ppp_ifaces': return mtPagePppIfaces();
    case 'ppp_profiles': return mtPagePppProfiles();
    case 'ppp_secrets': return mtPagePppSecrets();
    case 'switch_main': return mtPageSwitchMain();
    case 'switch_ports': return mtPageSwitchPorts();
    case 'switch_vlans': return mtPageSwitchVlans();
    case 'mesh': return mtPageMesh();
    case 'ip_arp': return mtPageIPArp();
    case 'ip_dhcp_client': return mtPageDhcpClient();
    case 'ip_fw_filter': return mtPageFwFilter();
    case 'ip_fw_mangle': return mtPageFwMangle();
    case 'ip_settings': return mtPageIPSettings();
    case 'ipv6_addresses': return mtPageIPv6Addresses();
    case 'ipv6_routes': return mtPageIPv6Routes();
    case 'ipv6_firewall': return mtPageIPv6Firewall();
    case 'routing_ospf': return mtPageRoutingOSPF();
    case 'routing_rip': return mtPageRoutingRIP();
    case 'routing_bgp': return mtPageRoutingBGP();
    case 'routing_filters': return mtPageRoutingFilters();
    case 'routing_rules': return mtPageRoutingRules();
    case 'routing_tables': return mtPageRoutingTables();
    case 'sys_clock': return mtPageClock();
    case 'sys_ntp': return mtPageNTP();
    case 'sys_users': return mtPageUsers();
    case 'sys_routerboard': return mtPageRouterBoard();
    case 'sys_packages': return mtPagePackages();
    case 'sys_health': return mtPageHealth();
    case 'queues': return mtPageQueues();
    case 'files': return mtPageFiles();
    case 'log': return mtPageLog();
    case 'tools_trace': return mtPageTraceroute();
    case 'tools_btest': return mtPageBtest();
    case 'tools_torch': return mtPageTorch();
    case 'tools_fetch': return mtPageFetch();
    default: return mtPageGeneric(pageId);
  }
}

function mtWbTbar(btns) {
  return '<div class="mt-wb-tbar">' + btns.map(function(b) {
    return '<button class="mt-wb-btn"' + (b.onclick?' onclick="'+b.onclick+'"':'') + (b.dis?' disabled':'') + '>' + b.label + '</button>';
  }).join('') + '</div>';
}

function mtWfTbar(btns) {
  return '<div class="mt-wf-tbar">' + btns.map(function(b) {
    return '<button class="mt-wf-btn' + (b.cls?' '+b.cls:'') + '"' + (b.onclick?' onclick="'+b.onclick+'"':'') + '>' + b.label + '</button>';
  }).join('') + '</div>';
}

function mtWbTable(cols, rows) {
  var th = cols.map(function(c){return '<th>'+c+'</th>';}).join('');
  var tr = rows.map(function(r){
    return '<tr>' + r.map(function(c){return '<td>'+c+'</td>';}).join('') + '</tr>';
  }).join('');
  return '<div class="mt-wb-tbl-wrap"><table class="mt-wb-tbl"><thead><tr>'+th+'</tr></thead><tbody>'+tr+'</tbody></table></div>';
}

function mtWfTable(cols, rows) {
  var th = cols.map(function(c){return '<th>'+c+'</th>';}).join('');
  var tr = rows.map(function(r){
    return '<tr>' + r.map(function(c){return '<td>'+c+'</td>';}).join('') + '</tr>';
  }).join('');
  return '<table class="mt-wf-tbl"><thead><tr>'+th+'</tr></thead><tbody>'+tr+'</tbody></table>';
}

function mtWbFrow(label, input) {
  return '<div class="mt-wb-frow"><span class="mt-wb-flbl">'+label+':</span>'+input+'</div>';
}

function mtWfFrow(label, input) {
  return '<div class="mt-wf-frow"><span class="mt-wf-flbl">'+label+'</span>'+input+'</div>';
}

function mtWbInp(id, val, w) {
  return '<input class="mt-wb-finp" id="'+id+'" value="'+(val||'')+'"'+(w?' style="width:'+w+'px"':'')+'>';
}

function mtWfInp(id, val, w) {
  return '<input class="mt-wf-finp" id="'+id+'" value="'+(val||'')+'"'+(w?' style="width:'+w+'px"':'')+'>';
}

function mtWbSel(id, opts, cur) {
  return '<select class="mt-wb-fsel" id="'+id+'">'+opts.map(function(o){return '<option'+(o===cur?' selected':'')+'>'+o+'</option>';}).join('')+'</select>';
}

function mtWfSel(id, opts, cur) {
  return '<select class="mt-wf-fsel" id="'+id+'">'+opts.map(function(o){return '<option'+(o===cur?' selected':'')+'>'+o+'</option>';}).join('')+'</select>';
}

function mtPageQuickSet() {
  var qs = MTRS.quickSet;
  var tbtns = [{label:'▶ Apply', onclick:'mtApplyQuickSet()'},{label:'✖ Discard', onclick:'renderMtPage(\'quickset\')'}];
  var wbHtml = mtWbTbar(tbtns)
    + '<table style="width:100%;font-size:11px;font-family:Tahoma"><tr><td style="vertical-align:top;width:50%;padding-right:10px">'
    + '<div style="font-weight:bold;margin-bottom:6px;color:#000">WAN</div>'
    + mtWbFrow('Mode', mtWbSel('mt-qs-mode',['Router','Bridge','AP Router'],qs.mode))
    + mtWbFrow('Address Acquisition', mtWbSel('mt-qs-acq',['Static','Automatic (DHCP)','PPPoE'],qs.acq))
    + mtWbFrow('IP Address', mtWbInp('mt-qs-ip',qs.ip))
    + mtWbFrow('Netmask', mtWbSel('mt-qs-mask',['/8 (255.0.0.0)','/16 (255.255.0.0)','/24 (255.255.255.0)','/25','/26','/27','/28','/29','/30'],qs.mask))
    + mtWbFrow('Gateway', mtWbInp('mt-qs-gw',qs.gw))
    + mtWbFrow('DNS Servers', mtWbInp('mt-qs-dns',qs.dns))
    + '</td><td style="vertical-align:top;padding-left:10px;border-left:1px solid #999">'
    + '<div style="font-weight:bold;margin-bottom:6px;color:#000">LAN</div>'
    + mtWbFrow('IP Address', mtWbInp('mt-qs-lanip',qs.lanip))
    + mtWbFrow('Netmask', mtWbSel('mt-qs-lanmask',['/24 (255.255.255.0)','/25','/16 (255.255.0.0)'],qs.lanmask))
    + '<hr class="mt-wb-sep-line">'
    + '<div style="font-weight:bold;margin-bottom:6px;color:#000">Wireless</div>'
    + mtWbFrow('Network Name', mtWbInp('mt-qs-ssid',qs.ssid))
    + mtWbFrow('WiFi Password', mtWbInp('mt-qs-wpass',qs.wpass))
    + mtWbFrow('Band', mtWbSel('mt-qs-band',['2GHz-B/G/N','5GHz-A/N/AC','2GHz-B/G/N+5GHz-A/N/AC'],qs.band))
    + mtWbFrow('Frequency', mtWbSel('mt-qs-freq',['auto','2412 (1)','2437 (6)','2462 (11)'],qs.freq))
    + '</td></tr></table>';

  var wfHtml = mtWfTbar([{label:'Apply',cls:'blue',onclick:'mtApplyQuickSet()'},{label:'Discard',onclick:'renderMtPage(\'quickset\')'}])
    + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">'
    + '<div><div class="mt-wf-panel-title" style="margin-bottom:8px">WAN</div>'
    + mtWfFrow('Mode', mtWfSel('mt-wf-qs-mode',['Router','Bridge','AP Router'],qs.mode))
    + mtWfFrow('Address Acquisition', mtWfSel('mt-wf-qs-acq',['Automatic (DHCP)','Static','PPPoE'],qs.acq))
    + mtWfFrow('IP Address', mtWfInp('mt-wf-qs-ip',qs.ip))
    + mtWfFrow('Gateway', mtWfInp('mt-wf-qs-gw',qs.gw))
    + mtWfFrow('DNS Servers', mtWfInp('mt-wf-qs-dns',qs.dns))
    + '</div>'
    + '<div><div class="mt-wf-panel-title" style="margin-bottom:8px">LAN &amp; Wireless</div>'
    + mtWfFrow('IP Address', mtWfInp('mt-wf-qs-lanip',qs.lanip))
    + mtWfFrow('DHCP Server', '<select class="mt-wf-fsel"><option>Enabled</option><option>Disabled</option></select>')
    + '<hr class="mt-wf-sep">'
    + mtWfFrow('Network Name (SSID)', mtWfInp('mt-wf-qs-ssid',qs.ssid))
    + mtWfFrow('WiFi Password', mtWfInp('mt-wf-qs-wpass',qs.wpass))
    + mtWfFrow('Band', mtWfSel('mt-wf-qs-band',['2GHz-B/G/N','5GHz-A/N/AC'],qs.band))
    + '</div></div>';

  return {wb: wbHtml, wf: wfHtml};
}

function mtPageInterfaces() {
  var cols = ['', 'Name', 'Type', 'MTU', 'Actual MTU', 'L2 MTU', 'TX', 'RX'];
  var rows = [
    ['<input type="checkbox">','ether1','ether','1500','1500','1598','0 bps','0 bps'],
    ['<input type="checkbox">','ether2','ether','1500','1500','1598','0 bps','0 bps'],
    ['<input type="checkbox">','ether3','ether','1500','1500','1598','0 bps','0 bps'],
    ['<input type="checkbox">','ether4','ether','1500','1500','1598','0 bps','0 bps'],
    ['<input type="checkbox">','ether5','ether','1500','1500','1598','0 bps','0 bps'],
    ['<input type="checkbox">','wlan1','wlan','1500','1500','1600','0 bps','0 bps']
  ];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'},{label:'Reset Counters'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add New',cls:'blue'},{label:'Remove',cls:'red'},{label:'Enable'},{label:'Disable'},{label:'Reset Counters'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageIPAddresses() {
  var cols = ['', 'Address', 'Network', 'Interface'];
  var rows = MTRS.addresses.map(function(a, i) {
    return ['<input type="checkbox" data-idx="'+i+'">', a.addr, a.net, a.iface];
  });
  var ifaces = ['ether1','ether2','ether3','ether4','ether5','wlan1','bridge'];
  var wbHtml = mtWbTbar([
      {label:'Add New', onclick:'document.getElementById(\'mt-wb-addr-form\').style.display=\'block\''},
      {label:'Remove', onclick:'mtRemoveIPAddr()'},
      {label:'Enable'},{label:'Disable'},{label:'Comment'}
    ])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" id="mt-wb-addr-form" style="margin-top:6px;display:none">'
    + '<div style="font-weight:bold;margin-bottom:6px;font-size:11px;color:#000">New Address</div>'
    + mtWbFrow('Address', mtWbInp('mt-wb-addr-addr','',180))
    + mtWbFrow('Network', mtWbInp('mt-wb-addr-net','',180))
    + mtWbFrow('Interface', mtWbSel('mt-wb-addr-iface',ifaces,'ether1'))
    + '<div style="margin-top:6px;display:flex;gap:4px">'
    + '<button class="mt-wb-btn" onclick="mtApplyIPAddr()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplyIPAddr()">OK</button>'
    + '<button class="mt-wb-btn" onclick="document.getElementById(\'mt-wb-addr-form\').style.display=\'none\'">Cancel</button>'
    + '</div></div>';
  var wfHtml = mtWfTbar([
      {label:'Add',cls:'blue', onclick:'document.getElementById(\'mt-wf-addr-form\').style.display=\'block\''},
      {label:'Remove',cls:'red', onclick:'mtRemoveIPAddr()'},
      {label:'Enable'},{label:'Disable'}
    ])
    + mtWfTable(cols, rows)
    + '<div id="mt-wf-addr-form" style="margin-top:10px;display:none">'
    + '<hr class="mt-wf-sep"><div style="font-weight:bold;margin-bottom:8px;font-size:12px">New Address</div>'
    + mtWfFrow('Address', mtWfInp('mt-wf-addr','','180'))
    + mtWfFrow('Network', mtWfInp('mt-wf-addr-net',''))
    + mtWfFrow('Interface', mtWfSel('mt-wf-addr-iface',ifaces,'ether1'))
    + mtWfTbar([
        {label:'Apply',cls:'blue', onclick:'mtApplyIPAddr()'},
        {label:'OK',cls:'blue', onclick:'mtApplyIPAddr()'},
        {label:'Cancel', onclick:'document.getElementById(\'mt-wf-addr-form\').style.display=\'none\''}
      ])
    + '</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageIPRoutes() {
  var cols = ['','Dst. Address','Gateway','Distance','Routing Table','Interface','Gateway Status'];
  // wiersze połączone/dynamiczne — tylko do wyświetlenia (bez data-idx)
  var baseRows = [
    ['<span style="color:#888">DC</span>','192.168.88.0/24','bridge','0','main','bridge','reachable']
  ];
  // trasy dodane przez ucznia — usuwalne (data-idx)
  var userRows = MTRS.routes.map(function(rt, i) {
    return ['<input type="checkbox" data-idx="'+i+'">', rt.dst, rt.gw, (rt.dist||'1'), 'main', '', 'reachable'];
  });
  var rows = baseRows.concat(userRows);
  var wbHtml = mtWbTbar([
      {label:'Add New', onclick:'document.getElementById(\'mt-wb-route-form\').style.display=\'block\''},
      {label:'Remove', onclick:'mtRemoveRoute()'},
      {label:'Enable'},{label:'Disable'},{label:'Comment'}
    ])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" id="mt-wb-route-form" style="margin-top:6px;display:none">'
    + '<div style="font-weight:bold;margin-bottom:6px;font-size:11px;color:#000">New Route</div>'
    + mtWbFrow('Dst. Address', mtWbInp('mt-wb-route-dst','0.0.0.0/0',160))
    + mtWbFrow('Gateway', mtWbInp('mt-wb-route-gw','',160))
    + '<div style="margin-top:6px;display:flex;gap:4px">'
    + '<button class="mt-wb-btn" onclick="mtApplyRoute()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplyRoute()">OK</button>'
    + '<button class="mt-wb-btn" onclick="document.getElementById(\'mt-wb-route-form\').style.display=\'none\'">Cancel</button>'
    + '</div></div>';
  var wfHtml = mtWfTbar([
      {label:'Add',cls:'blue', onclick:'document.getElementById(\'mt-wf-route-form\').style.display=\'block\''},
      {label:'Remove',cls:'red', onclick:'mtRemoveRoute()'}
    ])
    + mtWfTable(cols, rows)
    + '<div id="mt-wf-route-form" style="margin-top:10px;display:none">'
    + '<hr class="mt-wf-sep"><div style="font-weight:bold;margin-bottom:8px;font-size:12px">New Route</div>'
    + mtWfFrow('Dst. Address', mtWfInp('mt-wf-route-dst','0.0.0.0/0'))
    + mtWfFrow('Gateway', mtWfInp('mt-wf-route-gw',''))
    + mtWfTbar([
        {label:'Apply',cls:'blue', onclick:'mtApplyRoute()'},
        {label:'OK',cls:'blue', onclick:'mtApplyRoute()'},
        {label:'Cancel', onclick:'document.getElementById(\'mt-wf-route-form\').style.display=\'none\''}
      ])
    + '</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageIPDNS() {
  var dns = MTRS.dns;
  var wbHtml = mtWbFrow('Servers', mtWbInp('mt-wb-dns1',dns.s1))
    + mtWbFrow('', mtWbInp('mt-wb-dns2',dns.s2))
    + mtWbFrow('Allow Remote Requests', '<input type="checkbox" id="mt-wb-dns-remote" class="mt-wb-fchk"'+(dns.remote?' checked':'')+'>')
    + mtWbFrow('Max UDP Packet Size', mtWbInp('mt-wb-dns-udp','4096',80))
    + mtWbFrow('Query Server Timeout', mtWbInp('mt-wb-dns-qtout','2.000s',80))
    + mtWbFrow('Cache Size', mtWbInp('mt-wb-dns-cache','2048 KiB',100))
    + mtWbFrow('Cache Max TTL', mtWbInp('mt-wb-dns-ttl','1d 00:00:00',120))
    + '<hr class="mt-wb-sep-line">'
    + '<div class="mt-wb-tbar">'
    + '<button class="mt-wb-btn" onclick="mtApplyDNS()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplyDNS()">OK</button>'
    + '<button class="mt-wb-btn" onclick="renderMtPage(\'ip_dns\')">Cancel</button>'
    + '</div>';
  var wfHtml = '<div class="mt-wf-panel-body">'
    + mtWfFrow('Servers', mtWfInp('mt-wf-dns1',dns.s1))
    + mtWfFrow('', mtWfInp('mt-wf-dns2',dns.s2))
    + mtWfFrow('Allow Remote Requests', '<input type="checkbox" id="mt-wf-dns-remote"'+(dns.remote?' checked':'')+'>')
    + mtWfFrow('Max UDP Packet Size', mtWfInp('mt-wf-dns-udp','4096','80'))
    + mtWfFrow('Cache Size', mtWfInp('mt-wf-dns-cache','2048 KiB','100'))
    + '<hr class="mt-wf-sep">'
    + mtWfTbar([
        {label:'Apply',cls:'blue', onclick:'mtApplyDNS()'},
        {label:'OK',cls:'blue', onclick:'mtApplyDNS()'},
        {label:'Cancel', onclick:'renderMtPage(\'ip_dns\')'}
      ])
    + '</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageDHCPServer() {
  var cols = ['','Name','Interface','Relay','Address Pool','Add ARP','Lease Time','Authoritative'];
  var rows = MTRS.dhcpServers.map(function(s, i) {
    return ['<input type="checkbox" data-idx="'+i+'">', s.name, s.iface, '', s.pool, 'no', s.lease, s.auth];
  });
  var wbHtml = mtWbTbar([
      {label:'Add New', onclick:'document.getElementById(\'mt-wb-dhcp-form\').style.display=\'block\''},
      {label:'Remove', onclick:'mtRemoveDHCP()'},
      {label:'Enable'},{label:'Disable'},{label:'DHCP Setup'}
    ])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" id="mt-wb-dhcp-form" style="margin-top:6px;display:none">'
    + '<div style="font-weight:bold;font-size:11px;margin-bottom:4px">New DHCP Server</div>'
    + mtWbFrow('Name', mtWbInp('mt-wb-dhcps-name','dhcp1'))
    + mtWbFrow('Interface', mtWbSel('mt-wb-dhcps-iface',['bridge','ether1','ether2','ether3','ether4','ether5'],'bridge'))
    + mtWbFrow('Address Pool', mtWbInp('mt-wb-dhcps-pool','default-dhcp'))
    + mtWbFrow('Lease Time', mtWbInp('mt-wb-dhcps-lease','10m',80))
    + mtWbFrow('Authoritative', mtWbSel('mt-wb-dhcps-auth',['yes','no'],'yes'))
    + '<div style="margin-top:6px;display:flex;gap:4px">'
    + '<button class="mt-wb-btn" onclick="mtApplyDHCP()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplyDHCP()">OK</button>'
    + '<button class="mt-wb-btn" onclick="document.getElementById(\'mt-wb-dhcp-form\').style.display=\'none\'">Cancel</button>'
    + '</div></div>';
  var wfHtml = mtWfTbar([
      {label:'Add',cls:'blue', onclick:'document.getElementById(\'mt-wf-dhcp-form\').style.display=\'block\''},
      {label:'Remove',cls:'red', onclick:'mtRemoveDHCP()'},
      {label:'DHCP Setup',cls:'blue'}
    ])
    + mtWfTable(cols, rows)
    + '<div id="mt-wf-dhcp-form" style="margin-top:10px;display:none">'
    + '<hr class="mt-wf-sep"><div style="font-weight:bold;margin-bottom:8px;font-size:12px">New DHCP Server</div>'
    + mtWfFrow('Name', mtWfInp('mt-wf-dhcps-name','dhcp1'))
    + mtWfFrow('Interface', mtWfSel('mt-wf-dhcps-iface',['bridge','ether1','ether2','ether3','ether4','ether5'],'bridge'))
    + mtWfFrow('Address Pool', mtWfInp('mt-wf-dhcps-pool','default-dhcp'))
    + mtWfFrow('Lease Time', mtWfInp('mt-wf-dhcps-lease','10m','80'))
    + mtWfFrow('Authoritative', mtWfSel('mt-wf-dhcps-auth',['yes','no'],'yes'))
    + mtWfTbar([
        {label:'Apply',cls:'blue', onclick:'mtApplyDHCP()'},
        {label:'OK',cls:'blue', onclick:'mtApplyDHCP()'},
        {label:'Cancel', onclick:'document.getElementById(\'mt-wf-dhcp-form\').style.display=\'none\''}
      ])
    + '</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageNAT() {
  var cols = ['','Chain','Action','Out. Interface','To Addresses','To Ports','Comment'];
  var rows = [['<input type="checkbox">','srcnat','masquerade','ether1','','','defconf']];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageIPPool() {
  var cols = ['','Name','Ranges'];
  var rows = MTRS.ipPool.map(function(p, i) {
    return ['<input type="checkbox" data-idx="'+i+'">', p.name, p.range];
  });
  var wbHtml = mtWbTbar([
      {label:'Add New', onclick:'document.getElementById(\'mt-wb-pool-form\').style.display=\'block\''},
      {label:'Remove'},{label:'Comment'}
    ])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" id="mt-wb-pool-form" style="margin-top:6px;display:none">'
    + mtWbFrow('Name', mtWbInp('mt-wb-pool-name','pool1'))
    + mtWbFrow('Addresses', mtWbInp('mt-wb-pool-range','192.168.88.10-192.168.88.254',200))
    + '<div style="margin-top:6px;display:flex;gap:4px">'
    + '<button class="mt-wb-btn" onclick="mtApplyIPPool()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplyIPPool()">OK</button>'
    + '<button class="mt-wb-btn" onclick="document.getElementById(\'mt-wb-pool-form\').style.display=\'none\'">Cancel</button>'
    + '</div></div>';
  var wfHtml = mtWfTbar([
      {label:'Add',cls:'blue', onclick:'document.getElementById(\'mt-wf-pool-form\').style.display=\'block\''},
      {label:'Remove',cls:'red', onclick:'mtRemoveIPPool()'}
    ])
    + mtWfTable(cols, rows)
    + '<div id="mt-wf-pool-form" style="margin-top:10px;display:none">'
    + '<hr class="mt-wf-sep"><div style="font-weight:bold;margin-bottom:8px;font-size:12px">New Pool</div>'
    + mtWfFrow('Name', mtWfInp('mt-wf-pool-name','pool1'))
    + mtWfFrow('Addresses', mtWfInp('mt-wf-pool-range','192.168.88.10-192.168.88.254','200'))
    + mtWfTbar([
        {label:'Apply',cls:'blue', onclick:'mtApplyIPPool()'},
        {label:'OK',cls:'blue', onclick:'mtApplyIPPool()'},
        {label:'Cancel', onclick:'document.getElementById(\'mt-wf-pool-form\').style.display=\'none\''}
      ])
    + '</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageBridge() {
  var cols = ['','Name','MTU','Actual MTU','L2 MTU','STP','RSTP','VLAN Filtering','Arp'];
  var rows = [['<input type="checkbox">','bridge','auto','1500','1598','no','no','no','enabled']];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageBridgePorts() {
  var cols = ['','Interface','Bridge','Priority','Path Cost','Edge','Point To Point','Role','Status'];
  var rows = [
    ['<input type="checkbox">','ether2','bridge','0x80 (128)','10','yes','auto','designated','active'],
    ['<input type="checkbox">','ether3','bridge','0x80 (128)','10','yes','auto','designated','active'],
    ['<input type="checkbox">','ether4','bridge','0x80 (128)','10','yes','auto','designated','active'],
    ['<input type="checkbox">','ether5','bridge','0x80 (128)','10','yes','auto','designated','active']
  ];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageIdentity() {
  var name = MTRS.identity;
  var wbHtml = '<div style="padding:4px">'
    + mtWbFrow('Name', mtWbInp('mt-wb-identity',name,200))
    + '<hr class="mt-wb-sep-line">'
    + '<div class="mt-wb-tbar">'
    + '<button class="mt-wb-btn" onclick="mtApplyIdentity()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplyIdentity()">OK</button>'
    + '<button class="mt-wb-btn" onclick="renderMtPage(\'sys_identity\')">Cancel</button>'
    + '</div></div>';
  var wfHtml = mtWfFrow('Name', mtWfInp('mt-wf-identity-inp',name))
    + '<hr class="mt-wf-sep">'
    + mtWfTbar([
        {label:'Apply',cls:'blue', onclick:'mtApplyIdentity()'},
        {label:'OK',cls:'blue', onclick:'mtApplyIdentity()'},
        {label:'Cancel', onclick:'renderMtPage(\'sys_identity\')'}
      ]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPagePassword() {
  var wbHtml = '<div style="padding:4px">'
    + mtWbFrow('Old Password', '<input type="password" class="mt-wb-finp" style="min-width:160px">')
    + mtWbFrow('New Password', '<input type="password" class="mt-wb-finp" style="min-width:160px">')
    + mtWbFrow('Confirm Password', '<input type="password" class="mt-wb-finp" style="min-width:160px">')
    + '<hr class="mt-wb-sep-line">'
    + '<div class="mt-wb-tbar"><button class="mt-wb-btn">Change</button><button class="mt-wb-btn">Cancel</button></div>'
    + '</div>';
  var wfHtml = mtWfFrow('Old Password', '<input type="password" class="mt-wf-finp">')
    + mtWfFrow('New Password', '<input type="password" class="mt-wf-finp">')
    + mtWfFrow('Confirm Password', '<input type="password" class="mt-wf-finp">')
    + '<hr class="mt-wf-sep">'
    + mtWfTbar([{label:'Change',cls:'blue'},{label:'Cancel'}]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageResources() {
  var rows = [
    ['Uptime','0d 00:05:00'],['Version','7.15.3 (stable)'],['Build Time','2024-08-29 07:47:49'],
    ['Factory Software','6.49.8'],['Free Memory','180.6 MiB'],['Total Memory','256.0 MiB'],
    ['CPU','MIPS 74Kc V5.0'],['CPU Count','1'],['CPU Frequency','400 MHz'],['CPU Load','2%'],
    ['Free HDD Space','1920.0 KiB'],['Sector Writes','3 777'],['Bad Blocks','0%'],
    ['Uptime','00:05:00'],['Architecture Name','mipsbe'],['Board Name','RB941-2nD'],
    ['Platform','MikroTik']
  ];
  var wbHtml = mtWbTable(['Property','Value'], rows);
  var wfHtml = '<div style="columns:2;column-gap:20px">' + mtWfTable(['Property','Value'], rows) + '</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtRunPing(wf) {
  var dstId = wf ? 'mt-wf-ping-dst' : 'mt-wb-ping-dst';
  var cntId = wf ? 'mt-wf-ping-cnt' : 'mt-wb-ping-cnt';
  var resId = wf ? 'mt-wf-ping-result' : 'mt-ping-result';
  var dst = (document.getElementById(dstId)||{}).value || '';
  var cnt = parseInt((document.getElementById(cntId)||{}).value) || 4;
  var res = document.getElementById(resId);
  if (!res) return;
  if (!dst.trim()) { res.innerHTML = 'Wprowadź adres docelowy.'; return; }
  res.innerHTML = 'Pingowanie ' + labEsc(dst) + '...';
  var lines = [];
  var i = 0;
  var t = setInterval(function() {
    if (i >= cnt) {
      clearInterval(t);
      lines.push('');
      lines.push(cnt + ' packets transmitted, ' + cnt + ' received, 0% packet loss');
      lines.push('round-trip min/avg/max = 1/2/4 ms');
      res.innerHTML = lines.join('<br>');
      return;
    }
    var ms = Math.floor(Math.random() * 5) + 1;
    lines.push('  seq=' + (i+1) + ' ttl=64 time=' + ms + 'ms');
    res.innerHTML = lines.join('<br>');
    i++;
  }, 300);
}

function mtPagePing() {
  var wbHtml = mtWbFrow('Ping To', mtWbInp('mt-wb-ping-dst','',160))
    + mtWbFrow('Interface', mtWbSel('mt-wb-ping-iface',['(any)','ether1','bridge','wlan1'],'(any)'))
    + mtWbFrow('Count', mtWbInp('mt-wb-ping-cnt','4',60))
    + mtWbFrow('Size', mtWbInp('mt-wb-ping-size','56',60))
    + mtWbFrow('Interval', mtWbInp('mt-wb-ping-int','0.05s',80))
    + '<div class="mt-wb-tbar" style="margin-top:8px">'
    + '<button class="mt-wb-btn" onclick="mtRunPing(false)">Ping</button>'
    + '<button class="mt-wb-btn">Stop</button>'
    + '</div>'
    + '<div id="mt-ping-result" style="margin-top:8px;font-family:Courier New,monospace;font-size:11px;background:#fff;border:1px inset #999;padding:4px;min-height:60px;color:#000"></div>';
  var wfHtml = mtWfFrow('Ping To', mtWfInp('mt-wf-ping-dst',''))
    + mtWfFrow('Interface', mtWfSel('mt-wf-ping-iface',['(any)','ether1','bridge','wlan1'],'(any)'))
    + mtWfFrow('Count', mtWfInp('mt-wf-ping-cnt','4','60'))
    + mtWfFrow('Interval', mtWfInp('mt-wf-ping-int','0.05s','80'))
    + mtWfTbar([{label:'Ping',cls:'blue', onclick:'mtRunPing(true)'},{label:'Stop'}])
    + '<div id="mt-wf-ping-result" style="margin-top:10px;font-family:monospace;font-size:12px;background:#f9f9f9;border:1px solid #ddd;border-radius:3px;padding:8px;min-height:60px"></div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageWifiIfaces() {
  var qs = MTRS.quickSet;
  var ssid = qs.ssid||'MikroTik';
  var band = qs.band||'2GHz-B/G/N';
  var freq = qs.freq||'auto';
  var bands = ['2GHz-B/G/N','5GHz-A/N/AC','2GHz-B/G/N+5GHz-A/N/AC'];
  var freqs = ['auto','2412','2437','2462'];
  var cols = ['','Name','SSID','Band','Channel','Tx Rate','Rx Rate','Clients'];
  var rows = [['<input type="checkbox">','wlan1',ssid,band,freq==='auto'?'auto':freq,'0 Mbps','0 Mbps','0']];
  var wbHtml = mtWbTbar([{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" style="margin-top:6px;display:block">'
    + '<div style="font-weight:bold;font-size:11px;margin-bottom:4px">WiFi Interface — wlan1</div>'
    + mtWbFrow('SSID', mtWbInp('mt-wb-wif-ssid',ssid,180))
    + mtWbFrow('Band', mtWbSel('mt-wb-wif-band',bands,band))
    + mtWbFrow('Frequency', mtWbSel('mt-wb-wif-freq',freqs,freq))
    + '<div style="margin-top:6px;display:flex;gap:4px">'
    + '<button class="mt-wb-btn" onclick="mtApplyWifi()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplyWifi()">OK</button>'
    + '<button class="mt-wb-btn" onclick="renderMtPage(\'wifi_ifaces\')">Cancel</button>'
    + '</div></div>';
  var wfHtml = mtWfTbar([{label:'Apply',cls:'blue',onclick:'mtApplyWifi()'},{label:'Discard',onclick:'renderMtPage(\'wifi_ifaces\')'}])
    + mtWfTable(cols, rows)
    + '<hr class="mt-wf-sep"><div style="font-weight:bold;margin-bottom:8px">WiFi Interface — wlan1</div>'
    + mtWfFrow('SSID', mtWfInp('mt-wf-wif-ssid',ssid))
    + mtWfFrow('Band', mtWfSel('mt-wf-wif-band',bands,band))
    + mtWfFrow('Frequency', mtWfSel('mt-wf-wif-freq',freqs,freq))
    + mtWfTbar([
        {label:'Apply',cls:'blue', onclick:'mtApplyWifi()'},
        {label:'OK',cls:'blue', onclick:'mtApplyWifi()'},
        {label:'Cancel', onclick:'renderMtPage(\'wifi_ifaces\')'}
      ]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageWifiSec() {
  var pass = MTRS.quickSet.wpass||'';
  var cols = ['','Name','Authentication Types','Encryption'];
  var rows = [['<input type="checkbox">','default','wpa-psk wpa2-psk','aes ccm']];
  var wbHtml = mtWbTbar([{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" style="margin-top:6px;display:block">'
    + '<div style="font-weight:bold;font-size:11px;margin-bottom:4px">Security Profile — default</div>'
    + mtWbFrow('Mode', mtWbSel('mt-wb-wsec-mode',['none','dynamic-keys'],'dynamic-keys'))
    + mtWbFrow('Authentication Types', '<label style="font-size:11px"><input type="checkbox" checked> wpa-psk</label> &nbsp; <label style="font-size:11px"><input type="checkbox" checked> wpa2-psk</label>')
    + mtWbFrow('Unicast Ciphers', '<label style="font-size:11px"><input type="checkbox" checked> aes ccm</label>')
    + mtWbFrow('WPA Pre-Shared Key', '<input type="password" class="mt-wb-finp" style="min-width:180px" value="'+pass+'">')
    + mtWbFrow('WPA2 Pre-Shared Key', '<input type="password" class="mt-wb-finp" id="mt-wb-wsec-wpa2" style="min-width:180px" value="'+pass+'">')
    + '<div style="margin-top:6px;display:flex;gap:4px">'
    + '<button class="mt-wb-btn" onclick="mtApplyWifiSec()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplyWifiSec()">OK</button>'
    + '<button class="mt-wb-btn" onclick="renderMtPage(\'wifi_sec\')">Cancel</button>'
    + '</div></div>';
  var wfHtml = mtWfTbar([{label:'Apply',cls:'blue',onclick:'mtApplyWifiSec()'},{label:'Discard',onclick:'renderMtPage(\'wifi_sec\')'}])
    + mtWfTable(cols, rows)
    + '<hr class="mt-wf-sep"><div style="font-weight:bold;margin-bottom:8px">Security Profile — default</div>'
    + mtWfFrow('Mode', mtWfSel('mt-wf-wsec-mode',['none','dynamic-keys'],'dynamic-keys'))
    + mtWfFrow('WPA Pre-Shared Key', '<input type="password" class="mt-wf-finp" value="'+pass+'">')
    + mtWfFrow('WPA2 Pre-Shared Key', '<input type="password" class="mt-wf-finp" id="mt-wf-wsec-wpa2" value="'+pass+'">')
    + mtWfTbar([
        {label:'Apply',cls:'blue', onclick:'mtApplyWifiSec()'},
        {label:'OK',cls:'blue', onclick:'mtApplyWifiSec()'},
        {label:'Cancel', onclick:'renderMtPage(\'wifi_sec\')'}
      ]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageWifiAccess() {
  var cols = ['','Interface','MAC Address','Action','Signal Range','Comment'];
  var rows = [];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div style="font-size:11px;color:#808080;padding:4px">Brak wpisów. Kliknij Add New, aby dodać regułę dostępu.</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows)
    + '<div style="color:#999;font-size:12px;padding:8px">Brak wpisów.</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageWireGuard() {
  var cols = ['','Name','Listen Port','MTU','Public Key','Comment'];
  var rows = [];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div style="font-size:11px;color:#808080;padding:4px">Brak interfejsów WireGuard.</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows)
    + '<div style="color:#999;font-size:12px;padding:8px">Brak interfejsów WireGuard.</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageBridgeVlans() {
  var cols = ['','Bridge','VLAN IDs','Tagged','Untagged'];
  var vlans = MTRS.bridgeVlans || [];
  var rows = vlans.map(function(v,i){
    return ['<input type="checkbox" data-brvl="'+i+'">',v.bridge,v.vids,v.tagged||'',v.untagged||''];
  });
  var wbHtml = mtWbTbar([
      {label:'Add New', onclick:'document.getElementById(\'mt-wb-brvl-form\').style.display=\'block\''},
      {label:'Remove', onclick:'mtRemoveBridgeVlan()'}
    ])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" id="mt-wb-brvl-form" style="margin-top:6px;display:block">'
    + mtWbFrow('Bridge', mtWbSel('mt-wb-brvl-br',['bridge'],'bridge'))
    + mtWbFrow('VLAN IDs', mtWbInp('mt-wb-brvl-vid','',80))
    + mtWbFrow('Tagged', mtWbInp('mt-wb-brvl-tag','bridge',140))
    + mtWbFrow('Untagged', mtWbInp('mt-wb-brvl-untag','',140))
    + '<div style="margin-top:6px;display:flex;gap:4px">'
    + '<button class="mt-wb-btn" onclick="mtApplyBridgeVlan()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplyBridgeVlan()">OK</button>'
    + '<button class="mt-wb-btn" onclick="document.getElementById(\'mt-wb-brvl-form\').style.display=\'none\'">Cancel</button>'
    + '</div></div>';
  var wfHtml = mtWfTbar([
      {label:'Add',cls:'blue', onclick:'document.getElementById(\'mt-wf-brvl-form\').style.display=\'block\''},
      {label:'Remove',cls:'red', onclick:'mtRemoveBridgeVlan()'}
    ])
    + mtWfTable(cols, rows)
    + '<div id="mt-wf-brvl-form" style="margin-top:10px">'
    + mtWfFrow('Bridge', mtWfSel('mt-wf-brvl-br',['bridge'],'bridge'))
    + mtWfFrow('VLAN IDs', mtWfInp('mt-wf-brvl-vid',''))
    + mtWfFrow('Tagged', mtWfInp('mt-wf-brvl-tag','bridge'))
    + mtWfFrow('Untagged', mtWfInp('mt-wf-brvl-untag',''))
    + mtWfTbar([
        {label:'Apply',cls:'blue', onclick:'mtApplyBridgeVlan()'},
        {label:'OK',cls:'blue', onclick:'mtApplyBridgeVlan()'},
        {label:'Cancel', onclick:'document.getElementById(\'mt-wf-brvl-form\').style.display=\'none\''}
      ])
    + '</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPagePppIfaces() {
  var cols = ['','Name','Type','Running','MTU','Comment'];
  var rows = [
    ['<input type="checkbox">','pppoe-out1','pppoe-client','no','1480','PPPoE WAN']
  ];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'}])
    + '<div class="mt-wb-tbar" style="margin-bottom:2px">'
    + '<button class="mt-wb-btn">PPPoE Client</button><button class="mt-wb-btn">PPTP Client</button><button class="mt-wb-btn">L2TP Client</button><button class="mt-wb-btn">SSTP Client</button>'
    + '</div>'
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" style="margin-top:6px">'
    + '<div style="font-weight:bold;font-size:11px;margin-bottom:4px">PPPoE Client</div>'
    + mtWbFrow('Name', mtWbInp('mt-wb-ppp-name','pppoe-out1'))
    + mtWbFrow('Interfaces', mtWbSel('mt-wb-ppp-iface',['ether1','ether2','ether3'],'ether1'))
    + mtWbFrow('User', mtWbInp('mt-wb-ppp-user',''))
    + mtWbFrow('Password', '<input type="password" class="mt-wb-finp" style="min-width:160px">')
    + mtWbFrow('Service Name', mtWbInp('mt-wb-ppp-svc','',120))
    + mtWbFrow('Add Default Route', '<input type="checkbox" class="mt-wb-fchk" checked>')
    + '<div style="margin-top:6px;display:flex;gap:4px"><button class="mt-wb-btn">Apply</button><button class="mt-wb-btn">OK</button><button class="mt-wb-btn">Cancel</button></div>'
    + '</div>';
  var wfHtml = mtWfTbar([{label:'Add PPPoE Client',cls:'blue'},{label:'Remove',cls:'red'},{label:'Enable'},{label:'Disable'}])
    + mtWfTable(cols, rows)
    + '<hr class="mt-wf-sep"><div style="font-weight:bold;margin-bottom:8px">PPPoE Client</div>'
    + mtWfFrow('Name', mtWfInp('mt-wf-ppp-name','pppoe-out1'))
    + mtWfFrow('Interface', mtWfSel('mt-wf-ppp-iface',['ether1','ether2','ether3'],'ether1'))
    + mtWfFrow('User', mtWfInp('mt-wf-ppp-user',''))
    + mtWfFrow('Password', '<input type="password" class="mt-wf-finp">')
    + mtWfFrow('Add Default Route', '<input type="checkbox" checked>')
    + mtWfTbar([{label:'Apply',cls:'blue'},{label:'OK',cls:'blue'},{label:'Cancel'}]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPagePppProfiles() {
  var cols = ['','Name','Local Address','Remote Address','Bridge','Change TCP MSS'];
  var rows = [
    ['<input type="checkbox">','default','','','','yes'],
    ['<input type="checkbox">','default-encryption','','','','yes']
  ];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Comment'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPagePppSecrets() {
  var cols = ['','Name','Password','Service','Profile','Local Address','Remote Address'];
  var rows = [['<input type="checkbox">','user1','','pppoe','default','','192.168.100.2']];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" style="margin-top:6px">'
    + mtWbFrow('Name', mtWbInp('mt-wb-ppps-name','',140))
    + mtWbFrow('Password', '<input type="password" class="mt-wb-finp" style="min-width:140px">')
    + mtWbFrow('Service', mtWbSel('mt-wb-ppps-svc',['any','pppoe','pptp','l2tp','sstp'],'pppoe'))
    + mtWbFrow('Profile', mtWbSel('mt-wb-ppps-prof',['default','default-encryption'],'default'))
    + mtWbFrow('Remote Address', mtWbInp('mt-wb-ppps-raddr','',160))
    + '<div style="margin-top:6px;display:flex;gap:4px"><button class="mt-wb-btn">Apply</button><button class="mt-wb-btn">OK</button><button class="mt-wb-btn">Cancel</button></div>'
    + '</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageSwitchMain() {
  var cols = ['','Name','Mirror Source','Mirror Target','Store CPU','L3HW Offloading'];
  var rows = [['<input type="checkbox">','switch1','none','none','yes','no']];
  var wbHtml = mtWbTbar([{label:'Comment'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Comment'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageSwitchPorts() {
  var cols = ['','Name','Switch','Default VLAN ID','VLAN Mode','Flood','Tx Rate','Rx Rate'];
  var rows = MTRS.switchPorts.map(function(p, i) {
    return ['<input type="checkbox" data-idx="'+i+'">', p.name, p.sw, p.vlan, p.mode, p.flood, p.txRate, p.rxRate];
  });
  var ifaces = ['ether1','ether2','ether3','ether4','ether5','wlan1','bridge'];
  var wbHtml = mtWbTbar([
      {label:'Add New', onclick:'document.getElementById(\'mt-wb-swp-form\').style.display=\'block\''},
      {label:'Remove', onclick:'mtRemoveSwitchPort()'},
      {label:'Comment'}
    ])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" id="mt-wb-swp-form" style="margin-top:6px;display:block">'
    + '<div style="font-weight:bold;font-size:11px;margin-bottom:4px">Switch Port</div>'
    + mtWbFrow('Name', mtWbSel('mt-wb-swp-name',ifaces,'ether1'))
    + mtWbFrow('Default VLAN ID', mtWbInp('mt-wb-swp-vlan','1',60))
    + mtWbFrow('VLAN Mode', mtWbSel('mt-wb-swp-mode',['fallback','check','secure','disabled'],'fallback'))
    + '<div style="margin-top:6px;display:flex;gap:4px">'
    + '<button class="mt-wb-btn" onclick="mtApplySwitchPort()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplySwitchPort()">OK</button>'
    + '<button class="mt-wb-btn" onclick="document.getElementById(\'mt-wb-swp-form\').style.display=\'none\'">Cancel</button>'
    + '</div></div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue', onclick:'mtApplySwitchPort()'},{label:'Remove',cls:'red', onclick:'mtRemoveSwitchPort()'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageSwitchVlans() {
  var cols = ['','Switch','VLAN ID','Ports','Invalid'];
  var vlans = MTRS.switchVlans || [];
  var rows = vlans.map(function(v,i){
    return ['<input type="checkbox" data-swvl="'+i+'">',v.sw,v.id,v.ports||'—','no'];
  });
  var wbHtml = mtWbTbar([
      {label:'Add New', onclick:'document.getElementById(\'mt-wb-swvl-form\').style.display=\'block\''},
      {label:'Remove', onclick:'mtRemoveSwitchVlan()'}
    ])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" id="mt-wb-swvl-form" style="margin-top:6px;display:block">'
    + mtWbFrow('Switch', mtWbSel('mt-wb-swvl-sw',['switch1'],'switch1'))
    + mtWbFrow('VLAN ID', mtWbInp('mt-wb-swvl-id','',80))
    + '<div style="margin-top:6px;display:flex;gap:4px">'
    + '<button class="mt-wb-btn" onclick="mtApplySwitchVlan()">Apply</button>'
    + '<button class="mt-wb-btn" onclick="mtApplySwitchVlan()">OK</button>'
    + '<button class="mt-wb-btn" onclick="document.getElementById(\'mt-wb-swvl-form\').style.display=\'none\'">Cancel</button>'
    + '</div></div>';
  var wfHtml = mtWfTbar([
      {label:'Add',cls:'blue', onclick:'document.getElementById(\'mt-wf-swvl-form\').style.display=\'block\''},
      {label:'Remove',cls:'red', onclick:'mtRemoveSwitchVlan()'}
    ])
    + mtWfTable(cols, rows)
    + '<div id="mt-wf-swvl-form" style="margin-top:10px">'
    + mtWfFrow('Switch', mtWfSel('mt-wf-swvl-sw',['switch1'],'switch1'))
    + mtWfFrow('VLAN ID', mtWfInp('mt-wf-swvl-id',''))
    + mtWfTbar([
        {label:'Apply',cls:'blue', onclick:'mtApplySwitchVlan()'},
        {label:'OK',cls:'blue', onclick:'mtApplySwitchVlan()'},
        {label:'Cancel', onclick:'document.getElementById(\'mt-wf-swvl-form\').style.display=\'none\''}
      ])
    + '</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageMesh() {
  var cols = ['','Name','MTU','ARP','Comment'];
  var rows = [];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div style="font-size:11px;color:#808080;padding:4px">Brak sieci Mesh.</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows)
    + '<div style="color:#999;font-size:12px;padding:8px">Brak sieci Mesh.</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageIPArp() {
  var cols = ['','IP Address','MAC Address','Interface','Complete','Dynamic','Published'];
  var rows = [
    ['<input type="checkbox">','192.168.88.1','08:00:27:FB:A2:59','bridge','yes','yes','no'],
    ['<input type="checkbox">','192.168.88.100','AA:BB:CC:DD:EE:01','bridge','yes','yes','no'],
    ['<input type="checkbox">','192.168.88.101','AA:BB:CC:DD:EE:02','bridge','yes','yes','no']
  ];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Flush'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'},{label:'Flush'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageDhcpClient() {
  var cols = ['','Interface','Use Peer DNS','Use Peer NTP','Add Default Route','Status','Comment'];
  var rows = [['<input type="checkbox">','ether1','yes','yes','yes','searching...','defconf']];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Renew'},{label:'Release'}])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" style="margin-top:6px">'
    + '<div style="font-weight:bold;font-size:11px;margin-bottom:4px">DHCP Client</div>'
    + mtWbFrow('Interface', mtWbSel('mt-wb-dhcpc-iface',['ether1','ether2','ether3','wlan1'],'ether1'))
    + mtWbFrow('Use Peer DNS', '<input type="checkbox" class="mt-wb-fchk" checked>')
    + mtWbFrow('Use Peer NTP', '<input type="checkbox" class="mt-wb-fchk" checked>')
    + mtWbFrow('Add Default Route', mtWbSel('mt-wb-dhcpc-route',['yes','no','special-classless'],'yes'))
    + '<div style="margin-top:6px;display:flex;gap:4px"><button class="mt-wb-btn">Apply</button><button class="mt-wb-btn">OK</button><button class="mt-wb-btn">Cancel</button></div>'
    + '</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'},{label:'Renew'},{label:'Release'}])
    + mtWfTable(cols, rows)
    + '<hr class="mt-wf-sep"><div style="font-weight:bold;margin-bottom:8px">DHCP Client</div>'
    + mtWfFrow('Interface', mtWfSel('mt-wf-dhcpc-iface',['ether1','ether2','ether3','wlan1'],'ether1'))
    + mtWfFrow('Use Peer DNS', '<input type="checkbox" checked>')
    + mtWfFrow('Use Peer NTP', '<input type="checkbox" checked>')
    + mtWfFrow('Add Default Route', mtWfSel('mt-wf-dhcpc-route',['yes','no','special-classless'],'yes'))
    + mtWfTbar([{label:'Apply',cls:'blue'},{label:'OK',cls:'blue'},{label:'Cancel'}]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageFwFilter() {
  var cols = ['','Chain','Src. Address','Dst. Address','Protocol','In. Interface','Action','Comment'];
  var rows = [
    ['<input type="checkbox">','input','','','icmp','','accept','accept ping'],
    ['<input type="checkbox">','input','','','','!ether1','accept','accept LAN'],
    ['<input type="checkbox">','input','','','','','drop','drop other'],
    ['<input type="checkbox">','forward','','','','','fasttrack-connection','fasttrack'],
    ['<input type="checkbox">','forward','','','','','accept','accept established'],
    ['<input type="checkbox">','forward','','','','ether1','drop','drop invalid WAN']
  ];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'},{label:'Move Up'},{label:'Move Down'}])
    + '<div class="mt-wb-tbar" style="margin-bottom:2px">'
    + '<label style="font-size:11px;font-family:Tahoma">Chain:&nbsp;</label>'
    + mtWbSel('mt-wb-fwf-chain',['input','output','forward'],'input')
    + '</div>'
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'},{label:'Enable'},{label:'Disable'},{label:'Move Up'},{label:'Move Down'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageFwMangle() {
  var cols = ['','Chain','Src. Address','Dst. Address','Protocol','In. Interface','Action','Comment'];
  var rows = [];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div style="font-size:11px;color:#808080;padding:4px">Brak reguł mangle.</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows)
    + '<div style="color:#999;font-size:12px;padding:8px">Brak reguł mangle.</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageIPSettings() {
  var wbHtml = mtWbFrow('IP Forward', '<input type="checkbox" class="mt-wb-fchk" checked>')
    + mtWbFrow('Allow Fast Path', '<input type="checkbox" class="mt-wb-fchk" checked>')
    + mtWbFrow('Max Neighbor Entries', mtWbInp('mt-wb-ips-mne','8192',80))
    + mtWbFrow('Route Cache', '<input type="checkbox" class="mt-wb-fchk" checked>')
    + mtWbFrow('TCP SYN Cookie', '<input type="checkbox" class="mt-wb-fchk">')
    + mtWbFrow('ARP Timeout', mtWbInp('mt-wb-ips-arp','30m',80))
    + '<hr class="mt-wb-sep-line">'
    + '<div class="mt-wb-tbar"><button class="mt-wb-btn">Apply</button><button class="mt-wb-btn">OK</button><button class="mt-wb-btn">Cancel</button></div>';
  var wfHtml = mtWfFrow('IP Forward', '<input type="checkbox" checked>')
    + mtWfFrow('Allow Fast Path', '<input type="checkbox" checked>')
    + mtWfFrow('Max Neighbor Entries', mtWfInp('mt-wf-ips-mne','8192','80'))
    + mtWfFrow('Route Cache', '<input type="checkbox" checked>')
    + mtWfFrow('TCP SYN Cookie', '<input type="checkbox">')
    + '<hr class="mt-wf-sep">'
    + mtWfTbar([{label:'Apply',cls:'blue'},{label:'OK',cls:'blue'},{label:'Cancel'}]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageIPv6Addresses() {
  var cols = ['','Address','From Pool','Interface','Advertise','EUI-64','Comment'];
  var rows = [['<input type="checkbox">','fe80::1/64','','bridge','yes','no','link-local']];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageIPv6Routes() {
  var cols = ['','Dst. Address','Gateway','Distance','Routing Table','Comment'];
  var rows = [['<input type="checkbox">','::/0','(dynamic)','1','main','']];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageIPv6Firewall() {
  var cols = ['','Chain','Src. Address','Dst. Address','Protocol','Action','Comment'];
  var rows = [
    ['<input type="checkbox">','input','','','icmpv6','accept','accept ICMPv6'],
    ['<input type="checkbox">','input','fe80::/10','','','accept','accept link-local'],
    ['<input type="checkbox">','forward','','','','accept','accept established']
  ];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Comment'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageRoutingOSPF() {
  var cols = ['','Name','Router ID','Redistribute Connected','Redistribute Static','Comment'];
  var rows = [];
  var wbHtml = mtWbTbar([{label:'Instances'},{label:'Areas'},{label:'Neighbors'},{label:'LSAs'},{label:'Interface Templates'}])
    + '<hr class="mt-wb-sep-line">'
    + mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div style="font-size:11px;color:#808080;padding:4px">Brak konfiguracji OSPF.</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows)
    + '<div style="color:#999;font-size:12px;padding:8px">Brak konfiguracji OSPF.</div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageRoutingRIP() {
  var cols = ['','Name','Redistribute Connected','Redistribute Static','Comment'];
  var rows = [];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div style="font-size:11px;color:#808080;padding:4px">Brak konfiguracji RIP.</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageRoutingBGP() {
  var cols = ['','Name','AS','Router ID','Peers','Comment'];
  var rows = [];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div style="font-size:11px;color:#808080;padding:4px">Brak konfiguracji BGP.</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageRoutingFilters() {
  var cols = ['','Name','Comment'];
  var rows = [];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div style="font-size:11px;color:#808080;padding:4px">Brak filtrów routingu.</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageRoutingRules() {
  var cols = ['','Dst. Address','Src. Address','Routing Mark','Action','Table','Comment'];
  var rows = [['<input type="checkbox">','','','','lookup','main','']];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageRoutingTables() {
  var cols = ['','Name','FIB','Comment'];
  var rows = [['','main','yes','main routing table']];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Comment'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageClock() {
  var wbHtml = mtWbFrow('Time', mtWbInp('mt-wb-clock-time','12:00:00',100))
    + mtWbFrow('Date', mtWbInp('mt-wb-clock-date','may/31/2026',130))
    + mtWbFrow('Time Zone', mtWbSel('mt-wb-clock-tz',['UTC','Europe/Warsaw','Europe/London','America/New_York','Asia/Tokyo'],'Europe/Warsaw'))
    + mtWbFrow('Time Zone Autodetect', '<input type="checkbox" class="mt-wb-fchk">')
    + '<hr class="mt-wb-sep-line">'
    + '<div class="mt-wb-tbar"><button class="mt-wb-btn">Apply</button><button class="mt-wb-btn">OK</button><button class="mt-wb-btn">Cancel</button></div>';
  var wfHtml = mtWfFrow('Time', mtWfInp('mt-wf-clock-time','12:00:00','100'))
    + mtWfFrow('Date', mtWfInp('mt-wf-clock-date','may/31/2026','130'))
    + mtWfFrow('Time Zone', mtWfSel('mt-wf-clock-tz',['UTC','Europe/Warsaw','Europe/London','America/New_York','Asia/Tokyo'],'Europe/Warsaw'))
    + mtWfFrow('Time Zone Autodetect', '<input type="checkbox">')
    + '<hr class="mt-wf-sep">'
    + mtWfTbar([{label:'Apply',cls:'blue'},{label:'OK',cls:'blue'},{label:'Cancel'}]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageNTP() {
  var wbHtml = mtWbFrow('Enabled', '<input type="checkbox" class="mt-wb-fchk" checked>')
    + mtWbFrow('Primary NTP Server', mtWbInp('mt-wb-ntp1','0.pool.ntp.org',200))
    + mtWbFrow('Secondary NTP Server', mtWbInp('mt-wb-ntp2','1.pool.ntp.org',200))
    + mtWbFrow('VRF', mtWbSel('mt-wb-ntp-vrf',['main'],'main'))
    + mtWbFrow('Status', '<span style="font-size:11px;font-family:Tahoma;color:#008000">synchronized</span>')
    + '<hr class="mt-wb-sep-line">'
    + '<div class="mt-wb-tbar"><button class="mt-wb-btn">Apply</button><button class="mt-wb-btn">OK</button><button class="mt-wb-btn">Cancel</button></div>';
  var wfHtml = mtWfFrow('Enabled', '<input type="checkbox" checked>')
    + mtWfFrow('Primary NTP Server', mtWfInp('mt-wf-ntp1','0.pool.ntp.org'))
    + mtWfFrow('Secondary NTP Server', mtWfInp('mt-wf-ntp2','1.pool.ntp.org'))
    + mtWfFrow('Status', '<span style="color:#2e7d32;font-weight:bold">synchronized</span>')
    + '<hr class="mt-wf-sep">'
    + mtWfTbar([{label:'Apply',cls:'blue'},{label:'OK',cls:'blue'},{label:'Cancel'}]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageUsers() {
  var cols = ['','Name','Group','Allowed Address','Last Logged In'];
  var rows = [['<input type="checkbox">','admin','full','','just now']];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Comment'}])
    + mtWbTable(cols, rows)
    + '<div class="mt-wb-dialog" style="margin-top:6px">'
    + '<div style="font-weight:bold;font-size:11px;margin-bottom:4px">New User</div>'
    + mtWbFrow('Name', mtWbInp('mt-wb-user-name','',140))
    + mtWbFrow('Password', '<input type="password" class="mt-wb-finp" style="min-width:160px">')
    + mtWbFrow('Confirm Password', '<input type="password" class="mt-wb-finp" style="min-width:160px">')
    + mtWbFrow('Group', mtWbSel('mt-wb-user-grp',['full','write','read'],'full'))
    + mtWbFrow('Allowed Address', mtWbInp('mt-wb-user-addr','',160))
    + '<div style="margin-top:6px;display:flex;gap:4px"><button class="mt-wb-btn">Apply</button><button class="mt-wb-btn">OK</button><button class="mt-wb-btn">Cancel</button></div>'
    + '</div>';
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'}])
    + mtWfTable(cols, rows)
    + '<hr class="mt-wf-sep"><div style="font-weight:bold;margin-bottom:8px">New User</div>'
    + mtWfFrow('Name', mtWfInp('mt-wf-user-name',''))
    + mtWfFrow('Password', '<input type="password" class="mt-wf-finp">')
    + mtWfFrow('Confirm Password', '<input type="password" class="mt-wf-finp">')
    + mtWfFrow('Group', mtWfSel('mt-wf-user-grp',['full','write','read'],'full'))
    + mtWfTbar([{label:'Apply',cls:'blue'},{label:'OK',cls:'blue'},{label:'Cancel'}]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageRouterBoard() {
  var rows = [
    ['routerboard','yes'],['model','RB941-2nD'],['serial-number','B4570BB033D9'],
    ['firmware-type','qca9533L'],['factory-firmware','3.41'],['current-firmware','7.15.3'],
    ['upgrade-firmware','7.15.3']
  ];
  var wbHtml = mtWbTable(['Property','Value'], rows)
    + '<div class="mt-wb-tbar" style="margin-top:8px"><button class="mt-wb-btn">Upgrade Firmware</button></div>';
  var wfHtml = mtWfTable(['Property','Value'], rows)
    + mtWfTbar([{label:'Upgrade Firmware'}]);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPagePackages() {
  var cols = ['','Name','Version','Build Time','Scheduled','Status'];
  var rows = [
    ['<input type="checkbox">','routeros','7.15.3','Aug/29/2024','','installed'],
    ['<input type="checkbox">','wireless','7.15.3','Aug/29/2024','','installed'],
    ['<input type="checkbox">','dhcp','7.15.3','Aug/29/2024','','installed'],
    ['<input type="checkbox">','ppp','7.15.3','Aug/29/2024','','installed'],
    ['<input type="checkbox">','security','7.15.3','Aug/29/2024','','installed'],
    ['<input type="checkbox">','advanced-tools','7.15.3','Aug/29/2024','','installed']
  ];
  var wbHtml = mtWbTbar([{label:'Enable'},{label:'Disable'},{label:'Uninstall'},{label:'Check For Updates'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Enable'},{label:'Disable'},{label:'Check For Updates'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageHealth() {
  var rows = [
    ['voltage','11.8 V'],['temperature','38 C'],['cpu-temperature','41 C'],
    ['board-temperature1','38 C'],['fan1-speed','0 RPM'],['psu1-state','ok']
  ];
  var wbHtml = mtWbTable(['Property','Value'], rows);
  var wfHtml = mtWfTable(['Property','Value'], rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageQueues() {
  var cols = ['','Name','Target','Max Limit','Burst Limit','Priority','Bytes','Packets'];
  var rows = [['<input type="checkbox">','default-small','0.0.0.0/0','10M/10M','0/0','8/8','0 B','0']];
  var wbHtml = mtWbTbar([{label:'Add New'},{label:'Remove'},{label:'Enable'},{label:'Disable'},{label:'Reset Counters'},{label:'Comment'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Add',cls:'blue'},{label:'Remove',cls:'red'},{label:'Enable'},{label:'Disable'},{label:'Reset Counters'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageFiles() {
  var cols = ['Name','Type','Size','Creation Time'];
  var rows = [
    ['backup.backup','backup','56.0 KiB','may/31/2026 12:00:00'],
    ['skins/','directory','',''],
    ['pub/','directory','','']
  ];
  var wbHtml = mtWbTbar([{label:'Backup'},{label:'Restore'},{label:'Remove'},{label:'Download'},{label:'Upload'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Backup'},{label:'Restore'},{label:'Remove',cls:'red'},{label:'Download'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageLog() {
  var cols = ['Time','Topics','Message'];
  var rows = [
    ['12:00:00','system,info','system started'],
    ['12:00:01','dhcp,info','bound 192.168.88.1/24'],
    ['12:00:02','firewall,info','NAT masquerade added'],
    ['12:00:03','wireless,info','wlan1: channel 6 activated'],
    ['12:00:04','system,info','configuration saved']
  ];
  var wbHtml = mtWbTbar([{label:'Clear'},{label:'Settings'}])
    + mtWbTable(cols, rows);
  var wfHtml = mtWfTbar([{label:'Clear'},{label:'Settings'}])
    + mtWfTable(cols, rows);
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageTraceroute() {
  var wbHtml = mtWbFrow('Target', mtWbInp('mt-wb-trace-dst','',160))
    + mtWbFrow('Interface', mtWbSel('mt-wb-trace-iface',['(any)','ether1','bridge','wlan1'],'(any)'))
    + mtWbFrow('Count', mtWbInp('mt-wb-trace-cnt','3',60))
    + mtWbFrow('Size', mtWbInp('mt-wb-trace-size','28',60))
    + mtWbFrow('Max Hops', mtWbInp('mt-wb-trace-hops','30',60))
    + '<div class="mt-wb-tbar" style="margin-top:8px"><button class="mt-wb-btn" onclick="mtRunTrace(false)">Start</button><button class="mt-wb-btn" onclick="mtStopTool(false,\'trace\')">Stop</button></div>'
    + '<div id="mt-trace-result" style="margin-top:8px;font-family:Courier New,monospace;font-size:11px;background:#fff;border:1px solid #999;padding:4px;min-height:80px;color:#000"></div>';
  var wfHtml = mtWfFrow('Target', mtWfInp('mt-wf-trace-dst',''))
    + mtWfFrow('Interface', mtWfSel('mt-wf-trace-iface',['(any)','ether1','bridge','wlan1'],'(any)'))
    + mtWfFrow('Count', mtWfInp('mt-wf-trace-cnt','3','60'))
    + mtWfFrow('Max Hops', mtWfInp('mt-wf-trace-hops','30','60'))
    + mtWfTbar([{label:'Start',cls:'blue',onclick:'mtRunTrace(true)'},{label:'Stop',onclick:'mtStopTool(true,\'trace\')'}])
    + '<div id="mt-wf-trace-result" style="margin-top:10px;font-family:monospace;font-size:12px;background:#f9f9f9;border:1px solid #ddd;border-radius:3px;padding:8px;min-height:80px"></div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageBtest() {
  var wbHtml = mtWbFrow('Target', mtWbInp('mt-wb-btest-dst','',160))
    + mtWbFrow('User', mtWbInp('mt-wb-btest-user','admin',120))
    + mtWbFrow('Password', '<input type="password" class="mt-wb-finp" style="min-width:120px">')
    + mtWbFrow('Direction', mtWbSel('mt-wb-btest-dir',['both','transmit','receive'],'both'))
    + mtWbFrow('Protocol', mtWbSel('mt-wb-btest-proto',['tcp','udp'],'tcp'))
    + mtWbFrow('Duration', mtWbInp('mt-wb-btest-dur','10s',80))
    + '<div class="mt-wb-tbar" style="margin-top:8px"><button class="mt-wb-btn" onclick="mtRunBtest(false)">Start</button><button class="mt-wb-btn" onclick="mtStopTool(false,\'btest\')">Stop</button></div>'
    + '<div id="mt-btest-result" style="margin-top:8px;font-family:Courier New,monospace;font-size:11px;background:#fff;border:1px solid #999;padding:4px;min-height:60px;color:#000"></div>';
  var wfHtml = mtWfFrow('Target', mtWfInp('mt-wf-btest-dst',''))
    + mtWfFrow('User', mtWfInp('mt-wf-btest-user','admin'))
    + mtWfFrow('Password', '<input type="password" class="mt-wf-finp">')
    + mtWfFrow('Direction', mtWfSel('mt-wf-btest-dir',['both','transmit','receive'],'both'))
    + mtWfFrow('Protocol', mtWfSel('mt-wf-btest-proto',['tcp','udp'],'tcp'))
    + mtWfFrow('Duration', mtWfInp('mt-wf-btest-dur','10s','80'))
    + mtWfTbar([{label:'Start',cls:'blue',onclick:'mtRunBtest(true)'},{label:'Stop',onclick:'mtStopTool(true,\'btest\')'}])
    + '<div id="mt-wf-btest-result" style="margin-top:10px;font-family:monospace;font-size:12px;background:#f9f9f9;border:1px solid #ddd;border-radius:3px;padding:8px;min-height:60px"></div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageTorch() {
  var wbHtml = mtWbFrow('Interface', mtWbSel('mt-wb-torch-iface',['ether1','bridge','wlan1'],'ether1'))
    + mtWbFrow('Src. Address', mtWbInp('mt-wb-torch-src','',150))
    + mtWbFrow('Dst. Address', mtWbInp('mt-wb-torch-dst','',150))
    + mtWbFrow('Port', mtWbInp('mt-wb-torch-port','',80))
    + mtWbFrow('Protocol', mtWbSel('mt-wb-torch-proto',['any','tcp','udp','icmp'],'any'))
    + '<div class="mt-wb-tbar" style="margin-top:8px"><button class="mt-wb-btn" onclick="mtRunTorch(false)">Start</button><button class="mt-wb-btn" onclick="mtStopTool(false,\'torch\')">Stop</button></div>'
    + '<div id="mt-torch-result" style="margin-top:8px"></div>';
  var wfHtml = mtWfFrow('Interface', mtWfSel('mt-wf-torch-iface',['ether1','bridge','wlan1'],'ether1'))
    + mtWfFrow('Src. Address', mtWfInp('mt-wf-torch-src',''))
    + mtWfFrow('Dst. Address', mtWfInp('mt-wf-torch-dst',''))
    + mtWfFrow('Port', mtWfInp('mt-wf-torch-port','','80'))
    + mtWfFrow('Protocol', mtWfSel('mt-wf-torch-proto',['any','tcp','udp','icmp'],'any'))
    + mtWfTbar([{label:'Start',cls:'blue',onclick:'mtRunTorch(true)'},{label:'Stop',onclick:'mtStopTool(true,\'torch\')'}])
    + '<div id="mt-wf-torch-result" style="margin-top:10px"></div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtPageFetch() {
  var wbHtml = mtWbFrow('URL', mtWbInp('mt-wb-fetch-url','https://example.com/file.txt',260))
    + mtWbFrow('Mode', mtWbSel('mt-wb-fetch-mode',['https','http','ftp'],'https'))
    + mtWbFrow('Dst. Path', mtWbInp('mt-wb-fetch-dst','file.txt',150))
    + mtWbFrow('Keep Result', '<input type="checkbox" class="mt-wb-fchk" id="mt-wb-fetch-keep" checked>')
    + '<div class="mt-wb-tbar" style="margin-top:8px"><button class="mt-wb-btn" onclick="mtRunFetch(false)">Start</button><button class="mt-wb-btn" onclick="mtStopTool(false,\'fetch\')">Stop</button></div>'
    + '<div id="mt-fetch-result" style="margin-top:8px;font-family:Courier New,monospace;font-size:11px;background:#fff;border:1px solid #999;padding:4px;min-height:52px;color:#000"></div>';
  var wfHtml = mtWfFrow('URL', mtWfInp('mt-wf-fetch-url','https://example.com/file.txt'))
    + mtWfFrow('Mode', mtWfSel('mt-wf-fetch-mode',['https','http','ftp'],'https'))
    + mtWfFrow('Dst. Path', mtWfInp('mt-wf-fetch-dst','file.txt'))
    + mtWfFrow('Keep Result', '<input type="checkbox" checked>')
    + mtWfTbar([{label:'Start',cls:'blue',onclick:'mtRunFetch(true)'},{label:'Stop',onclick:'mtStopTool(true,\'fetch\')'}])
    + '<div id="mt-wf-fetch-result" style="margin-top:10px;font-family:monospace;font-size:12px;background:#f9f9f9;border:1px solid #ddd;border-radius:3px;padding:8px;min-height:52px"></div>';
  return {wb: wbHtml, wf: wfHtml};
}

function mtToolOutput(id, html) {
  var el = document.getElementById(id);
  if (el) el.innerHTML = html;
}

window.mtRunTrace = function(isWf) {
  var target = document.getElementById(isWf ? 'mt-wf-trace-dst' : 'mt-wb-trace-dst');
  var host = (target && target.value.trim()) || '8.8.8.8';
  var safeHost = labEsc(host);
  var rows = [
    ' 1  192.168.88.1  1ms  1ms  1ms',
    ' 2  10.0.0.1      5ms  5ms  6ms',
    ' 3  ' + safeHost + '  18ms 19ms 18ms'
  ];
  mtToolOutput(isWf ? 'mt-wf-trace-result' : 'mt-trace-result', rows.join('<br>'));
  toast('Traceroute zakończony');
};

window.mtRunBtest = function(isWf) {
  var dir = document.getElementById(isWf ? 'mt-wf-btest-dir' : 'mt-wb-btest-dir');
  var proto = document.getElementById(isWf ? 'mt-wf-btest-proto' : 'mt-wb-btest-proto');
  var rows = [
    'status: running',
    'direction: ' + (dir ? dir.value : 'both'),
    'protocol: ' + (proto ? proto.value : 'tcp'),
    'tx-current: 94.7Mbps',
    'rx-current: 91.2Mbps'
  ];
  mtToolOutput(isWf ? 'mt-wf-btest-result' : 'mt-btest-result', rows.join('<br>'));
  toast('Bandwidth Test uruchomiony');
};

window.mtRunTorch = function(isWf) {
  var iface = document.getElementById(isWf ? 'mt-wf-torch-iface' : 'mt-wb-torch-iface');
  var cols = ['Src. Address','Dst. Address','Protocol','Port','Tx Rate','Rx Rate'];
  var rows = [
    ['192.168.88.10','89.123.45.2','tcp','443','1.8Mbps','420kbps'],
    ['192.168.88.20','8.8.8.8','udp','53','12kbps','18kbps'],
    ['192.168.88.1','192.168.88.255','icmp','-','4kbps','4kbps']
  ];
  var html = '<div style="font-family:Arial;font-size:12px;margin-bottom:6px">Interface: ' + labEsc(iface ? iface.value : 'ether1') + '</div>';
  html += isWf ? mtWfTable(cols, rows) : mtWbTable(cols, rows);
  mtToolOutput(isWf ? 'mt-wf-torch-result' : 'mt-torch-result', html);
  toast('Torch pokazuje ruch');
};

window.mtRunFetch = function(isWf) {
  var urlEl = document.getElementById(isWf ? 'mt-wf-fetch-url' : 'mt-wb-fetch-url');
  var dstEl = document.getElementById(isWf ? 'mt-wf-fetch-dst' : 'mt-wb-fetch-dst');
  var url = (urlEl && urlEl.value.trim()) || 'https://example.com/file.txt';
  var dst = (dstEl && dstEl.value.trim()) || 'file.txt';
  mtToolOutput(isWf ? 'mt-wf-fetch-result' : 'mt-fetch-result',
    'status: finished<br>url: ' + labEsc(url) + '<br>dst-path: ' + labEsc(dst) + '<br>downloaded: 12.4KiB');
  toast('Fetch zakończony');
};

window.mtStopTool = function(isWf, tool) {
  var ids = {
    trace: ['mt-trace-result','mt-wf-trace-result'],
    btest: ['mt-btest-result','mt-wf-btest-result'],
    torch: ['mt-torch-result','mt-wf-torch-result'],
    fetch: ['mt-fetch-result','mt-wf-fetch-result']
  };
  var id = (ids[tool] || [])[isWf ? 1 : 0];
  mtToolOutput(id, 'stopped');
  toast('Zatrzymano narzędzie');
};

function mtPageGeneric(pageId) {
  var title = mtPageTitle(pageId);
  var wbHtml = '<div style="color:#808080;font-size:11px;font-family:Tahoma;padding:10px">' + title + ' – brak konfigurowalnych opcji dla tego symulatora.</div>';
  var wfHtml = '<div style="color:#999;font-size:12px;padding:16px">' + title + ' – brak konfigurowalnych opcji dla tego symulatora.</div>';
  return {wb: wbHtml, wf: wfHtml};
}

(function initMikroTik() {
  mtLoad();
  var wbNav = document.getElementById('mt-wb-nav');
  var wfNav = document.getElementById('mt-wf-nav');
  if (wbNav) buildMtWbNav(MT_MENU, wbNav);
  if (wfNav) buildMtWfNav(MT_MENU, wfNav);
  renderMtPage('quickset');
})();
