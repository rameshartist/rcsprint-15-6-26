const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../templates/admin/customers.php'),'utf8');
const fields={crmSearch:{value:''},crmSort:{value:'name'}};
const now=Date.parse('2026-11-17T12:00:00Z');
class Clock extends Date { static now(){return now;} }
const context=vm.createContext({Date:Clock,document:{getElementById:id=>fields[id]},CRM_FILTER:'all',CRM_CUSTOMERS:[
{id:12,name:'zara',created_at:'2026-11-16T00:00:00Z',order_count:0,segment:'no_orders'},
{id:2,name:'Anil',created_at:'2026-01-01T00:00:00Z',order_count:4,segment:'repeat'},
{id:4,name:'Bhavna',created_at:'2026-11-16T00:00:00Z',order_count:1,segment:'new'},
{id:7,name:' anil ',created_at:'2026-11-15T00:00:00Z',order_count:2,segment:'repeat'},
{id:8,name:'Future',created_at:'2026-12-01T00:00:00Z',order_count:0},
{id:9,name:'Unknown',created_at:null,order_count:0}]});
vm.runInContext(source.slice(source.indexOf('function filteredCustomers()'),source.indexOf('function segmentLabel(')),context);
const ids=()=>Array.from(context.filteredCustomers(),c=>c.id);
assert.deepEqual(ids(),[2,7,4,8,9,12]);
context.CRM_FILTER='new';assert.deepEqual(ids(),[12,4,7]);
fields.crmSearch.value='zara';assert.deepEqual(ids(),[12]);
fields.crmSearch.value='';context.CRM_FILTER='no_orders';assert.deepEqual(ids(),[8,9,12]);
context.CRM_FILTER='repeat';assert.deepEqual(ids(),[2,7]);
context.CRM_FILTER='all';fields.crmSort.value='id';assert.deepEqual(ids(),[2,4,7,8,9,12]);
console.log('PASS: A–Z names, stable ties, recent registrations only/newest first, search and existing filters');
