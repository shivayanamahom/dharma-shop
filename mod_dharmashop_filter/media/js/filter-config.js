/** Fill native Joomla subform with missing choices, preserving configured rows and order. */
document.addEventListener('click', (event) => {
  if(!event.target.closest('[data-filter-fill]'))return;
  const subform=document.querySelector('joomla-field-subform[name="jform[params][fields_config]"]') || document.querySelector('joomla-field-subform[id*="fields_config"]');
  if(!subform || typeof subform.addRow !== 'function')return;
  const selector='select[name$="[field]"]';
  let selects=[...subform.querySelectorAll(selector)];
  if(!selects.length){subform.addRow();selects=[...subform.querySelectorAll(selector)];}
  const options=[...selects[0].options].filter(o=>o.value && !o.disabled);
  const used=new Set(selects.map(s=>s.value));
  options.forEach(option=>{
    if(used.has(option.value))return;
    const blank=selects.find(s=>!s.value);
    const row=blank?blank.closest('.subform-repeatable-group'):subform.addRow(); if(!row)return;
    const select=blank || row.querySelector(selector); if(!select)return;
    select.value=option.value;select.dispatchEvent(new Event('change',{bubbles:true}));used.add(option.value);selects=[...subform.querySelectorAll(selector)];
  });
});
