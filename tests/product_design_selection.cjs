const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../templates/product.php'),'utf8');
const elements={};
function element(){return {style:{},hidden:false,disabled:false,checked:false,textContent:'',value:'',innerHTML:'',classList:{toggle(){},add(){},remove(){}},setAttribute(){},focus(){},addEventListener(){}};}
for(const id of ['pdQty','ppBase','ppTotal','pricePanel','ppDesignRow','ppDesignFee','dopt-upload','dopt-rcs','panel-upload','panel-rcs','uploadZone','uploadLaterCheck','uploadLaterNote','uploadPreview','artworkFile','designLibraryModal','designLibraryList','designLibraryApply','designLibraryClose','designLibraryCount'])elements[id]=element();
elements.pdQty.options=[{dataset:{price:'250'}}];elements.pdQty.selectedIndex=0;
const checkboxes=[{value:1,checked:true},{value:2,checked:true}];
elements.designLibraryList.querySelectorAll=()=>checkboxes;
elements.designLibraryList.querySelector=selector=>checkboxes.find(x=>selector.includes(`"${x.value}"`));
const dialog=element();
const messages=[],calls=[];
let failId=null;
const context=vm.createContext({console,Set,document:{activeElement:element(),getElementById:id=>elements[id],querySelector:()=>dialog},requestAnimationFrame:fn=>fn(),setTimeout:()=>{},toast:(...m)=>messages.push(m),fetch:async(url,options)=>{
 calls.push({url,options});
 if(url==='/api/artwork/library')return {ok:true,json:async()=>({ok:true,files:[{id:1,original_name:'Front "card" <img>',file_path:'/uploads/front.png',mime_type:'image/png',created_at:'2026-11-17 10:00:00'},{id:2,original_name:'Back.pdf',file_path:'/uploads/back.pdf',mime_type:'application/pdf',created_at:'2026-11-17 10:00:00'}]})};
 const id=Number(url.match(/artwork\/(\d+)/)[1]);
 return {ok:id!==failId,json:async()=>id===failId?{ok:false,msg:'Retry this file'}:{ok:true,file:{id:id+100,name:`Design ${id}`,path:`/uploads/${id}.png`,size:1024}}};
}});
let code=`let selectedQualityId=1, selectedQty=100, currentBasePrice=0, designChoice='upload', uploadDesignLater=false, artworkId=null, uploadedFileName=null, artworkFiles=[];
const DESIGN_FEE=75,CSRF='test-token',QUALITIES=[];
function refreshOrderReadiness(){} function renderArtworkFiles(){} function processFile(){}\n`;
for(const [a,b] of [['function calcPrice()','// Design Option'],['function selDesignOpt(','// File Upload'],['function handleFileSelect(','async function processFile('],['let selectedLibraryIds','function toggleUploadLater'],['function toggleUploadLater','// Add to Cart']])code+=source.slice(source.indexOf(a),source.indexOf(b));
code=code.replace(/<\?= !empty\(\$user\['id'\]\)\?'true':'false' \?>/g,'true');
vm.runInContext(code,context);
const run=code=>vm.runInContext(code,context);
(async()=>{
 run("selDesignOpt('rcs')");assert.equal(elements.ppTotal.textContent,'₹325');assert.equal(elements.ppDesignRow.style.display,'');
 assert(source.includes("event.stopPropagation();selDesignOpt('upload');document.getElementById('artworkFile').click()"));
 // The file-picker click chooses Upload before a file is selected (or the picker is cancelled).
 run("selDesignOpt('upload')");assert.equal(elements.ppTotal.textContent,'₹250');assert.equal(elements.ppDesignRow.style.display,'none');
 run("selDesignOpt('rcs');handleFileSelect({target:{files:[]}})");assert.equal(elements.ppTotal.textContent,'₹250');
 run("selDesignOpt('rcs');handleFileDrop({preventDefault(){},stopPropagation(){},dataTransfer:{files:[]}})");assert.equal(elements.ppTotal.textContent,'₹250');
 run("selDesignOpt('rcs');toggleUploadLater(true)");assert.equal(elements.ppTotal.textContent,'₹250');
 await context.openDesignLibrary();
 assert.equal(elements.designLibraryModal.hidden,false);
 assert.equal((elements.designLibraryList.innerHTML.match(/type="checkbox"/g)||[]).length,2);
 assert(elements.designLibraryList.innerHTML.includes('&quot;card&quot; &lt;img&gt;'));
 context.toggleLibraryDesign(1,true);context.toggleLibraryDesign(2,true);assert.equal(elements.designLibraryCount.textContent,'2 files selected');
 context.toggleLibraryDesign(2,false);assert.equal(elements.designLibraryCount.textContent,'1 file selected');context.toggleLibraryDesign(2,true);
 run("selDesignOpt('rcs')");
 // Fail the second request: keep the first attachment, retry only the remaining selection.
 failId=2;await context.reuseSelectedDesignFiles();
 assert.equal(run('artworkFiles.length'),1);assert.equal(run('selectedLibraryIds.size'),1);assert.equal(elements.designLibraryModal.hidden,false);
 assert.equal(elements.ppTotal.textContent,'₹250');
 failId=null;
 await Promise.all([context.reuseSelectedDesignFiles(),context.reuseSelectedDesignFiles()]);
 assert.equal(run('artworkFiles.length'),2);assert.equal(run('artworkId'),101);assert.equal(run('selectedLibraryIds.size'),0);
 assert.equal(elements.designLibraryModal.hidden,true);assert.equal(elements.uploadLaterCheck.checked,false);
 assert.equal(calls.filter(c=>c.url==='/api/artwork/1/reuse').length,1);
 assert.equal(calls.filter(c=>c.url==='/api/artwork/2/reuse').length,2);
 assert.equal(calls.find(c=>c.url.endsWith('/reuse')).options.headers['X-CSRF-TOKEN'],'test-token');
 assert(source.includes('artwork_ids: uploadDesignLater ? [] : artworkFiles.map(file=>file.id)'));
 console.log('PASS: design-fee toggles, file-picker cancel/drop/upload-later, multi-selection, partial failure/retry, double-click guard, CSRF and cart artwork IDs');
})().catch(e=>{console.error(e);process.exitCode=1;});
