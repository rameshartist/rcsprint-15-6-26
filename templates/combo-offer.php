<?php
$comboTitle = (string)($combo['title'] ?? 'Combo Offer');
$pageTitle = $comboTitle . ' — Combo Offer';
$pageImage = trim((string)($combo['banner_image'] ?? '')) ?: '/assets/images/RCS%20PRINT%20LOGO.png';
$pageDesc = trim(strip_tags((string)($combo['description'] ?? $combo['short_description'] ?? '')));
$saving = max(0, (float)$combo['regular_price'] - (float)$combo['combo_price']);
$detailVisible = static fn(string $field): bool => (int)($combo['show_'.$field.'_detail'] ?? $combo['show_'.$field] ?? 1) === 1;
$parseSpecs = static function (string $text): array {
    $rows=[];
    foreach (preg_split('/\R/u',$text) ?: [] as $line) {
        $line=trim($line); if($line==='') continue;
        $parts=preg_split('/\s*:\s*/u',$line,2);
        $rows[]=['label'=>count($parts)>1?$parts[0]:'Specification','value'=>count($parts)>1?$parts[1]:$parts[0]];
    }
    return $rows;
};
$comboItems=[];
foreach (($combo['items'] ?? []) as $item) $comboItems[]=['key'=>'product-'.(int)$item['id'],'kind'=>'product','name'=>(string)$item['product_name'],'image'=>(string)($item['product_image']?:'/assets/images/RCS%20PRINT%20LOGO.png'),'quantity'=>(int)$item['quantity'],'price'=>(float)$item['regular_price'],'description'=>(string)($item['product_description']??''),'specs'=>$item['specs']??[],'product_code'=>(string)($item['product_code']??'')];
foreach (($combo['custom_items'] ?? []) as $item) $comboItems[]=['key'=>'custom-'.(int)$item['id'],'kind'=>'custom','name'=>(string)$item['item_name'],'image'=>(string)($item['thumbnail_path']?:'/assets/images/RCS%20PRINT%20LOGO.png'),'quantity'=>(int)($item['quantity']??1),'price'=>(float)$item['item_price']*(int)($item['quantity']??1),'description'=>'','specs'=>$parseSpecs((string)($item['description']??'')),'product_code'=>''];
include INCLUDE_PATH.'/partials/head.php';
include INCLUDE_PATH.'/partials/header.php';
?>
<div class="pd-page-wrap combo-product-page"><div class="container">
  <div class="breadcrumb"><a href="/">Home</a><span>/</span><a href="/#combo-offers">Combo Offers</a><span>/</span><span style="color:var(--ink);font-weight:600"><?= htmlspecialchars($comboTitle) ?></span></div>
  <div class="pd-grid combo-product-grid">
    <div class="pd-gallery" data-reveal>
      <div class="pd-main"><img id="pdMainImg" src="<?= htmlspecialchars($pageImage) ?>" alt="<?= htmlspecialchars($comboTitle) ?>"></div>
      <div class="pd-gallery-actions"><button type="button"><i class="fa-solid fa-box-open"></i> <?= count($comboItems) ?> Included Items</button><button type="button"><i class="fa-solid fa-percent"></i> Save ₹<?= number_format($saving,2) ?></button></div>
    </div>
    <div class="pd-info-col" data-reveal data-reveal-delay="80">
      <?php if($detailVisible('badge')&&!empty($combo['badge'])):?><span class="combo-pd-badge"><?= htmlspecialchars((string)$combo['badge']) ?></span><?php endif;?>
      <?php if($detailVisible('title')):?><h1 class="pd-name"><?= htmlspecialchars($comboTitle) ?></h1><?php endif;?>
      <div class="pd-rating-row"><span class="pd-stars">★★★★★</span><strong>Combo Value</strong><span>(<?= count($comboItems) ?> products included)</span></div>
      <div class="pd-price-strip"><span class="pd-price-now">₹<?= number_format((float)$combo['combo_price'],2) ?></span><span class="pd-price-label">Combo Price</span><?php if((float)$combo['regular_price']>(float)$combo['combo_price']):?><span class="pd-price-old">₹<?= number_format((float)$combo['regular_price'],2) ?></span><?php endif;?><?php if((float)$combo['discount_percent']>0):?><span class="pd-discount">Save <?= number_format((float)$combo['discount_percent'],0) ?>%</span><?php endif;?></div>
      <?php $desc=$detailVisible('description')?(string)($combo['description']??''):($detailVisible('short_description')?(string)($combo['short_description']??''):''); if(trim($desc)!==''):?><p class="combo-pd-description"><?= nl2br(htmlspecialchars($desc)) ?></p><?php endif;?>
      <div class="pd-spec-table"><div class="pd-spec-row"><div class="pd-spec-label">Included Products</div><div class="pd-spec-value"><?= count($comboItems) ?></div></div><div class="pd-spec-row"><div class="pd-spec-label">You Save</div><div class="pd-spec-value">₹<?= number_format($saving,2) ?></div></div></div>
      <div class="price-panel"><div class="pp-row"><span class="pp-l">Regular Total</span><span class="pp-v">₹<?= number_format((float)$combo['regular_price'],2) ?></span></div><div class="pp-row"><span class="pp-tl">Combo Total</span><span class="pp-tv">₹<?= number_format((float)$combo['combo_price'],2) ?></span></div></div>
      <div class="pd-action-stack"><div class="pd-action-row"><button class="btn btn-blue btn-full" id="addComboBtn" onclick="addComboToCart()"><i class="fa-solid fa-cart-plus"></i> <?= $detailVisible('cta')?htmlspecialchars((string)($combo['cta_text']?:'ADD TO CART')):'ADD TO CART' ?></button></div><div class="pd-checkout-note pd-delivery-row"><span><i class="fa-solid fa-layer-group"></i> Configure each included item below</span><small><i class="fa-solid fa-circle-check"></i> GST and eligible coupons are calculated in cart</small><em id="comboCartMsg" class="pd-order-hint" aria-live="polite"></em></div></div>
    </div>
  </div>
  <section class="combo-included pd-tabs-section" aria-labelledby="comboItemsTitle"><div class="pd-tabs-card combo-items-card"><div class="combo-items-heading"><span>Configure Your Combo</span><h2 id="comboItemsTitle">Included Items</h2><p>Select a design option for every item before adding this combo to cart.</p></div>
  <?php foreach($comboItems as $index=>$item):?>
    <details class="combo-item-card" data-combo-item="<?= htmlspecialchars($item['key']) ?>" <?= $index===0?'open':'' ?>>
      <summary><img src="<?= htmlspecialchars($item['image']) ?>" alt=""><span><strong><?= htmlspecialchars($item['name']) ?></strong><small><?= number_format($item['quantity']) ?> qty · Configure design</small></span><b>₹<?= number_format($item['price'],2) ?></b><i>⌄</i></summary>
      <div class="combo-item-body"><img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>"><div class="combo-item-config"><h3><?= htmlspecialchars($item['name']) ?></h3><?php if($item['description']!==''):?><p><?= nl2br(htmlspecialchars($item['description'])) ?></p><?php endif;?>
        <dl><?php if($item['product_code']!==''):?><div><dt>Product Code</dt><dd><?= htmlspecialchars($item['product_code']) ?></dd></div><?php endif;?><div><dt>Quantity</dt><dd><?= number_format($item['quantity']) ?></dd></div><?php foreach($item['specs'] as $spec): if(trim((string)($spec['value']??''))==='')continue;?><div><dt><?= htmlspecialchars((string)($spec['label']??'Specification')) ?></dt><dd><?= htmlspecialchars((string)$spec['value']) ?></dd></div><?php endforeach;?></dl>
        <div class="pd-design-section combo-item-design"><div class="pd-design-heading">Design Option</div><div class="pd-design-grid"><button type="button" class="design-opt sel" data-design-choice="upload" onclick="selectComboDesign(this,'upload')"><span class="design-opt-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span><span class="design-opt-title">Upload Your Design</span><span class="design-opt-copy">Upload now or after ordering</span></button><span class="pd-design-or">OR</span><button type="button" class="design-opt" data-design-choice="rcs" onclick="selectComboDesign(this,'rcs')"><span class="design-opt-icon"><i class="fa-regular fa-pen-to-square"></i></span><span class="design-opt-title">Design by RCS Graphic</span><span class="design-opt-copy">Our team will prepare your artwork</span></button></div>
          <div class="combo-item-upload"><label class="upload-zone"><input type="file" accept=".pdf,.ai,.eps,.png,.jpg,.jpeg,.psd,.cdr,.svg,.tif,.tiff,.zip" onchange="uploadComboArtwork(this)"><span class="design-opt-icon"><i class="fa-solid fa-file-arrow-up"></i></span><strong>Choose artwork file</strong><small>PDF, AI, PSD, PNG, JPG and supported print files</small></label><label class="design-later-check"><input type="checkbox" onchange="toggleComboUploadLater(this)"><span>I will Upload Design Later</span></label><div class="combo-upload-status" aria-live="polite"></div></div>
          <textarea class="fi combo-design-brief" rows="3" placeholder="Design brief or special instructions for this item"></textarea>
        </div>
      </div></div>
    </details>
  <?php endforeach;?>
  </div></section>
  <?php if($pageDesc!==''):?><section class="pd-tabs-section"><div class="pd-tabs-card"><div class="pd-tabs-content"><div class="pd-tab-panel is-active"><h2>About This Combo</h2><p><?= nl2br(htmlspecialchars($pageDesc)) ?></p></div></div></div></section><?php endif;?>
