document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const message = document.getElementById('formMessage');

  function setMessage(text, ok = false) {
    if (!message) return;
    message.textContent = text || '';
    message.className = `form-message ${ok ? 'success' : 'error'}`;
  }

  function destination() {
    const next = new URLSearchParams(window.location.search).get('next');
    if (!next) return './index.php';
    try {
      const parsed = new URL(next, window.location.origin);
      if (parsed.origin !== window.location.origin) return './index.php';
      return parsed.href;
    } catch (_) { return './index.php'; }
  }

  document.querySelectorAll('[data-password-toggle]').forEach(btn => btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.passwordToggle);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.textContent = show ? 'Gizle' : 'Göster';
    btn.setAttribute('aria-label', show ? 'Şifreyi gizle' : 'Şifreyi göster');
  }));

  const password = document.getElementById('registerPassword');
  const strengthBar = document.getElementById('passwordStrengthBar');
  const strengthText = document.getElementById('passwordStrengthText');
  password?.addEventListener('input', () => {
    const v = password.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (v.length >= 12) score++;
    if (/[A-ZÇĞİÖŞÜ]/.test(v) && /[a-zçğıöşü]/.test(v)) score++;
    if (/\d/.test(v)) score++;
    if (/[^\w\s]/.test(v)) score++;
    const percent = [0,24,42,62,82,100][score];
    strengthBar?.style.setProperty('--strength', `${percent}%`);
    strengthBar?.setAttribute('data-score', String(score));
    if (strengthText) strengthText.textContent = score <= 1 ? 'Zayıf şifre' : score === 2 ? 'Orta seviye' : score <= 4 ? 'İyi şifre' : 'Güçlü şifre';
  });

  async function submitForm(form, endpoint, successText) {
    const submit = form.querySelector('button[type="submit"]');
    const old = submit?.innerHTML;
    if (submit) { submit.disabled = true; submit.classList.add('loading'); submit.innerHTML = '<span>İşleniyor...</span><b>•••</b>'; }
    try {
      const formData = new FormData(form);
      const result = await LogiApp.api(endpoint, { method: 'POST', body: JSON.stringify(Object.fromEntries(formData.entries())) });
      const adminTarget = result?.redirect || (result?.user?.isAdmin ? './admin.php' : '');
      setMessage(adminTarget ? 'Admin girişi başarılı. Yönetim paneli açılıyor...' : successText, true);
      document.body.classList.add('auth-success');
      setTimeout(() => { window.location.href = adminTarget || destination(); }, 300);
    } catch (error) {
      setMessage(error.message);
      form.classList.remove('auth-shake');
      requestAnimationFrame(() => form.classList.add('auth-shake'));
      if (submit) { submit.disabled = false; submit.classList.remove('loading'); submit.innerHTML = old; }
    }
  }

  loginForm?.addEventListener('submit', e => { e.preventDefault(); submitForm(loginForm, '/auth/login.php', 'Giriş başarılı. Ana sayfa açılıyor...'); });
  registerForm?.addEventListener('submit', e => { e.preventDefault(); submitForm(registerForm, '/auth/register.php', 'Hesabın oluşturuldu. BUFF açılıyor...'); });
});
