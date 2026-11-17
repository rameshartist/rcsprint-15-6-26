const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../templates/admin/customers.php'),'utf8');
const fields={crmSearch:{value:''},crmSort:{value:'id'}};
const context=vm.createContext({document:{getElementById:id=>fields[id]},CRM_FILTER:'all',CRM_CUSTOMERS:[
{id:12,name:'Latest',created_at:'2026-11-16',order_count:0,segment:'no_orders'},
{id:2,name:'Older',created_at:'2026-01-01',order_count:4,segment:'repeat'},
{id:4,name:'Recent',created_at:'2026-11-16',order_count:1,segment:'new'}]});
vm.runInContext(source.slice(source.indexOf('function filteredCustomers()'),source.indexOf('function segmentLabel(')),context);
const ids=()=>Array.from(context.filteredCustomers(),c=>c.id);
assert.deepEqual(ids(),[2,4,12]);
context.CRM_FILTER='new';assert.deepEqual(ids(),[12,4,2]);
fields.crmSearch.value='latest';assert.deepEqual(ids(),[12]);
fields.crmSearch.value='';context.CRM_FILTER='no_orders';assert.deepEqual(ids(),[12]);
context.CRM_FILTER='repeat';assert.deepEqual(ids(),[2]);
console.log('PASS: numeric customer ordering, newest registration order/tie-breaks, zero-order customers, search and existing filters');
