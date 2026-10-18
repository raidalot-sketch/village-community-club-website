(()=>{'use strict';
const max=30*1024*1024;
for(const input of document.querySelectorAll('input[type=file][data-club-upload], [data-club-upload] input[type=file]')){
 const form=input.closest('form');if(!form)continue;
 const preview=document.createElement('div');preview.className='attachment-previews';preview.setAttribute('aria-live','polite');input.after(preview);
 let generation=0,busy=false,invalid=false,prepared=[];const urls=[];
 const status=document.createElement('p');status.className='attachment-status';status.setAttribute('role','status');input.after(status);
 function paint(){preview.replaceChildren();for(const url of urls)URL.revokeObjectURL(url);urls.length=0;
  prepared.forEach((file,i)=>{const row=document.createElement('div');row.className='attachment-preview';
   if(file.type.startsWith('image/')){const img=document.createElement('img');img.src=URL.createObjectURL(file);urls.push(img.src);img.alt='Selected photograph';row.append(img);}
   const text=document.createElement('span');text.textContent=file.name+' · '+(file.size/1024<1024?Math.ceil(file.size/1024)+' KB':(file.size/max*30).toFixed(1)+' MB');row.append(text);
   const button=document.createElement('button');button.type='button';button.className='text-button';button.textContent='Remove';button.onclick=()=>{prepared.splice(i,1);sync();paint();};row.append(button);preview.append(row);
  });
 }
 function sync(){const transfer=new DataTransfer();prepared.forEach(f=>transfer.items.add(f));input.files=transfer.files;}
 input.addEventListener('change',async()=>{const batch=++generation;busy=true;invalid=false;prepared=[];preview.replaceChildren();status.textContent='Preparing your files…';input.setCustomValidity('');
 try{const files=Array.from(input.files),results=[];if(input.multiple&&files.length>3)throw Error('Choose up to three files.');
  for(const original of files){if(original.size>max)throw Error('Each file can be up to 30 MB.');let blob=original;const isHEIC=/\.(heic|heif)$/i.test(original.name)||/heic|heif/.test(original.type);
   if(isHEIC){if(typeof window.heic2any!=='function')throw Error('This device could not convert that iPhone photo. Choose a JPEG version and try again.');blob=await window.heic2any({blob:original,toType:'image/jpeg',quality:.9});if(Array.isArray(blob))blob=blob[0];}
   if(blob.type.startsWith('image/')){const bitmap=await createImageBitmap(blob);const scale=Math.min(1,2000/Math.max(bitmap.width,bitmap.height));const canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(bitmap.width*scale));canvas.height=Math.max(1,Math.round(bitmap.height*scale));canvas.getContext('2d').drawImage(bitmap,0,0,canvas.width,canvas.height);bitmap.close?.();const output=await new Promise(resolve=>canvas.toBlob(resolve,'image/webp',.84));if(!output)throw Error('That photo could not be prepared. Try a JPEG or smaller photo.');results.push(new File([output],original.name.replace(/\.[^.]+$/,'')+'.webp',{type:'image/webp'}));}
   else{if(!/\.(pdf|docx|xlsx)$/i.test(original.name))throw Error('Choose a photo, PDF, Word (.docx) or Excel (.xlsx) document.');results.push(original);}
  }
  if(batch!==generation)return;prepared=results;sync();paint();status.textContent=prepared.length?'Files ready. Photographs have been resized automatically.':'';
 }catch(error){if(batch!==generation)return;invalid=true;input.setCustomValidity(error.message||'Please choose these files again.');status.textContent=error.message;prepared=[];}
 finally{if(batch===generation)busy=false;}
 });
 form.addEventListener('submit',event=>{if(busy||invalid){event.preventDefault();if(busy)status.textContent='Please wait while your photos are prepared.';input.reportValidity();}});
}
})();
