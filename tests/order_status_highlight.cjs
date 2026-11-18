const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../templates/admin/orders.php'),'utf8');
const statuses=['all','new_order','received','design_approved','printing','other_process','ready','delivered'];
const summaries=statuses.map(status=>({dataset:{statusSummary:status},active:false,classList:{toggle(_,active){this.owner.active=active;}},querySelector(){return {setAttribute(){}};}}));
summaries.forEach(s=>s.classList.owner=s);
const c=vm.createContext({document:{querySelectorAll:()=>summaries}});
vm.runInContext(source.slice(source.indexOf('function highlightOrderStatus('),source.indexOf('function setOrderCardOpen(')),c);
for(const status of statuses.slice(1)){
 c.highlightOrderStatus({dataset:{orderStatus:status}});
 assert.deepEqual(summaries.filter(s=>s.active).map(s=>s.dataset.statusSummary),[status]);
}
c.highlightOrderStatus({dataset:{orderStatus:'processing'}});
assert.deepEqual(summaries.filter(s=>s.active).map(s=>s.dataset.statusSummary),['other_process']);
assert(source.includes('if (open) highlightOrderStatus(card)'));
assert(source.includes("window.addEventListener('hashchange'"));
console.log('PASS: notification/opened-order status highlight, single active card and legacy status alias');