</div></div>
<script>
const COMBO_ID=<?= (int)$combo['id'] ?>;const COMBO_ITEM_KEYS=<?= json_encode(array_column($comboItems,'key'),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;const comboDesigns=Object.fromEntries(COMBO_ITEM_KEYS.map(key=>[key,{design_choice:'upload',artwork_id:null,upload_later:false,design_brief:''}]));
function comboCard(el){return el.closest('[data-combo-item]')}function comboKey(el){return comboCard(el)?.dataset.comboItem||''}
function selectComboDesign(button,choice){const card=comboCard(button),key=comboKey(button);if(!card||!comboDesigns[key])return;comboDesigns[key].design_choice=choice;card.querySelectorAll('[data-design-choice]').forEach(el=>el.classList.toggle('sel',el===button));card.querySelector('.combo-item-upload').hidden=choice==='rcs';}
function toggleComboUploadLater(input){const key=comboKey(input);if(!comboDesigns[key])return;comboDesigns[key].upload_later=input.checked;if(input.checked){comboDesigns[key].artwork_id=null;input.closest('.combo-item-upload').querySelector('.combo-upload-status').textContent='You can upload this artwork later from My Account.';}}
async function uploadComboArtwork(input){const key=comboKey(input),file=input.files?.[0],status=input.closest('.combo-item-upload').querySelector('.combo-upload-status');if(!file||!comboDesigns[key])return;input.disabled=true;status.textContent='Uploading…';const fd=new FormData();fd.append('artwork',file);const result=await fetch('/api/upload/artwork',{method:'POST',headers:{'X-CSRF-TOKEN':APP.csrfToken},credentials:'same-origin',body:fd}).then(r=>r.json()).catch(()=>({ok:false,msg:'Upload failed'}));input.disabled=false;if(!result.ok){status.textContent=result.msg||'Upload failed';return;}comboDesigns[key].artwork_id=Number(result.artwork_id);comboDesigns[key].upload_later=false;input.closest('.combo-item-upload').querySelector('input[type=checkbox]').checked=false;status.textContent=`Uploaded: ${file.name}`;}
async function addComboToCart(){const msg=document.getElementById('comboCartMsg'),button=document.getElementById('addComboBtn');for(const key of COMBO_ITEM_KEYS){const design=comboDesigns[key];const card=document.querySelector(`[data-combo-item="${CSS.escape(key)}"]`);design.design_brief=card?.querySelector('.combo-design-brief')?.value.trim()||'';if(design.design_choice==='upload'&&!design.upload_later&&!design.artwork_id){msg.textContent='Please upload a design or select “Upload Later” for every combo item.';card?.scrollIntoView({behavior:'smooth',block:'center'});card&&(card.open=true);return;}}button.disabled=true;msg.textContent='Adding combo to cart…';const result=await fetch('/api/cart/combo',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':APP.csrfToken},credentials:'same-origin',body:JSON.stringify({combo_offer_id:COMBO_ID,item_designs:comboDesigns})}).then(r=>r.json()).catch(()=>({ok:false,msg:'Could not add combo'}));if(result.ok){location.href='/cart';return;}msg.textContent=result.msg||'Could not add combo';button.disabled=false;}
</script>
<?php include INCLUDE_PATH.'/partials/footer.php'; ?>
