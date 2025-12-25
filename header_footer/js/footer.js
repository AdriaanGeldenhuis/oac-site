(function(){
const form=document.getElementById('regForm'); const err=document.getElementById('err'); const ok=document.getElementById('ok');
if(!form) return;
form.addEventListener('submit', async (e)=>{
  e.preventDefault(); err.hidden=true; ok.hidden=true;
  const fd=new FormData(form);
  const payload={
    name:fd.get('name'), surname:fd.get('surname'), email:fd.get('email'),
    password:fd.get('password'), password2:fd.get('password2'),
    csrf_token:fd.get('csrf_token')||window.CSRF_TOKEN||''
  };
  try{
    const res=await fetch(form.action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload),credentials:'same-origin'});
    const data=await res.json(); if(!res.ok||!data.ok) throw new Error(data.error||'Kon nie registreer nie');
    ok.textContent='Gereed. Jou rekening wag vir goedkeuring.'; ok.hidden=false;
    setTimeout(()=>location.href='/login.php',1400);
  }catch(ex){ err.textContent=ex.message; err.hidden=false; }
});})();