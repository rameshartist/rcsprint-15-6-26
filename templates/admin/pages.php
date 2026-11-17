<?php $pageTitle='Pages — RCS Admin';$currentAdmPage='pages';include __DIR__.'/layout.php'; ?>
<div class="adm-page-head"><div><div class="adm-pt">Pages</div><p>Edit customer policy pages with formatted text.</p></div></div>
<section class="managed-pages-shell">
  <nav class="managed-page-tabs" id="managedPageTabs" aria-label="Policy pages"></nav>
  <article class="managed-page-editor">
    <div class="managed-editor-toolbar" aria-label="Formatting tools">
      <button type="button" data-cmd="formatBlock" data-value="h2">Heading</button><button type="button" data-cmd="formatBlock" data-value="h3">Subheading</button><button type="button" data-cmd="formatBlock" data-value="p">Paragraph</button>
      <button type="button" data-cmd="bold"><b>B</b></button><button type="button" data-cmd="italic"><i>I</i></button><button type="button" data-cmd="underline"><u>U</u></button>
      <button type="button" data-cmd="insertUnorderedList">• List</button><button type="button" data-cmd="insertOrderedList">1. List</button><button type="button" id="managedLinkBtn">Link</button><button type="button" data-cmd="undo">Undo</button><button type="button" data-cmd="redo">Redo</button><button type="button" id="managedClearBtn">Clear Content</button>
    </div>
    <div id="managedPageEditor" class="managed-page-content" contenteditable="true" spellcheck="true"></div>
    <div class="managed-page-actions"><span id="managedPageStatus" aria-live="polite"></span><button type="button" class="btn btn-blue" id="managedPageSave">Save Page</button></div>
  </article>
</section>
<script>
const PAGES_CSRF='<?= htmlspecialchars($csrf??'',ENT_QUOTES) ?>';let managedPages=[],activeManagedPage='';const escPage=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function selectManagedPage(key){const page=managedPages.find(row=>row.page_key===key);if(!page)return;activeManagedPage=key;document.getElementById('managedPageEditor').innerHTML=page.content_html||'';document.querySelectorAll('[data-page-key]').forEach(button=>button.classList.toggle('act',button.dataset.pageKey===key));document.getElementById('managedPageStatus').textContent='Editing '+page.label;}
async function loadManagedPages(){const data=await fetch('/admin/api/pages').then(r=>r.json());managedPages=data.pages||[];document.getElementById('managedPageTabs').innerHTML=managedPages.map(page=>`<button type="button" data-page-key="${escPage(page.page_key)}" onclick="selectManagedPage('${escPage(page.page_key)}')">${escPage(page.label||page.title)}</button>`).join('');if(managedPages[0])selectManagedPage(managedPages[0].page_key);}
document.querySelectorAll('[data-cmd]').forEach(button=>button.addEventListener('click',()=>{document.getElementById('managedPageEditor').focus();document.execCommand(button.dataset.cmd,false,button.dataset.value||null);}));
document.getElementById('managedLinkBtn').addEventListener('click',()=>{const url=prompt('Enter link URL');if(url&&/^(https?:\/\/|mailto:|tel:|\/)/i.test(url)){document.getElementById('managedPageEditor').focus();document.execCommand('createLink',false,url);}});
document.getElementById('managedClearBtn').addEventListener('click',()=>{if(confirm('Clear all content from this page? Click Save Page to confirm the change.'))document.getElementById('managedPageEditor').innerHTML='';});
document.getElementById('managedPageSave').addEventListener('click',async()=>{const button=document.getElementById('managedPageSave'),status=document.getElementById('managedPageStatus');button.disabled=true;status.textContent='Saving…';const data=await fetch(`/admin/api/pages/${encodeURIComponent(activeManagedPage)}`,{method:'PUT',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':PAGES_CSRF},body:JSON.stringify({content_html:document.getElementById('managedPageEditor').innerHTML})}).then(r=>r.json()).catch(()=>({ok:false,msg:'Save failed.'}));button.disabled=false;status.textContent=data.msg||(data.ok?'Page saved.':'Save failed.');if(data.ok){const page=managedPages.find(row=>row.page_key===activeManagedPage);if(page)page.content_html=document.getElementById('managedPageEditor').innerHTML;}});loadManagedPages();
</script></div></div></div></body></html>
