(function () {
  const $ = (id) => document.getElementById(id);
  const blockedWords = ['kurw', 'chuj', 'huj', 'pierd', 'jeb', 'spier', 'wypier', 'cwel', 'dziwk', 'kutas', 'skurw', 'zjeb', 'debil', 'idiot', 'szmata', 'dupa', 'ruchac', 'fuck', 'shit', 'bitch', 'cunt', 'asshole', 'bastard', 'retard', 'whore', 'slut', 'nigg', 'nazi', 'hitler', 'puta', 'puto', 'mierda', 'cabron', 'scheisse', 'arschloch', 'putain', 'merde', 'blyat', 'pidor'];

  function normalizeText(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[@$€0134578!+]/g, (c) => ({ '@': 'a', '$': 's', '€': 'e', '0': 'o', '1': 'i', '!': 'i', '3': 'e', '4': 'a', '5': 's', '7': 't', '+': 't', '8': 'b' }[c] || c))
      .replace(/(.)\1{2,}/g, '$1$1')
      .replace(/[^a-z0-9]+/g, '');
  }

  function containsProfanity(value) {
    const clean = normalizeText(value);
    return blockedWords.some((word) => clean.includes(word));
  }

  function logicValue(gate, a, b) {
    switch (gate) {
      case 'BUFFER': return a;
      case 'NOT': return !a;
      case 'AND': return a && b;
      case 'NAND': return !(a && b);
      case 'OR': return a || b;
      case 'NOR': return !(a || b);
      case 'XOR': return a !== b;
      case 'XNOR': return a === b;
      default: return false;
    }
  }

  function initLogic() {
    const board = $('logicBoard');
    const wireLayer = $('logicWireLayer');
    const truthTable = $('truthTable');
    const hint = $('logicHint');
    if (!board || !wireLayer || !truthTable) return;

    const MAX_NODES = 80;
    const BASE_HEIGHT = 380;
    const BASE_WIDTH = Math.max(820, board.clientWidth || 820);
    const state = { nodes: [], wires: [], nextId: 1, pending: null };
    const inputCounts = { BUFFER: 1, NOT: 1, AND: 2, NAND: 2, OR: 2, NOR: 2, XOR: 2, XNOR: 2, LED: 1, TABLE: 1 };
    const iconMap = {
      INPUT: 'bi-toggle-on',
      CONST1: 'bi-1-circle',
      CONST0: 'bi-0-circle',
      LED: 'bi-lightbulb',
      TABLE: 'bi-table',
      BUFFER: 'bi-arrow-right',
      NOT: 'bi-slash-circle',
      AND: 'bi-intersect',
      NAND: 'bi-intersect',
      OR: 'bi-union',
      NOR: 'bi-union',
      XOR: 'bi-shuffle',
      XNOR: 'bi-shuffle'
    };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    const nodeById = (id) => state.nodes.find((node) => node.id === id);
    const inputCountFor = (type) => inputCounts[type] || 0;
    const setHint = (text, tone = 'muted') => {
      if (!hint) return;
      hint.textContent = text;
      hint.className = `small text-${tone}`;
    };
    const componentFromButton = (button) => {
      if (button.dataset.logicInput) return { type: 'INPUT', label: button.dataset.logicInput };
      if (button.dataset.logicConst) return { type: button.dataset.logicConst === '1' ? 'CONST1' : 'CONST0', label: button.dataset.logicConst };
      if (button.dataset.gate) return { type: button.dataset.gate, label: button.dataset.gate };
      if (button.dataset.output) return { type: button.dataset.output, label: button.dataset.output };
      return null;
    };
    const syncBoardSize = () => {
      const extra = Math.max(0, state.nodes.length - 10);
      const steps = Math.ceil(extra / 10);
      const nextHeight = Math.min(980, BASE_HEIGHT + steps * 90);
      const nextWidth = Math.min(1600, BASE_WIDTH + steps * 120);
      board.style.minHeight = `${nextHeight}px`;
      board.style.width = nextWidth > BASE_WIDTH ? `${nextWidth}px` : '';
      wireLayer.style.width = '100%';
      wireLayer.style.height = '100%';
    };
    const clampPoint = (x, y) => ({
      x: Math.max(0, Math.min(Math.max(0, board.clientWidth - 150), Number.isFinite(x) ? x : 0)),
      y: Math.max(0, Math.min(Math.max(0, board.clientHeight - 92), Number.isFinite(y) ? y : 0))
    });

    const addNode = (type, x, y, label) => {
      if (state.nodes.length >= MAX_NODES) {
        setHint(`Limit układu to ${MAX_NODES} elementów. Usuń coś, aby dodać kolejny komponent.`, 'danger');
        return null;
      }
      syncBoardSize();
      const point = clampPoint(
        Number.isFinite(x) ? x : 80 + ((state.nextId * 53) % Math.max(420, board.clientWidth - 220)),
        Number.isFinite(y) ? y : 60 + ((state.nextId * 41) % Math.max(240, board.clientHeight - 140))
      );
      const id = `n${state.nextId++}`;
      const node = {
        id,
        type,
        label: label || type,
        x: point.x,
        y: point.y,
        value: type === 'CONST1',
        isNew: true
      };
      state.nodes.push(node);
      syncBoardSize();
      render();
      setHint(`Dodano ${label || type}. Elementy możesz przeciągać z listy albo klikać.`);
      return node;
    };

    const addWire = (fromId, toId, inputIndex) => {
      const toNode = nodeById(toId);
      if (fromId === toId || !nodeById(fromId) || !toNode) return;
      const input = Math.max(0, Math.min(inputCountFor(toNode.type) - 1, Number(inputIndex) || 0));
      state.wires = state.wires.filter((wire) => !(wire.to === toId && wire.input === input));
      state.wires.push({ from: fromId, to: toId, input });
      state.pending = null;
      render();
    };

    const evaluateNode = (nodeId, overrides = {}, seen = new Set()) => {
      const node = nodeById(nodeId);
      if (!node || seen.has(nodeId)) return false;
      seen.add(nodeId);
      if (node.type === 'INPUT') return Boolean(overrides[node.label] ?? node.value);
      if (node.type === 'CONST1') return true;
      if (node.type === 'CONST0') return false;
      const values = Array.from({ length: inputCountFor(node.type) }, (_, index) => {
        const wire = state.wires.find((item) => item.to === nodeId && item.input === index);
        return wire ? evaluateNode(wire.from, overrides, new Set(seen)) : false;
      });
      if (node.type === 'LED' || node.type === 'TABLE') return Boolean(values[0]);
      return logicValue(node.type, Boolean(values[0]), Boolean(values[1]));
    };

    const portCenter = (node, selector) => {
      const el = board.querySelector(`[data-node-id="${node.id}"] ${selector}`);
      if (!el) return { x: node.x, y: node.y };
      const boardRect = board.getBoundingClientRect();
      const rect = el.getBoundingClientRect();
      return {
        x: rect.left - boardRect.left + rect.width / 2,
        y: rect.top - boardRect.top + rect.height / 2
      };
    };

    const renderWires = () => {
      const width = Math.max(board.clientWidth, board.scrollWidth, BASE_WIDTH);
      const height = Math.max(board.clientHeight, board.scrollHeight, BASE_HEIGHT);
      wireLayer.setAttribute('viewBox', `0 0 ${width} ${height}`);
      wireLayer.innerHTML = state.wires.map((wire) => {
        const from = nodeById(wire.from);
        const to = nodeById(wire.to);
        if (!from || !to) return '';
        const start = portCenter(from, '.logic-port-out');
        const end = portCenter(to, `.logic-port-in[data-input-index="${wire.input}"]`);
        const mid = Math.max(40, Math.abs(end.x - start.x) / 2);
        const active = evaluateNode(from) ? ' is-active' : '';
        return `<path class="logic-wire-path${active}" d="M ${start.x} ${start.y} C ${start.x + mid} ${start.y}, ${end.x - mid} ${end.y}, ${end.x} ${end.y}" />`;
      }).join('');
    };

    const renderTruthTable = () => {
      const tableNode = state.nodes.find((node) => node.type === 'TABLE') || state.nodes.find((node) => node.type === 'LED');
      if (!tableNode) {
        truthTable.innerHTML = '<tbody><tr><td>Dodaj LED lub tabelę prawdy i połącz układ.</td></tr></tbody>';
        return;
      }
      const labels = [...new Set(state.nodes.filter((node) => node.type === 'INPUT').slice(0, 4).map((node) => node.label))];
      if (labels.length === 0) {
        truthTable.innerHTML = '<tbody><tr><td>Dodaj przynajmniej jedno wejście.</td></tr></tbody>';
        return;
      }
      const rows = Array.from({ length: 2 ** labels.length }, (_, row) => {
        const overrides = {};
        labels.forEach((label, index) => {
          overrides[label] = Boolean((row >> (labels.length - index - 1)) & 1);
        });
        return { overrides, out: evaluateNode(tableNode.id, overrides) ? 1 : 0 };
      });
      truthTable.innerHTML = `<thead><tr>${labels.map((label) => `<th>${escapeHtml(label)}</th>`).join('')}<th>Y</th></tr></thead><tbody>${rows.map((row) => `<tr>${labels.map((label) => `<td>${row.overrides[label] ? 1 : 0}</td>`).join('')}<td><strong>${row.out}</strong></td></tr>`).join('')}</tbody>`;
    };

    const exportLogicPdf = () => {
      render();
      requestAnimationFrame(() => {
        const boardClone = board.cloneNode(true);
        boardClone.removeAttribute('id');
        boardClone.style.width = `${Math.max(board.clientWidth, board.scrollWidth, BASE_WIDTH)}px`;
        boardClone.style.minHeight = `${Math.max(board.clientHeight, board.scrollHeight, BASE_HEIGHT)}px`;
        const exportedAt = new Date().toLocaleString('pl-PL');
        const popup = window.open('', '_blank', 'width=1200,height=900');
        if (!popup) {
          setHint('Przeglądarka zablokowała okno PDF. Zezwól na wyskakujące okna.', 'danger');
          return;
        }
        popup.document.write(`<!doctype html><html lang="pl"><head><meta charset="utf-8"><title>Układ logiczny - ZSEM Tech</title>
          <style>
            @page { size: A4 landscape; margin: 12mm; }
            body { font-family: Inter, Segoe UI, Arial, sans-serif; color: #0f172a; margin: 0; }
            .pdf-brand { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; border-bottom:3px solid #2563eb; padding-bottom:12px; margin-bottom:16px; }
            .pdf-brand strong { display:block; font-size:22pt; letter-spacing:.2px; color:#1d4ed8; }
            .pdf-brand span { display:block; margin-top:4px; color:#475569; font-size:10pt; }
            .pdf-meta { text-align:right; color:#64748b; font-size:9pt; line-height:1.5; }
            h1 { font-size: 18pt; margin: 0 0 10px; }
            h2 { font-size: 13pt; margin: 16px 0 8px; }
            .logic-canvas { position: relative; min-height: 380px; overflow: hidden; border: 1px solid #cbd5e1; border-radius: 10px; background: #f8fafc; }
            .logic-wire-layer { position: absolute; inset: 0; width: 100%; height: 100%; pointer-events: none; z-index: 1; }
            .logic-wire-path { fill: none; stroke: #64748b; stroke-width: 4; stroke-linecap: round; }
            .logic-wire-path.is-active { stroke: #16a34a; }
            .logic-node { position: absolute; min-width: 126px; padding: 10px; border-radius: 8px; border: 2px solid #cbd5e1; background: #fff; font-weight: 800; text-align: center; z-index: 2; box-shadow: 0 8px 20px rgba(15,23,42,.08); }
            .logic-node-header { display: flex; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
            .logic-node-delete, .logic-port { display: none !important; }
            .logic-led { display: inline-block; width: 26px; height: 26px; border-radius: 50%; background: #cbd5e1; border: 4px solid #e2e8f0; vertical-align: middle; margin-right: 8px; }
            .logic-led.is-on { background: #22c55e; box-shadow: 0 0 18px rgba(34,197,94,.8); }
            .logic-switch { border: 1px solid #cbd5e1; border-radius: 999px; min-width: 42px; padding: 6px 12px; font-weight: 900; background: #e2e8f0; }
            .logic-switch.is-on { background: #22c55e; color: #fff; border-color: #16a34a; }
            table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 10pt; }
            th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; }
            th { background: #eff6ff; }
          </style></head><body>
            <header class="pdf-brand">
              <div><strong>ZSEM Tech</strong><span>Eksport z symulatora bramek logicznych</span></div>
              <div class="pdf-meta">Układ logiczny<br>Wygenerowano: ${escapeHtml(exportedAt)}</div>
            </header>
            <h1>Schemat układu</h1>${boardClone.outerHTML}
            <h2>Tabela prawdy</h2>${truthTable.outerHTML}
          </body></html>`);
        popup.document.close();
        popup.focus();
        setTimeout(() => popup.print(), 250);
      });
    };

    const render = () => {
      syncBoardSize();
      board.querySelectorAll('.logic-node').forEach((node) => node.remove());
      state.nodes.forEach((node) => {
        const outputValue = evaluateNode(node.id) ? 1 : 0;
        const inputPorts = Array.from({ length: inputCountFor(node.type) }, (_, index) => `<button type="button" class="logic-port logic-port-in" data-node-id="${node.id}" data-input-index="${index}" title="Wejście ${index + 1}"></button>`).join('');
        const hasOutput = !['LED', 'TABLE'].includes(node.type);
        const el = document.createElement('div');
        el.className = `logic-node logic-node-dynamic ${node.isNew ? 'is-new' : ''} ${node.type === 'INPUT' ? 'input-node' : ''} ${['LED', 'TABLE'].includes(node.type) ? 'output-node' : 'gate-node'}`;
        el.dataset.nodeId = node.id;
        el.style.left = `${node.x}px`;
        el.style.top = `${node.y}px`;
        el.innerHTML = `
          <div class="logic-node-header" data-drag-handle>
            <strong><i class="bi ${iconMap[node.type] || 'bi-cpu'}"></i>${escapeHtml(node.label)}</strong>
            <button type="button" class="logic-node-delete" data-delete-node="${node.id}" aria-label="Usuń">×</button>
          </div>
          <div class="logic-node-body">
            <div class="logic-ports-in">${inputPorts}</div>
            ${node.type === 'INPUT' ? `<button type="button" class="logic-switch ${node.value ? 'is-on' : ''}" data-toggle-input="${node.id}">${node.value ? '1' : '0'}</button>` : ''}
            ${node.type === 'LED' ? `<span class="logic-led ${outputValue ? 'is-on' : ''}"></span><strong>${outputValue}</strong>` : ''}
            ${node.type === 'TABLE' ? `<span class="small text-muted">Y = ${outputValue}</span>` : ''}
            ${!['INPUT', 'LED', 'TABLE'].includes(node.type) ? `<span class="logic-gate-label">${escapeHtml(node.type)}</span>` : ''}
            ${hasOutput ? `<button type="button" class="logic-port logic-port-out ${outputValue ? 'is-on' : ''} ${state.pending === node.id ? 'is-pending' : ''}" data-node-id="${node.id}" title="Wyjście"></button>` : ''}
          </div>`;
        board.appendChild(el);
        node.isNew = false;
      });
      requestAnimationFrame(() => {
        renderWires();
        renderTruthTable();
      });
    };

    const seedDemo = () => {
      state.nodes = [];
      state.wires = [];
      state.pending = null;
      state.nextId = 1;
      const a = addNode('INPUT', 40, 74, 'A');
      const b = addNode('INPUT', 40, 210, 'B');
      const gate = addNode('AND', 330, 142, 'AND');
      const led = addNode('LED', 640, 142, 'LED');
      if (!a || !b || !gate || !led) return;
      state.wires = [
        { from: a.id, to: gate.id, input: 0 },
        { from: b.id, to: gate.id, input: 1 },
        { from: gate.id, to: led.id, input: 0 }
      ];
      setHint('Kliknij wyjście, potem wejście. Możesz też przeciągać komponenty z listy.');
      render();
    };

    board.addEventListener('click', (event) => {
      const outPort = event.target.closest('.logic-port-out');
      const inPort = event.target.closest('.logic-port-in');
      const toggle = event.target.closest('[data-toggle-input]');
      const remove = event.target.closest('[data-delete-node]');
      if (remove) {
        const id = remove.dataset.deleteNode;
        state.nodes = state.nodes.filter((node) => node.id !== id);
        state.wires = state.wires.filter((wire) => wire.from !== id && wire.to !== id);
        if (state.pending === id) state.pending = null;
        render();
        return;
      }
      if (toggle) {
        const node = nodeById(toggle.dataset.toggleInput);
        if (node) node.value = !node.value;
        render();
        return;
      }
      if (outPort) {
        state.pending = outPort.dataset.nodeId;
        render();
        return;
      }
      if (inPort && state.pending) {
        addWire(state.pending, inPort.dataset.nodeId, Number(inPort.dataset.inputIndex) || 0);
      }
    });

    board.addEventListener('pointerdown', (event) => {
      if (event.target.closest('[data-delete-node]')) return;
      const handle = event.target.closest('[data-drag-handle]');
      if (!handle) return;
      const el = handle.closest('.logic-node');
      const node = nodeById(el?.dataset.nodeId);
      if (!node) return;
      event.preventDefault();
      const startX = event.clientX;
      const startY = event.clientY;
      const originX = node.x;
      const originY = node.y;
      el.classList.add('is-dragging');
      el.setPointerCapture(event.pointerId);
      const move = (moveEvent) => {
        const point = clampPoint(originX + moveEvent.clientX - startX, originY + moveEvent.clientY - startY);
        node.x = point.x;
        node.y = point.y;
        el.style.left = `${node.x}px`;
        el.style.top = `${node.y}px`;
        renderWires();
      };
      const up = () => {
        el.classList.remove('is-dragging');
        el.removeEventListener('pointermove', move);
        el.removeEventListener('pointerup', up);
        el.removeEventListener('pointercancel', up);
        render();
      };
      el.addEventListener('pointermove', move);
      el.addEventListener('pointerup', up);
      el.addEventListener('pointercancel', up);
    });

    board.addEventListener('dragover', (event) => {
      if (![...event.dataTransfer.types].includes('application/x-logic-component')) return;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'copy';
      board.classList.add('is-drop-target');
    });
    board.addEventListener('dragleave', (event) => {
      if (!board.contains(event.relatedTarget)) board.classList.remove('is-drop-target');
    });
    board.addEventListener('drop', (event) => {
      const raw = event.dataTransfer.getData('application/x-logic-component');
      if (!raw) return;
      event.preventDefault();
      board.classList.remove('is-drop-target');
      try {
        const component = JSON.parse(raw);
        const rect = board.getBoundingClientRect();
        addNode(component.type, event.clientX - rect.left - 70, event.clientY - rect.top - 38, component.label);
      } catch (error) {
        setHint('Nie udało się dodać komponentu.', 'danger');
      }
    });

    document.querySelectorAll('[data-logic-input], [data-logic-const], [data-gate], [data-output]').forEach((button) => {
      button.draggable = true;
      button.addEventListener('click', () => {
        const component = componentFromButton(button);
        if (component) addNode(component.type, undefined, undefined, component.label);
      });
      button.addEventListener('dragstart', (event) => {
        const component = componentFromButton(button);
        if (!component) return;
        event.dataTransfer.effectAllowed = 'copy';
        event.dataTransfer.setData('application/x-logic-component', JSON.stringify(component));
      });
    });
    $('logicReset')?.addEventListener('click', () => {
      state.nodes = [];
      state.wires = [];
      state.pending = null;
      state.nextId = 1;
      setHint('Układ wyczyszczony. Dodaj komponenty kliknięciem albo przeciągnięciem.');
      render();
    });
    $('logicDemo')?.addEventListener('click', seedDemo);
    $('logicExportPdf')?.addEventListener('click', exportLogicPdf);
    window.addEventListener('resize', () => {
      syncBoardSize();
      renderWires();
    });
    seedDemo();
  }

  function initPsu() {
    if (!$('psuRecommended')) return;
    const ids = ['psuCpuTdp', 'psuGpuTbp', 'psuBoard', 'psuDriveCount', 'psuFanCount', 'psuExtra', 'psuHeadroom', 'psuEfficiency'];
    const sync = () => {
      const cpu = Number($('psuCpuTdp').value) || 0;
      const gpu = Number($('psuGpuTbp').value) || 0;
      const board = Number($('psuBoard').value) || 0;
      const drives = Math.max(0, Number($('psuDriveCount').value) || 0) * 8;
      const fans = Math.max(0, Number($('psuFanCount').value) || 0) * 3;
      const extra = Number($('psuExtra').value) || 0;
      const headroom = Math.max(10, Math.min(80, Number($('psuHeadroom').value) || 30));
      const efficiency = Math.max(70, Math.min(94, Number($('psuEfficiency').value) || 85));
      const load = cpu + gpu + board + drives + fans + extra;
      const recommended = Math.ceil((load * (1 + headroom / 100)) / 50) * 50;
      const wallPower = Math.round(load / (efficiency / 100));
      const psuLoad = Math.round(load / Math.max(1, recommended) * 100);
      $('psuRecommended').textContent = `${recommended} W`;
      $('psuDetails').textContent = `Podzespoły: ${load} W. Pobór z gniazdka przy ${efficiency}%: ok. ${wallPower} W. Obciążenie PSU: ok. ${psuLoad}%.`;
    };
    ids.forEach((id) => $(id).addEventListener('input', sync));
    sync();
  }

  function ipToInt(ip) {
    const parts = ip.split('.').map(Number);
    if (parts.length !== 4 || parts.some((n) => !Number.isInteger(n) || n < 0 || n > 255)) return null;
    return parts.reduce((n, p) => ((n << 8) + p) >>> 0, 0);
  }

  function intToIp(n) {
    return [24, 16, 8, 0].map((s) => (n >>> s) & 255).join('.');
  }

  function initSubnet() {
    if (!$('ipv4Out')) return;
    const sync4 = () => {
      const ip = ipToInt($('ipv4Input').value.trim());
      const cidr = Math.max(1, Math.min(32, Number($('ipv4Cidr').value) || 24));
      if (ip === null) {
        $('ipv4Out').innerHTML = '<div><span>Błąd</span>Niepoprawny adres IPv4</div>';
        return;
      }
      const mask = cidr === 0 ? 0 : (0xffffffff << (32 - cidr)) >>> 0;
      const network = ip & mask;
      const broadcast = (network | (~mask >>> 0)) >>> 0;
      const hosts = cidr >= 31 ? (cidr === 31 ? 2 : 1) : Math.max(0, 2 ** (32 - cidr) - 2);
      $('ipv4Out').innerHTML = [
        ['Maska', intToIp(mask)],
        ['Sieć', intToIp(network)],
        ['Broadcast', intToIp(broadcast)],
        ['Pierwszy host', cidr >= 31 ? intToIp(network) : intToIp(network + 1)],
        ['Ostatni host', cidr >= 31 ? intToIp(broadcast) : intToIp(broadcast - 1)],
        ['Hosty', hosts.toLocaleString('pl-PL')]
      ].map(([k, v]) => `<div><span>${k}</span><strong>${v}</strong></div>`).join('');
    };
    const sync6 = () => {
      const value = $('ipv6Input').value.trim();
      const prefix = Math.max(1, Math.min(128, Number($('ipv6Prefix').value) || 64));
      const ok = /^[0-9a-f:]+$/i.test(value) && value.includes(':');
      const networkHint = ok ? `${value.split('::')[0].replace(/:+$/, '') || '::'}/${prefix}` : 'Niepoprawny IPv6';
      const hosts = prefix <= 64 ? '2^(128-' + prefix + ') adresów' : Math.pow(2, Math.min(32, 128 - prefix)).toLocaleString('pl-PL') + '+ adresów';
      $('ipv6Out').innerHTML = [
        ['Prefiks', `/${prefix}`],
        ['Sieć', networkHint],
        ['Część hosta', `${128 - prefix} bitów`],
        ['Pula', ok ? hosts : '—']
      ].map(([k, v]) => `<div><span>${k}</span><strong>${v}</strong></div>`).join('');
    };
    ['ipv4Input', 'ipv4Cidr'].forEach((id) => $(id).addEventListener('input', sync4));
    ['ipv6Input', 'ipv6Prefix'].forEach((id) => $(id).addEventListener('input', sync6));
    sync4();
    sync6();
  }

  function parseByBase(value, base) {
    const clean = String(value).trim().replace(/^0x/i, '');
    const parsed = parseInt(clean, base);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function initNumbers() {
    if (!$('numOut')) return;
    const syncNum = () => {
      const value = parseByBase($('numInput').value, Number($('numBase').value));
      if (value === null) {
        $('numOut').innerHTML = '<div><span>Błąd</span>Nie można przeliczyć wartości</div>';
        return;
      }
      const signed8 = value & 0xff;
      const u2 = signed8 > 127 ? signed8 - 256 : signed8;
      $('numOut').innerHTML = [
        ['BIN', value.toString(2)],
        ['OCT', value.toString(8)],
        ['DEC', value.toString(10)],
        ['HEX', value.toString(16).toUpperCase()],
        ['8-bit', signed8.toString(2).padStart(8, '0')],
        ['U2 8-bit', String(u2)]
      ].map(([k, v]) => `<div><span>${k}</span><strong>${v}</strong></div>`).join('');
    };
    const syncBit = () => {
      const a = parseByBase($('bitA').value, 10) ?? 0;
      const b = parseByBase($('bitB').value, 10) ?? 0;
      const op = $('bitOp').value;
      let out = 0;
      if (op === 'AND') out = a & b;
      if (op === 'OR') out = a | b;
      if (op === 'XOR') out = a ^ b;
      if (op === 'SHL') out = a << Math.max(0, Math.min(16, b));
      if (op === 'SHR') out = a >>> Math.max(0, Math.min(16, b));
      $('bitOut').innerHTML = [
        ['DEC', out.toString(10)],
        ['BIN', (out >>> 0).toString(2)],
        ['HEX', (out >>> 0).toString(16).toUpperCase()]
      ].map(([k, v]) => `<div><span>${k}</span><strong>${v}</strong></div>`).join('');
    };
    ['numInput', 'numBase'].forEach((id) => $(id).addEventListener('input', syncNum));
    ['bitA', 'bitB', 'bitOp'].forEach((id) => $(id).addEventListener('input', syncBit));
    syncNum();
    syncBit();
  }

  function initLive() {
    if (!$('runCode')) return;
    const warning = $('codeWarning');
    const draftKey = 'zsem.sandbox.liveDraft.v1';
    const fields = ['htmlCode', 'cssCode', 'jsCode'];
    const demo = {
      html: '<main class="card">\n  <h1>Live demo</h1>\n  <p>Ten projekt zostaje po odświeżeniu, ale znika po zmianie narzędzia.</p>\n  <button id="btn">Zmień kolor</button>\n</main>',
      css: 'body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: Inter, system-ui, sans-serif; background: #eef2ff; }\n.card { width: min(420px, 92vw); padding: 28px; border-radius: 14px; background: white; box-shadow: 0 20px 60px rgba(15,23,42,.14); }\nbutton { border: 0; border-radius: 999px; padding: 10px 16px; background: #1d4ed8; color: white; font-weight: 800; }',
      js: "const colors = ['#1d4ed8', '#dc2626', '#16a34a', '#7c3aed'];\nlet index = 0;\ndocument.getElementById('btn').onclick = () => {\n  index = (index + 1) % colors.length;\n  document.body.style.background = colors[index] + '22';\n};"
    };
    const saveDraft = () => {
      const payload = {
        html: $('htmlCode').value,
        css: $('cssCode').value,
        js: $('jsCode').value
      };
      sessionStorage.setItem(draftKey, JSON.stringify(payload));
    };
    const restoreDraft = () => {
      try {
        const payload = JSON.parse(sessionStorage.getItem(draftKey) || 'null');
        if (!payload) return;
        if (typeof payload.html === 'string') $('htmlCode').value = payload.html;
        if (typeof payload.css === 'string') $('cssCode').value = payload.css;
        if (typeof payload.js === 'string') $('jsCode').value = payload.js;
      } catch (_) {}
    };
    const clearDraft = () => sessionStorage.removeItem(draftKey);
    document.querySelectorAll('.sandbox-tabs a, .sandbox-tool-tile').forEach((link) => {
      link.addEventListener('click', () => {
        if (!String(link.getAttribute('href') || '').includes('tool=live')) clearDraft();
      });
    });
    restoreDraft();
    const run = () => {
      const html = $('htmlCode').value;
      const css = $('cssCode').value;
      const js = $('jsCode').value;
      if ([html, css, js].some(containsProfanity)) {
        warning.textContent = 'Kod zawiera niedozwolone słowa. Usuń je przed uruchomieniem podglądu.';
        warning.classList.remove('d-none');
        return;
      }
      warning.classList.add('d-none');
      saveDraft();
      $('codePreview').srcdoc = `<!doctype html><html><head><meta charset="utf-8"><style>${css.replace(/<\/style>/gi, '<\\/style>')}</style></head><body>${html}<script>${js.replace(/<\/script>/gi, '<\\/script>')}<\/script></body></html>`;
    };
    $('runCode').addEventListener('click', run);
    let liveTimer = null;
    fields.forEach((id) => $(id).addEventListener('input', () => {
      clearTimeout(liveTimer);
      liveTimer = setTimeout(run, 180);
    }));
    $('liveDemo')?.addEventListener('click', () => {
      $('htmlCode').value = demo.html;
      $('cssCode').value = demo.css;
      $('jsCode').value = demo.js;
      run();
    });
    $('clearCode').addEventListener('click', () => {
      fields.forEach((id) => { $(id).value = ''; });
      clearDraft();
      run();
    });
    run();
  }

  function initOhm() {
    if (!$('ohmOut')) return;
    const preferred = [10, 12, 15, 18, 22, 27, 33, 39, 47, 56, 68, 82, 100, 120, 150, 180, 220, 270, 330, 390, 470, 560, 680, 820, 1000, 1200, 1500, 1800, 2200, 2700, 3300, 3900, 4700, 5600, 6800, 8200, 10000];
    const fmt = (value, unit) => `${Number(value).toLocaleString('pl-PL', { maximumFractionDigits: 3 })} ${unit}`;
    const nearest = (value) => preferred.reduce((best, current) => Math.abs(current - value) < Math.abs(best - value) ? current : best, preferred[0]);
    const sync = () => {
      const v = Number($('ohmVoltage').value);
      const i = Number($('ohmCurrent').value);
      const r = Number($('ohmResistance').value);
      const resistance = r > 0 ? r : (i > 0 ? v / i : 0);
      const current = i > 0 ? i : (resistance > 0 ? v / resistance : 0);
      const power = v * current;
      $('ohmOut').innerHTML = [
        ['Opór', resistance > 0 ? fmt(resistance, 'Ω') : '—'],
        ['Prąd', current > 0 ? fmt(current, 'A') : '—'],
        ['Moc', power > 0 ? fmt(power, 'W') : '—']
      ].map(([k, val]) => `<div><span>${k}</span><strong>${val}</strong></div>`).join('');

      const supply = Number($('ledSupply').value) || 0;
      const forward = Number($('ledForward').value) || 0;
      const ledCurrent = Math.max(1, Number($('ledCurrent').value) || 20) / 1000;
      const raw = Math.max(0, (supply - forward) / ledCurrent);
      const chosen = nearest(raw);
      const ledPower = (supply - forward) * ledCurrent;
      $('ledOut').innerHTML = [
        ['Wyliczony opór', raw > 0 ? fmt(raw, 'Ω') : '—'],
        ['Najbliższy E12', raw > 0 ? fmt(chosen, 'Ω') : '—'],
        ['Moc rezystora', ledPower > 0 ? `${fmt(ledPower, 'W')} (dobierz min. ${(ledPower * 2).toFixed(2)} W)` : '—']
      ].map(([k, val]) => `<div><span>${k}</span><strong>${val}</strong></div>`).join('');
    };
    ['ohmVoltage', 'ohmCurrent', 'ohmResistance', 'ledSupply', 'ledForward', 'ledCurrent'].forEach((id) => $(id).addEventListener('input', sync));
    sync();
  }

  document.addEventListener('DOMContentLoaded', () => {
    initLogic();
    initPsu();
    initSubnet();
    initNumbers();
    initOhm();
    initLive();
  });
}());
