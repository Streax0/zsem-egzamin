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

  if (passInput && bar) {
    passInput.addEventListener('input', () => {
      const val = passInput.value;
      let score = 0;
      if (val.length >= 10) score++;
      if (/[a-z]/.test(val)) score++;
      if (/[A-Z]/.test(val)) score++;
      if (/[0-9]/.test(val)) score++;
      if (/[^A-Za-z0-9]/.test(val)) score++;

      const colors = ['#ef4444', '#ef4444', '#f59e0b', '#2563eb', '#10b981'];
      bar.style.width = `${Math.min(score, 5) * 20}%`;
      bar.style.backgroundColor = colors[score - 1] || 'transparent';
    });
  }

  if (usernameInput && usernameFeedback) {
    usernameInput.addEventListener('input', () => {
      const username = usernameInput.value.trim();
      if (!username) {
        usernameFeedback.textContent = '';
        usernameFeedback.className = 'small mt-1';
        return;
      }
      const ok = /^[A-Za-z0-9_.-]{3,16}$/.test(username);
      usernameFeedback.textContent = ok ? 'Format loginu jest poprawny. Dostępność sprawdzimy po wysłaniu formularza.' : 'Login: 3-16 znaków, litery, cyfry, kropka, myślnik lub podkreślenie.';
      usernameFeedback.className = ok ? 'small mt-1 feedback-ok' : 'small mt-1 feedback-error';
    });
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
      emailFeedback.textContent = ok ? 'Domena e-mail jest akceptowana.' : 'Ta domena e-mail nie jest obsługiwana. Użyj innego adresu.';
      emailFeedback.className = ok ? 'small mt-1 feedback-ok' : 'small mt-1 feedback-error';
    });
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
        let kept = words.slice(0, 100).join(' ');
        teacherMotivation.value = kept;
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
