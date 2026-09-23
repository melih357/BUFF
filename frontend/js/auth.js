function setMessage(text, success = false) {
  const el = document.getElementById('formMessage');
  if (!el) return;
  el.textContent = text;
  el.classList.toggle('success', success);
}

function saveSession(data) {
  localStorage.setItem('streamora_token', data.token);
  localStorage.setItem('streamora_user', JSON.stringify(data.user));
}

const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    setMessage('');
    const button = loginForm.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Giriş yapılıyor...';
    try {
      const form = new FormData(loginForm);
      const data = await API.request('/api/auth/login', {
        method: 'POST',
        body: JSON.stringify({ login: form.get('login'), password: form.get('password') })
      });
      saveSession(data);
      setMessage('Giriş başarılı. Ana sayfaya yönlendiriliyorsunuz...', true);
      setTimeout(() => location.href = '/', 500);
    } catch (error) {
      setMessage(error.message);
    } finally {
      button.disabled = false;
      button.textContent = 'Giriş Yap';
    }
  });
}

const registerForm = document.getElementById('registerForm');
if (registerForm) {
  registerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    setMessage('');
    const button = registerForm.querySelector('button[type="submit"]');
    button.disabled = true;
    button.textContent = 'Hesap oluşturuluyor...';
    try {
      const form = new FormData(registerForm);
      const data = await API.request('/api/auth/register', {
        method: 'POST',
        body: JSON.stringify({
          displayName: form.get('displayName'),
          username: form.get('username'),
          email: form.get('email'),
          password: form.get('password')
        })
      });
      saveSession(data);
      setMessage('Hesap oluşturuldu. Ana sayfaya yönlendiriliyorsunuz...', true);
      setTimeout(() => location.href = '/', 500);
    } catch (error) {
      setMessage(error.message);
    } finally {
      button.disabled = false;
      button.textContent = 'Hesap Oluştur';
    }
  });
}
