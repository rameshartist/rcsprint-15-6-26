// Run: node tests/checkout_addresses.cjs
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const template = fs.readFileSync(require('node:path').join(__dirname, '../templates/checkout.php'), 'utf8');
assert.equal((template.match(/checkout-add-address-btn/g) || []).length, 2);
assert.equal(template.includes('<option value="new">'), false);
assert.equal(template.includes('>Legal Business Name'), false);
for (const prefix of ['s', 'b']) for (const field of ['type','business','legal','gst','add1','city','state','pin','default-shipping','default-billing']) {
  assert.equal((template.match(new RegExp('id="' + prefix + '-' + field + '"', 'g')) || []).length, 1);
}
let code = template.slice(template.indexOf('function openCheckoutAddressForm('), template.lastIndexOf("document.addEventListener('DOMContentLoaded'"));
code = code.replace(/<\?php if \(empty\(\$user\['id'\]\)\): \?>\s*return;\s*<\?php else: \?>/, '').replace(/<\?php endif; \?>/g, '');
for (const [start, end] of [['function syncBillingFromShipping()', 'function getCheckoutCustomer()'], ['function getCheckoutBilling()', 'function setField(']]) {
  code += '\n' + template.slice(template.indexOf(start), template.indexOf(end));
}
code += '\nfunction toggleShippingFields() {} function toggleBillingFields() {}';
const ids = [...template.matchAll(/id="([^"]+)"/g)].map(m => m[1]);
async function scenario(addresses, {guest = false, fail = false} = {}) {
  const els = Object.fromEntries(ids.map(id => [id, {value:'', hidden:false, checked:false, style:{}, innerHTML:''}]));
  for (const id of ['saved-address-wrap','saved-billing-address-wrap']) els[id].hidden = true;
  for (const id of ['ship-save-default','bill-save-default']) els[id].checked = true;
  if (guest) for (const id of ['saved-address-select','saved-billing-address-select','ship-save-default','bill-save-default','selectedAddressText','selectedBillingAddressText']) delete els[id];
  const context = vm.createContext({document:{getElementById:id => els[id]}, checkoutAddresses:[], checkoutProfile:{}, fetch:async()=>{if(fail) throw Error('Offline');return {json:async()=>({ok:true,profile:{name:'Customer',addresses}})}}});
  vm.runInContext(code, context);
  if (!guest) await context.prefillCheckoutFromProfile();
  return {els, context};
}
(async()=>{
  for (const options of [{}, {guest:true}, {fail:true}]) {
    const {els,context:c} = await scenario([],options);
    assert.equal(els['shippingAddressFields'].hidden,false);
    assert.equal(els['billingFields'].hidden,false);
    assert.equal(els['saved-address-wrap'].hidden,true);
    assert.equal(els['saved-billing-address-wrap'].hidden,true);
    for (const prefix of ['s','b']) for(const [field,value] of Object.entries({add1:'New Street',city:'Rajkot',state:'Gujarat',pin:'360001'})) els[`${prefix}-${field}`].value=value;
    els['s-business'].value='Customer'; els['b-legal'].value='Customer';
    assert.equal(c.getCheckoutShipping().save_as_default,true);
    assert.equal(c.getCheckoutBilling().save_as_default,true);
    assert.equal(c.getCheckoutBilling().gst_no,'');
    els['b-legal'].value=''; assert.notEqual(c.getCheckoutBilling(),false);
    els['b-legal'].value='Optional Business'; els['b-business'].value='Full Name';
    els['b-type'].value='office';
    assert.equal(c.getCheckoutBilling().legal_name,'Optional Business');
    assert.equal(c.getCheckoutBilling().business_name,'Full Name');
    assert.equal(c.getCheckoutBilling().address_type,'office');
    els['b-gst'].value='24ABCDE1234F1Z5';
    assert.equal(c.getCheckoutBilling().gst_no,'24ABCDE1234F1Z5');
    els['b-gst'].value='invalid'; assert.equal(c.getCheckoutBilling(),false);
    els['b-gst'].value=''; els['bill-same-ship'].checked=true; c.syncBillingFromShipping();
    assert.equal(els['billingFields'].hidden,false);
    assert.equal(c.getCheckoutBilling().address_line1,'New Street');
    els['bill-same-ship'].checked=false; c.syncBillingFromShipping();
    assert.equal(els['billingFields'].hidden,false);
  }
  const address={id:7,address_line1:'Saved Street',city:'Rajkot',state:'Gujarat',pincode:'360001',business_name:'Saved Customer',legal_name:'RCS & Partners <Print>',gst_no:'24ABCDE1234F1Z5',address_line2:'Suite 2'};
  const {els,context:c}=await scenario([address]);
  assert.equal(els['saved-address-wrap'].hidden,false);
  assert.ok(els.selectedAddressText.innerHTML.includes('RCS &amp; Partners &lt;Print&gt;'));
  assert.ok(els.selectedAddressText.innerHTML.includes('Suite 2'));
  assert.ok(els.selectedBillingAddressText.innerHTML.includes('RCS &amp; Partners &lt;Print&gt;'));
  assert.equal(els['saved-billing-address-wrap'].hidden,false);
  assert.equal(els['shippingAddressFields'].hidden,true);
  assert.equal(els['billingFields'].hidden,true);
  assert.equal(c.getCheckoutShipping().address_line1,'Saved Street');
  assert.equal(c.getCheckoutShipping().save_as_default,false);
  assert.equal(c.getCheckoutBilling().save_as_default,false);
  c.openCheckoutAddressForm('shipping'); c.openCheckoutAddressForm('billing');
  assert.equal(els['shippingAddressFields'].hidden,false);
  assert.equal(els['billingFields'].hidden,false);
  assert.equal(els['s-add1'].value,''); assert.equal(els['b-add1'].value,'');
  console.log('PASS: guest, newly registered, empty/saved address lists, profile failure, GST and same-as-shipping checkout checks');
})().catch(error=>{console.error(error);process.exitCode=1;});
