document.addEventListener('DOMContentLoaded', () => {
  const passInput = document.getElementById('regPassword');
  const bar = document.getElementById('strengthBar');
  const usernameInput = document.getElementById('regUsername');
  const usernameFeedback = document.getElementById('usernameFeedback');
  const emailInput = document.getElementById('email');
  const emailFeedback = document.getElementById('emailFeedback');
  const classSuffix = document.getElementById('classSuffix');
  const applyTeacher = document.getElementById('applyTeacher');
  const teacherWrap = document.getElementById('teacherMotivationWrap');
  const teacherMotivation = document.getElementById('teacherMotivation');
  const teacherMotivationHelp = document.getElementById('teacherMotivationHelp');
  const passwordPolicy = document.getElementById('passwordPolicy');
  const passwordPolicyMessage = document.getElementById('passwordPolicyMessage');

  if (passInput && bar) {
    const syncPasswordRules = () => {
      const val = passInput.value;
      const requiredChecks = [
        { ok: val.length >= 6, text: 'minimum 6 znaków' },
        { ok: /[a-z]/.test(val), text: 'mała litera' },
        { ok: /[A-Z]/.test(val), text: 'wielka litera' },
        { ok: /[0-9]/.test(val), text: 'cyfra' }
      ];
      const bonusCheck = { ok: /[^A-Za-z0-9]/.test(val), text: 'znak specjalny' };
      const checks = [...requiredChecks, bonusCheck];
      let score = 0;
      checks.forEach((rule) => { if (rule.ok) score++; });
      const missing = requiredChecks.filter((rule) => !rule.ok).map((rule) => rule.text);
      const colors = ['#ef4444', '#f97316', '#f59e0b', '#2563eb', '#10b981'];
      bar.style.width = `${Math.min(score, 5) * 20}%`;
      bar.style.backgroundColor = colors[score - 1] || 'transparent';
      bar.parentElement?.setAttribute('aria-valuenow', String(score));
      if (passwordPolicy && passwordPolicyMessage) {
        passwordPolicy.classList.toggle('is-complete', missing.length === 0 && val.length > 0);
        passwordPolicy.classList.toggle('is-empty', val.length === 0);
        passwordPolicyMessage.textContent = val.length === 0
          ? 'Wpisz hasło, aby sprawdzić wymagania.'
          : (missing.length ? `Brakuje: ${missing.join(', ')}.` : (bonusCheck.ok ? 'Hasło spełnia wszystkie wymagania.' : 'Znak specjalny zwiększa siłę hasła, ale nie jest wymagany.'));
      }
    };
    passInput.addEventListener('input', syncPasswordRules);
    syncPasswordRules();
  }

  const renderUsernameSuggestions = (target, suggestions = []) => {
    const clean = Array.isArray(suggestions) ? suggestions.filter(Boolean).slice(0, 2) : [];
    if (!target || clean.length === 0) return;
    const wrap = document.createElement('div');
    wrap.className = 'username-suggestions d-flex flex-wrap align-items-center gap-2 mt-2';
    const label = document.createElement('span');
    label.className = 'text-muted';
    label.textContent = 'Propozycje:';
    wrap.appendChild(label);
    suggestions.forEach((suggestion) => {
      if (!clean.includes(suggestion)) return;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn-sm btn-outline-primary py-0 px-2';
      button.textContent = suggestion;
      button.addEventListener('click', () => {
        usernameInput.value = suggestion;
        usernameInput.dispatchEvent(new Event('input', { bubbles: true }));
        usernameInput.focus();
      });
      wrap.appendChild(button);
    });
    target.appendChild(wrap);
  };

  const checkAvailability = (() => {
    const timers = new Map();
    return (type, value, target, localOk = true, localMessage = '') => {
      clearTimeout(timers.get(type));
      if (!target) return;
      if (!value) {
        target.textContent = type === 'username' ? 'Puste pole = losowy prywatny login.' : '';
        target.className = 'small mt-1 text-muted';
        return;
      }
      if (!localOk) {
        target.textContent = localMessage;
        target.className = 'small mt-1 feedback-error';
        return;
      }
      target.textContent = 'Sprawdzam dostępność...';
      target.className = 'small mt-1 text-muted';
      timers.set(type, setTimeout(async () => {
        try {
          const res = await fetch(`ajax/check_registration_availability.php?type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
          const data = await res.json();
          target.textContent = data.message || 'Nie udało się sprawdzić dostępności.';
          target.className = data.ok && data.available ? 'small mt-1 feedback-ok' : 'small mt-1 feedback-error';
          if (type === 'username' && data.ok && !data.available) {
            renderUsernameSuggestions(target, data.suggestions || []);
          }
        } catch (_) {
          target.textContent = 'Nie udało się sprawdzić dostępności.';
          target.className = 'small mt-1 feedback-error';
        }
      }, 260));
    };
  })();

  if (usernameInput && usernameFeedback) {
    usernameInput.addEventListener('input', () => {
      const username = usernameInput.value.trim();
      const ok = /^[A-Za-z0-9_.-]{3,16}$/.test(username);
      checkAvailability('username', username, usernameFeedback, ok, 'Login: 3-16 znaków, litery, cyfry, kropka, myślnik lub podkreślenie.');
    });
    usernameInput.dispatchEvent(new Event('input'));
  }

  if (emailInput && emailFeedback) {
    const acceptedDomains = ['gmail.com', 'interia.pl', 'outlook.com', 'hotmail.com', 'live.com', 'msn.com', 'wp.pl', 'o2.pl', 'op.pl', 'onet.pl', 'int.pl', 'yahoo.com', 'icloud.com', 'me.com', 'mac.com', 'proton.me', 'protonmail.com', 'mail.com', 'zsem.edu.pl'];
    emailInput.addEventListener('input', () => {
      const parts = emailInput.value.trim().toLowerCase().split('@');
      if (parts.length !== 2 || !parts[1]) {
        emailFeedback.textContent = '';
        emailFeedback.className = 'small mt-1';
        return;
      }
      const ok = acceptedDomains.includes(parts[1]);
      checkAvailability('email', emailInput.value.trim(), emailFeedback, ok, 'Ta domena e-mail nie jest obsługiwana. Użyj innego adresu.');
    });
    emailInput.dispatchEvent(new Event('input'));
  }

  if (classSuffix) {
    classSuffix.addEventListener('input', () => {
      classSuffix.value = classSuffix.value.replace(/[^a-zA-Z]/g, '').slice(0, 2).toUpperCase();
    });
  }

  if (applyTeacher && teacherWrap && teacherMotivation) {
    const wordRegex = /[\p{L}\p{N}_-]+/gu;
    const syncTeacherMotivation = () => {
      teacherWrap.classList.toggle('d-none', !applyTeacher.checked);
      const words = teacherMotivation.value.match(wordRegex) || [];
      const longWord = words.find((word) => word.length > 20);
      if (words.length > 100) {
        teacherMotivation.value = words.slice(0, 100).join(' ');
      }
      const nextWords = teacherMotivation.value.match(wordRegex) || [];
      const hasLongWord = nextWords.some((word) => word.length > 20) || !!longWord;
      if (teacherMotivationHelp) {
        teacherMotivationHelp.textContent = `${nextWords.length}/100 słów. Każde słowo maksymalnie 20 znaków.`;
        teacherMotivationHelp.className = hasLongWord ? 'small mt-1 feedback-error' : 'small text-muted mt-1';
      }
    };
    applyTeacher.addEventListener('change', syncTeacherMotivation);
    teacherMotivation.addEventListener('input', syncTeacherMotivation);
    syncTeacherMotivation();
  }
});
