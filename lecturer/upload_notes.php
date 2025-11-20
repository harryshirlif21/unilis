<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Lecturer Notes Creator</title>
<style>
:root{
    --topic-color: #1f6feb;
    --subtopic-color: #0ea5a4;
    --file-label-color: #f59e0b;
    --image-label-color: #ef4444;
}
body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
.container { display: flex; gap: 20px; }
.form-section, .preview-section { background: white; padding: 20px; border-radius: 10px; width: 50%; overflow-y: auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
.topic-block { border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; border-radius: 8px; position: relative; }
.subtopic-block { margin-left: 20px; margin-top: 10px; padding: 10px; border-left: 3px solid #888; position: relative; }
input[type="text"], textarea, select { width: 100%; margin-top: 8px; padding: 8px; box-sizing: border-box; }
button { padding: 6px 12px; margin-top: 10px; cursor: pointer; }
.label-pill { display: inline-block; padding: 4px 8px; border-radius: 999px; color: white; font-size: 12px; margin-right:8px; }
.topic-pill { background: var(--topic-color); }
.subtopic-pill { background: var(--subtopic-color); }
.file-pill { background: var(--file-label-color); }
.image-pill { background: var(--image-label-color); }
#imagePreview img { width: 150px; margin:5px; border-radius:8px; }
.choice-row { display:flex; gap:8px; align-items:center; margin-top:6px; }
.remove-btn { background:#ef4444; color:white; border:none; padding:4px 8px; border-radius:6px; cursor:pointer; }
.edit-btn { background:#10b981; color:white; border:none; padding:4px 8px; border-radius:6px; cursor:pointer; margin-left:5px;}
.unit-select { margin-bottom:15px; }
#existingTopics { margin-top:20px; background:#fff; padding:15px; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.1); }
</style>
</head>
<body>
<h1>Lecturer Notes Creator</h1>

<div class="unit-select">
    <label>Select Unit: 
        <select id="unitDropdown" onchange="loadUnitTopics()">
            <option value="">-- Select Unit --</option>
        </select>
    </label>
</div>

<div class="container">
    <div class="form-section">
        <h2>Input Notes</h2>
        <div id="topics"></div>
        <button onclick="addTopic()">+ Add Topic</button>
        <hr>
        <h3>Upload Files</h3>
        <input type="file" multiple id="fileUploads" onchange="updateFiles()">
        <div id="fileList"></div>
        <h3>Upload Images</h3>
        <input type="file" accept="image/*" multiple id="imageUploads" onchange="updateImages()">
        <hr>
        <button onclick="submitNotes()">Save Notes</button>
        <button onclick="updateNotes()">Update Notes</button>
    </div>

    <div class="preview-section">
        <h2>Live Preview</h2>
        <div id="preview"></div>

        <h3>Files</h3>
        <ul id="filePreview"></ul>

        <h3>Images</h3>
        <div id="imagePreview"></div>
    </div>
</div>

<div id="existingTopics">
    <h3>Already Added Topics in this Unit</h3>
    <div id="topicsList"></div>
</div>

<script>
let topics = [];
let lastFiles = [];
let lastImages = [];
let existingTopics = [];
let selectedUnitId = null;
const lecturerId = 1; // Replace with logged-in lecturer ID

// --- Fetch lecturer units from backend ---
function loadLecturerUnits() {
    fetch('/getLecturerUnits?lecturer_id=' + lecturerId)
        .then(res => res.json())
        .then(data => {
            const unitDropdown = document.getElementById('unitDropdown');
            unitDropdown.innerHTML = "<option value=''>-- Select Unit --</option>";
            data.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.unit_id;
                opt.textContent = u.unit_name;
                unitDropdown.appendChild(opt);
            });
        })
        .catch(err => console.error('Error loading units:', err));
}

// --- Load topics for selected unit ---
function loadUnitTopics(){
    selectedUnitId = document.getElementById('unitDropdown').value;
    if(!selectedUnitId){ topics = []; renderForm(); renderExistingTopics(); return; }
    topics = [];
    fetchExistingTopics(selectedUnitId);
    renderForm();
}

// --- Fetch existing topics from backend ---
function fetchExistingTopics(unitId){
    fetch('/getClassnotes?unit_id=' + unitId)
        .then(res=>res.json())
        .then(data=>{
            existingTopics = data;
            renderExistingTopics();
        });
}

// --- Render existing topics list ---
function renderExistingTopics(){
    const listDiv = document.getElementById('topicsList');
    listDiv.innerHTML = "";
    existingTopics.forEach(t=>{
        const div = document.createElement('div');
        div.innerHTML = `<strong>${t.title}</strong> - Uploaded at: ${t.uploaded_at} 
        <button class='edit-btn' onclick="editExisting(${t.id})">Edit</button>`;
        listDiv.appendChild(div);
    });
}

// --- Edit existing topic ---
function editExisting(classnoteId){
    fetch('/getClassnote?id='+classnoteId)
        .then(res=>res.json())
        .then(data=>{
            topics = [data]; // Assume API returns full topic object
            renderForm();
        });
}

// --- Topic & Subtopic Functions ---
function addTopic(){ const id=Date.now()+Math.floor(Math.random()*1000); topics.push({id,title:"",subtopics:[]}); renderForm(); }
function removeTopic(topicId){ topics = topics.filter(t=>t.id!==topicId); renderForm(); }
function addSubtopic(topicId){ const t = topics.find(x=>x.id===topicId); t.subtopics.push({id:Date.now(), title:"", questions:"", choices:[], correctChoice:null}); renderForm(); }
function removeSubtopic(topicId, subId){ const t = topics.find(x=>x.id===topicId); t.subtopics = t.subtopics.filter(s=>s.id!==subId); renderForm(); }
function updateTopicTitle(id,val){ const t = topics.find(x=>x.id===id); if(t)t.title=val; renderPreview(); }
function updateSubtopicTitle(topicId, subId, val){ const s = topics.find(t=>t.id===topicId).subtopics.find(x=>x.id===subId); if(s)s.title=val; renderPreview(); }
function updateQuestions(topicId, subId,val){ const s = topics.find(t=>t.id===topicId).subtopics.find(x=>x.id===subId); if(s)s.questions=val; renderPreview(); }

// Choices
function addChoice(topicId, subId){ const s = topics.find(t=>t.id===topicId).subtopics.find(x=>x.id===subId); s.choices.push({id:Date.now(), text:""}); renderForm(); }
function updateChoice(topicId, subId, choiceId, val){ const c = topics.find(t=>t.id===topicId).subtopics.find(x=>x.id===subId).choices.find(x=>x.id===choiceId); if(c)c.text=val; renderPreview(); }
function removeChoice(topicId, subId, choiceId){ const s = topics.find(t=>t.id===topicId).subtopics.find(x=>x.id===subId); s.choices = s.choices.filter(c=>c.id!==choiceId); if(s.correctChoice===choiceId)s.correctChoice=null; renderForm(); }
function setCorrectChoice(topicId, subId, choiceId){ const s = topics.find(t=>t.id===topicId).subtopics.find(x=>x.id===subId); s.correctChoice=choiceId; renderPreview(); }

// --- File & Image Functions ---
function updateFiles(){ const files = Array.from(document.getElementById('fileUploads').files); lastFiles = files.map((f,i)=>({id:Date.now()+i,file:f,label:""})); renderFileInputs(); renderFilePreview(); }
function renderFileInputs(){ const c = document.getElementById('fileList'); c.innerHTML=""; lastFiles.forEach(f=>{ const d=document.createElement('div'); d.innerHTML=`<div style="margin-top:8px;"><strong>${f.file.name}</strong><br><input type='text' placeholder='Label' style="color:var(--file-label-color)" oninput="updateFileLabel(${f.id},this.value)"><button class='remove-btn' onclick="removeFile(${f.id})">Remove</button></div>`; c.appendChild(d); }); }
function updateFileLabel(id,val){ const f = lastFiles.find(x=>x.id===id); if(f) f.label=val; renderFilePreview(); }
function removeFile(id){ lastFiles=lastFiles.filter(x=>x.id!==id); renderFileInputs(); renderFilePreview(); }
function renderFilePreview(){ const ul = document.getElementById('filePreview'); ul.innerHTML=""; lastFiles.forEach(f=>{ const li = document.createElement('li'); li.innerHTML=`<span class='file-pill label-pill'>${f.label||'File'}</span> ${f.file.name}`; ul.appendChild(li); }); }

function updateImages(){ const imgs = Array.from(document.getElementById('imageUploads').files); lastImages = imgs.map((img,i)=>({id:Date.now()+i,file:img,label:""})); renderImagePreview(); }
function renderImagePreview(){ const c = document.getElementById('imagePreview'); c.innerHTML=""; lastImages.forEach(imgObj=>{ const reader = new FileReader(); reader.onload=e=>{ const wrapper=document.createElement('div'); wrapper.style.display='inline-block'; wrapper.style.margin='6px'; wrapper.innerHTML=`<img src='${e.target.result}'><br><span class='image-pill label-pill'>${imgObj.label||'Image'}</span>`; c.appendChild(wrapper); }; reader.readAsDataURL(imgObj.file); }); }

// --- Render Form & Preview ---
function renderForm(){
    const container = document.getElementById('topics'); container.innerHTML="";
    topics.forEach(topic=>{
        const div = document.createElement('div'); div.className='topic-block';
        div.innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center">
            <div><label class='topic-pill label-pill'>Topic</label><input type='text' placeholder='Topic title' value='${escapeHtml(topic.title)}' oninput="updateTopicTitle(${topic.id},this.value)" style="color:var(--topic-color)"></div>
            <div><button onclick="addSubtopic(${topic.id})">+ Subtopic</button><button class='remove-btn' onclick="removeTopic(${topic.id})">Delete</button></div>
        </div>
        <div id='subs-${topic.id}'></div>`;
        container.appendChild(div);

        const subsDiv = document.getElementById(`subs-${topic.id}`);
        topic.subtopics.forEach(sub=>{
            const subDiv = document.createElement('div'); subDiv.className='subtopic-block';
            subDiv.innerHTML=`<div style="display:flex;justify-content:space-between;align-items:center">
                <div style='flex:1'><label class='subtopic-pill label-pill'>Subtopic</label><input type='text' value='${escapeHtml(sub.title)}' placeholder='Subtopic title' style="color:var(--subtopic-color)" oninput="updateSubtopicTitle(${topic.id},${sub.id},this.value)"></div>
                <div><button onclick="addChoice(${topic.id},${sub.id})">+ Choice</button><button class='remove-btn' onclick="removeSubtopic(${topic.id},${sub.id})">Delete</button></div>
            </div>
            <label>Question:</label><textarea rows='2' oninput="updateQuestions(${topic.id},${sub.id},this.value)">${escapeHtml(sub.questions)}</textarea>
            <div id='choices-${sub.id}'></div>`;

            subsDiv.appendChild(subDiv);

            const choicesDiv = document.getElementById(`choices-${sub.id}`); choicesDiv.innerHTML="";
            sub.choices.forEach(c=>{
                const row = document.createElement('div'); row.className='choice-row';
                row.innerHTML=`<input type='radio' name='correct-${sub.id}' ${sub.correctChoice===c.id?'checked':''} onclick="setCorrectChoice(${topic.id},${sub.id},${c.id})">
                <input type='text' value='${escapeHtml(c.text)}' placeholder='Choice text' oninput="updateChoice(${topic.id},${sub.id},${c.id},this.value)">
                <button class='remove-btn' onclick="removeChoice(${topic.id},${sub.id},${c.id})">Remove</button>`;
                choicesDiv.appendChild(row);
            });
        });
    });
    renderPreview();
}

function renderPreview(){
    const preview=document.getElementById('preview');
    preview.innerHTML=topics.map(t=>{
        return `<div style='margin-bottom:14px;'><div style="font-family:Georgia,serif;font-size:20px;color:var(--topic-color);font-weight:bold;">${escapeHtml(t.title)}</div>
        ${t.subtopics.map(s=>`<div style='margin-left:15px;margin-top:10px;'>
            <div style="font-family:'Trebuchet MS',sans-serif;font-size:16px;color:var(--subtopic-color);font-weight:600;">${escapeHtml(s.title)}</div>
            <div style="font-family:Calibri,sans-serif;font-size:14px;margin-top:4px;"><b>Question:</b> <span style="color:#555">${escapeHtml(s.questions)}</span></div>
            <div style="margin-left:10px;margin-top:6px;font-family:Arial,sans-serif;">
            ${s.choices.map(c=>`<div style='margin-top:4px;color:#333;'><label><input type='radio' disabled ${s.correctChoice===c.id?'checked':''}><i>${escapeHtml(c.text)}</i></label></div>`).join('')}
            </div>
        </div>`).join('')}
        </div>`;
    }).join('');
    renderFilePreview();
    renderImagePreview();
}

// --- Submit / Update Notes ---
function submitNotes(){
    if(!selectedUnitId){ alert('Select a unit'); return; }
    const formData = new FormData();
    formData.append('unit_id', selectedUnitId);
    formData.append('lecturer_id', lecturerId);
    formData.append('topics', JSON.stringify(topics));
    lastFiles.forEach(f=> formData.append('files[]', f.file));
    lastImages.forEach(img=> formData.append('images[]', img.file));
    fetch('/saveClassnotes',{method:'POST',body:formData})
        .then(res=>res.json())
        .then(r=>{ alert('Saved'); loadUnitTopics(); })
        .catch(err=>console.error(err));
}

function updateNotes(){
    if(!selectedUnitId){ alert('Select a unit'); return; }
    const formData = new FormData();
    formData.append('unit_id', selectedUnitId);
    formData.append('lecturer_id', lecturerId);
    formData.append('topics', JSON.stringify(topics));
    lastFiles.forEach(f=> formData.append('files[]', f.file));
    lastImages.forEach(img=> formData.append('images[]', img.file));
    fetch('/updateClassnotes',{method:'POST',body:formData})
        .then(res=>res.json())
        .then(r=>{ alert('Updated'); loadUnitTopics(); })
        .catch(err=>console.error(err));
}

function escapeHtml(text){ if(!text && text!==0) return ''; return String(text).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// --- Initialize ---
loadLecturerUnits();
renderForm();
</script>
</body>
</html>
