const messages = document.getElementById('chatMessages');
const form = document.getElementById('chatForm');
const input = document.getElementById('messageInput');
const statusEl = document.getElementById('chatStatus');
const quickPrompts = document.getElementById('quickPrompts');
const leadDialog = document.getElementById('leadDialog');
const leadForm = document.getElementById('leadForm');
let conversation = [];
let isSending = false;
let latestSummary = '';
let lastAssistantText = '';

function scrollMessages(){ messages.scrollTop = messages.scrollHeight; }
function addMessage(text, who='assistant') {
  const wrap = document.createElement('div'); wrap.className = `message ${who === 'user' ? 'user-message' : 'assistant-message'}`;
  if(who !== 'user'){ const avatar = document.createElement('span'); avatar.className='message-avatar'; avatar.textContent='✳'; wrap.appendChild(avatar); }
  const bubble = document.createElement('div'); bubble.className='bubble';
  text.split(/\n+/).filter(Boolean).forEach(line=>{const p=document.createElement('p');p.textContent=line;bubble.appendChild(p)});
  wrap.appendChild(bubble); messages.appendChild(wrap); scrollMessages(); return wrap;
}
function showTyping(){const wrap=document.createElement('div');wrap.className='message assistant-message';wrap.id='typingIndicator';const avatar=document.createElement('span');avatar.className='message-avatar';avatar.textContent='✳';const dots=document.createElement('div');dots.className='bubble typing';dots.innerHTML='<i></i><i></i><i></i>';wrap.append(avatar,dots);messages.appendChild(wrap);scrollMessages();}
function setBusy(value){isSending=value;form.querySelector('button').disabled=value;input.disabled=value;statusEl.textContent=value?'Мира формулирует ответ…':'Мира на связи';}
async function sendMessage(text){
  const clean=text.trim(); if(!clean||isSending)return;
  addMessage(clean,'user'); conversation.push({role:'user',content:clean}); input.value=''; input.style.height='auto'; quickPrompts.hidden=true; setBusy(true); showTyping();
  try{
    const response=await fetch('api/chat.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({messages:conversation})});
    const data=await response.json(); document.getElementById('typingIndicator')?.remove();
    if(!response.ok) throw new Error(data.error||'Не удалось получить ответ. Попробуйте ещё раз.');
    const answer=String(data.reply||'Не получилось сформировать ответ. Попробуйте задать вопрос иначе.');
    addMessage(answer,'assistant'); conversation.push({role:'assistant',content:answer}); lastAssistantText=answer;
    if(data.summary) latestSummary=String(data.summary);
    if(data.offer_contact){
      const offer=document.createElement('button');offer.className='quick-prompts';offer.style.cssText='align-self:flex-start;margin:0 17px 10px;border:1px solid #d7b67566;background:#1a2637;color:#e8c68a;border-radius:12px;padding:10px 13px;font-size:11px';offer.textContent='Передать вопрос специалисту';offer.onclick=()=>openLead();messages.appendChild(offer);scrollMessages();
    }
  }catch(err){document.getElementById('typingIndicator')?.remove();addMessage((err.message||'Ошибка соединения.')+' Если сайт только что установлен, проверьте настройку API в инструкции.');statusEl.textContent='Проверьте подключение';}
  finally{setBusy(false);input.focus();}
}
function openLead(){
  if(!conversation.length){addMessage('Сначала расскажите Мире немного о своей ситуации — так специалист получит более полезное обращение.');return;}
  leadDialog.showModal();
}
form.addEventListener('submit',e=>{e.preventDefault();sendMessage(input.value)});
input.addEventListener('input',()=>{input.style.height='auto';input.style.height=Math.min(input.scrollHeight,90)+'px'});
document.querySelectorAll('[data-prompt]').forEach(btn=>btn.addEventListener('click',()=>{sendMessage(btn.dataset.prompt);document.querySelector('.chat-card').scrollIntoView({behavior:'smooth',block:'center'});}));
document.getElementById('startChat').addEventListener('click',()=>{document.querySelector('.chat-card').scrollIntoView({behavior:'smooth',block:'center'});setTimeout(()=>input.focus(),450)});
document.getElementById('closeLead').addEventListener('click',()=>leadDialog.close());
leadDialog.addEventListener('click',e=>{if(e.target===leadDialog)leadDialog.close()});
leadForm.addEventListener('submit',async e=>{
  e.preventDefault(); const error=document.getElementById('leadError');error.textContent='';
  if(!document.getElementById('consent').checked){error.textContent='Для отправки обращения нужно подтвердить согласие.';return;}
  const submit=leadForm.querySelector('.submit-lead');submit.disabled=true;submit.textContent='Отправляем…';
  const payload={name:document.getElementById('leadName').value.trim(),phone:document.getElementById('leadPhone').value.trim(),city:document.getElementById('leadCity').value.trim(),contact_time:document.getElementById('leadTime').value,consent:true,summary:latestSummary||'Резюме сформировать по переписке.',conversation:conversation.slice(-20)};
  try{
    const response=await fetch('api/lead.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});const data=await response.json();if(!response.ok)throw new Error(data.error||'Не удалось отправить обращение.');
    leadDialog.close();addMessage('Спасибо! Обращение отправлено специалисту. Он свяжется с вами выбранным способом, если этот способ поддерживается настройками проекта.','assistant');
  }catch(err){error.textContent=err.message||'Ошибка отправки. Попробуйте позже.';}
  finally{submit.disabled=false;submit.innerHTML='Отправить обращение <span>→</span>';}
});
document.getElementById('year').textContent=new Date().getFullYear();
