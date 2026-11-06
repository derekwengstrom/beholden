(()=>{'use strict';
const modal=document.getElementById('cart-modal');if(!modal)return;
const body=modal.querySelector('.cart-modal-body');let origin=null;let pending=false;
const close=()=>{modal.hidden=true;document.body.classList.remove('modal-open');origin?.focus();};
const open=()=>{modal.hidden=false;document.body.classList.add('modal-open');modal.querySelector('.cart-modal-close').focus();};
modal.querySelector('.cart-modal-close').addEventListener('click',close);
modal.addEventListener('click',e=>{if(e.target===modal||e.target.closest('.cart-continue'))close();});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.hidden)close();if(e.key==='Tab'&&!modal.hidden){const focusable=[...modal.querySelectorAll('button:not([disabled]),a[href]')].filter(el=>el.getClientRects().length);if(!focusable.length)return;const first=focusable[0],last=focusable[focusable.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}});
document.querySelectorAll('form.add-cart').forEach(form=>form.addEventListener('submit',async e=>{
e.preventDefault();if(pending)return;pending=true;origin=form.querySelector('button[type=submit],button:not([type])');if(origin)origin.disabled=true;
try{
 const res=await fetch(form.getAttribute('action') || '/cart.php',{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
 if(!res.ok)throw Error('Could not add item to cart.');
 const data=await res.json();if(!data.ok)throw Error('Unable to add item.');
 const detail=await fetch('/cart.php?modal=1',{credentials:'same-origin',cache:'no-store'});
 if(!detail.ok)throw Error('Could not load cart.');
 body.innerHTML=await detail.text();
 document.querySelectorAll('.nav-links a[href="/cart.php"]').forEach(a=>{a.textContent=`Cart (${data.count})`;});
 open();
}catch(err){window.alert(err.message+' Please try again.');}
finally{pending=false;if(origin)origin.disabled=false;}
}));
})();
