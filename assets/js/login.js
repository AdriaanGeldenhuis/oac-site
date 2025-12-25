(function(){
const form=document.getElementById('loginForm'); const err=document.getElementById('err');
if(!form) return;
form.addEventListener('submit', async (e)=>{
  e.preventDefault(); err.hidden=true;
  const fd=new FormData(form);
  const payload={ email:fd.get('email'), password:fd.get('password'), remember:fd.get('remember')?1:0, csrf_token:fd.get('csrf_token')||window.CSRF_TOKEN||'' };
  try{
    const res=await fetch(form.action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload),credentials:'same-origin'});
    const data=await res.json(); if(!res.ok||!data.ok) throw new Error(data.error||'Kon nie inteken nie');
    location.href=data.redirect||'/WELCOME.php';
  }catch(ex){ err.textContent=ex.message; err.hidden=false; }
});})();