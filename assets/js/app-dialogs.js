(function () {
  'use strict';

  let pendingForm = null;
  let confirmModal = null;
  let promptModal = null;
  let promptResolve = null;

  function ensureConfirmModal() {
    let el = document.getElementById('appConfirmModal');
    if (!el) {
      el = document.createElement('div');
      el.className = 'modal fade';
      el.id = 'appConfirmModal';
      el.tabIndex = -1;
      el.setAttribute('aria-hidden', 'true');
      el.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0">
              <h5 class="modal-title fw-bold"><i class="bi bi-question-circle text-primary me-2"></i>Potwierdzenie</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body pt-0 text-muted" id="appConfirmText"></div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Wróć</button>
              <button type="button" class="btn btn-primary rounded-pill px-4" id="appConfirmSubmit">Potwierdź</button>
            </div>
          </div>
        </div>`;
      document.body.appendChild(el);
      document.getElementById('appConfirmSubmit')?.addEventListener('click', () => {
        const form = pendingForm;
        pendingForm = null;
        if (confirmModal) confirmModal.hide();
        if (form) HTMLFormElement.prototype.submit.call(form);
      });
    }
    confirmModal = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
    return el;
  }

  function ensurePromptModal() {
    let el = document.getElementById('appPromptModal');
    if (!el) {
      el = document.createElement('div');
      el.className = 'modal fade';
      el.id = 'appPromptModal';
      el.tabIndex = -1;
      el.setAttribute('aria-hidden', 'true');
      el.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0">
              <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Wpisz treść</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body pt-0">
              <label class="form-label fw-semibold" id="appPromptLabel" for="appPromptInput"></label>
              <textarea class="form-control" id="appPromptInput" rows="3" maxlength="500"></textarea>
            </div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Anuluj</button>
              <button type="button" class="btn btn-primary rounded-pill px-4" id="appPromptSubmit">Wyślij</button>
            </div>
          </div>
        </div>`;
      document.body.appendChild(el);
      el.addEventListener('hidden.bs.modal', () => {
        if (promptResolve) promptResolve(null);
        promptResolve = null;
      });
      document.getElementById('appPromptSubmit')?.addEventListener('click', () => {
        const value = document.getElementById('appPromptInput')?.value || '';
        const done = promptResolve;
        promptResolve = null;
        if (promptModal) promptModal.hide();
        if (done) done(value);
      });
    }
    promptModal = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null;
    return el;
  }

  window.appNotice = function appNotice(message, type = 'info') {
    let box = document.getElementById('appNoticeStack');
    if (!box) {
      box = document.createElement('div');
      box.id = 'appNoticeStack';
      box.className = 'position-fixed top-0 start-50 translate-middle-x p-3 d-flex flex-column gap-2 align-items-center';
      box.style.zIndex = '1090';
      box.style.pointerEvents = 'none';
      box.style.maxWidth = '92vw';
      document.body.appendChild(box);
    }
    const safeType = ['success', 'danger', 'warning', 'info', 'primary', 'secondary'].includes(type) ? type : 'info';
    const alertEl = document.createElement('div');
    alertEl.className = `alert alert-${safeType} shadow-lg mb-0 py-2 px-3 rounded-pill d-flex align-items-center gap-2 animate-in`;
    alertEl.setAttribute('role', 'status');
    alertEl.style.pointerEvents = 'auto';
    alertEl.style.transition = 'opacity 0.25s ease, transform 0.25s ease';

    const iconMap = {
      success: 'bi-check-circle-fill',
      danger: 'bi-exclamation-triangle-fill',
      warning: 'bi-exclamation-circle-fill',
      info: 'bi-info-circle-fill',
      primary: 'bi-bell-fill',
      secondary: 'bi-info-circle'
    };
    const iconClass = iconMap[safeType] || 'bi-info-circle-fill';
    alertEl.innerHTML = `<i class="bi ${iconClass} fs-5 flex-shrink-0"></i><span class="small fw-medium"></span>`;
    alertEl.querySelector('span').textContent = String(message || '');

    box.appendChild(alertEl);

    // Auto dismiss after 4200ms with smooth fade out
    window.setTimeout(() => {
      alertEl.style.opacity = '0';
      alertEl.style.transform = 'translateY(-8px)';
      window.setTimeout(() => {
        alertEl.remove();
        if (box.children.length === 0) box.innerHTML = '';
      }, 260);
    }, 4200);
  };

  window.appConfirmSubmit = function appConfirmSubmit(form, message) {
    pendingForm = form || null;
    const el = ensureConfirmModal();
    const text = document.getElementById('appConfirmText');
    if (text) text.textContent = message || 'Potwierdź wykonanie akcji.';
    if (confirmModal) {
      confirmModal.show();
    } else if (pendingForm) {
      const form = pendingForm;
      pendingForm = null;
      HTMLFormElement.prototype.submit.call(form);
    }
    return false;
  };

  window.appPrompt = function appPrompt(message, defaultValue = '') {
    const el = ensurePromptModal();
    const label = document.getElementById('appPromptLabel');
    const input = document.getElementById('appPromptInput');
    if (label) label.textContent = message || '';
    if (input) input.value = defaultValue || '';
    return new Promise((resolve) => {
      promptResolve = resolve;
      if (promptModal) {
        promptModal.show();
        setTimeout(() => input?.focus(), 160);
      } else {
        resolve(defaultValue || '');
      }
    });
  };
})();
