document.addEventListener('DOMContentLoaded', () => {
  const form=document.getElementById('adminLoginForm'); const msg=document.getElementById('adminLoginMessage'); const pass=document.getElementById('adminPassword');
  document.getElementById('adminPasswordToggle')?.addEventListener('click',e=>{const show=pass.type==='password';pass.type=show?'text':'password';e.currentTarget.textContent=show?'Gizle':'Göster'});
  form?.addEventListener('submit',async e=>{
    e.preventDefault();const btn=form.querySelector('button[type="submit"]');btn.disabled=true;msg.className='form-message';msg.textContent='Yönetici hesabı doğrulanıyor...';
    try{const data=Object.fromEntries(new FormData(form).entries());const res=await LogiApp.api('/auth/admin-login.php',{method:'POST',body:JSON.stringify(data)});msg.className='form-message success';msg.textContent='Admin girişi başarılı. Yönetim konsolu açılıyor...';setTimeout(()=>{const target=new URL(res.redirect||'./admin.php',window.location.href);window.location.assign(target.href)},120)}
    catch(err){msg.className='form-message error';msg.textContent=err.message||'Admin girişi başarısız.';btn.disabled=false}
  });
});
